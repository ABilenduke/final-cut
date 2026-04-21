# Plan 08: Menu, Promo Codes & Gift Cards

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 05 (Locations — menu is location-scoped)
> **Unlocks:** —

## Overview

Merchandise and operational CRUD:

- **`MenuItemResource`** — food/drink items scoped per-location (category, allergens, dietary tags, price in cents, image, availability toggle). Menu items have no downstream invariants in v1, so Filament pages write directly via Eloquent — no dedicated service.
- **`PromoCodeResource`** — discount codes (code, discount type enum, amount, usage limit, expires_at, active flag). Routes through `App\Services\PromoCodeService`.
- **`GiftCardResource`** — read-focused; list all, search by code/recipient, view balance history. Single write action: "Void gift card" sets `status=voided`, logs reason, and alerts finance via queued email (since bookings are read-only in v1, void marks the card inactive without triggering a real refund). Routes through `App\Services\GiftCardService`.

`PromoCodeService` and `GiftCardService` are created in this plan and stored in `backend/app/Services/` alongside the existing `TmdbService`, `SeatAvailabilityService`, `StripeService`, `LoyaltyService` (and the services created in Plans 04–07). Every write method accepts an optional `?AdminUser $actor = null` for audit attribution — Filament pages pass `auth('admin')->user()`; customer controllers pass `null`. The services write `activity_log` rows when `$actor` is non-null and skip admin activity attribution otherwise.

Filament Resources consume `App\Models\MenuItem`, `App\Models\PromoCode`, and `App\Models\GiftCard` directly — there is no admin-side model mirror, no shared package, no cross-app boundary. Services and models live in the same codebase and autoload via PSR-4.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 admin-to-domain-logic boundary, § 5 Plan 08
- `docs/plans/backend/v1/07-calendar-content-api.md` — food menu & gift card backend
- `docs/architecture/DATA_MODELS.md` — MenuItem, PromoCode, GiftCard schemas
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — BaseResource, FormatsCurrency, TimestampColumns

---

## Tasks

### Task 1: Create `PromoCodeService` and `GiftCardService` in `backend/app/Services/`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/PromoCodeService.php` (new)
  - `backend/app/Services/GiftCardService.php` (new — may reuse an existing backend class as the starting point)
  - `backend/app/Enums/GiftCardStatus.php` (modify — add `Voided` case)
  - `backend/app/Enums/GiftCardLedgerType.php` (new)
  - `backend/app/Exceptions/GiftCardNotVoidableException.php` (new)
  - `backend/app/Exceptions/PromoCodeInUseException.php` (new)
  - `backend/app/Models/GiftCardLedgerEntry.php` (new)
  - `backend/app/Http/Controllers/Api/BookingController.php` (modify — delegate promo lookup and gift-card redemption to the services; pass `null` as `$actor`, since the customer-facing booking flow is not admin-attributed)
  - `backend/app/Http/Controllers/Api/GiftCardController.php` (modify — customer gift card purchase endpoint; pass `null` as `$actor` for guest purchases, and for authenticated customer purchases too since these are not admin writes)
  - `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php` (modify in-place per CLAUDE.md "Pre-launch migrations" — add `voided_at`, `voided_reason`, `voided_by_admin_user_id`)
  - `backend/database/migrations/<timestamp>_create_promo_codes_table.php` (new)
  - `backend/database/migrations/<timestamp>_create_gift_card_ledger_entries_table.php` (new)
  - `backend/tests/Feature/GiftCardPurchaseTest.php` (modify — assertions adapt to ledger writes being additive, no customer-visible response shape change)
  - `backend/tests/Feature/GiftCardRedemptionTest.php` (modify — same)
  - `backend/tests/Feature/BookingTest.php` (modify — tolerate ledger-entry rows added by gift-card redemption during booking confirmation)
  - `backend/tests/Feature/PromoCodeValidationTest.php` (new if absent — covers the switch from static `config/promo_codes.php` to DB-backed `PromoCodeService::validateCode`)
  - `backend/tests/Unit/Admin/PromoCodeServiceTest.php` (new)
  - `backend/tests/Unit/Admin/GiftCardServiceTest.php` (new)
