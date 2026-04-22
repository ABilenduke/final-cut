# Plan 07: Bookings, Customers & Loyalty

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 03 (BaseResource, FormatsCurrency, TimestampColumns, `loyalty_adjustments` table), Plan 06 (Showtime references)
> **Unlocks:** —

## Overview

Read-focused operations. `BookingResource` is **read-only in v1** (no cancel/refund — deferred per spec § 4.4) with lookup by confirmation code, email, or showtime. `UserResource` manages customer users, read-only except for loyalty tier and points. Loyalty actions — "Adjust Points" (with required reason), "Upgrade to Premier" (with expiry), "Revoke Premier" — write to the admin-owned `loyalty_adjustments` table (Plan 03 Task 3) and update `users.loyalty_points` / `users.loyalty_tier` / `users.premier_expiry` via `LoyaltyService`. Large point adjustments (configurable threshold) warn the admin via a confirmation modal; full dual-control approval is deferred to v2 per spec § 8.

`LoyaltyService` already exists in `backend/app/Services/LoyaltyService.php` — this plan **extends** it with admin-facing methods (`adjustPoints`, `grantPremier`, `revokePremier`), not extracts it. Every write method accepts an optional `?AdminUser $actor = null` for audit attribution — Filament pages pass `auth('admin')->user()`; customer-path callers (booking completion, checkout redemption) pass `null`. The service writes `activity_log` rows when `$actor` is non-null and skips admin activity attribution otherwise.

Filament Resources consume `App\Models\Booking`, `App\Models\User`, and `App\Models\LoyaltyAdjustment` directly — there is no admin-side model mirror, no shared package, no cross-app boundary.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 5 Plan 07, § 3.3 loyalty permission split, § 8 resolved open question #5
- `docs/plans/backend/v1/06-account-api.md` — loyalty model and earn/redeem contract
- `docs/architecture/DATA_MODELS.md` — User, Booking, BookingSeat, BookingFoodItem
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — BaseResource, FormatsCurrency, `loyalty_adjustments` migration
- `backend/app/Services/LoyaltyService.php` — existing service extended in this plan

---

## Tasks

