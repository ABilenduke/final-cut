# Plan 08: Menu, Promo Codes & Gift Cards

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 05 (Locations — menu is location-scoped)
> **Unlocks:** —

## Overview

Merchandise and operational CRUD:

- **`MenuItemResource`** — food/drink items scoped per-location (category, allergens, dietary tags, price in cents, image, availability toggle).
- **`PromoCodeResource`** — discount codes (code, discount type enum, amount, usage limit, expires_at, active flag).
- **`GiftCardResource`** — read-focused; list all, search by code/recipient, view balance history. Single write action: "Void gift card" sets `status=voided`, logs reason, and alerts finance via email stub (since bookings are read-only in v1, void marks inactive without triggering a real refund).

All mutations route through the respective backend services (`MenuService`, `PromoCodeService`, `GiftCardService`) per spec § 2.6.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 5 Plan 08
- `docs/plans/backend/v1/07-calendar-content-api.md` — food menu & gift card backend
- `docs/architecture/DATA_MODELS.md` — MenuItem, PromoCode, GiftCard schemas

---

## Tasks

### Task 1: Extract PromoCodeService and GiftCardService into the shared-domain package

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files (domain package):**
  - `packages/shared-domain/src/Services/PromoCodeService.php` (new)
  - `packages/shared-domain/src/Services/GiftCardService.php` (new — may reuse an existing backend class as the starting point)
  - `packages/shared-domain/src/Enums/GiftCardStatus.php` (new — moves from `backend/app/Enums/`; add `Voided` case)
  - `packages/shared-domain/src/Enums/GiftCardLedgerType.php` (new)
  - `packages/shared-domain/src/Exceptions/GiftCardNotVoidableException.php` (new)
  - `packages/shared-domain/src/Exceptions/PromoCodeInUseException.php` (new)
  - `packages/shared-domain/src/Models/GiftCardLedgerEntry.php` (new)
- **Files (backend — customer-facing surface this task retrofits):**
  - `backend/app/Http/Controllers/Api/BookingController.php` (modify — pass explicit `Causer` when calling `redeemAgainstBooking` from the booking confirmation flow; switch the promo-code lookup from `config('promo_codes.*')` to `PromoCodeService::validateCode`)
  - `backend/app/Http/Controllers/Api/GiftCardController.php` (modify — customer gift card purchase endpoint; pass explicit `Causer` for the purchasing customer, or a `SystemCauser` for guest purchases)
  - `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php` (modify in-place per CLAUDE.md "Pre-launch migrations" — voided columns; add `disabled_at`-style fields)
  - `backend/database/migrations/<timestamp>_create_gift_card_ledger_entries_table.php` (new)
  - `backend/tests/Feature/GiftCardPurchaseTest.php` (modify — assertions adapt to ledger writes being additive, no customer-visible response shape change)
  - `backend/tests/Feature/GiftCardRedemptionTest.php` (modify — same)
  - `backend/tests/Feature/BookingTest.php` (modify — any assertions that exercise promo-code or gift-card redemption from booking flow update to pass Causer in tests and tolerate ledger rows)
  - `backend/tests/Feature/PromoCodeValidationTest.php` (new if absent — covers the switch from static config to DB-backed `PromoCodeService::validateCode`)
- **Files (admin):**
  - `admin/app/Services/Backend/PromoCodeService.php` (new — admin facade)
  - `admin/app/Services/Backend/GiftCardService.php` (new — admin facade)
