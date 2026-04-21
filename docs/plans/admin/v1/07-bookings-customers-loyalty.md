# Plan 07: Bookings, Customers & Loyalty

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 03 (Booking, User, LoyaltyAdjustment models), Plan 06 (Showtime references)
> **Unlocks:** —

## Overview

Read-focused operations. `BookingResource` is **read-only in v1** (no cancel/refund — deferred per spec § 4.4) with lookup by confirmation code, email, or showtime. `UserResource` is customer-user management, read-only except for loyalty tier and points. Loyalty actions — "Adjust points" (with required reason), "Upgrade to premier" (with expiry), "Revoke premier" — write to the admin-owned `loyalty_adjustments` table (Plan 03 Task 3) and update the customer `users` table via `LoyaltyService`. Large point adjustments (configurable threshold) warn the admin via a confirmation modal; full dual-control approval is deferred to v2 per spec § 8.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 5 Plan 07, § 8 open question #5
- `docs/plans/backend/v1/06-account-api.md` — loyalty model
- `docs/architecture/DATA_MODELS.md` — User, Booking, BookingSeat, BookingFoodItem

---

## Tasks

### Task 1: Extract LoyaltyService into the shared-domain package

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `packages/shared-domain/src/Services/LoyaltyService.php` (new — may already partially exist in backend per Plan 03 audit)
  - `packages/shared-domain/tests/Feature/LoyaltyServiceTest.php` (new)
  - `admin/app/Services/Backend/LoyaltyService.php` (new — admin facade)
- **Details:**
  Per Plan 03's ADR, `LoyaltyService` lives in `packages/shared-domain/src/Services/` under `FinalCut\Domain\Services`. Backend already ships a `LoyaltyService` (per the Plan 03 Task 1 audit's initial findings); this task moves/extracts it into the shared package, extends it with admin-facing methods, and brings every write method under the explicit-Causer contract from Plan 02 Task 4.

  Every write method takes an explicit `Causer $causer` argument. `earnPoints` and `redeemPoints` are called from customer-facing paths (booking completion, redemption at checkout) with a customer `User` as the causer; `adjustPoints`, `upgradeToPremier`, and `revokePremier` are called from admin paths with an `AdminUser` as the causer. The interface is unified so neither caller has to fall back on ambient guard resolution.

  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\User;

  class LoyaltyService
  {
      public function earnPoints(User $user, int $points, string $source, Causer $causer, ?int $sourceId = null): void;
      public function redeemPoints(User $user, int $points, string $reason, Causer $causer): void;
      public function adjustPoints(User $user, int $delta, string $reason, Causer $causer): void;
      public function upgradeToPremier(User $user, Carbon $expiry, Causer $causer): void;
      public function revokePremier(User $user, string $reason, Causer $causer): void;
  }
  ```

  `adjustPoints` is the admin-facing operation. It:
  - Takes a `lockForUpdate()` on the target `User` row inside the transaction — **always**, not conditional on contention. This closes the concurrent-adjustment window where two admins applying simultaneous deltas could produce a wrong final balance (previously a Risks-section note; promoted to a hard acceptance criterion).
  - Re-reads the user inside the lock so the balance calculation sees the latest committed state.
  - Updates `users.loyalty_points` (allowing negative balances if admin is correcting fraud — with a warning in the UI).
  - Writes a `LoyaltyAdjustment` row for audit.
  - Calls `activity()->causedBy($causer)->log('loyalty.points_adjusted')` with the delta and reason in properties.
  - All inside the transaction.

  `upgradeToPremier` sets `loyalty_tier='premier'` and `premier_expiry`, writes a `LoyaltyAdjustment` with `change_type='tier_upgrade'`, and takes the same `lockForUpdate` for the same reason — tier and expiry must transition atomically.

  `earnPoints` / `redeemPoints` take `lockForUpdate` as well — customer-path concurrent writes (two browser tabs redeeming the same points balance) have the same race shape.

  Configurable threshold for large adjustments (open question resolution):
  ```php
  // config/loyalty.php
  return [
      'large_adjustment_threshold' => env('LOYALTY_LARGE_ADJUSTMENT_THRESHOLD', 1000),
  ];
  ```

  Admin UI (Task 5) uses this threshold to gate with a stronger confirmation dialog. Per spec § 8, v1 ships with **single-admin + activity-log** as the compensating control; v2 may add dual control.

- **Acceptance Criteria:**
  - [ ] `FinalCut\Domain\Services\LoyaltyService` exists in `packages/shared-domain/src/Services/` with all documented methods
  - [ ] Every method signature declares an explicit `Causer $causer` parameter — no method reads `auth()->user()` internally
  - [ ] **Every balance-changing method (`adjustPoints`, `earnPoints`, `redeemPoints`, `upgradeToPremier`, `revokePremier`) takes `User::where('id', $user->id)->lockForUpdate()` inside its transaction** — this is a required implementation detail, not a runtime choice, and is covered by the concurrency tests in Task 7
  - [ ] `adjustPoints` writes adjustment row + activity log (with `causedBy($causer)`) atomically
  - [ ] Premier upgrade/revoke tracked in adjustment ledger; tier and expiry transition atomically under the row lock
  - [ ] Threshold configurable via env
  - [ ] Pest tests cover each method's happy path, rollback, and the concurrent-adjustment scenario: two parallel transactions adjusting the same user converge on the correct final balance (delta sum), and neither silently discards the other's write
  - [ ] Admin facade at `admin/app/Services/Backend/LoyaltyService.php` delegates to the domain service, resolves `Causer` from `auth()->user()`, imports from `FinalCut\Domain` — no `Backend\` namespace references

---

### Task 2: BookingResource (read-only)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/BookingResource.php` (new)
  - `admin/app/Filament/Resources/BookingResource/Pages/ListBookings.php`
  - `admin/app/Filament/Resources/BookingResource/Pages/ViewBooking.php`