### Task 1: Extend `LoyaltyService` with admin-facing methods

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/LoyaltyService.php` (modify — add admin methods, apply `lockForUpdate` to balance writes)
  - `backend/tests/Unit/LoyaltyServiceTest.php` (modify or new — extend to cover admin methods + concurrency)
- **Details:**
  `LoyaltyService` already ships in `backend/app/Services/` and handles customer-path earn/redeem. This task adds the admin-facing write surface and brings every balance-changing method under the same `lockForUpdate` contract so concurrent adjustments (two admins simultaneously correcting the same account, or an admin adjustment racing a customer earn/redeem) converge on a correct balance.

  Required additions to the existing class:

  ```php
  namespace App\Services;

  use App\Models\AdminUser;
  use App\Models\LoyaltyAdjustment;
  use App\Models\User;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Facades\DB;

  class LoyaltyService
  {
      // Existing customer-path methods — signatures updated to take optional actor.
      public function earnPoints(User $user, int $points, string $source, ?int $sourceId = null, ?AdminUser $actor = null): void { /* ... */ }
      public function redeemPoints(User $user, int $points, string $reason, ?AdminUser $actor = null): void { /* ... */ }

      // New admin-path methods.
      public function adjustPoints(User $user, int $delta, string $reason, ?AdminUser $actor = null): void
      {
          DB::transaction(function () use ($user, $delta, $reason, $actor) {
              $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

              $fresh->loyalty_points = $fresh->loyalty_points + $delta;
              $fresh->save();

              LoyaltyAdjustment::create([
                  'user_id' => $fresh->id,
                  'admin_user_id' => $actor?->id,
                  'change_type' => $delta >= 0 ? 'earn_manual' : 'revoke_manual',
                  'points_delta' => $delta,
                  'reason' => $reason,
              ]);

              $this->logIfAdmin('loyalty.points_adjusted', $fresh, $actor, [
                  'delta' => $delta,
                  'reason' => $reason,
                  'balance_after' => $fresh->loyalty_points,
              ]);
          });
      }

      public function grantPremier(User $user, Carbon $expiry, ?AdminUser $actor = null): void
      {
          DB::transaction(function () use ($user, $expiry, $actor) {
              $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

              $fresh->loyalty_tier = 'premier';
              $fresh->premier_expiry = $expiry;
              $fresh->save();

              LoyaltyAdjustment::create([
                  'user_id' => $fresh->id,
                  'admin_user_id' => $actor?->id,
                  'change_type' => 'tier_grant',
                  'points_delta' => 0,
                  'reason' => "Premier granted through {$expiry->toDateString()}",
              ]);

              $this->logIfAdmin('loyalty.premier_granted', $fresh, $actor, [
                  'expiry' => $expiry->toIso8601String(),
              ]);
          });
      }

      public function revokePremier(User $user, string $reason, ?AdminUser $actor = null): void
      {
          DB::transaction(function () use ($user, $reason, $actor) {
              $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

              $fresh->loyalty_tier = 'member';
              $fresh->premier_expiry = null;
              $fresh->save();

              LoyaltyAdjustment::create([
                  'user_id' => $fresh->id,
                  'admin_user_id' => $actor?->id,
                  'change_type' => 'tier_revoke',
                  'points_delta' => 0,
                  'reason' => $reason,
              ]);

              $this->logIfAdmin('loyalty.premier_revoked', $fresh, $actor, [
                  'reason' => $reason,
              ]);
          });
      }

      private function logIfAdmin(string $event, User $subject, ?AdminUser $actor, array $properties = []): void
      {
          if ($actor === null) return;

          activity('admin')
              ->causedBy($actor)
              ->performedOn($subject)
              ->withProperties($properties)
              ->log($event);
      }
  }
  ```

  **Row locks are non-negotiable.** Every balance-changing method (`earnPoints`, `redeemPoints`, `adjustPoints`, `grantPremier`, `revokePremier`) must take `User::whereKey($user->id)->lockForUpdate()->firstOrFail()` inside its transaction — **always**, not conditional on observed contention. Without the lock, two parallel admin adjustments can read the same starting balance and one write silently overwrites the other. The concurrency tests in Task 7 prove this holds.

  **Configurable warning threshold** (resolves spec § 8 open question #5):

  ```php
  // backend/config/loyalty.php
  return [
      'large_adjustment_threshold' => env('LOYALTY_LARGE_ADJUSTMENT_THRESHOLD', 1000),
  ];
  ```

  The admin UI (Task 5) reads this threshold to gate the Adjust Points action with a stronger confirmation modal. Per spec § 8, v1 ships with **single-admin + activity-log** as the compensating control; dual control is v2.

  **Extension principles:**
  - Admin-path methods accept pre-validated arguments — validation of delta bounds, reason length, and expiry shape lives in the Filament action form (Task 5).
  - Every write runs inside `DB::transaction` so the `users` row update, the `loyalty_adjustments` row, and the `activity_log` row commit together or not at all.
  - `$actor = null` is legal on every method — customer-path callers (booking completion earning points, checkout redeeming) continue to pass no actor, and no `activity_log` row is written.

- **Acceptance Criteria:**
  - [ ] `App\Services\LoyaltyService` exposes `adjustPoints`, `grantPremier`, `revokePremier` alongside the existing earn/redeem methods
  - [ ] Every write method signature accepts `?AdminUser $actor = null` as the last parameter
  - [ ] **Every balance-changing method (`earnPoints`, `redeemPoints`, `adjustPoints`, `grantPremier`, `revokePremier`) takes `User::whereKey($id)->lockForUpdate()->firstOrFail()` inside its transaction** — this is a required implementation detail, not a runtime choice, and is covered by concurrency tests in Task 7
  - [ ] `adjustPoints` writes a `loyalty_adjustments` row with `change_type` of `earn_manual` (delta ≥ 0) or `revoke_manual` (delta < 0) atomically with the balance update
  - [ ] `grantPremier` sets `loyalty_tier='premier'` and `premier_expiry`, writes a `loyalty_adjustments` row with `change_type='tier_grant'`, and transitions tier + expiry atomically under the row lock
  - [ ] `revokePremier` sets `loyalty_tier='member'` and `premier_expiry=null`, writes a `loyalty_adjustments` row with `change_type='tier_revoke'`
  - [ ] When `$actor` is set, each write emits an `activity_log` row with `causer` resolving to the admin user
  - [ ] When `$actor` is null, no `activity_log` row is written and no `admin_user_id` is stored on the adjustment row
  - [ ] Threshold configurable via `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` env
  - [ ] Unit tests cover each method's happy path, rollback on transactional failure, and the concurrent-adjustment scenario: two parallel transactions adjusting the same user converge on the correct final balance (sum of deltas) and produce two distinct `loyalty_adjustments` rows
  - [ ] Existing customer-path test suite still green after the extension

---

### Task 2: BookingResource (read-only)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/BookingResource.php` (new)
  - `backend/app/Filament/Resources/BookingResource/Pages/ListBookings.php` (new)
  - `backend/app/Filament/Resources/BookingResource/Pages/ViewBooking.php` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'bookings'`. All mutation surfaces disabled — v1 does not cancel or refund.

  ```php
  namespace App\Filament\Resources;

  use App\Models\Booking;

  class BookingResource extends BaseResource
  {
      protected static ?string $model = Booking::class;
      protected static ?string $permissionPrefix = 'bookings';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-ticket';
      protected static ?int $navigationSort = 20;

      public static function canCreate(): bool { return false; }
      public static function canEdit(Model $record): bool { return false; }
      public static function canDelete(Model $record): bool { return false; }

      public static function table(Table $table): Table { /* Task 2 below */ }

      public static function getPages(): array
      {
          return [
              'index' => Pages\ListBookings::route('/'),
              'view' => Pages\ViewBooking::route('/{record}'),
          ];
      }
  }
  ```

  **Table columns:**

  ```php
  TextColumn::make('confirmation_code')->searchable()->copyable()->sortable(),
  TextColumn::make('customer_email')->label('Email')->searchable(),
  TextColumn::make('showtime.movie.title')->label('Movie')->searchable(),
  TextColumn::make('showtime.start_time')->dateTime()->sortable(),
  TextColumn::make('showtime.auditorium.location.name')->label('Location'),
  TextColumn::make('total')
      ->label('Total')
      ->formatStateUsing(fn ($state) => self::centsToDisplay($state))
      ->sortable(),
  BadgeColumn::make('status')
      ->getStateUsing(fn (Booking $r) => $r->flagged_at ? 'flagged' : $r->status)
      ->colors([
          'success' => 'confirmed',
          'warning' => 'flagged',
          'danger' => fn ($state) => in_array($state, ['refunded', 'cancelled']),
      ]),
  ...TimestampColumns::standardTimestamps(),
  ```

  **Filters:** date range (default: last 30 days), status, location, showtime autocomplete.

  **Search.** Full-text across `confirmation_code`, `customer_email`, and `customer_name`. Uses Postgres `ILIKE` with a compound `orWhere` group — no full-text index required at current scale. Confirmation codes are normalized to uppercase on lookup so "cvf-a3x9k2" and "CVF-A3X9K2" both hit.

  **View page** shows:
  - Confirmation code, customer email + name, status (including `flagged_at` badge)
  - Movie + showtime + location + auditorium
  - Seats (list of seat IDs with section and per-seat price, formatted via `FormatsCurrency::centsToDisplay`)
  - Food items (itemized list)
  - Payment breakdown (subtotal, discount, total, Stripe PaymentIntent ID)
  - Activity log relation manager (read-only history of any admin views or flags)

- **Acceptance Criteria:**
  - [ ] Resource registers under "Operations" navigation group
  - [ ] List page searchable across confirmation code + email + name
  - [ ] Create, edit, and delete all return `false` for every role (no admin can mutate a booking in v1)
  - [ ] View page shows seats, food items, and payment breakdown with currency formatted via `FormatsCurrency::centsToDisplay`
  - [ ] Status column reflects `flagged_at` state (renders as "flagged" when `flagged_at IS NOT NULL`)
  - [ ] Filters apply: date range, status, location, showtime
  - [ ] Activity log relation manager attached to the view page
  - [ ] Ops role can list and view bookings; manager role same; admin role same (all three have `bookings.view`)

---

### Task 3: UserResource (customer users)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/UserResource.php` (new)
  - `backend/app/Filament/Resources/UserResource/Pages/ListUsers.php` (new)
  - `backend/app/Filament/Resources/UserResource/Pages/ViewUser.php` (new)
  - `backend/app/Filament/Resources/UserResource/Pages/EditUser.php` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'users'`. Read-only with one narrow exception: `loyalty_tier`, `loyalty_points`, and `premier_expiry` are editable via the dedicated actions in Task 5, not via a plain form. The direct edit form exists but is gated to the same loyalty fields and helper text nudges staff toward the actions.

  ```php
  namespace App\Filament\Resources;

  use App\Models\User;

  class UserResource extends BaseResource
  {
      protected static ?string $model = User::class;
      protected static ?string $permissionPrefix = 'users';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-user';
      protected static ?int $navigationSort = 30;

      public static function canCreate(): bool { return false; }
      public static function canDelete(Model $record): bool { return false; }

      // canEdit defers to the permission gate — manager/admin can edit the loyalty fields only.
  }
  ```

  **Table:**

  ```php
  TextColumn::make('name')->searchable()->sortable(),
  TextColumn::make('email')->searchable()->copyable(),
  BadgeColumn::make('loyalty_tier')->colors([
      'gray' => 'member',
      'warning' => 'premier',
  ])->sortable(),
  TextColumn::make('loyalty_points')->numeric()->sortable(),
  TextColumn::make('premier_expiry')->date()->toggleable(),
  TextColumn::make('created_at')->date()->label('Joined')->sortable(),
  TextColumn::make('bookings_count')->counts('bookings')->label('Bookings'),
  ```

  **Filters:** loyalty tier (member / premier), joined after date, has upcoming booking.

  **View page sections:**
  - Profile (name, email, phone, date of birth, joined date)
  - Loyalty summary (current points, tier, premier expiry)
  - Loyalty action buttons (Task 5)
  - Bookings relation manager (Task 4 — read-only list of customer's history)
  - Loyalty adjustments relation manager (Task 4 — read-only log of admin-applied changes)

  **Edit form** (narrow):
  - Only `loyalty_tier`, `loyalty_points`, and `premier_expiry` appear as form fields
  - Email, password, name, phone, DOB are not rendered on the form
  - Submission routes through `LoyaltyService` — not via a direct Eloquent save — by overriding `EditUser::handleRecordUpdate`:

    ```php
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(\App\Services\LoyaltyService::class);
        $actor = auth('admin')->user();

        if (($data['loyalty_points'] ?? $record->loyalty_points) !== $record->loyalty_points) {
            $delta = (int) $data['loyalty_points'] - (int) $record->loyalty_points;
            $service->adjustPoints($record, $delta, 'Edited via profile form', $actor);
        }

        if (($data['loyalty_tier'] ?? $record->loyalty_tier) !== $record->loyalty_tier) {
            if ($data['loyalty_tier'] === 'premier') {
                $service->grantPremier(
                    $record,
                    Carbon::parse($data['premier_expiry'] ?? now()->addYear()),
                    $actor,
                );
            } else {
                $service->revokePremier($record, 'Edited via profile form', $actor);
            }
        }

        return $record->fresh();
    }
    ```

  - Form helper text on the loyalty-points field: "Large point adjustments should use the Adjust Points action instead of this form — it requires a reason and triggers an elevated confirmation."

- **Acceptance Criteria:**
  - [ ] Resource lists customer users under "Operations"
  - [ ] `canCreate` and `canDelete` return `false` for every role
  - [ ] Direct-edit form exposes only `loyalty_tier`, `loyalty_points`, `premier_expiry`
  - [ ] Non-loyalty fields (email, password, name, phone, DOB) are not rendered on the form and cannot be submitted
  - [ ] Bookings relation shows customer history in reverse-chronological order
  - [ ] Loyalty adjustments relation shows admin-applied changes with causer
  - [ ] Form submission routes through `LoyaltyService` — no direct Eloquent writes to loyalty fields
  - [ ] Ops role can list and view users; manager role can edit the loyalty fields; admin role same as manager

---

### Task 4: BookingsRelationManager and LoyaltyAdjustmentsRelationManager

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Resources/UserResource/RelationManagers/BookingsRelationManager.php` (new)
  - `backend/app/Filament/Resources/UserResource/RelationManagers/LoyaltyAdjustmentsRelationManager.php` (new)
