# promo_codes.per_user_limit enforcement (by user_id AND normalized guest_email)

> **For agentic workers:** verified TDD dossier produced by the `design-three-hardening-features` workflow (8 mappers → 3 designers → 6 adversarial critics → 3 finalizers), empirically validated against the live `final_cut_test` Postgres. Steps are TDD: failing test first, minimal change, exact verify command.

**Goal:** Enforce `promo_codes.per_user_limit` per customer (authenticated by `user_id`, guest by canonicalized `guest_email`) via a `promo_code_id` link on bookings and a per-user lifetime count under the existing promo-row lock.

**Tech Stack:** Laravel 13 (PHP 8.4, Pest) · PostgreSQL 18 · Nuxt 4 (Vitest) · Docker Compose · Filament 5

> **Implementation deviations from this dossier (authoritative = the shipped code + `docs/progress/promo-per-user-limit.md`):**
> 1. **FK ordering.** The bookings migration could NOT use `->constrained()->nullOnDelete()` because `bookings` (04-04) is created before `promo_codes` (04-24). Shipped: a plain BIGINT `promo_code_id` column + composite index in the bookings migration, and the FK constraint added in the `create_promo_codes_table` migration (where the referenced table exists).
> 2. **No confirm() Phase-A pre-charge per-user check** (the dossier's Task 4 step 5). The store-time `validateCode` pre-check + the authoritative `consume()` lock cover correctness; the Phase-A optimization was dropped to keep the change focused (the rare concurrent-3DS race is handled by `consume()`'s charge-then-refund 409).
> 3. **Identity symmetry fix** (post-review): a guest email that belongs to a registered account also resolves that account's `user_id`, so prior authed redemptions count.

---


## Summary & verification notes

Enforce `promo_codes.per_user_limit` so a single customer (authenticated by `user_id`, or guest by canonicalized `guest_email`) cannot redeem a promo more than N times. The feature is built on five sequenced TDD tasks: (1) add a nullable BIGINT `promo_code_id` FK to `bookings` (promo_codes uses an auto-increment id, so `foreignId` NOT `foreignUuid`) plus a `promoCode()` relation; (2) persist `promo_code_id` onto the booking row in BOTH the direct and 3DS finalize paths; (3) enforce the limit in `PromoCodeService::consume()` (authoritative, under the existing `lockForUpdate`) and as a friendly `validateCode()` pre-check, keyed on a new `PromoRedemptionIdentity` DTO; (4) thread customer identity through `BookingController` (authed store, guest store, 3DS confirm) including a pre-charge per-user check in confirm() Phase A; (5) un-hide the Filament `per_user_limit` field and invert the guard test.

This dossier FOLDS IN every validated adversarial critique. The most important corrections vs. the original design: (a) the original instructed re-adding the already-existing `REASON_LIMIT_REACHED` constant — that is a fatal `Cannot redeclare class constant` and is REMOVED (only `REASON_PER_USER_LIMIT` is added); (b) guest `guest_email` is stored verbatim and matched exactly, trivially bypassable by case/whitespace — FIXED by canonicalizing email (lowercase+trim) in `CreateBookingRequest::prepareForValidation()` AND case-insensitive `lower()` matching in the count; (c) the authed-path identity must carry the user's lowercased account email so prior guest redemptions under the same email still count; (d) the count must NOT borrow `occupyingStatuses()` (that both creates a ~20-min abandoned-Held lockout and lets refunds reopen the slot) — it counts a deliberate lifetime set [Confirmed, RefundPending, Refunded]; (e) all existing `consume()`/`validateCode()` callers pass positional args, so new params are APPENDED nullable and new tests use named args; (f) the 3DS persistence/feature tests must call `$fakeStripe->shouldSucceed()` between the 3DS-trigger and the confirm POST or they 402-fail.

---

## Files