- **Details:**
  Extends `BaseResource` with `$permissionPrefix = 'bookings'`. Explicitly disables create/edit/delete:

  ```php
  class BookingResource extends BaseResource
  {
      protected static ?string $model = Booking::class;
      protected static ?string $permissionPrefix = 'bookings';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-ticket';

      public static function canCreate(): bool { return false; }
      public static function canEdit(Model $record): bool { return false; }
      public static function canDelete(Model $record): bool { return false; }
  }
  ```

  **Table:**
  ```php
  TextColumn::make('confirmation_code')->searchable()->copyable(),
  TextColumn::make('customer_email')->searchable(),
  TextColumn::make('showtime.movie.title')->label('Movie')->searchable(),
  TextColumn::make('showtime.start_time')->dateTime()->sortable(),
  TextColumn::make('showtime.auditorium.location.name')->label('Location'),
  TextColumn::make('total_cents')->label('Total')
      ->formatStateUsing(fn ($s) => CurrencyFormatter::format($s))->sortable(),
  BadgeColumn::make('status')->colors([
      'success' => 'confirmed',
      'warning' => 'flagged',
      'danger' => 'refunded',
  ])->getStateUsing(fn ($r) => $r->flagged_at ? 'flagged' : $r->status),
  TextColumn::make('created_at')->dateTime()->sortable(),
  ```

  **Filters:**
  - Date range (default: last 30 days)
  - Status
  - Location
  - Showtime (auto-complete)

  **Search:** full-text on `confirmation_code` + `customer_email` + `customer_name`. Uses Postgres native `ILIKE` — no full-text index required at this scale.

  **View page** shows:
  - Confirmation code, customer email + name, status
  - Movie + showtime + location + auditorium
  - Seats (list of seat IDs with section and price)
  - Food items (list)
  - Payment breakdown (subtotal, discount, total, Stripe PaymentIntent ID)
  - Activity log relation manager (who viewed, any flags)

- **Acceptance Criteria:**
  - [ ] Resource registers under "Operations"
  - [ ] List page searchable, filterable
  - [ ] Create/edit/delete all disabled
  - [ ] View page shows seats, food, payment breakdown
  - [ ] Status column reflects `flagged_at` state
  - [ ] Activity relation manager attached

---