- **Details:**
  Both render on the user view page — staff get a complete customer picture at a glance.

  **BookingsRelationManager** — read-only list of this customer's bookings, most recent first:

  ```php
  namespace App\Filament\Resources\UserResource\RelationManagers;

  use App\Filament\Concerns\FormatsCurrency;
  use Filament\Resources\RelationManagers\RelationManager;
  use Filament\Tables\Columns\TextColumn;
  use Filament\Tables\Table;

  class BookingsRelationManager extends RelationManager
  {
      use FormatsCurrency;

      protected static string $relationship = 'bookings';
      protected static ?string $title = 'Bookings';

      public function table(Table $table): Table
      {
          return $table
              ->query(fn () => $this->getOwnerRecord()->bookings()->latest())
              ->columns([
                  TextColumn::make('confirmation_code')->copyable(),
                  TextColumn::make('showtime.movie.title')->label('Movie'),
                  TextColumn::make('showtime.start_time')->dateTime(),
                  TextColumn::make('total')
                      ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
                  TextColumn::make('status')->badge(),
              ])
              ->headerActions([])
              ->actions([]);
      }

      public function isReadOnly(): bool
      {
          return true;
      }
  }
  ```

  **LoyaltyAdjustmentsRelationManager** — read-only log of admin-applied adjustments against this customer, most recent first:

  ```php
  class LoyaltyAdjustmentsRelationManager extends RelationManager
  {
      protected static string $relationship = 'loyaltyAdjustments';
      protected static ?string $title = 'Loyalty Adjustments';

      public function table(Table $table): Table
      {
          return $table
              ->query(fn () => $this->getOwnerRecord()->loyaltyAdjustments()->latest())
              ->columns([
                  TextColumn::make('created_at')->dateTime(),
                  TextColumn::make('adminUser.email')->label('By'),
                  TextColumn::make('change_type')->badge(),
                  TextColumn::make('points_delta')->numeric(),
                  TextColumn::make('reason')->wrap()->limit(80),
              ])
              ->headerActions([])
              ->actions([]);
      }

      public function isReadOnly(): bool
      {
          return true;
      }
  }
  ```

  Both use `App\Models\User::bookings()` (existing relationship) and `App\Models\User::loyaltyAdjustments()` (added in this task if not already present on the model — it reads from the `loyalty_adjustments` table created in Plan 03).