- **[modify]** `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — Add `$table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();` after the user_id line (line 23) and `$table->index(['promo_code_id', 'status']);` in the index block (after line 49). foreignId NOT foreignUuid (promo_codes uses $table->id()). Edit in place; `make fresh` after.
- **[modify]** `backend/app/Models/Booking.php` — Add 'promo_code_id' to #[Fillable] (line 39-43); add `@property int|null $promo_code_id` and `@property PromoCode|null $promoCode` docblock lines; add `promoCode(): BelongsTo` relation (BelongsTo already imported line 14; PromoCode is same App\Models namespace, no import needed).
- **[modify]** `backend/app/Http/Requests/CreateBookingRequest.php` — In prepareForValidation(), canonicalize the email field: merge a lowercased+trimmed email when present so guest_email becomes the source of truth and the per-user guest limit cannot be bypassed via case/whitespace. Mirrors RegisterRequest:31 / LoginRequest:29.
- **[create]** `backend/app/Services/PromoRedemptionIdentity.php` — NEW final readonly DTO with constructor props ?string $userId, ?string $guestEmail, ?string $excludeBookingId and an isEmpty() method. Carries customer identity for per-user enforcement; distinct from the admin $actor.
- **[modify]** `backend/app/Exceptions/PromoCodeNotConsumableException.php` — Add ONLY `public const REASON_PER_USER_LIMIT = 'per_user_limit';` and ONLY its match arm. DO NOT touch REASON_LIMIT_REACHED — it already exists at line 28/42; re-adding it is a fatal redeclare.
- **[modify]** `backend/app/Services/PromoCodeService.php` — Add imports `use App\Enums\BookingStatus;` and `use App\Models\Booking;` (with first usage in same edit). Append `?PromoRedemptionIdentity $identity = null` to consume() (after $actor) and `?PromoRedemptionIdentity $identity = null` to validateCode() (after $bookingTotalCents). Add per-user check under the existing lock in consume() (throws REASON_PER_USER_LIMIT) and an unlocked pre-check in validateCode() (returns null). Add private countRedemptions(PromoCode, PromoRedemptionIdentity): int using a deliberate lifetime status set [Confirmed, RefundPending, Refunded], case-insensitive lower(guest_email) match, and excludeBookingId. Update class + method docblocks (per-user enforcement now ships).
- **[modify]** `backend/app/Http/Controllers/Api/BookingController.php` — Add `use App\Services\PromoRedemptionIdentity;` (alphabetical among App\Services). Build identity for validateCode() pre-check (line 117). Set `$booking->promo_code_id = $promo?->id;` before save in store() Phase C (line 374) and confirm() Phase C (line 595). Extend finalizeBooking() signature with `?PromoRedemptionIdentity $promoIdentity = null` and pass it to consume(). Build identity at both finalize call sites (store line 376 from $booking + authed account email; confirm line 599 from $pendingData). Add a per-user pre-charge check in confirm() Phase A (lines 476-516) so a second concurrent 3DS redemption bails 409 before capture where possible.
- **[modify]** `backend/app/Models/PromoCode.php` — Update the `@property int|null $per_user_limit` docblock (line 17) from 'Reserved for v2 enforcement.' to reflect that it is now enforced per customer (by account or normalized email).
- **[modify]** `backend/app/Filament/Resources/PromoCodeResource.php` — Replace the comment block at lines 102-105 with a real `TextInput::make('per_user_limit')->numeric()->nullable()->minValue(1)->helperText(...)` mirroring usage_limit. No new permission (promos.create/update already gate the form).
- **[create]** `backend/tests/Feature/BookingPromoCodeLinkTest.php` — NEW Pest feature test: column exists, relation loads, nullable default, nullOnDelete behavior.
- **[modify]** `backend/tests/Unit/Admin/PromoCodeServiceTest.php` — Add imports (BookingStatus, Booking, PromoRedemptionIdentity) in same edit as usage. Add consume() per-user tests (authed limit, guest limit, lifetime-status counting incl. Refunded does NOT reopen, Held does NOT count, scoped-to-this-promo, no-identity back-compat) and validateCode() pre-check tests. Existing positional-arg tests stay untouched.
- **[modify]** `backend/tests/Feature/Api/BookingControllerTest.php` — Add promo_code_id persistence tests (direct + 3DS — 3DS test MUST call shouldSucceed() before confirm). Add end-to-end per-user-limit tests: authed second-redemption 400, guest second-redemption 400, guest case/whitespace bypass blocked, different-user independent 201, 3DS second-redemption charge-then-refund 409.
- **[modify]** `backend/tests/Feature/Admin/Resources/PromoCodeResourceTest.php` — Invert the lines 22-27 guard from assertFormFieldDoesNotExist to assertFormFieldExists('per_user_limit'); add a dehydration round-trip test (assert per_user_limit reaches the service create payload; use ->toEqual(3) loose to dodge Livewire string/int).

---

## Tasks

### T1: Add bookings.promo_code_id FK (schema + model + relation)

**Test scenarios:**
- Schema::hasColumn('bookings','promo_code_id') is true
- A booking can be created with promo_code_id and ->promoCode relation loads the PromoCode
- A booking with no promo persists promo_code_id = null
- Hard-deleting the promo nulls the booking link (nullOnDelete), booking row survives

**Implementation:**

Write failing test backend/tests/Feature/BookingPromoCodeLinkTest.php first (4 tests above; use PromoCode::factory()->create() and Booking::factory()->create(['promo_code_id'=>$promo->id])). Then: (1) Edit migration 2026_04_04_200009_create_bookings_table.php IN PLACE — after line 23 (`$table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();`) add `$table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();` with a comment noting foreignId-not-foreignUuid because promo_codes uses $table->id(). In the index block (after line 49 `$table->index('showtime_id');`) add `$table->index(['promo_code_id', 'status']);`. (2) Booking.php: add 'promo_code_id' to the #[Fillable] array (after 'guest_email' on line 39); add `@property int|null $promo_code_id` after the guest_email property line (24) and `@property PromoCode|null $promoCode` near the relation properties (after line 36); add `public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }` after the user() relation (~line 88). BelongsTo is already imported (line 14); PromoCode is in the same App\Models namespace so NO use-import is needed. BookingFactory needs no change (promo_code_id defaults null via fillable).

**Verify:** `make fresh && docker compose exec -u 1000 backend php artisan optimize:clear && docker compose exec -u 1000 backend php artisan test --filter=BookingPromoCodeLinkTest — all 4 green.`

### T2: Canonicalize booking email + persist promo_code_id in both finalize paths

**Test scenarios:**
- A direct booking with promoCode WELCOME5 records booking.promo_code_id = that promo's id
- A direct booking with no promo records promo_code_id = null
- A 3DS-confirmed booking records promo_code_id (test calls shouldSucceed() before confirm)
- A guest booking submitted with email 'Mixed@Case.com ' (mixed case + trailing space) persists guest_email = 'mixed@case.com'

**Implementation:**

Write failing tests in BookingControllerTest.php first. CRITICAL for the 3DS test: mirror the existing pattern at lines 940-955 — call $fakeStripe->shouldRequire3ds() for the store POST, capture data.paymentIntentId, then $fakeStripe->shouldSucceed() BEFORE the confirm POST, else confirm() returns 402. Then implement: (A) Email canonicalization — in CreateBookingRequest::prepareForValidation() add, alongside the existing idempotencyKey merge, `if ($this->filled('email')) { $this->merge(['email' => strtolower(trim((string) $this->input('email')))]); }`. This makes guest_email (stored from $request->input('email') at BookingController:157 and cached at :310) the canonical source of truth and is the write-half of the guest-bypass fix. (B) store() Phase C — at line ~373-374 add `$booking->promo_code_id = $promo?->id;` before `$booking->save();` ($promo is resolved at lines 365-370). (C) confirm() Phase C — at line ~593-595 add `$booking->promo_code_id = $promo?->id;` before `$booking->save();` ($promo resolved at lines 566-582). NOTE: no enforcement yet — this task only records the link + canonicalizes email.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest — new persistence + email-canonicalization tests green, no regressions.`