- **Scope note — this task retrofits customer-facing paths.** Task 1 touches the booking-flow controllers that already redeem gift cards and apply promo codes. The ledger writes are additive (new rows in a new table) and do not change customer-visible response shape. Existing backend feature tests for gift-card purchase, gift-card redemption against a booking, and promo-code application must continue to pass with their assertions intact. Any test change should be limited to (a) adapting setup to the service call signature, and (b) adding ledger-row assertions — never modifying or deleting existing response-shape assertions.
- **Details:**
  Both services live next to the existing domain services in `backend/app/Services/`. Every write method takes an optional `?AdminUser $actor = null` as the last parameter. Filament pages pass `auth('admin')->user()`; customer API controllers pass `null`.

  **PromoCodeService:**

  ```php
  namespace App\Services;

  use App\Exceptions\PromoCodeInUseException;
  use App\Models\AdminUser;
  use App\Models\PromoCode;

  class PromoCodeService
  {
      public function create(array $attributes, ?AdminUser $actor = null): PromoCode;

      public function update(PromoCode $promo, array $attributes, ?AdminUser $actor = null): PromoCode;

      public function deactivate(PromoCode $promo, ?AdminUser $actor = null): void;

      /**
       * Hard delete. Used codes must be deactivated (not deleted) to preserve historical records.
       * Throws PromoCodeInUseException when $promo->uses_count > 0.
       */
      public function delete(PromoCode $promo, ?AdminUser $actor = null): void;

      // Read path — no actor.
      public function validateCode(string $code, int $bookingTotalCents): ?PromoCode;

      // Write path called from booking confirmation to record consumption.
      public function incrementUsage(PromoCode $promo, ?AdminUser $actor = null): void;
  }
  ```

  `validateCode` is a read-only helper (no audit, no actor). `incrementUsage` is the write path called from the customer booking confirmation; customer callers pass `null` for `$actor`, so no admin activity row is written — the usage increment itself is the audit trail on the promo code row.

  **Current state — config-based promo codes.** Promo codes today live in `config/promo_codes.php` as static config (see `BookingController` — `config("promo_codes.{$promoCode}")`). This task creates the `promo_codes` table, model, and factory from scratch, and ports the customer `validateCode` path off the config lookup onto the service. **Edit `BookingController` in place, no compatibility shim.** The customer-facing response for a valid promo code does not change shape; the internal lookup source does.

  **GiftCardService (single domain service, mixed customer + admin surface):**

  ```php
  namespace App\Services;

  use App\Exceptions\GiftCardNotVoidableException;
  use App\Models\AdminUser;
  use App\Models\Booking;
  use App\Models\GiftCard;

  class GiftCardService
  {
      // Customer paths — retrofit: still write ledger entries, now accept optional actor.
      public function purchase(array $attributes, ?AdminUser $actor = null): GiftCard;

      public function redeemAgainstBooking(
          GiftCard $giftCard,
          int $amountCents,
          Booking $booking,
          ?AdminUser $actor = null,
      ): void;

      // Read paths — no actor.
      public function findByCode(string $code): ?GiftCard;
      public function getBalance(GiftCard $giftCard): int;

      // Admin-only operation on the same domain service.
      // Callers from the customer API pass $actor = null; in practice only the Filament action calls this.
      public function void(GiftCard $giftCard, string $reason, ?AdminUser $actor = null): void;
  }
  ```

  **`void()` domain rules (must be enforced in the service, not only in the UI):**

  - Precondition: `$giftCard->status === GiftCardStatus::Active`. Any other status (`Depleted`, `Expired`, or the new `Voided` case added in this task) raises `GiftCardNotVoidableException` with a structured reason (`already_voided`, `depleted`, `expired`). The Filament action catches this and converts it to a user-facing notification so stale UI state does not produce a silent no-op or double-log.
  - Operation is atomic: single DB transaction writes `status = Voided`, `voided_at = now()`, `voided_reason = $reason`, `voided_by_admin_user_id = $actor?->id`, writes a `gift_card_ledger_entries` row of `type = void` with `amount_cents = -($currentBalance)` and `balance_after_cents = 0`, logs an Activity entry (causer = `$actor` when set), and dispatches `GiftCardVoidedMail` (queued — see Task 5).
  - Idempotency: void is **not** silently idempotent. A second call on an already-voided card throws `GiftCardNotVoidableException('already_voided')`. This is intentional — two admins voiding the same card in a race should see a clear error, not both receive a "success" toast.
  - No Stripe refund in v1. Voiding sets the card inactive; finance manually processes refund to the original purchase source if requested.

  **Ledger retrofit (this task, customer-path surface):**

  Every existing `GiftCardService` write path also writes a `gift_card_ledger_entries` row in the same transaction:

  - `purchase()` → ledger entry `type = purchase`, `amount_cents = +$initialBalance`, `balance_after_cents = $initialBalance`, `admin_user_id = null`, `booking_id = null`.
  - `redeemAgainstBooking()` → ledger entry `type = redemption`, `amount_cents = -$amount`, `balance_after_cents = $currentBalance - $amount`, `booking_id` set.
  - `void()` → as described above.

  The ledger write is additive: customer response shapes are unchanged. Existing `GiftCardPurchaseTest`, `GiftCardRedemptionTest`, and `BookingTest` assertions about response structure, status codes, and customer-visible payloads must continue to pass without modification.

  **Schema additions required as part of Task 1:**

  - Add `Voided` case to `GiftCardStatus` (currently only `Active | Depleted | Expired`).
  - Edit migration `2026_04_04_200004_create_gift_cards_table.php` in place (pre-launch — see CLAUDE.md "Pre-launch migrations"; fall back to additive if that state has ended) to add: `voided_at` (nullable timestamp), `voided_reason` (nullable text), `voided_by_admin_user_id` (nullable FK → admin users table from Plan 02).
  - Create `promo_codes` table with columns `code`, `discount_type`, `amount`, `usage_limit`, `per_user_limit`, `uses_count`, `expires_at`, `is_active`, timestamps.
  - Create `gift_card_ledger_entries` table with the fields documented in Task 4.

  **Extraction principles:**

  - Validation stays at the HTTP boundary (Laravel `FormRequest` or `$request->validate()`) — the services accept pre-validated arrays and enforce only domain invariants (unique promo code at save time, void precondition).
  - Mutation and orchestration move into the services — create models, sync relationships, write ledger rows, dispatch mail, emit activity-log rows.
  - Existing customer API controllers continue to handle HTTP request parsing and response formatting; they pass `null` for `$actor` because customer API writes are not admin-attributed.