- **Acceptance Criteria:**
  - [ ] Bookings relation renders customer bookings in reverse-chronological order
  - [ ] Bookings relation total rendered via `FormatsCurrency::centsToDisplay`
  - [ ] Loyalty adjustments relation renders admin changes with causer email, change type, delta, and reason
  - [ ] Both relations are read-only — no create/edit/delete actions
  - [ ] Both appear on the user view page

---

### Task 5: Loyalty actions (adjust, upgrade, revoke)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/UserResource.php` (modify — add header actions)
- **Details:**
  Three header actions on UserResource's view page. Permissions split per spec § 3.3: `loyalty.adjust_points` for point adjustments, `loyalty.adjust_tier` for premier grants/revokes. This is narrower than a broad `loyalty.adjust` and lets staff roles expand independently.

  **Adjust Points** (permission: `loyalty.adjust_points`):

  ```php
  Action::make('adjust_points')
      ->label('Adjust Points')
      ->icon('heroicon-o-sparkles')
      ->visible(fn () => auth('admin')->user()->can('loyalty.adjust_points'))
      ->form([
          TextInput::make('points_delta')
              ->numeric()
              ->required()
              ->helperText('Positive to add, negative to deduct. Negative balances are allowed for fraud corrections.'),
          Textarea::make('reason')
              ->required()
              ->minLength(10)
              ->helperText('Required. Logged permanently for audit.'),
      ])
      ->requiresConfirmation()
      ->modalDescription(function ($record, array $data): string {
          $delta = (int) ($data['points_delta'] ?? 0);
          $threshold = (int) config('loyalty.large_adjustment_threshold');

          if (abs($delta) >= $threshold) {
              return "This is a large adjustment (±{$threshold}+ points). Double-check before confirming. This action is permanent and logged.";
          }
          return "Adjusting {$delta} points for {$record->email}. Reason will be logged.";
      })
      ->action(function ($record, array $data) {
          app(\App\Services\LoyaltyService::class)->adjustPoints(
              $record,
              (int) $data['points_delta'],
              $data['reason'],
              auth('admin')->user(),
          );
      })
      ->successNotificationTitle('Points adjusted and logged.');
  ```

  **Upgrade to Premier** (permission: `loyalty.adjust_tier`):

  ```php
  Action::make('upgrade_premier')
      ->label('Upgrade to Premier')
      ->icon('heroicon-o-star')
      ->visible(fn ($record) => auth('admin')->user()->can('loyalty.adjust_tier')
          && $record->loyalty_tier === 'member')
      ->form([
          DatePicker::make('expiry')
              ->required()
              ->default(now()->addYear())
              ->helperText('Premier tier expires on this date unless renewed.'),
          Textarea::make('reason')->required()->minLength(10),
      ])
      ->requiresConfirmation()
      ->action(function ($record, array $data) {
          app(\App\Services\LoyaltyService::class)->grantPremier(
              $record,
              Carbon::parse($data['expiry']),
              auth('admin')->user(),
          );
      })
      ->successNotificationTitle('Premier granted.');
  ```

  **Revoke Premier** (permission: `loyalty.adjust_tier`):

  ```php
  Action::make('revoke_premier')
      ->label('Revoke Premier')
      ->icon('heroicon-o-shield-exclamation')
      ->color('danger')
      ->visible(fn ($record) => auth('admin')->user()->can('loyalty.adjust_tier')
          && $record->loyalty_tier === 'premier')
      ->form([
          Textarea::make('reason')->required()->minLength(10),
      ])
      ->requiresConfirmation()
      ->action(function ($record, array $data) {
          app(\App\Services\LoyaltyService::class)->revokePremier(
              $record,
              $data['reason'],
              auth('admin')->user(),
          );
      })
      ->successNotificationTitle('Premier revoked.');
  ```

  **Resolves spec § 8 open question #5.** v1 uses an elevated confirmation modal for large adjustments (configurable threshold via `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD`) without requiring a second admin's sign-off. Activity log is the compensating control. Plan 09 deployment docs emphasize that admin accounts are strictly controlled (not shared) because the accountability trail depends on accurate causer attribution.