### Task 3: UserResource (customer users)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/UserResource.php` (new)
  - `admin/app/Filament/Resources/UserResource/Pages/*`
  - `admin/app/Filament/Resources/UserResource/RelationManagers/BookingsRelationManager.php`
  - `admin/app/Filament/Resources/UserResource/RelationManagers/LoyaltyAdjustmentsRelationManager.php`
- **Details:**
  `$permissionPrefix = 'users'`. Read-only with one exception: loyalty tier + points are editable via dedicated actions (Task 5), not via a plain form.

  **Table:**
  ```php
  TextColumn::make('name')->searchable(),
  TextColumn::make('email')->searchable()->copyable(),
  BadgeColumn::make('loyalty_tier')->colors([
      'gray' => 'member',
      'warning' => 'premier',
  ]),
  TextColumn::make('loyalty_points')->numeric()->sortable(),
  TextColumn::make('premier_expiry')->date()->toggleable(),
  TextColumn::make('created_at')->date()->label('Joined')->sortable(),
  TextColumn::make('bookings_count')->counts('bookings')->label('Bookings'),
  ```

  **Filters:** loyalty tier, joined after X, has upcoming booking.

  **View page** sections:
  - Profile (name, email, phone, DOB, joined)
  - Loyalty summary (current points, tier, premier expiry)
  - Loyalty action buttons (Task 5)
  - Bookings relation manager (read-only list)
  - Loyalty adjustments relation manager (read-only log)

  **Edit form** (limited):
  - Only `loyalty_tier`, `loyalty_points`, `premier_expiry` editable
  - All changes routed through `LoyaltyService` — no direct Eloquent save
  - Form helper text explicitly states: "Large point adjustments should use the dedicated action instead of this form."

- **Acceptance Criteria:**
  - [ ] Resource lists customer users
  - [ ] Direct-edit form limited to loyalty fields
  - [ ] Bookings relation shows customer history
  - [ ] Loyalty adjustments relation shows admin-applied changes
  - [ ] Non-loyalty fields (email, password) read-only
  - [ ] Service facade used for writes

---