- **Scope note — this task retrofits customer-facing paths.** Task 1 touches the booking-flow controllers that already redeem gift cards and apply promo codes. The ledger writes are additive (new rows in a new table) and do not change customer-visible response shape. The existing backend feature tests for gift-card purchase, gift-card redemption against a booking, and promo-code application must continue to pass with their assertions intact. Any test change should be limited to (a) passing `Causer` in test setup, and (b) adding ledger-row assertions — never modifying or deleting existing response-shape assertions.
- **Details:**
  Per Plan 03's ADR, both services live in `packages/shared-domain/src/Services/` under `FinalCut\Domain\Services`. Every write method takes an explicit `Causer $causer` per the Plan 02 Task 4 contract. Customer-path callers pass a customer `User` as the causer (or a `SystemCauser` for guest purchases); admin-path callers pass an `AdminUser`.

  **PromoCodeService:**
  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\PromoCode;

  class PromoCodeService
  {
      public function create(array $attributes, Causer $causer): PromoCode;
      public function update(PromoCode $promo, array $attributes, Causer $causer): PromoCode;
      public function deactivate(PromoCode $promo, Causer $causer): void;
      // Enforces: $promo->uses_count === 0. Throws PromoCodeInUseException otherwise.
      // Used codes must be deactivated (not deleted) to preserve historical records.
      public function delete(PromoCode $promo, Causer $causer): void;
      public function validateCode(string $code, int $bookingTotalCents): ?PromoCode; // read path — no causer
      public function incrementUsage(PromoCode $promo, Causer $causer): void;         // called from booking confirmation
  }
  ```

  `validateCode` is a read-only helper (no audit, no Causer). `incrementUsage` is the write path called from the customer booking confirmation to record consumption — it takes a Causer (the customer or the SystemCauser) so the activity log correctly attributes "promo code used" events.

  **Current state — config-based promo codes.** Promo codes today live in `config/promo_codes.php` as static config (see `BookingController::87` — `config("promo_codes.{$promoCode}")`). This task includes creating the `promo_codes` table, model, and factory from scratch, plus porting the customer `validateCode` path off the config lookup onto the service. **Edit `BookingController` in place, no compatibility shim.** The customer-facing response for a valid promo code does not change shape; the internal lookup source does.

  **GiftCardService (single domain service, mixed customer + admin surface):**
  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\GiftCard;
  use FinalCut\Domain\Models\Booking;

  class GiftCardService
  {
      // Customer paths — retrofit: now take explicit Causer, still write ledger entries.
      public function purchase(array $attributes, Causer $causer): GiftCard;
      public function redeemAgainstBooking(GiftCard $giftCard, int $amountCents, Booking $booking, Causer $causer): void;

      // Read paths — no causer.
      public function findByCode(string $code): ?GiftCard;
      public function getBalance(GiftCard $giftCard): int;

      // Admin-only operation on the same domain service.
      // @admin — not called from customer-facing code.
      public function void(GiftCard $giftCard, string $reason, Causer $causer): void;
  }
  ```

  **`void()` domain rules (must be enforced in the service, not only in the UI):**

  - Precondition: `$giftCard->status === GiftCardStatus::Active`. Any other status (`Depleted`, `Expired`, or the new `Voided` case added in this task) raises `GiftCardNotVoidableException` with a structured reason (`already_voided`, `depleted`, `expired`). The Filament action catches this and converts it to a user-facing notification so stale UI state does not produce a silent no-op or double-log.
  - Operation is atomic: single DB transaction writes `status = Voided`, `voided_at = now()`, `voided_reason = $reason`, `voided_by_admin_user_id = $causer->id` (when the Causer is an `AdminUser`), writes a `gift_card_ledger_entries` row of `type = void` with `amount_cents = -($currentBalance)` and `balance_after_cents = 0`, logs an Activity entry (causer = `$causer`), and dispatches `GiftCardVoidedMail` (queued — see Task 5).
  - Idempotency: void is **not** silently idempotent. A second call on an already-voided card throws `GiftCardNotVoidableException('already_voided')`. This is intentional — two admins voiding the same card in a race should see a clear error, not both receive a "success" toast.
  - No Stripe refund in v1. Voiding sets the card inactive; finance manually processes refund to the original purchase source if requested.

  **Ledger retrofit (this task, customer-path surface):**

  Every existing `GiftCardService` write path also writes a `gift_card_ledger_entries` row in the same transaction:

  - `purchase()` → ledger entry `type = purchase`, `amount_cents = +$initialBalance`, `balance_after_cents = $initialBalance`, `admin_user_id = null`, `booking_id = null`, causer is the purchasing customer.
  - `redeemAgainstBooking()` → ledger entry `type = redemption`, `amount_cents = -$amount`, `balance_after_cents = $currentBalance - $amount`, `booking_id` set, causer is the booking's customer.
  - `void()` → as described above.

  The ledger write is additive: customer response shapes are unchanged. Existing `GiftCardPurchaseTest`, `GiftCardRedemptionTest`, and `BookingTest` assertions about response structure, status codes, and customer-visible payloads must continue to pass without modification.

  **Schema additions required as part of Task 1:**

  - Add `Voided` case to `GiftCardStatus` (currently only `Active | Depleted | Expired`).
  - Edit migration `2026_04_04_200004_create_gift_cards_table.php` in place (pre-launch — see CLAUDE.md "Pre-launch migrations"; fall back to additive if that state has ended) to add: `voided_at` (nullable timestamp), `voided_reason` (nullable text), `voided_by_admin_user_id` (nullable FK → admin users table from Plan 02).
  - Create `gift_card_ledger_entries` table with the fields documented in Task 4.

