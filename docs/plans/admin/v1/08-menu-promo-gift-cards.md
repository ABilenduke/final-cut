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

### Task 1: Audit/extract backend PromoCodeService and GiftCardService

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/PromoCodeService.php` (new or extend)
  - `backend/app/Services/GiftCardService.php` (new or extend)
  - `backend/tests/Feature/PromoCodeServiceTest.php`
  - `backend/tests/Feature/GiftCardServiceTest.php`
  - `admin/app/Services/Backend/PromoCodeService.php` (modify)
  - `admin/app/Services/Backend/GiftCardService.php` (modify)
- **Details:**
  Audit backend. Extract if needed.

  **PromoCodeService:**
  ```php
  class PromoCodeService
  {
      public function create(array $attributes): PromoCode;
      public function update(PromoCode $promo, array $attributes): PromoCode;
      public function deactivate(PromoCode $promo): void;
      public function validateCode(string $code, int $bookingTotalCents): ?PromoCode;
  }
  ```

  `validateCode` already likely exists on customer side — ensure it handles expiry, usage limit, and `is_active` correctly. No changes to customer flow.

  **GiftCardService:**
  ```php
  class GiftCardService
  {
      public function purchase(array $attributes): GiftCard; // customer path — leave alone
      public function void(GiftCard $giftCard, string $reason, AdminUser $by): void;
      public function findByCode(string $code): ?GiftCard;
      public function getBalance(GiftCard $giftCard): int;
      public function redeemAgainstBooking(GiftCard $giftCard, int $amountCents, Booking $booking): void;
  }
  ```

  `void` is admin-only. It sets `status='voided'`, writes `voided_at=now()`, stores reason, and dispatches a finance notification email (Task 5).

  No real Stripe refund in v1 — voiding sets the card inactive. Finance manually processes refund to original purchase source if requested.

- **Acceptance Criteria:**
  - [ ] Both services exist with documented methods
  - [ ] `PromoCodeService::validateCode` works as customer flow
  - [ ] `GiftCardService::void` atomic: status + timestamp + activity log
  - [ ] Backend tests green
  - [ ] Admin facades delegate correctly

---

### Task 2: MenuItemResource (location-scoped)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/MenuItemResource.php` (new)
  - `admin/app/Filament/Resources/MenuItemResource/Pages/*`
- **Details:**
  `$permissionPrefix = 'menu'`. Scoped per-location via form and filter.

  **Form schema:**
  ```php
  Section::make('Identity')->schema([
      Select::make('location_id')->relationship('location', 'name')->required()->searchable(),
      TextInput::make('name')->required()->maxLength(255),
      TextInput::make('slug')->required()->unique(ignoreRecord: true),
      Select::make('category')->options([
          'popcorn' => 'Popcorn',
          'drinks' => 'Drinks',
          'snacks' => 'Snacks',
          'combos' => 'Combos',
          'specials' => 'Specials',
      ])->required(),
  ])->columns(2),

  Section::make('Content')->schema([
      Textarea::make('description'),
      FileUpload::make('image_path')
          ->image()
          ->directory('menu-items')
          ->disk('public')
          ->imageEditor(),
      TextInput::make('price_cents')->numeric()->required()->suffix(' ¢')
          ->helperText('Cents, e.g., $5.99 = 599'),
  ]),

  Section::make('Dietary / Allergens')->schema([
      CheckboxList::make('allergens')->options([
          'nuts' => 'Nuts', 'dairy' => 'Dairy', 'gluten' => 'Gluten',
          'soy' => 'Soy', 'eggs' => 'Eggs', 'shellfish' => 'Shellfish',
      ])->columns(3),
      CheckboxList::make('dietary')->options([
          'vegan' => 'Vegan', 'vegetarian' => 'Vegetarian', 'gluten_free' => 'Gluten-Free',
      ])->columns(3),
  ]),

  Section::make('Availability')->schema([
      Toggle::make('available')->default(true)
          ->helperText('Toggle off to temporarily hide from the customer menu without deleting the item.'),
  ]),
  ```

  **Table:**
  ```php
  ImageColumn::make('image_path')->square()->defaultImageUrl('/images/menu-placeholder.png'),
  TextColumn::make('name')->searchable()->sortable(),
  TextColumn::make('location.name')->label('Location'),
  BadgeColumn::make('category'),
  TextColumn::make('price_cents')->formatStateUsing(fn ($s) => CurrencyFormatter::format($s))->sortable(),
  IconColumn::make('available')->boolean(),
  TextColumn::make('allergens')->badge()->separator(','),
  ```

  **Filters:** location, category, availability.

  Writes go through `MenuService` facade — even though menu items are simple CRUD with no downstream invariants today, routing through the service keeps the pattern consistent and leaves space for future pricing/availability rules.

  **Image handling:** Filament's `FileUpload` stores images in `storage/app/public/menu-items/`. Ensure admin container has `php artisan storage:link` run (Plan 01 Task 7's `admin-install` target handles this). Images must be served via the admin domain — add a public asset proxy if needed, or write to a shared storage volume both apps can read from.