### Task 4: BookingsRelationManager and LoyaltyAdjustmentsRelationManager

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Resources/UserResource/RelationManagers/BookingsRelationManager.php`
  - `admin/app/Filament/Resources/UserResource/RelationManagers/LoyaltyAdjustmentsRelationManager.php`
- **Details:**
  **BookingsRelationManager** — read-only list of this customer's bookings, most recent first:
  ```php
  protected static string $relationship = 'bookings';
  public function isReadOnly(): bool { return true; }

  // Columns: confirmation_code, showtime.movie.title, showtime.start_time, total_cents, status
  ```

  **LoyaltyAdjustmentsRelationManager** — read-only list of admin-applied adjustments:
  ```php
  protected static string $relationship = 'loyaltyAdjustments';
  public function isReadOnly(): bool { return true; }

  // Columns: created_at, adminUser.email (who), change_type, points_delta, reason
  ```

  Both surface prominently on the user view page — staff get a complete customer picture at a glance.

- **Acceptance Criteria:**
  - [ ] Bookings relation shows customer bookings in reverse-chronological order
  - [ ] LoyaltyAdjustments relation shows admin changes with causer
  - [ ] Both read-only

---

### Task 5: Loyalty actions (adjust, upgrade, revoke)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/UserResource.php` (modify — add actions)
- **Details:**
  Three header/row actions on UserResource.

  **Adjust Points** (permission: `loyalty.adjust_points` — narrower than a broad `loyalty.adjust` per Plan 02 Task 3):
  ```php
  Action::make('adjust_points')
      ->label('Adjust Points')
      ->icon('heroicon-o-sparkles')
      ->visible(fn () => auth()->user()->can('loyalty.adjust_points'))
      ->form([
          TextInput::make('points_delta')
              ->numeric()
              ->required()
              ->helperText('Positive to add, negative to deduct'),
          Textarea::make('reason')
              ->required()
              ->minLength(10)
              ->helperText('Required. Logged permanently for audit.'),
      ])
      ->requiresConfirmation()
      ->modalDescription(function ($record, $data) {
          $delta = (int) ($data['points_delta'] ?? 0);
          $threshold = config('loyalty.large_adjustment_threshold');

          if (abs($delta) >= $threshold) {
              return "⚠️ This is a large adjustment (±{$threshold}+ points). Please double-check before confirming. This action is permanent and logged.";
          }
          return "Adjusting {$delta} points for {$record->email}. Reason will be logged.";
      })
      ->action(fn ($record, $data) =>
          app(LoyaltyService::class)->adjustPoints(
              $record,
              (int) $data['points_delta'],
              $data['reason'],
              auth()->user(),
          ))
      ->successNotificationTitle('Points adjusted and logged.');
  ```

  **Upgrade to Premier** (permission: `loyalty.adjust_tier`):
  ```php
  Action::make('upgrade_premier')
      ->label('Upgrade to Premier')
      ->icon('heroicon-o-star')
      ->visible(fn ($record) => auth()->user()->can('loyalty.adjust_tier') && $record->loyalty_tier === 'member')
      ->form([
          DatePicker::make('expiry')
              ->required()
              ->default(now()->addYear())
              ->helperText('Premier tier expires on this date unless renewed'),
          Textarea::make('reason')->required(),
      ])
      ->requiresConfirmation()
      ->action(fn ($record, $data) =>
          app(LoyaltyService::class)->upgradeToPremier(
              $record, Carbon::parse($data['expiry']), auth()->user(),
          ));
  ```

  **Revoke Premier** (permission: `loyalty.adjust_tier`):
  ```php
  Action::make('revoke_premier')
      ->label('Revoke Premier')
      ->icon('heroicon-o-shield-exclamation')
      ->color('danger')
      ->visible(fn ($record) => auth()->user()->can('loyalty.adjust_tier') && $record->loyalty_tier === 'premier')
      ->form([Textarea::make('reason')->required()])
      ->requiresConfirmation()
      ->action(fn ($record, $data) =>
          app(LoyaltyService::class)->revokePremier($record, $data['reason'], auth()->user()));
  ```

  **Resolves spec § 8 open question #5:** v1 uses an elevated confirmation modal for large adjustments (configurable threshold via `loyalty.large_adjustment_threshold`) but does not require a second admin's sign-off. Activity log is the compensating control. Document in the admin README.

- **Acceptance Criteria:**
  - [ ] Adjust Points action gated on `loyalty.adjust_points` permission (seeded in Plan 02 Task 3)
  - [ ] Upgrade Premier and Revoke Premier actions gated on `loyalty.adjust_tier` permission (seeded in Plan 02 Task 3)
  - [ ] Large-adjustment modal warning triggers at configured threshold
  - [ ] Reason required, min 10 chars
  - [ ] Upgrade Premier available only for members; revoke only for premier
  - [ ] All three call `LoyaltyService` via facade
  - [ ] Activity log captures causer, delta, reason
  - [ ] Permission test covers the split: a user with only `loyalty.adjust_points` can adjust points but cannot upgrade/revoke premier; a user with only `loyalty.adjust_tier` can change tier but cannot adjust raw points

---

### Task 6: Booking lookup landing page

- **MoSCoW:** Could Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Pages/BookingLookup.php` (new)
- **Details:**
  Dedicated quick-lookup page for phone-based customer support. Staff enter a confirmation code or email, hit enter, land on the booking view.

  ```php
  class BookingLookup extends Page
  {
      protected static string $view = 'filament.pages.booking-lookup';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
      protected static ?int $navigationSort = 1;

      public ?string $query = null;

      public function search(): void
      {
          $this->validate(['query' => 'required|string|min:3']);

          $booking = Booking::query()
              ->where('confirmation_code', strtoupper($this->query))
              ->orWhere('customer_email', $this->query)
              ->latest()
              ->first();

          if (!$booking) {
              Notification::make()->title('No booking found')->warning()->send();
              return;
          }

          $this->redirect(BookingResource::getUrl('view', ['record' => $booking]));
      }
  }
  ```

  Keyboard-friendly: form submits on Enter, focus persists for repeated lookups.

- **Acceptance Criteria:**
  - [ ] Page at `/admin/booking-lookup`
  - [ ] Accepts confirmation code or email
  - [ ] Redirects to matching booking view on success
  - [ ] Shows "not found" notification on miss
  - [ ] Navigation item in Operations group

---

### Task 7: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/BookingResourceTest.php` (new)
  - `admin/tests/Feature/Resources/UserResourceTest.php` (new)
  - `admin/tests/Feature/LoyaltyActionsTest.php` (new — integration)
  - `admin/tests/Feature/Pages/BookingLookupTest.php` (new)