- **Acceptance Criteria:**
  - [ ] `App\Services\PromoCodeService` and `App\Services\GiftCardService` exist in `backend/app/Services/`
  - [ ] Every write method signature accepts `?AdminUser $actor = null` (last parameter)
  - [ ] `PromoCodeService::validateCode` is DB-backed and `BookingController` calls it; `config/promo_codes.php` is removed from the customer lookup path (kept only if still used elsewhere, flagged to remove)
  - [ ] `GiftCardStatus` enum includes `Voided`; `gift_cards` migration (edited in place or additive per pre-launch rule) adds `voided_at`, `voided_reason`, `voided_by_admin_user_id`
  - [ ] `GiftCardService::void` atomic (status + timestamp + reason + actor FK + ledger entry + activity log + queued mail) in a single transaction
  - [ ] `GiftCardService::void` rejects non-active cards with `GiftCardNotVoidableException` carrying a structured reason (`already_voided`, `depleted`, `expired`)
  - [ ] `gift_card_ledger_entries` table and `GiftCardLedgerEntry` model exist (fields documented in Task 4)
  - [ ] Every `GiftCardService` write path (`purchase`, `redeemAgainstBooking`, `void`) writes a ledger entry in the same transaction; a test asserts that a forced rollback leaves no orphaned ledger rows
  - [ ] When `$actor` is set, each write emits an `activity_log` row with `causer` resolving to the admin user; when `$actor` is null, no row is written
  - [ ] **Existing customer-path tests (`GiftCardPurchaseTest`, `GiftCardRedemptionTest`, `BookingTest`) pass without modification of their response-shape assertions.** Test changes are limited to adapting setup and adding ledger-row assertions.
  - [ ] Existing backend test suite still green after the extraction

---