- **Acceptance Criteria:**
  - [ ] `FinalCut\Domain\Services\PromoCodeService` and `FinalCut\Domain\Services\GiftCardService` exist in `packages/shared-domain/src/Services/`
  - [ ] Every write method signature declares an explicit `Causer $causer` parameter
  - [ ] `PromoCodeService::validateCode` is DB-backed and `BookingController` calls it; `config/promo_codes.php` is removed from the customer lookup path (kept only if still used elsewhere, flagged to remove)
  - [ ] `GiftCardStatus` enum includes `Voided`; `gift_cards` migration (edited in place or additive per pre-launch rule) adds `voided_at`, `voided_reason`, `voided_by_admin_user_id`
  - [ ] `GiftCardService::void` atomic (status + timestamp + reason + causer + ledger entry + activity log + queued mail) in a single transaction
  - [ ] `GiftCardService::void` rejects non-active cards with `GiftCardNotVoidableException` carrying a structured reason (`already_voided`, `depleted`, `expired`)
  - [ ] `gift_card_ledger_entries` table and `GiftCardLedgerEntry` model exist (fields documented in Task 4)
  - [ ] Every `GiftCardService` write path (`purchase`, `redeemAgainstBooking`, `void`) writes a ledger entry in the same transaction; a test asserts that a forced rollback leaves no orphaned ledger rows
  - [ ] **Existing customer-path tests (`GiftCardPurchaseTest`, `GiftCardRedemptionTest`, `BookingTest`) pass without modification of their response-shape assertions.** Test changes are limited to passing a `Causer` in setup and adding ledger-row assertions.
  - [ ] Admin facades at `admin/app/Services/Backend/{Promo,GiftCard}Service.php` delegate to the domain services, resolve `Causer` from `auth()->user()`, import from `FinalCut\Domain` — no `Backend\` namespace references

---

### Task 2: MenuItemResource (location-scoped)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/MenuItemResource.php` (new)
  - `admin/app/Filament/Resources/MenuItemResource/Pages/*`
  - `admin/config/filesystems.php` (modify — reconfigure `public` disk root to shared mount)
  - `admin/.env.example` (modify — document shared-storage env)
  - `docker-compose.yml` (modify — shared `storage-public` named volume across backend + admin; coordinate with Plan 01)