- **Acceptance Criteria:**
  - [ ] Adjust Points action gated on `loyalty.adjust_points` permission (seeded in Plan 02 Task 3)
  - [ ] Upgrade Premier and Revoke Premier actions gated on `loyalty.adjust_tier` permission (seeded in Plan 02 Task 3)
  - [ ] Large-adjustment modal description text switches when `abs(delta) >= config('loyalty.large_adjustment_threshold')`
  - [ ] Reason is required on all three actions, min 10 chars
  - [ ] Upgrade Premier is visible only for users with `loyalty_tier === 'member'`
  - [ ] Revoke Premier is visible only for users with `loyalty_tier === 'premier'`
  - [ ] All three call `LoyaltyService` and pass `auth('admin')->user()` as the actor
  - [ ] Activity log rows capture causer, delta (or tier transition), and reason
  - [ ] Permission split covered by test: a user with only `loyalty.adjust_points` can adjust points but cannot upgrade/revoke; a user with only `loyalty.adjust_tier` can change tier but cannot adjust raw points

---

### Task 6: Booking lookup landing page

- **MoSCoW:** Could Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Pages/BookingLookup.php` (new)
  - `backend/resources/views/filament/pages/booking-lookup.blade.php` (new)
- **Details:**
  Dedicated quick-lookup page for phone-based customer support. Staff enter a confirmation code or email, hit Enter, land on the booking view page.

  ```php
  namespace App\Filament\Pages;

  use App\Filament\Resources\BookingResource;
  use App\Models\Booking;
  use Filament\Notifications\Notification;
  use Filament\Pages\Page;

  class BookingLookup extends Page
  {
      protected static string $view = 'filament.pages.booking-lookup';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
      protected static ?string $title = 'Booking Lookup';
      protected static ?int $navigationSort = 1;

      public ?string $query = null;

      public function search(): void
      {
          $this->validate(['query' => 'required|string|min:3']);

          $needle = trim($this->query);

          $booking = Booking::query()
              ->where('confirmation_code', strtoupper($needle))
              ->orWhere('customer_email', $needle)
              ->latest()
              ->first();

          if (! $booking) {
              Notification::make()->title('No booking found')->warning()->send();
              return;
          }

          $this->redirect(BookingResource::getUrl('view', ['record' => $booking]));
      }
  }
  ```

  Keyboard-friendly: form submits on Enter, focus persists for repeated lookups. Confirmation codes uppercase-normalized before lookup so case insensitivity works.

- **Acceptance Criteria:**
  - [ ] Page at `/booking-lookup` on the admin subdomain
  - [ ] Accepts confirmation code (case-insensitive) or email
  - [ ] Redirects to the matching `BookingResource` view page on success
  - [ ] Shows "No booking found" notification on miss
  - [ ] Navigation item appears in the Operations group, sorted above `BookingResource`

---

### Task 7: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Feature/Admin/Resources/BookingResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/UserResourceTest.php` (new)
  - `backend/tests/Feature/Admin/LoyaltyActionsTest.php` (new — integration)
  - `backend/tests/Feature/Admin/Pages/BookingLookupTest.php` (new)
  - `backend/tests/Unit/Admin/LoyaltyServiceConcurrencyTest.php` (new)