- **Details:**
  **BookingResourceTest:**
  - Test: list page searchable by confirmation_code
  - Test: canCreate / canEdit / canDelete return false for all roles
  - Test: view page shows all booking details
  - Test: filters (date range, status, location) apply correctly

  **UserResourceTest:**
  - Test: list page shows users with loyalty badges
  - Test: edit form restricted to loyalty fields
  - Test: non-loyalty fields not submittable

  **LoyaltyActionsTest:**
  - Test: adjust points with positive delta increases balance + writes adjustment
  - Test: adjust points with negative delta decreases balance
  - Test: large delta (≥ threshold) triggers elevated modal (assert form state)
  - Test: upgrade premier sets tier + expiry + writes adjustment
  - Test: revoke premier sets tier=member + writes adjustment
  - Test: Adjust Points requires `loyalty.adjust_points`; Upgrade / Revoke Premier require `loyalty.adjust_tier`. A user with only one of the two permissions can exercise that surface but not the other.
  - Test (concurrency): two parallel transactions each call `adjustPoints($user, +100)`. Both succeed, the final balance reflects both deltas (+200 total), and two distinct `LoyaltyAdjustment` rows exist with different causer ids. Neither call silently discards the other's write. Uses separate DB connections or explicit `DB::transaction` blocks to simulate the race.
  - Test (concurrency): parallel `adjustPoints($user, +100)` and `redeemPoints($user, 50)` converge on +50 net, with both activity-log rows written in the correct causer context.

  **BookingLookupTest:**
  - Test: valid confirmation code redirects to booking view
  - Test: valid email redirects to most recent booking
  - Test: invalid input shows error notification

- **Acceptance Criteria:**
  - [ ] All four test files green
  - [ ] Loyalty flow tested end-to-end (action → service → adjustment row → activity log)
  - [ ] Permission matrix covered

---

## Testing Requirements

- **Pest Feature Tests:** booking read-only, user edit constraints, loyalty action flows, lookup shortcut
- **Integration:** full loyalty adjustment chain (UI → facade → backend service → DB write → activity log)
- **Backend service tests:** Task 1 coverage of `LoyaltyService`

## Dependencies Map

```
Task 1 (LoyaltyService) ← foundational
Task 2 (BookingResource) ← parallel to Task 1
Task 3 (UserResource) ← parallel
Task 4 (relation managers) ← needs Task 3
Task 5 (loyalty actions) ← needs Tasks 1, 3
Task 6 (lookup page) ← needs Task 2
Task 7 (tests) ← needs all
```

## Risks & Open Questions

1. **Threshold tuning.** Default 1000 points may be too low or too high depending on program economics. Document in admin README that the threshold is configurable via `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` env and leave final value to product.
2. **Dual control deferral.** Spec § 8 flags dual control as a v1 non-goal with single-admin + activity log as the compensating control. Plan 09 deployment docs must emphasize that admin accounts are strictly controlled (not shared) because the accountability trail depends on accurate causer attribution.
3. **Adjustment race conditions — closed.** Previous note flagged the need for `lockForUpdate`; this is now a hard acceptance criterion on Task 1, covered by a concurrency test in Task 7. No longer a risk.
4. **Bulk adjustments.** Some future use case (campaign promo: give every user in segment X 100 points) isn't in v1 scope. If requested, build via a dedicated `bulk:adjust-loyalty` artisan command that still calls `LoyaltyService` per user — keeps the write boundary intact. A bulk path must either take a single `SystemCauser` for the batch or a per-user `Causer` resolver; bulk-without-causer is rejected at the service boundary.