- **Details:**
  `$permissionPrefix = 'menu'`.

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
  TextColumn::make('price')->formatStateUsing(fn ($s) => CurrencyFormatter::format($s))->sortable(),
  IconColumn::make('available')->boolean(), // computed attribute — no column, renders the derived state
  TextColumn::make('allergens')->badge()->separator(','),
  ```

  **Filters:** location (filters by pivot presence), category, availability (`whereNull('unavailable_at')`), accepts multi-location filter via the pivot.

  **Availability contract (what `is_available=false` means):**

  - **Browse endpoints** (`GET /api/locations/:location/food-menu`) filter out items where the global `menu_items.unavailable_at` is not null, OR the pivot row's `location_menu_item.unavailable_at` is not null. Either level hides the item from customer-facing listings.
  - **In-progress carts** are not mutated retroactively. If an admin marks an item unavailable while a customer has it in their cart, the cart keeps the line item for display purposes. The server-side **checkout validator** (Plan 05 food-menu service already covers this for price; extend to availability) rejects the booking with a `410 Gone`-style structured error listing the unavailable items, and the customer is prompted to remove them before retrying payment.
  - **No retroactive refunds.** Bookings that already confirmed are unaffected. Availability only gates future carts and in-flight checkouts.

  This contract is the same at both the global and per-location level. The pivot `unavailable_at` is a narrower hide (one location only); the base-table `unavailable_at` is a global hide.

  Writes go through `MenuService` facade — even though menu items are simple CRUD with no downstream invariants today, routing through the service keeps the pattern consistent and leaves space for future pricing/availability rules.

  **Image handling (prerequisite, not optional):**

  Menu images uploaded by admin must be readable by the customer-facing Nuxt + Laravel stack without copying or proxying at request time. The v1 decision is a **shared `public` disk backed by a shared named volume** mounted into both the backend and admin containers at the same path.

  - Docker Compose: add a named volume `storage-public` mounted to `/var/www/html/storage/app/public` in both the backend and admin services. This is a change to Plan 01's compose file and belongs in Plan 01 Task 7 (or a follow-up task there). Explicitly listed in Dependencies Map below.
  - Backend `filesystems.php`: the `public` disk continues to point at `storage/app/public` (default).
  - Admin `filesystems.php`: the `public` disk is reconfigured to point at the same mount — either via `FILESYSTEM_PUBLIC_ROOT` env plus a custom disk definition, or by running admin from the same `storage/` directory shape as backend. Whichever path is taken must be the same in `.env.example`.
  - `php artisan storage:link` runs in the backend container only (the customer app is what serves `/storage/...` URLs). Admin writes to the shared disk; the backend serves it.
  - `FileUpload::make('image_url')->disk('public')->directory('menu-items')` — the stored value is the relative path (e.g., `menu-items/popcorn-large.jpg`). API responses turn this into a full URL via `Storage::disk('public')->url(...)` in the menu resource.

  No proxying, no duplicate uploads, no separate asset domain. Both containers see the same bytes at the same path.

- **Acceptance Criteria:**
  - [ ] Resource under "Catalog" or dedicated "Menu" navigation group
  - [ ] Form validates required fields
  - [ ] Image upload works end-to-end from admin to customer app via the shared storage volume (read from customer domain, not proxied)
  - [ ] Allergens / dietary persisted as JSON on `menu_items`, cast to `array` on the model (no pivot table)
  - [ ] Per-location scoping uses the `location_menu_item` pivot with `price_override` and per-location `unavailable_at`; form supports attaching one item to multiple locations
  - [ ] Global availability toggle maps to `menu_items.unavailable_at` (null = available)
  - [ ] Customer browse endpoint filters items where either global or per-location `unavailable_at` is not null
  - [ ] Checkout-time food validator rejects unavailable items with a structured error; in-progress carts are not silently mutated

---

### Task 3: PromoCodeResource

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Resources/PromoCodeResource.php` (new)
  - `admin/app/Filament/Resources/PromoCodeResource/Pages/*`
- **Details:**
  `$permissionPrefix = 'promos'`.

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
      $r->discount_type === 'percentage' ? "{$s}%" : CurrencyFormatter::format($s)),
  TextColumn::make('uses_count')->label('Used')->formatStateUsing(fn ($s, $r) =>
      $r->usage_limit ? "{$s} / {$r->usage_limit}" : (string) $s),
  TextColumn::make('expires_at')->dateTime()->sortable(),
  IconColumn::make('is_active')->boolean(),
  ```

  **Row actions:**
  - Edit
  - Deactivate (sets `is_active=false` via `PromoCodeService::deactivate`). Use this for codes that have been used at least once — deactivation preserves the historical record for refunds, finance reconciliation, and accounting.
  - Delete — **restricted to never-used codes only**. Action is hidden (not just disabled) when `uses_count > 0`. `PromoCodeService::delete()` enforces the same rule at the service layer and throws `PromoCodeInUseException` if called on a used code, so stale UI state cannot bypass the rule. Used codes are operational history; overwriting them via delete makes past bookings harder to audit.

- **Acceptance Criteria:**
  - [ ] Form validates code format
  - [ ] Code input is auto-uppercased in the UI and persisted uppercase; lowercase/mixed input saves as uppercase with no manual step
  - [ ] Uniqueness check runs against the uppercased value, so `promo10` and `PROMO10` cannot coexist
  - [ ] Amount field helper switches with discount type
  - [ ] List shows usage / limit
  - [ ] Deactivate action routes through service
  - [ ] Delete action visible only for codes with `uses_count = 0`; used codes show Deactivate only (see Task 3 deletion rule below)
  - [ ] Activity log captures deactivation with causer

---

### Task 4: GiftCardResource (read-focused)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/GiftCardResource.php` (new)
  - `admin/app/Filament/Resources/GiftCardResource/Pages/*`
  - `admin/app/Filament/Resources/GiftCardResource/RelationManagers/BalanceHistoryRelationManager.php` (new)