### Task 2: MenuItemResource (location-scoped, direct Eloquent writes)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/MenuItemResource.php` (new)
  - `backend/app/Filament/Resources/MenuItemResource/Pages/*` (new)
  - `backend/config/filesystems.php` (verify — `public` disk configuration)
  - `docker-compose.yml` (verify — `storage/app/public` is already writable from the backend container; no cross-app storage changes needed in the single-app architecture)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'menu'`.

  **Menu items have no downstream invariants in v1** (no pricing rules beyond the stored cents, no cart-state mutation when availability flips — see the availability contract below). Writes go through Filament's default Eloquent persistence; there is no `MenuService`. The spec's admin-to-domain-logic boundary table (§ 2.6) explicitly lists menu items as "Direct Eloquent write (no invariant)".

  If a future invariant appears (e.g., per-location min/max prices, synchronized combos), promote the writes into a `MenuService` following the same `?AdminUser $actor = null` pattern as the other services.

  **Canonical backend schema (anchoring this resource, verified against `backend/app/Models/MenuItem.php` and `2026_04_04_200006_create_menu_items_table.php`):**

  - `price` — `unsignedInteger` (cents). Not `price_cents`.
  - `image_url` — nullable `string`. Not `image_path`.
  - `allergens` — `json` column cast to `array`. Values are string tokens (`'nuts'`, `'dairy'`, ...). Not a pivot table.
  - `dietary` — `json` column cast to `array`. Same shape as allergens.
  - `unavailable_at` — nullable `timestamp`. This is the source of truth for availability per CLAUDE.md's "booleans as timestamps" convention. The `available` attribute on the model is a computed `Attribute` (`is_null($this->unavailable_at)`), not a stored column.
  - **Per-location scoping is via the `location_menu_item` pivot**, not a `location_id` column on `menu_items`. The pivot carries `price_override` and per-location `unavailable_at`. A single menu item can belong to multiple locations, each with independent price and availability. (The form spec below reflects this — the location selector is a multi-select pivot, not a belongsTo.)

  **Form schema:**

  ```php
  Section::make('Identity')->schema([
      TextInput::make('name')->required()->maxLength(255),
      Select::make('category')->options(MenuCategory::options())->required(),
  ])->columns(2),

  Section::make('Locations & Pricing')
      ->description('Menu items can be offered at multiple locations with per-location price overrides.')
      ->schema([
          Repeater::make('locations')
              ->relationship('locations')
              ->schema([
                  Select::make('location_id')
                      ->relationship('location', 'name')
                      ->required(),
                  TextInput::make('price_override')->numeric()->nullable()
                      ->suffix(' ¢')
                      ->helperText('Leave blank to inherit the base price.'),
                  DateTimePicker::make('unavailable_at')->nullable()
                      ->helperText('Set a timestamp to hide this item at this location only. Clear to re-enable.'),
              ])
              ->columns(3)
              ->minItems(1),
      ]),

  Section::make('Content')->schema([
      Textarea::make('description'),
      FileUpload::make('image_url')
          ->image()
          ->directory('menu-items')
          ->disk('public')
          ->imageEditor(),
      TextInput::make('price')->numeric()->required()->suffix(' ¢')
          ->helperText('Base price in cents, e.g., $5.99 = 599. Individual locations may override.'),
  ]),

  Section::make('Dietary / Allergens')->schema([
      CheckboxList::make('allergens')->options([
          'nuts' => 'Nuts', 'dairy' => 'Dairy', 'gluten' => 'Gluten',
          'soy' => 'Soy', 'eggs' => 'Eggs', 'shellfish' => 'Shellfish',
      ])->columns(3)
          ->helperText('Persisted as a JSON array on menu_items.allergens (cast to array on the model).'),
      CheckboxList::make('dietary')->options([
          'vegan' => 'Vegan', 'vegetarian' => 'Vegetarian', 'gluten_free' => 'Gluten-Free',
      ])->columns(3)
          ->helperText('Persisted as a JSON array on menu_items.dietary (cast to array on the model).'),
  ]),

  Section::make('Availability (global)')->schema([
      Toggle::make('is_available')
          ->label('Available globally')
          ->default(true)
          ->helperText('Off = hide this item across all customer menus regardless of per-location state.')
          ->dehydrateStateUsing(fn (bool $state) => $state ? null : now())
          ->formatStateUsing(fn (?string $state) => is_null($state))
          ->afterStateHydrated(function (Toggle $component, $state, $record) {
              $component->state(is_null($record?->unavailable_at));
          })
          // Bound to the `unavailable_at` column — Filament surfaces a boolean UI
          // backed by the real nullable-timestamp column.
          ->statePath('unavailable_at'),
  ]),
  ```

  **Table:**

  ```php
  ImageColumn::make('image_url')->square()->defaultImageUrl('/images/menu-placeholder.png'),
  TextColumn::make('name')->searchable()->sortable(),
  TextColumn::make('locations.name')->label('Locations')->badge()->separator(','),
  BadgeColumn::make('category'),
  TextColumn::make('price')
      ->formatStateUsing(fn ($s) => self::centsToDisplay($s))
      ->sortable(),
  IconColumn::make('available')->boolean(), // computed attribute — no column, renders the derived state
  TextColumn::make('allergens')->badge()->separator(','),
  ...TimestampColumns::standardTimestamps(),
  ```

  **Filters:** location (filters by pivot presence), category, availability (`whereNull('unavailable_at')`), multi-location filter via the pivot.

  **Availability contract (what `is_available=false` means):**

  - **Browse endpoints** (`GET /api/locations/:location/food-menu`) filter out items where the global `menu_items.unavailable_at` is not null, OR the pivot row's `location_menu_item.unavailable_at` is not null. Either level hides the item from customer-facing listings.
  - **In-progress carts** are not mutated retroactively. If an admin marks an item unavailable while a customer has it in their cart, the cart keeps the line item for display purposes. The server-side **checkout validator** (Plan 05 food-menu service already covers this for price; extend to availability) rejects the booking with a `410 Gone`-style structured error listing the unavailable items, and the customer is prompted to remove them before retrying payment.
  - **No retroactive refunds.** Bookings that already confirmed are unaffected. Availability only gates future carts and in-flight checkouts.

  This contract is the same at both the global and per-location level. The pivot `unavailable_at` is a narrower hide (one location only); the base-table `unavailable_at` is a global hide.

  **Image handling.** In the single-app architecture, Filament writes uploaded images to the backend's `storage/app/public/menu-items/` directory via `FileUpload::make('image_url')->disk('public')`. `php artisan storage:link` exposes them at `/storage/menu-items/...` on the customer domain. The same Laravel process serves both admin and customer routes from a shared filesystem — no cross-container volume mounts, no proxy, no separate storage disk. The stored value on the row is the relative path (`menu-items/popcorn-large.jpg`); API responses turn it into a full URL via `Storage::disk('public')->url(...)` in the menu resource.

- **Acceptance Criteria:**
  - [ ] Resource under "Catalog" or dedicated "Menu" navigation group
  - [ ] Form validates required fields; writes go through Filament's default Eloquent persistence (no service)
  - [ ] Image upload works end-to-end from admin page to customer food-menu API via the shared `public` disk
  - [ ] Allergens / dietary persisted as JSON on `menu_items`, cast to `array` on the model (no pivot table)
  - [ ] Per-location scoping uses the `location_menu_item` pivot with `price_override` and per-location `unavailable_at`; form supports attaching one item to multiple locations
  - [ ] Global availability toggle maps to `menu_items.unavailable_at` (null = available)
  - [ ] Customer browse endpoint filters items where either global or per-location `unavailable_at` is not null
  - [ ] Checkout-time food validator rejects unavailable items with a structured error; in-progress carts are not silently mutated
  - [ ] Permission gating works per role (admin full, manager full, ops read-only)

---

### Task 3: PromoCodeResource

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Resources/PromoCodeResource.php` (new)
  - `backend/app/Filament/Resources/PromoCodeResource/Pages/*` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'promos'`.

  **Form:**

  ```php
  TextInput::make('code')
      ->required()
      ->maxLength(32)
      ->alphaDash()
      // Normalize to uppercase on every keystroke (live) AND on save (dehydrate).
      // Validation, uniqueness check, and persistence all see the uppercased value,
      // so we never store mixed-case codes that technically validate but confuse
      // the "uppercase only" UX contract.
      ->live(onBlur: false)
      ->afterStateUpdated(fn ($state, callable $set) => $set('code', strtoupper((string) $state)))
      ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
      ->unique(ignoreRecord: true)
      ->helperText('Uppercase letters, numbers, and dashes only. Input is auto-uppercased before validation and save. Customers enter this at checkout.'),
  Select::make('discount_type')->options([
      'percentage' => 'Percentage off',
      'fixed_cents' => 'Fixed amount (cents)',
  ])->required()->reactive(),
  TextInput::make('amount')->numeric()->required()
      ->suffix(fn ($get) => $get('discount_type') === 'percentage' ? '%' : ' ¢')
      ->helperText(fn ($get) => $get('discount_type') === 'percentage'
          ? '1-100 (represents percentage)'
          : 'Cents, e.g., 500 = $5.00 off'),
  TextInput::make('usage_limit')->numeric()->nullable()
      ->helperText('Max total uses across all customers. Leave blank for unlimited.'),
  TextInput::make('per_user_limit')->numeric()->nullable()
      ->helperText('Max uses per individual user. Leave blank for unlimited.'),
  DateTimePicker::make('expires_at')->nullable(),
  Toggle::make('is_active')->default(true),
  ```

  **Table:**

  ```php
  TextColumn::make('code')->searchable()->copyable()->badge(),
  TextColumn::make('discount_type')->formatStateUsing(fn ($s) => match ($s) {
      'percentage' => 'Percent', 'fixed_cents' => 'Fixed',
  }),
  TextColumn::make('amount')->formatStateUsing(fn ($r, $s) =>
      $r->discount_type === 'percentage' ? "{$s}%" : self::centsToDisplay($s)),
  TextColumn::make('uses_count')->label('Used')->formatStateUsing(fn ($s, $r) =>
      $r->usage_limit ? "{$s} / {$r->usage_limit}" : (string) $s),
  TextColumn::make('expires_at')->dateTime()->sortable(),
  IconColumn::make('is_active')->boolean(),
  ```

  **Service routing.** Override `CreateRecord::handleRecordCreation` and `EditRecord::handleRecordUpdate` to call `PromoCodeService` instead of letting Filament persist directly:

  ```php
  // CreatePromoCode.php
  protected function handleRecordCreation(array $data): Model
  {
      return app(\App\Services\PromoCodeService::class)
          ->create($data, auth('admin')->user());
  }

  // EditPromoCode.php
  protected function handleRecordUpdate(Model $record, array $data): Model
  {
      return app(\App\Services\PromoCodeService::class)
          ->update($record, $data, auth('admin')->user());
  }
  ```

  **Row actions:**
  - Edit
  - Deactivate — sets `is_active=false` via `PromoCodeService::deactivate`. Use this for codes that have been used at least once — deactivation preserves the historical record for refunds, finance reconciliation, and accounting.

    ```php
    Action::make('deactivate')
        ->label('Deactivate')
        ->icon('heroicon-o-pause')
        ->color('warning')
        ->visible(fn ($record) => $record->is_active && auth('admin')->user()->can('promos.update'))
        ->requiresConfirmation()
        ->action(fn ($record) => app(\App\Services\PromoCodeService::class)
            ->deactivate($record, auth('admin')->user()));
    ```

  - Delete — **restricted to never-used codes only**. Action is hidden (not just disabled) when `uses_count > 0`. `PromoCodeService::delete()` enforces the same rule at the service layer and throws `PromoCodeInUseException` if called on a used code, so stale UI state cannot bypass the rule. Used codes are operational history; overwriting them via delete makes past bookings harder to audit.

    ```php
    DeleteAction::make()
        ->visible(fn ($record) => $record->uses_count === 0)
        ->using(fn (Model $record) => app(\App\Services\PromoCodeService::class)
            ->delete($record, auth('admin')->user()));
    ```

  **Delete must route through the service too.** A stock `DeleteAction::make()` without `->using()` would default to `$record->delete()` — a direct Eloquent write that bypasses the service, the `uses_count` guard, and the audit-log attribution. This is a convention enforced by test (Task 6), not by static analysis. A stock `DeleteAction::make()` without `->using()` is a test-caught regression.

- **Acceptance Criteria:**
  - [ ] Form validates code format
  - [ ] Code input is auto-uppercased in the UI and persisted uppercase; lowercase/mixed input saves as uppercase with no manual step
  - [ ] Uniqueness check runs against the uppercased value, so `promo10` and `PROMO10` cannot coexist
  - [ ] Amount field helper switches with discount type
  - [ ] List shows usage / limit
  - [ ] `handleRecordCreation` and `handleRecordUpdate` call `PromoCodeService`, passing `auth('admin')->user()` as actor
  - [ ] Deactivate action routes through `PromoCodeService::deactivate`
  - [ ] Delete action visible only for codes with `uses_count = 0`; used codes show Deactivate only
  - [ ] Delete action routes through `PromoCodeService::delete` via `->using()` — no direct Eloquent delete
  - [ ] Activity log captures deactivation and deletion with causer

---

### Task 4: GiftCardResource (read-focused)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/GiftCardResource.php` (new)
  - `backend/app/Filament/Resources/GiftCardResource/Pages/*` (new)
  - `backend/app/Filament/Resources/GiftCardResource/RelationManagers/BalanceHistoryRelationManager.php` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'gift_cards'`. Create/edit disabled. Delete disabled. Void is the sole write action.

  **Table:**

  ```php
  TextColumn::make('code')->searchable()->copyable()->badge(),
  TextColumn::make('recipient_name'),
  TextColumn::make('recipient_email')->searchable(),
  TextColumn::make('sender_name'),
  TextColumn::make('initial_balance_cents')->label('Initial')
      ->formatStateUsing(fn ($s) => self::centsToDisplay($s)),
  TextColumn::make('current_balance_cents')->label('Balance')
      ->formatStateUsing(fn ($s) => self::centsToDisplay($s))
      ->sortable(),
  BadgeColumn::make('status')->colors([
      'success' => 'active',
      'gray' => 'depleted',
      'danger' => 'voided',
      'warning' => 'expired',
  ]),
  TextColumn::make('purchased_at')->date()->sortable(),
  ```

  **Filters:** status, purchased date range, balance > 0.

  **View page:**
  - Code, recipient, sender, message
  - Initial balance, current balance, status
  - `BalanceHistoryRelationManager` — read-only ledger of redemptions, purchases, and voids. Backed by the `gift_card_ledger_entries` table and the `GiftCard::ledgerEntries()` hasMany relation (both new in Task 1). Fields per entry: `id`, `gift_card_id` (FK), `type` (enum: `purchase | redemption | void | adjustment`), `amount_cents` (signed integer — positive for credits, negative for debits), `balance_after_cents` (cached running balance after this entry, for fast reads without replaying the ledger), `booking_id` (nullable FK — set for redemptions), `admin_user_id` (nullable FK — set for voids/adjustments), `reason` (nullable text), `created_at`. Every `GiftCardService` write (purchase, redeem, void, adjustment) writes a ledger entry inside the same transaction; the resource surface here is strictly read-only.
  - Void action (if status='active' and permission `gift_cards.void`)

  **Void action:**

  ```php
  Action::make('void')
      ->label('Void Gift Card')
      ->color('danger')
      ->icon('heroicon-o-x-circle')
      ->visible(fn ($record) => auth('admin')->user()->can('gift_cards.void') && $record->status === 'active')
      ->form([
          Textarea::make('reason')->required()->minLength(20)
              ->helperText('Required. Finance team is notified via email.'),
      ])
      ->requiresConfirmation()
      ->modalDescription(fn ($record) =>
          "This will void the gift card with remaining balance " .
          self::centsToDisplay($record->current_balance_cents) .
          ". Finance will be notified by email to process a refund to the original purchaser.")
      ->action(function ($record, array $data) {
          try {
              app(\App\Services\GiftCardService::class)
                  ->void($record, $data['reason'], auth('admin')->user());
              Notification::make()->title('Gift card voided. Finance notified.')->success()->send();
          } catch (\App\Exceptions\GiftCardNotVoidableException $e) {
              Notification::make()
                  ->title('Gift card cannot be voided')
                  ->body(match ($e->reason) {
                      'already_voided' => 'This card has already been voided.',
                      'depleted' => 'This card has a zero balance.',
                      'expired' => 'This card is expired.',
                  })
                  ->danger()
                  ->send();
          }
      });
  ```

- **Acceptance Criteria:**
  - [ ] Resource read-only except for void
  - [ ] Void form requires reason ≥ 20 chars
  - [ ] Void action visible only for active cards + permission
  - [ ] Confirmation modal shows remaining balance
  - [ ] Action routes through `GiftCardService::void`, passing `auth('admin')->user()` as actor
  - [ ] `GiftCardNotVoidableException` caught in the action and surfaced as a user-facing notification with a reason-specific message
  - [ ] `gift_card_ledger_entries` table exists with the fields above and a `GiftCard::ledgerEntries()` hasMany relation
  - [ ] `BalanceHistoryRelationManager` reads from `ledgerEntries` and displays purchase, redemption, and void rows in reverse-chronological order
  - [ ] Every existing `GiftCardService` write path (purchase, redeem against booking, void) writes a ledger entry in the same transaction

---

### Task 5: Finance notification stub

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Mail/GiftCardVoidedMail.php` (new)
  - `backend/resources/views/mail/gift-card-voided.blade.php` (new)
  - `backend/config/finance.php` (new — configurable finance email)