- **Details:**
  Use Filament's Livewire test helpers. Split into two layers — Layer A mocks the service to verify Resource wiring, Layer B runs the real service end-to-end to verify audit attribution and concurrency.

  **Layer A — Resource tests (service mocked).**

  **BookingResourceTest (service mocked):**
  - List page renders and is searchable by `confirmation_code` (case-insensitive)
  - `canCreate`, `canEdit`, `canDelete` all return `false` for every role
  - View page renders seats, food items, and payment breakdown
  - Filters apply correctly: date range, status, location, showtime
  - Status column renders "flagged" when `flagged_at` is set

  **UserResourceTest (service mocked):**
  - List page renders customer users with loyalty badges
  - `canCreate` and `canDelete` return `false` for every role
  - Edit form exposes only `loyalty_tier`, `loyalty_points`, `premier_expiry` — email/password/name/phone/DOB fields not rendered and not submittable
  - Editing `loyalty_points` routes through `LoyaltyService::adjustPoints` (not a direct Eloquent save) with actor set
  - Editing `loyalty_tier` from member to premier calls `LoyaltyService::grantPremier` with actor
  - Editing `loyalty_tier` from premier to member calls `LoyaltyService::revokePremier` with actor

  **BookingLookupTest:**
  - Valid confirmation code (any case) redirects to the booking view
  - Valid email redirects to the most recent booking for that email
  - Input shorter than 3 chars shows a validation error
  - Unknown query shows a "no booking found" notification and stays on the page

  **Layer B — Integration tests (real service, real DB).**

  **LoyaltyActionsTest:**
  - Adjust Points with positive delta increases `loyalty_points`, writes a `loyalty_adjustments` row with `change_type='earn_manual'`, and writes an `activity_log` row with causer
  - Adjust Points with negative delta decreases balance and writes a row with `change_type='revoke_manual'`
  - Adjust Points with `abs(delta) >= threshold` surfaces the elevated modal description (assert form state)
  - Upgrade Premier sets `loyalty_tier='premier'` and `premier_expiry`, writes `change_type='tier_grant'`, and writes activity log
  - Revoke Premier sets `loyalty_tier='member'` and `premier_expiry=null`, writes `change_type='tier_revoke'`
  - A user with only `loyalty.adjust_points` permission can exercise Adjust Points but cannot see Upgrade/Revoke Premier actions
  - A user with only `loyalty.adjust_tier` permission can exercise Upgrade/Revoke Premier but cannot see Adjust Points
  - `adjustPoints` called with `$actor = null` does not write an `activity_log` row and stores `admin_user_id = null` on the adjustment row

  **LoyaltyServiceConcurrencyTest** (real DB, parallel transactions):
  - Two parallel transactions each call `adjustPoints($user, +100)`. Both succeed, the final balance reflects both deltas (`+200` total), and two distinct `loyalty_adjustments` rows exist with different causer ids. Neither call silently discards the other's write. Uses separate DB connections or explicit `DB::transaction` blocks to simulate the race.
  - Parallel `adjustPoints($user, +100)` and `redeemPoints($user, 50)` converge on `+50` net, with both activity-log rows written in their correct causer contexts.
  - `grantPremier` called twice in parallel on the same user results in a single premier tier with a consistent expiry (last write wins under the row lock — no interleaved partial state).