### T3: Enforce per_user_limit in PromoCodeService (consume authoritative + validateCode pre-check)

**Test scenarios:**
- consume() with per_user_limit null + identity → increments (unlimited per user)
- consume() throws REASON_PER_USER_LIMIT when user already redeemed up to the limit; uses_count NOT incremented (rollback)
- consume() throws for guests keyed on guest_email
- Guest count is case-insensitive: prior booking guest_email 'user@x.com' blocks identity guestEmail 'USER@X.COM'
- Lifetime status set: a Refunded prior redemption STILL counts (does not reopen the slot); a Cancelled prior redemption does NOT count; a Held prior redemption does NOT count (no abandoned-3DS lockout)
- Count scoped to THIS promo only (other promos irrelevant)
- consume() with no identity → only global usage_limit checked (back-compat)
- excludeBookingId excludes the named booking from the count
- validateCode() returns null when identity already at per_user_limit; ignores per_user_limit when no identity supplied

**Implementation:**

Write failing tests in PromoCodeServiceTest.php first; add `use App\Enums\BookingStatus; use App\Models\Booking; use App\Services\PromoRedemptionIdentity;` in the same edit. New tests pass identity by NAMED arg (identity:) so the 12 existing positional-arg calls (lines 133,141,151,165,183,198 + integration test 96-98) stay green. Create backend/app/Services/PromoRedemptionIdentity.php: `final readonly class PromoRedemptionIdentity { public function __construct(public ?string $userId = null, public ?string $guestEmail = null, public ?string $excludeBookingId = null) {} public function isEmpty(): bool { return $this->userId === null && $this->guestEmail === null; } }`. Exception: add ONLY `public const REASON_PER_USER_LIMIT = 'per_user_limit';` to PromoCodeNotConsumableException and ONLY `self::REASON_PER_USER_LIMIT => 'You have already used this promo code the maximum number of times.',` to the match — DO NOT re-add REASON_LIMIT_REACHED (already at line 28/42; redeclare = fatal). PromoCodeService: add the two imports; append `?PromoRedemptionIdentity $identity = null` to consume() (after $actor) and to validateCode() (after $bookingTotalCents). In consume(), after the usage_limit check (line 226-230) and before increment (232), add: `if ($locked->per_user_limit !== null && $identity !== null && ! $identity->isEmpty()) { if ($this->countRedemptions($locked, $identity) >= $locked->per_user_limit) { throw new PromoCodeNotConsumableException(PromoCodeNotConsumableException::REASON_PER_USER_LIMIT); } }`. In validateCode(), after the usage_limit check (154-156): `if ($promo->per_user_limit !== null && $identity !== null && ! $identity->isEmpty()) { if ($this->countRedemptions($promo, $identity) >= $promo->per_user_limit) { return null; } }`. Add private helper: `private function countRedemptions(PromoCode $promo, PromoRedemptionIdentity $identity): int { $statuses = array_map(fn (BookingStatus $s) => $s->value, [BookingStatus::Confirmed, BookingStatus::RefundPending, BookingStatus::Refunded]); return Booking::query()->where('promo_code_id', $promo->id)->whereIn('status', $statuses)->where(function ($q) use ($identity) { if ($identity->userId !== null) { $q->orWhere('user_id', $identity->userId); } if ($identity->guestEmail !== null) { $q->orWhereRaw('lower(guest_email) = ?', [mb_strtolower($identity->guestEmail)]); } })->when($identity->excludeBookingId !== null, fn ($q) => $q->where('id', '!=', $identity->excludeBookingId))->count(); }`. DELIBERATE STATUS DECISION (reject borrowing occupyingStatuses): per_user_limit is a lifetime anti-abuse cap — count Confirmed+RefundPending+Refunded (a refund does NOT hand back a fresh redemption), EXCLUDE Held (an abandoned 3DS Held row must not self-lock the user) and Cancelled. Update class docblock + the consume()/validateCode() docblocks (replace the 'deferred to v2' sentence) to state per-user enforcement now applies when an identity is supplied.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=PromoCodeServiceTest — all new + existing green.`

### T4: Thread customer identity through BookingController (authed/guest/3DS) + pre-charge 3DS check

**Test scenarios:**
- Authed user second redemption of a per_user_limit:1 promo → 400 at pre-check (errors.0.field = promoCode); uses_count stays 1
- Guest second redemption keyed on same (canonical) email → 400
- Guest bypass attempt: book 'mixed@case.com', then 'MIXED@CASE.COM ' → second is 400 (email canonicalized + case-insensitive count)
- Different user redeems the same promo independently → 201; uses_count = 2
- Authed user whose account email equals a prior GUEST redemption email is blocked (authed identity carries lowercased account email)
- 3DS second redemption by same identity: first 3DS booking confirms; second is rejected (pre-charge 409 in confirm Phase A, or charge-then-refund 409 backstop in consume) and refundOrReport fires

**Implementation:**

Write failing feature tests in BookingControllerTest.php first (use fixture seats[0]/seats[1]/seats[2] for distinct seats; actingAs() for authed). Then BookingController: add `use App\Services\PromoRedemptionIdentity;`. (1) Pre-check at line 116-122: build `$identity = new PromoRedemptionIdentity(userId: $request->user()?->id, guestEmail: $request->user() ? strtolower((string) $request->user()->email) : $request->input('email'));` and call `$this->promoCodeService->validateCode($promoCode, 0, $identity);`. (email already canonicalized by CreateBookingRequest from T2; authed branch carries lowercased account email so prior guest redemptions under the same email count.) (2) finalizeBooking() signature (line 666-674): append `?PromoRedemptionIdentity $promoIdentity = null` and change the consume call (line 696) to `$this->promoCodeService->consume($promo, null, $promoIdentity);`. (3) store() Phase C call (line 376): pass `new PromoRedemptionIdentity(userId: $booking->user_id, guestEmail: $booking->user_id ? ($request->user() ? strtolower((string) $request->user()->email) : null) : $booking->guest_email, excludeBookingId: $booking->id)` — for authed bookings guest_email is null on the row, so supply the lowercased account email; for guests use the canonical guest_email. (4) confirm() Phase C call (line 599-607): identity from $pendingData (NOT $request) — `new PromoRedemptionIdentity(userId: $pendingData['user_id'], guestEmail: $pendingData['user_id'] ? (optional: User::find lookup or null) : $pendingData['guest_email'], excludeBookingId: $booking->id)`. For the authed 3DS path, also load the user's email: fetch `$pendingData['user_id'] ? strtolower((string) User::find($pendingData['user_id'])?->email) : $pendingData['guest_email']`. (5) Pre-charge 3DS guard — in confirm() Phase A transaction (lines 476-516, after the gift-card block, before `return null;`): if `!empty($pendingData['promo_code_id'])`, load the promo and if per_user_limit set, build the identity from $pendingData and if countRedemptions >= limit return a 409 errorResponse field=promoCode 'no longer redeemable' — this bails BEFORE Stripe capture (line 524). The consume() under lock in Phase C remains the authoritative backstop (charge-then-refund 409 if a sibling commits between Phase A and Phase C).

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest — all per-user-limit + bypass + 3DS tests green.`