- **Details:**
  `GiftCardVoidedMail` implements `Illuminate\Contracts\Queue\ShouldQueue` so every dispatch site (including future ones) queues by default and does not block the admin UI on SMTP latency. The mailable is placed on the `notifications` queue and uses the default connection (Redis in dev/prod, sync in tests via `phpunit.xml`). When a gift card is voided, `GiftCardService::void` dispatches:

  ```php
  // GiftCardVoidedMail implements ShouldQueue — this dispatch is queued.
  // The mailable constructor accepts the admin actor so the email can surface which admin voided the card.
  Mail::to(config('finance.notification_email'))->send(new GiftCardVoidedMail($giftCard, $reason, $actor));
  ```

  The `->send()` call is semantically correct here because `ShouldQueue` on the mailable makes Laravel queue the delivery regardless of the verb used. Tests exercise the queued path via `Mail::fake()` without needing a queue worker.

  Config:

  ```php
  // backend/config/finance.php
  return [
      'notification_email' => env('FINANCE_NOTIFICATION_EMAIL', 'finance@finalcut.test'),
  ];
  ```

  Template:

  ```blade
  {{-- backend/resources/views/mail/gift-card-voided.blade.php --}}
  @component('mail::message')
  # Gift Card Voided

  A gift card has been voided by an admin:

  - **Code:** {{ $giftCard->code }}
  - **Recipient:** {{ $giftCard->recipient_name }} ({{ $giftCard->recipient_email }})
  - **Sender:** {{ $giftCard->sender_name }}
  - **Remaining balance:** ${{ number_format($giftCard->current_balance_cents / 100, 2) }}
  - **Voided by:** {{ $by?->email ?? 'system' }}
  - **Reason:** {{ $reason }}

  Please contact the original purchaser to arrange a refund to their original payment method.

  @endcomponent
  ```

  In dev, Mailpit captures these — open `localhost:8025` to verify. In prod (Plan 09), the `FINANCE_NOTIFICATION_EMAIL` points at a real address.