- **Acceptance Criteria:**
  - [ ] Layer A Resource tests cover booking read-only surfaces, user edit constraints, and booking lookup page
  - [ ] Layer A tests use a mocked `LoyaltyService` — no real writes, no `activity_log` assertions at this layer
  - [ ] Layer B `LoyaltyActionsTest` runs the real service and verifies the full chain: UI action → service → `loyalty_adjustments` row → `activity_log` row
  - [ ] Layer B covers the permission split (adjust_points vs adjust_tier)
  - [ ] Layer B covers the `$actor = null` skip case on `activity_log`
  - [ ] `LoyaltyServiceConcurrencyTest` proves `lockForUpdate` holds under parallel admin writes and mixed admin/customer writes
  - [ ] `make admin-test` passes all loyalty-related tests green

---

## Testing Requirements

- **Layer A (Resource, service mocked):** booking read-only surfaces, user edit constraints, lookup page navigation, permission gates. No `activity_log` assertions.
- **Layer B (Service integration, real DB):** end-to-end loyalty action chain (UI → service → adjustment row → activity log), actor-null skip case, permission split enforcement.
- **Service unit tests (Task 1):** every write method's happy path, rollback on transactional failure, and the concurrent-adjustment scenario proving `lockForUpdate` holds.

## Dependencies Map