- **Details:**
  `$permissionPrefix = 'gift_cards'`. Create/edit disabled. Delete disabled. Void is the sole write action.

  **Table:**
  ```php
  TextColumn::make('code')->searchable()->copyable()->badge(),
  TextColumn::make('recipient_name'),
  TextColumn::make('recipient_email')->searchable(),
  TextColumn::make('sender_name'),
  TextColumn::make('initial_balance_cents')->label('Initial')
      ->formatStateUsing(fn ($s) => CurrencyFormatter::format($s)),
  TextColumn::make('current_balance_cents')->label('Balance')
      ->formatStateUsing(fn ($s) => CurrencyFormatter::format($s))
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
      ->visible(fn ($record) => auth()->user()->can('gift_cards.void') && $record->status === 'active')
      ->form([
          Textarea::make('reason')->required()->minLength(20)
              ->helperText('Required. Finance team is notified via email.'),
      ])
      ->requiresConfirmation()
      ->modalDescription(fn ($record) =>
          "This will void the gift card with remaining balance " .
          CurrencyFormatter::format($record->current_balance_cents) .
          ". Finance will be notified by email to process a refund to the original purchaser.")
      ->action(fn ($record, array $data) =>
          app(GiftCardService::class)->void($record, $data['reason'], auth()->user()))
      ->successNotificationTitle('Gift card voided. Finance notified.');
  ```

- **Acceptance Criteria:**
  - [ ] Resource read-only except for void
  - [ ] Void form requires reason ≥ 20 chars
  - [ ] Void action visible only for active cards + permission
  - [ ] Confirmation modal shows remaining balance
  - [ ] Action routes through `GiftCardService::void`
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
  // The mailable constructor accepts the Causer so the email can surface which admin voided the card.
  Mail::to(config('finance.notification_email'))->send(new GiftCardVoidedMail($giftCard, $reason, $causer));
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
  - **Voided by:** {{ $by->email }}
  - **Reason:** {{ $reason }}

  Please contact the original purchaser to arrange a refund to their original payment method.

  @endcomponent
  ```

  In dev, Mailpit captures these — open `localhost:8025` to verify. In prod (Plan 09), the `FINANCE_NOTIFICATION_EMAIL` points at a real address.

- **Acceptance Criteria:**
  - [ ] `GiftCardVoidedMail` implements `ShouldQueue` and runs on the `notifications` queue
  - [ ] Mail dispatches on void; asserted with `Mail::fake()` + `Mail::assertQueued(GiftCardVoidedMail::class, ...)` (not `assertSent`) to verify the queued path
  - [ ] Template renders all documented fields
  - [ ] Finance email configurable via env
  - [ ] Mailpit captures in dev (queue worker must be running — documented in dev setup notes)

---

### Task 6: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/MenuItemResourceTest.php` (new)
  - `admin/tests/Feature/Resources/PromoCodeResourceTest.php` (new)
  - `admin/tests/Feature/Resources/GiftCardResourceTest.php` (new)
  - `admin/tests/Feature/GiftCardVoidFlowTest.php` (new — integration)