- **Acceptance Criteria:**
  - [ ] `GiftCardVoidedMail` implements `ShouldQueue` and runs on the `notifications` queue
  - [ ] Mail dispatches on void; asserted with `Mail::fake()` + `Mail::assertQueued(GiftCardVoidedMail::class, ...)` (not `assertSent`) to verify the queued path
  - [ ] Template renders all documented fields, gracefully handling `$by === null` (system actor) by printing `'system'`
  - [ ] Finance email configurable via env
  - [ ] Mailpit captures in dev (queue worker must be running — documented in dev setup notes)

---

### Task 6: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Feature/Admin/Resources/MenuItemResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/MenuItemResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/PromoCodeResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/PromoCodeResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/GiftCardResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/GiftCardResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/GiftCardVoidFlowTest.php` (new — integration)
  - `backend/tests/Feature/Admin/Services/GiftCardServiceIntegrationTest.php` (new)
  - `backend/tests/Feature/Admin/Services/PromoCodeServiceIntegrationTest.php` (new)
- **Details:**
  Use Filament's Livewire test helpers. Tests split into two layers, matching the Plan 04 pattern.

  **Layer A — Resource tests (service mocked).** Verify each Resource wires form / actions / permissions to the service (for Promo and Gift Card) or to Eloquent (for Menu). Mock the relevant service via `$this->mock()` so no backend writes happen. These tests do not assert on `activity_log` — with a mocked service, no real mutation runs.

  **MenuItemResourceTest (direct Eloquent writes):**
  - Admin can list, create, update, and delete menu items
  - Image upload persists and is retrievable via the `public` disk
  - Per-location filter and multi-location pivot attach work
  - Global availability toggle maps to `unavailable_at` (null/non-null round-trip)
  - Customer browse endpoint filters out items hidden via global `unavailable_at` or per-location pivot `unavailable_at`

  **PromoCodeResourceTest (service mocked):**
  - Admin can list, create, and update promo codes → asserts `PromoCodeService::create` / `::update` called with expected payload and actor
  - Code is auto-uppercased on input and save; uniqueness check runs against the uppercased value
  - Amount field validation matches the selected discount type
  - Deactivate action calls `PromoCodeService::deactivate` with actor
  - **Delete action is hidden when `uses_count > 0`**
  - **Delete action routes through `PromoCodeService::delete` via `->using()` — direct `Model::delete()` was NOT called** (regression guard for stock `DeleteAction::make()` slipping in without `->using()`)

  **GiftCardResourceTest (service mocked):**
  - Create/edit pages not registered (or redirect)
  - List page accessible; search by code and recipient email works
  - Void action visible only for active cards + permission
  - Void form requires reason ≥ 20 chars
  - Void action calls `GiftCardService::void` with actor
  - `GiftCardNotVoidableException` thrown by the service is caught and surfaced as a user-facing notification (for each reason: `already_voided`, `depleted`, `expired`)
  - `BalanceHistoryRelationManager` renders ledger rows in reverse-chronological order

  **Permission tests (one per resource):**
  - ops cannot access menu create form (`canCreate` returns false)
  - ops cannot access promo create / edit / deactivate / delete
  - ops cannot see the gift card void action
  - manager can perform all menu / promo / gift card actions
  - nobody role cannot access any of the three list pages

  **Layer B — Service integration tests (real service, real DB).** A small number of tests exercise the real services end-to-end to verify activity-log attribution and ledger writes.

  **GiftCardVoidFlowTest (integration):**
  - Void action → real service write → mail dispatch → activity log entry
  - Assert `Mail::fake()` captured `GiftCardVoidedMail` via `Mail::assertQueued(...)` (not `assertSent`, to verify the queued path)
  - Assert email recipient matches `FINANCE_NOTIFICATION_EMAIL`
  - Assert activity log causer = acting admin
  - Assert a `gift_card_ledger_entries` row of type `void` was written in the same transaction as the status change (covers Task 4's ledger-write acceptance criterion)

  **GiftCardServiceIntegrationTest (backend-direct, bypasses UI):**

  This is the guardrail for stale-UI and direct-call scenarios. The Filament action's `visible()` check is necessary but not sufficient — staff can hit the service via a queued job, tinker, or a future API. Covers:

  - `purchase()` with `$actor = null` writes a `purchase` ledger entry and does NOT write an `activity_log` row
  - `redeemAgainstBooking()` with `$actor = null` writes a `redemption` ledger entry and does NOT write an `activity_log` row
  - `void()` on an `Active` card with `$actor` set writes status, ledger entry, activity row, and queues mail — all in one transaction (forced rollback leaves no orphaned ledger rows)
  - `void()` on a `Voided` card throws `GiftCardNotVoidableException` with reason `already_voided`; no second ledger entry, no second email, no activity log write
  - `void()` on a `Depleted` card throws with reason `depleted`
  - `void()` on an `Expired` card throws with reason `expired`
  - Race scenario: two concurrent `void()` calls on the same active card — first wins, second throws `already_voided`. Exactly one ledger entry, one email, one activity log row. Use `DB::transaction` + `lockForUpdate` inside the service (matches the booking concurrency pattern used elsewhere in the codebase) and assert the losing call sees the post-commit state

  **PromoCodeServiceIntegrationTest:**
  - Creating / updating / deactivating a code with `$actor` set writes an `activity_log` row with the expected description, causer, and subject
  - Writes with `$actor = null` do NOT write `activity_log` rows
  - `delete()` on a code with `uses_count > 0` throws `PromoCodeInUseException` — no row is removed, no activity log entry
  - `delete()` on an unused code with `$actor` set removes the row and writes a `deleted` activity entry
  - `validateCode()` returns the code row for an active, unexpired code with matching uppercased input; returns null for expired, inactive, over-limit, or non-existent codes
  - `incrementUsage()` increments `uses_count` atomically under concurrent calls

- **Acceptance Criteria:**
  - [ ] Layer A Resource tests cover list / create / update / delete (including stock-`DeleteAction` regression guard on PromoCode), void, and bulk / row actions
  - [ ] Layer A PermissionTests cover all three roles × all actions for each of the three resources
  - [ ] Layer A services are mocked — no real writes; no `activity_log` assertions at this layer
  - [ ] Layer B integration tests run the real services and verify `activity_log` writes (including the `$actor = null` skip case)
  - [ ] Gift card void integration covers queued mail dispatch and ledger-entry write
  - [ ] Service-level void rejection test covers `Voided`, `Depleted`, `Expired`, and the concurrent-void race — independent of any UI state
  - [ ] `make test-backend` passes all new tests green

---

## Testing Requirements

- **Layer A (Resource, service mocked):** menu CRUD, promo CRUD + service routing + delete visibility guard, gift card read + void action, per-location pivot scoping, permission matrix. No `activity_log` assertions.
- **Layer B (Service integration, real DB):** activity-log attribution with / without actor, gift card void atomicity + rejection + concurrent-void race, promo code `delete` guard, `validateCode` semantics, `incrementUsage` atomicity, ledger-entry writes on every `GiftCardService` write path.
- **Backend service tests (Task 1):** create / update / deactivate / delete / validate / increment paths for promo codes; purchase / redeem / void / find / balance paths for gift cards. Independent of Filament.

## Dependencies Map

```
Task 1 (PromoCodeService + GiftCardService + ledger migration) ← foundational
Task 2 (MenuItemResource) ← needs Plan 03 BaseResource + Plan 05 locations; parallel to Tasks 3, 4
Task 3 (PromoCodeResource) ← needs Task 1
Task 4 (GiftCardResource) ← needs Task 1 (ledger table must exist)
Task 5 (finance mail) ← needs Task 4
Task 6 (tests) ← needs all
```

## Risks & Open Questions

1. **Menu image storage (resolved by single-app architecture).** Admin and customer surfaces share the same Laravel process and filesystem; `storage/app/public` is already writable from the backend container and served via `php artisan storage:link`. No cross-container volume, no proxy, no separate disk. S3 remains the future path once the app grows beyond a single host, but this is not a v1 concern.
2. **Gift card void and legitimate refund path.** Spec § 8 open question #6 is resolved to queued `GiftCardVoidedMail` for v1 — sufficient for two locations and low gift card volume. If volume grows, add a dedicated "Gift Card Refund Queue" page in a v2 iteration.
3. **Promo code race on usage count.** Customer-side promo application increments `uses_count` via `PromoCodeService::incrementUsage` under `lockForUpdate` to serialize concurrent confirmations. Admin deactivating a code during active checkouts should not lose in-flight usage counts. Since admin just sets `is_active=false` (no counter manipulation), this is safe.
4. **PII in finance emails.** The void email includes recipient email + sender name. Ensure `FINANCE_NOTIFICATION_EMAIL` points at an internal distribution list with appropriate access controls.
5. **Stock `DeleteAction` regression on PromoCodeResource.** The write-boundary rule (admin deletes route through `PromoCodeService::delete` for the `uses_count` guard and audit attribution) is enforced only by Layer A Resource tests, not by static analysis. A future contributor adding a `DeleteAction::make()` without `->using()` slips through to a direct `$record->delete()` with no audit row and no usage guard. The Task 6 regression test catches it before merge. If regressions happen repeatedly, escalate — but start with the test, keep tooling light.