```
Task 1 (LoyaltyService extension) ← foundational
Task 2 (BookingResource) ← parallel to Task 1
Task 3 (UserResource) ← parallel to Task 1
Task 4 (relation managers) ← needs Task 3
Task 5 (loyalty actions) ← needs Tasks 1, 3
Task 6 (lookup page) ← needs Task 2
Task 7 (tests) ← needs all
```

## Risks & Open Questions

1. **Threshold tuning.** Default 1000 points may be too high or too low depending on program economics. Document in admin README that the threshold is configurable via `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` env and leave final value to product.
2. **Dual-control deferral.** Spec § 8 flags dual control as a v1 non-goal with single-admin + activity log as the compensating control. Plan 09 deployment docs must emphasize that admin accounts are strictly controlled (not shared) because the accountability trail depends on accurate causer attribution.
3. **Adjustment race conditions — closed.** Previous note flagged the need for `lockForUpdate`; this is now a hard acceptance criterion on Task 1, covered by `LoyaltyServiceConcurrencyTest` in Task 7. No longer a risk.
4. **Bulk adjustments.** Some future use case (campaign promo: give every user in segment X 100 points) is not in v1 scope. If requested, build via a dedicated `loyalty:bulk-adjust` artisan command that still calls `LoyaltyService::adjustPoints` per user — keeps the write boundary intact. A bulk path must pass a real admin actor or accept `null` (system-initiated) with an explicit source string in the adjustment reason; there is no third option.
5. **Search performance.** Booking search uses Postgres `ILIKE` across three columns without a full-text index. At current scale (single-theatre, booking volume < 10k/month) this is fine. If search latency degrades past ~500ms, add a GIN trigram index on `confirmation_code`, `customer_email`, `customer_name` — this is a single migration and does not change any application code.
