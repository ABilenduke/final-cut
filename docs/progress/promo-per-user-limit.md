# Progress: promo_codes.per_user_limit enforcement

Plan: [`docs/superpowers/plans/2026-06-05-promo-per-user-limit.md`](../superpowers/plans/2026-06-05-promo-per-user-limit.md)
Branch: `feat/promo-per-user-limit` (off `main`). One of three post-P1 hardening follow-ups.

Decision (user): enforce by **`user_id` AND canonicalized `guest_email`** (the weak-but-naive-repeat-closing option). Email aliasing (gmail dot/plus) is an accepted residual bypass; case/whitespace is closed.

## Step 1: bookings.promo_code_id FK (schema + model)
**Status:** ✅ Complete · **Completed:** 2026-06-05

### Work Done
- [2026-06-05] `bookings` migration: `promo_code_id` BIGINT column (NOT foreignUuid — promo_codes uses `$table->id()`) + composite `index(['promo_code_id','status'])`.
- [2026-06-05] **FK ordering fix (dossier miss):** `bookings` (2026_04_04) is created BEFORE `promo_codes` (2026_04_24), so `constrained()` in the bookings migration referenced a non-existent table. The column + index live in `bookings`; the actual FK constraint (with `nullOnDelete`) is added in the `promo_codes` migration's `up()` (and dropped in its `down()`), where the referenced table exists.
- [2026-06-05] `Booking` model: `promo_code_id` in `#[Fillable]`, `@property` lines, `promoCode()` relation.
- [2026-06-05] `BookingPromoCodeLinkTest` (4): column exists, relation loads, null default, nullOnDelete keeps the booking.

## Step 2-4: enforcement (service + controller)
**Status:** ✅ Complete · **Completed:** 2026-06-05

### Work Done
- [2026-06-05] `PromoRedemptionIdentity` DTO (userId, guestEmail, excludeBookingId).
- [2026-06-05] `PromoCodeNotConsumableException::REASON_PER_USER_LIMIT` added (only — `REASON_LIMIT_REACHED` already existed; re-declaring it is a fatal redeclare).
- [2026-06-05] `PromoCodeService`: `consume()` + `validateCode()` take an optional `?PromoRedemptionIdentity` (appended nullable; 12 positional callers unaffected). `countRedemptions()` counts a deliberate LIFETIME status set `[Confirmed, RefundPending, Refunded]` (NOT `occupyingStatuses()` — Held would self-lock abandoned 3DS holds; excluding Refunded would let refund-rebook farm codes), matching `user_id` OR `lower(guest_email)`, minus `excludeBookingId`.
- [2026-06-05] `CreateBookingRequest::prepareForValidation()` canonicalizes email (lowercase+trim) — the write-half of the guest-bypass fix.
- [2026-06-05] `BookingController`: `promoRedemptionIdentity()` helper (authed identity ALSO carries lowercased account email so prior guest redemptions under the same email count); validateCode pre-check + both Phase C `promo_code_id` saves + `finalizeBooking`→`consume()` thread identity (with `excludeBookingId` = the current booking, already saved with its promo_code_id).
- [2026-06-05] Tests: 10 unit (`PromoCodeServiceTest`) + 9 feature (`BookingControllerTest`) incl. the 3DS consume-backstop refund path.

### Decisions
- [2026-06-05] **No confirm() Phase-A pre-charge per-user check.** The store-time `validateCode` pre-check catches the common case (400, no charge), and `consume()` under lock is the authoritative backstop for the rare concurrent-3DS race (charge-then-refund 409 via the existing `PromoCodeNotConsumableException` handler). Skipping the Phase-A optimization keeps the change focused; correctness is unaffected. Proven by the "3DS redemption that hits the cap during its window is refunded" feature test.

## Step 5: Filament field + regression gate
**Status:** ✅ Complete · **Completed:** 2026-06-05

### Work Done
- [2026-06-05] `PromoCodeResource`: un-hid `per_user_limit` (`TextInput`, `minValue(1)`). Inverted the hidden-field guard test + added a dehydration round-trip test.
- [2026-06-05] `make fresh` survives; Pint clean; PHPStan clean (no `env()` introduced).

### Files Changed
- `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — promo_code_id column + composite index
- `backend/database/migrations/2026_04_24_100001_create_promo_codes_table.php` — FK constraint (+ down) + per_user_limit comment
- `backend/app/Models/Booking.php` — fillable/property/relation
- `backend/app/Http/Requests/CreateBookingRequest.php` — email canonicalization
- `backend/app/Services/PromoRedemptionIdentity.php` — new DTO
- `backend/app/Exceptions/PromoCodeNotConsumableException.php` — REASON_PER_USER_LIMIT
- `backend/app/Services/PromoCodeService.php` — enforcement + countRedemptions
- `backend/app/Http/Controllers/Api/BookingController.php` — identity threading + promo_code_id persistence
- `backend/app/Filament/Resources/PromoCodeResource.php` — un-hide field
- `backend/tests/Feature/BookingPromoCodeLinkTest.php`, `tests/Unit/Admin/PromoCodeServiceTest.php`, `tests/Feature/Api/BookingControllerTest.php`, `tests/Feature/Admin/Resources/PromoCodeResourceTest.php`