- **Acceptance Criteria:**
  - [ ] Resource under "Catalog" or dedicated "Menu" navigation group
  - [ ] Form validates required fields
  - [ ] Image upload works end-to-end (stored + displayed)
  - [ ] Allergens / dietary persisted as array columns
  - [ ] Per-location scoping via form select + list filter
  - [ ] Availability toggle soft-hides from customer menu (verify customer API filters)

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
      ->unique(ignoreRecord: true)
      ->alphaDash()
      ->helperText('Uppercase letters, numbers, and dashes only. Customers enter this at checkout.'),
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
  - Deactivate (sets `is_active=false` via `PromoCodeService::deactivate`)
  - Delete (soft-delete with cascade warning if code has been used)

- **Acceptance Criteria:**
  - [ ] Form validates code format
  - [ ] Amount field helper switches with discount type
  - [ ] List shows usage / limit
  - [ ] Deactivate action routes through service
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
  - BalanceHistoryRelationManager (read-only ledger of redemptions)
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
  - [ ] Balance history relation manager shows redemption ledger

---

### Task 5: Finance notification stub

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Mail/GiftCardVoidedMail.php` (new)
  - `backend/resources/views/mail/gift-card-voided.blade.php` (new)
  - `backend/config/finance.php` (new — configurable finance email)
- **Details:**
  When a gift card is voided, `GiftCardService::void` dispatches a mail to the configured finance address:

  ```php
  Mail::to(config('finance.notification_email'))->send(new GiftCardVoidedMail($giftCard, $reason, $by));
  ```

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
  - [ ] Mail class dispatches on void
  - [ ] Template renders all documented fields
  - [ ] Finance email configurable via env
  - [ ] Mailpit captures in dev
  - [ ] Mail dispatched asynchronously (queued) to avoid blocking the UI

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
  - Assert `Mail::fake()` captured `GiftCardVoidedMail`
  - Assert email recipient matches `FINANCE_NOTIFICATION_EMAIL`
  - Assert activity log causer = acting admin

- **Acceptance Criteria:**
  - [ ] All four test files green
  - [ ] Gift card void integration covers mail dispatch
  - [ ] Permission matrix covered per role (admin/manager/ops)

---

## Testing Requirements

- **Pest Feature Tests:** menu CRUD, promo CRUD, gift card read + void, per-location scoping, permission matrix
- **Integration:** gift card void → mail → activity log
- **Backend service tests:** Tasks 1 ensures services have independent coverage

## Dependencies Map

```
Task 1 (backend services) ← foundational
Task 2 (MenuItemResource) ← parallel to Tasks 3, 4
Task 3 (PromoCodeResource) ← needs Task 1
Task 4 (GiftCardResource) ← needs Task 1
Task 5 (finance mail) ← needs Task 4
Task 6 (tests) ← needs all
```

## Risks & Open Questions

1. **Menu image storage.** Admin writes to `storage/app/public/menu-items/` but the customer-facing app serves these. Both apps need to read the same disk. Options: (a) mount `backend/storage/app/public` into the admin container at the same path, (b) use S3/MinIO for both apps. MVP: mount the backend's public storage dir into admin as `/backend/storage/app/public` + configure admin's `public` disk root to point there. Document in Plan 01's compose changes.
2. **Gift card void and legitimate refund path.** Spec § 8 open question #6 — is email to finance sufficient? For v1 with two locations and low gift card volume, yes. If volume grows, add a dedicated "Gift Card Refund Queue" page in a v2 iteration.
3. **Promo code race on usage count.** Customer-side promo application increments `uses_count`. Admin deactivating a code during active checkouts should not lose in-flight usage counts. Since admin just sets `is_active=false` (no counter manipulation), this is safe.
4. **PII in finance emails.** The void email includes recipient email + sender name. Ensure `FINANCE_NOTIFICATION_EMAIL` points at an internal distribution list with appropriate access controls.