- **Details:**
  **MenuItemResourceTest:**
  - CRUD happy path via service facade
  - Image upload persists and is retrievable
  - Per-location filter works
  - Availability toggle hides from customer menu (backend integration assertion)

  **PromoCodeResourceTest:**
  - Create/update/deactivate
  - Unique code enforcement
  - Amount field validation by discount type
  - Deactivate routes through service

  **GiftCardResourceTest:**
  - Create/edit disabled
  - Void action visible only for active cards + permission
  - Void form requires reason
  - Balance history relation manager shows ledger rows

  **GiftCardVoidFlowTest (integration):**
  - Void action → service write → mail dispatch → activity log entry
  - Assert `Mail::fake()` captured `GiftCardVoidedMail` via `Mail::assertQueued(...)` (not `assertSent`, to verify the queued path)
  - Assert email recipient matches `FINANCE_NOTIFICATION_EMAIL`
  - Assert activity log causer = acting admin
  - Assert a `gift_card_ledger_entries` row of type `void` was written in the same transaction as the status change (covers Task 4's ledger-write acceptance criterion)

  **GiftCardServiceTest — void rejection (backend-direct, bypasses UI):**

  This is the guardrail for stale-UI and direct-call scenarios. The Filament action's `visible()` check is necessary but not sufficient — staff can hit the service via a queued job, tinker, or a future API. Covers:

  - `void()` on a `Voided` card throws `GiftCardNotVoidableException` with reason `already_voided`; no second ledger entry, no second email, no activity log write.
  - `void()` on a `Depleted` card throws with reason `depleted`.
  - `void()` on an `Expired` card throws with reason `expired`.
  - Race scenario: two concurrent `void()` calls on the same active card — first wins, second throws `already_voided`. Exactly one ledger entry, one email, one activity log row. Use `DB::transaction` + `lockForUpdate` inside the service (matches the booking concurrency pattern used elsewhere in the codebase) and assert the losing call sees the post-commit state.

- **Acceptance Criteria:**
  - [ ] All four test files green
  - [ ] Gift card void integration covers queued mail dispatch and ledger-entry write
  - [ ] Service-level void rejection test covers `Voided`, `Depleted`, `Expired`, and the concurrent-void race — independent of any UI state
  - [ ] Permission matrix covered per role (admin/manager/ops)

---

## Testing Requirements

- **Pest Feature Tests:** menu CRUD, promo CRUD, gift card read + void, per-location scoping, permission matrix
- **Integration:** gift card void → mail → activity log
- **Backend service tests:** Tasks 1 ensures services have independent coverage

## Dependencies Map

```
Plan 01 compose (shared storage-public volume) ← blocks Task 2
Task 1 (backend services + ledger migration) ← foundational
Task 2 (MenuItemResource) ← needs Plan 01 compose; parallel to Tasks 3, 4
Task 3 (PromoCodeResource) ← needs Task 1
Task 4 (GiftCardResource) ← needs Task 1 (ledger table must exist)
Task 5 (finance mail) ← needs Task 4
Task 6 (tests) ← needs all
```

## Risks & Open Questions

1. **Menu image storage (decided, tracked as a Plan 01 dependency).** The v1 decision is a shared named Docker volume mounted to both backend and admin at the same path, with the admin `public` disk reconfigured to write into that mount. Full details in Task 2's "Image handling" section. If Plan 01's compose file is not updated to add the shared volume before Plan 08 starts, Task 2 is blocked — not deferred with a workaround. S3/MinIO remains the future path once either app grows beyond a single host.
2. **Gift card void and legitimate refund path.** Spec § 8 open question #6 — is email to finance sufficient? For v1 with two locations and low gift card volume, yes. If volume grows, add a dedicated "Gift Card Refund Queue" page in a v2 iteration.
3. **Promo code race on usage count.** Customer-side promo application increments `uses_count`. Admin deactivating a code during active checkouts should not lose in-flight usage counts. Since admin just sets `is_active=false` (no counter manipulation), this is safe.
4. **PII in finance emails.** The void email includes recipient email + sender name. Ensure `FINANCE_NOTIFICATION_EMAIL` points at an internal distribution list with appropriate access controls.