### T5: Un-hide Filament per_user_limit field + invert guard test

**Test scenarios:**
- CreatePromoCode form exposes per_user_limit (assertFormFieldExists)
- per_user_limit set on the create form reaches the PromoCodeService::create payload (round-trip)
- Existing create/edit/deactivate/delete resource tests still pass

**Implementation:**

Invert PromoCodeResourceTest.php lines 22-27 to `Livewire::test(CreatePromoCode::class)->assertFormFieldExists('per_user_limit');` with a title like 'the promo form exposes per_user_limit now that enforcement ships'. Add a dehydration round-trip test mirroring the existing create-routing test (lines 29-57): mock PromoCodeService::create with the 2-arg signature `function (array $data, ?User $actor)`, set data.code/discount_type/amount/per_user_limit=3, call create, assert `$captured['per_user_limit']` with `->toEqual(3)` (loose — Livewire may deliver a string for a numeric TextInput). Then PromoCodeResource.php: replace the comment block lines 102-105 with `TextInput::make('per_user_limit')->numeric()->nullable()->minValue(1)->helperText('Max uses per customer (by account, or by email for guests). Leave blank for unlimited.'),` (the field is already in PromoCode #[Fillable] and integer-cast). No new permission — promos.create/promos.update already gate the form.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=PromoCodeResourceTest — all green.`

---

## Gotchas

- FATAL if ignored: REASON_LIMIT_REACHED ALREADY EXISTS at PromoCodeNotConsumableException.php:28 with its match arm at line 42. Add ONLY REASON_PER_USER_LIMIT. Re-declaring the existing constant is `Cannot redeclare class constant` and kills the whole suite at autoload.
- FK TYPE: promo_codes uses `$table->id()` (auto-increment BIGINT), NOT a UUID. The bookings FK must be `foreignId('promo_code_id')` — copy the nullable/constrained/nullOnDelete MODIFIERS from the user_id line (23) but NOT its foreignUuid base, or the constraint type-mismatches against promo_codes.id.
- GUEST EMAIL BYPASS (silent, no test failure unless you add the test): bookings.guest_email is a plain `string` (migration line 24) with no citext/collation, and CreateBookingRequest never lowercases email (only RegisterRequest:31/LoginRequest:29/AuthController:77,92 do). Without canonicalizing email at the write boundary (T2) AND lower()-matching in the count (T3), per_user_limit for guests is defeated by 'A@x.com' vs 'a@x.com' vs ' a@x.com '. Fix BOTH halves.
- AUTHED CROSS-IDENTITY: user_id and guest_email are mutually exclusive per row (BookingController:156-157). An authed booking's guest_email is NULL, so passing `guestEmail: $booking->guest_email` (the original design) makes the OR-count never match a prior GUEST redemption under the same email. The authed-path identity MUST carry the user's LOWERCASED account email so the claimed 'guest-who-registers can't re-redeem' guarantee actually holds.
- STATUS SET — do NOT borrow occupyingStatuses() (=[Confirmed,Held,RefundPending]). Held would let an abandoned 3DS hold self-lock the user for ~20 min (ExpireHeldBookings default --minutes=20). And occupyingStatuses excludes Refunded, which would let refund-then-rebook farm unlimited redemptions. Count a deliberate LIFETIME set [Confirmed, RefundPending, Refunded] (refund does NOT reopen the slot; Cancelled does NOT count).
- EXCLUDE THE CURRENT BOOKING: store() saves the booking WITH promo_code_id (T2, line 374) BEFORE consume() runs (line 376→696). The current row IS in the table at count time, so pass excludeBookingId: $booking->id and compare PRIOR redemptions `>= per_user_limit`. Without it every first redemption falsely counts itself and is blocked.
- BACK-COMPAT SIGNATURES: 12 existing call sites use positional args — consume($promo), consume($promo, null), validateCode($code, 5000), DB::transaction(fn () => $this->service->consume($promo)) in PromoCodeServiceIntegrationTest:96-98. APPEND the new identity param as nullable (after $actor on consume, after $bookingTotalCents on validateCode) and pass it by NAMED arg in new tests. Do NOT insert it positionally.
- 3DS TEST SHAPE: the working reference (BookingControllerTest.php:940-955) calls $fakeStripe->shouldRequire3ds() before store, captures data.paymentIntentId, then $fakeStripe->shouldSucceed() BEFORE the confirm POST. Any new 3DS test that omits shouldSucceed() gets 402 at confirm() line 526 and fails the assertStatus(201).
- 3DS IDENTITY SOURCE: in confirm() $request->user() is meaningless. Read user_id/guest_email from $pendingData (cached at store() lines 309-310, now canonicalized via T2). For the authed 3DS path you must look up the user's email (User::find) to populate the cross-identity email — $pendingData has no email field for authed bookings.
- PINT IMPORT STRIPPING: add `use App\Enums\BookingStatus;` + `use App\Models\Booking;` to PromoCodeService.php and `use App\Services\PromoRedemptionIdentity;` to BookingController.php IN THE SAME edit as their first usage, or Pint removes them as unused. PromoRedemptionIdentity is same-namespace in PromoCodeService (App\Services) so it needs no import there.
- MIGRATION EDITED IN PLACE then `make fresh` (pre-launch rule) — this is a normal in-place edit, NOT the booking_seats additive exception. Run `php artisan optimize:clear` before tests if routes were cached (stale route cache → 404 on /api/locations/{loc}/bookings).
- FILAMENT DEHYDRATION TYPE: a numeric TextInput may deliver per_user_limit as a string in the captured create payload (the existing test asserts code as a string). Use ->toEqual(3) (loose) not ->toBe(3) (strict int) in the round-trip assertion. The create mock must keep the existing 2-arg closure shape (array $data, ?User $actor).
- NO env() introduced — per_user_limit is a per-row DB value, not config. PHPStan/Larastan gate stays clean; the new code reads only model/request data.

## Test matrix

- T1: Schema::hasColumn('bookings','promo_code_id') === true
- T1: Booking created with promo_code_id → ->promoCode loads the PromoCode, promo_code_id matches
- T1: Booking with no promo → promo_code_id is null
- T1: Promo->delete() nulls booking.promo_code_id (nullOnDelete), booking row survives
- T2: Direct booking with WELCOME5 → booking.promo_code_id === promo id
- T2: Direct booking with no promo → promo_code_id null
- T2: 3DS-confirmed booking with WELCOME5 → promo_code_id set (shouldSucceed() before confirm)
- T2: Guest email 'Mixed@Case.com ' submitted → stored guest_email === 'mixed@case.com'
- T3 unit: consume() with per_user_limit null + identity → uses_count increments
- T3 unit: consume() throws REASON_PER_USER_LIMIT when authed user already at limit; uses_count unchanged
- T3 unit: consume() throws for guest at limit keyed on guest_email
- T3 unit: consume() guest count is case-insensitive (prior 'user@x.com' blocks identity 'USER@X.COM')
- T3 unit: prior Refunded booking STILL counts (refund does NOT reopen slot) → consume throws
- T3 unit: prior Cancelled booking does NOT count → consume succeeds
- T3 unit: prior Held booking does NOT count (no abandoned-3DS lockout) → consume succeeds
- T3 unit: count scoped to THIS promo (a redemption of another promo is irrelevant)
- T3 unit: consume() with NO identity → only global usage_limit checked (back-compat)
- T3 unit: excludeBookingId excludes the named booking from the count
- T3 unit: validateCode() returns null when identity already at per_user_limit
- T3 unit: validateCode() with no identity ignores per_user_limit (two-arg back-compat)
- T4 feature: authed second redemption of per_user_limit:1 → 400, errors.0.field=promoCode, uses_count stays 1
- T4 feature: guest second redemption (same canonical email) → 400
- T4 feature: guest bypass blocked — 'mixed@case.com' then 'MIXED@CASE.COM ' → second 400
- T4 feature: different user redeems same per_user_limit:1 promo → both 201, uses_count=2
- T4 feature: authed user whose account email == a prior guest redemption email → blocked
- T4 feature: 3DS second redemption same identity → second rejected (Phase A 409 pre-charge, or consume 409 + refundOrReport backstop)
- T5 resource: CreatePromoCode exposes per_user_limit (assertFormFieldExists)
- T5 resource: per_user_limit=3 set on create form reaches service create payload (->toEqual(3))
- T5 resource: existing create/edit/deactivate/delete/list resource tests unaffected

## Open risks

- Email aliasing (Gmail dot/plus: 'j.ohn@x.com', 'john+1@x.com') still yields distinct canonical guest_email values and is an ACCEPTED residual bypass — matches industry norm. Documented, not closed (would require provider-specific normalization). The case/whitespace bypass IS closed.
- The validateCode() pre-check is intentionally unlocked (UX gate). Under true concurrency two near-simultaneous first/second redemptions by the same identity can both pass the pre-check; the consume() lock + the confirm() Phase A pre-charge check are the real backstops. The HTTP-level guarantee is 'pre-check 400 OR (3DS) Phase-A 409 pre-charge OR consume 409 + refund', not 'always 400'. Tests assert sequentially; a real concurrency harness is out of scope.
- The (promo_code_id, status) index narrows the count to a promo's redemptions but cannot cover the user_id OR lower(guest_email) identity predicate — Postgres filters that half in memory. Acceptable at pre-launch volume; the count runs while holding the promo-row lock, so a very high-traffic promo serializes all redemptions on that row. If volume grows, revisit a dedicated redemption-ledger table (the deferred v2 approach).
- Correctness of the concurrent same-identity guarantee hinges on the booking save + consume() living in the SAME Phase-C DB transaction (true today: store line 350 / confirm line 547) with the promo-row lock as the serialization point. A future refactor that splits the booking save out of that transaction would silently break the count's exclusion/visibility invariant — worth a code comment in consume() pinning the assumption.
- bookings.discount still conflates promo + gift-card amounts (BookingController:203). This feature keys enforcement on promo_code_id + status, not on the discount figure, so it is unaffected — but reporting still cannot answer 'how much of discount was promo vs gift card'. Out of scope.
- BookingStatus enum docblock (lines 13-19) is stale re: Held (now produced by the 3-phase store flow). Not required for this feature; a one-line docblock fix is optional cleanup if the enum file is touched.
