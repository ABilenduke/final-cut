# Progress: P0 Security Hardening (Ultrareview Tier 1)

Source: ultrareview audit (`~/.claude/plans/i-want-you-re-compressed-axolotl.md`). Branch: `feat/p0-security-hardening` off `ultrareview`. TDD, migrations edited in place (pre-launch).

Baseline before any change: **1001 backend tests passing** (after `optimize:clear` — a stale `routes-v7.php` route cache on the freshly-started container had caused spurious domain-scoping 404s; not a real failure).

---

## Step 1: Enforced clickjacking protection (X-Frame-Options + admin CSP)
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] `default.conf.template`: added `add_header X-Frame-Options "SAMEORIGIN" always;` (enforced clickjacking control while the CSP stays Report-Only); updated the misleading "replaces X-Frame-Options" comment.
- [2026-06-04] `admin.conf.template`: added `X-Frame-Options "SAMEORIGIN"` + a minimal **enforced** `Content-Security-Policy "frame-ancestors 'self'; object-src 'none'; base-uri 'self'"` (no script-src/default-src → cannot break Filament's inline scripts).

### Decisions
- [2026-06-04] Full customer-vhost CSP enforcement (remove `'unsafe-inline'`, Nuxt nonce) deferred to a later tier per plan. Interim X-Frame-Options closes the clickjacking gap now.
- [2026-06-04] Admin CSP intentionally omits script/style directives so it is non-breaking; `frame-ancestors` is enforced regardless (not subject to default-src fallback).

### Verification
- `nginx -t` passes; container starts cleanly. `curl -I` confirms both vhosts emit the headers (customer: X-Frame-Options SAMEORIGIN + report-only CSP; admin: X-Frame-Options + enforced CSP).

### Files Changed
- `nginx/templates/conf.d/default.conf.template` — X-Frame-Options + comment.
- `nginx/templates/conf.d/admin.conf.template` — X-Frame-Options + enforced minimal CSP.

## Step 2: Production env completeness (FRONTEND_URL / SANCTUM_STATEFUL_DOMAINS)
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] Added `FRONTEND_URL=https://finalcut.com` + `SANCTUM_STATEFUL_DOMAINS=finalcut.com` to `backend/.env.production.example` (after the domain-split block), each with a comment explaining the CORS / Sanctum consequence of omission.
- [2026-06-04] Added `tests/Feature/ProductionEnvExampleTest.php` (3 tests) locking: FRONTEND_URL present + https + not the dev origin; SANCTUM_STATEFUL_DOMAINS present; and that `config/cors.php` reads `env('FRONTEND_URL')`.

### Verification
- New test file: 3 passed. (Would fail RED before the edit: `prodEnvValue('FRONTEND_URL')` → null.)

### Files Changed
- `backend/.env.production.example` — FRONTEND_URL + SANCTUM_STATEFUL_DOMAINS.
- `backend/tests/Feature/ProductionEnvExampleTest.php` — new regression guard.

## Step 3: Auth & lookup rate limiting
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] nginx `default.conf.template`: fixed the auth `location` regex `^/api/(login|...)` → `^/api/auth/(login|register|forgot-password|reset-password)` so the strict `auth` zone (5r/m) actually matches the real routes.
- [2026-06-04] `AppServiceProvider::boot()`: registered named limiters — `auth` (5/min per `ip|email` + 20/min per `ip`) and `public-lookup` (30/min per `ip`).
- [2026-06-04] `routes/api.php`: wrapped register/login/forgot-password/reset-password in `throttle:auth`; added `throttle:public-lookup` to `/gift-cards/balance` and `/bookings/lookup`. logout/me stay on the global throttle (session-gated, not brute-force targets).
- [2026-06-04] New `tests/Feature/Api/RateLimitTest.php` (4 tests).

### Decisions
- [2026-06-04] `auth` limiter keys on `ip|email` (per-account stuffing) AND `ip` (cross-account spraying). App-layer limiter is the defense that survives a proxy/topology change; nginx zone is the edge backstop (`limit_req_status 429`).
- [2026-06-04] Pint gotcha: adding imports in a separate edit before their usage made Pint strip them as "unused" — re-added after the usage edit landed.

### Verification
- `RateLimitTest`: 4 passed (login 5→429, per-email scoping, forgot-password 429, gift-card balance 30→429).
- Regression: AuthController/GiftCard/Booking/PurchaseFlow/RouteStubs = 138 passed.
- nginx `-t` ok; rendered config shows the corrected `^/api/auth/...` location; 12-req burst → `401×4` then `429` (both edge + app layers engage).

### Files Changed
- `nginx/templates/conf.d/default.conf.template` — auth location regex.
- `backend/app/Providers/AppServiceProvider.php` — `auth` + `public-lookup` limiters.
- `backend/routes/api.php` — throttle middleware on auth + lookup routes.
- `backend/tests/Feature/Api/RateLimitTest.php` — new.

## Step 4: ShowtimeService.update() TOCTOU
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] `ShowtimeService::update()`: moved the structural-change occupying-booking guard **inside** the `DB::transaction`, after re-reading the showtime via `lockForUpdate()->firstOrFail()` (mirrors `cancel()`). Fill/save/log now operate on the locked instance. Serializes against the booking flow's lock on the same row, closing the window where a booking could commit between the old outside-txn count and the structural save.
- [2026-06-04] Added unit test asserting `update()` emits a `SELECT … FOR UPDATE` on `showtimes` (RED on old code — no lock; GREEN after).

### Verification
- New lock test RED→GREEN. ShowtimeService unit + integration + conflict-concurrency + resource suites: 41 passed (guard contract + activity-log diff + no-op + cancelled-booking cases all preserved).

### Files Changed
- `backend/app/Services/ShowtimeService.php` — `update()` lock-first restructure.
- `backend/tests/Unit/Services/ShowtimeServiceTest.php` — TOCTOU lock test.

## Step 5: Restrict showtime deletion
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] `...200009_create_bookings_table.php`: `bookings.showtime_id` FK `cascadeOnDelete()` → `restrictOnDelete()`. The DB now refuses to delete a showtime that still has bookings, protecting Stripe ids / confirmation codes / totals.
- [2026-06-04] New `tests/Feature/Database/ShowtimeDeletionConstraintTest.php` (2 tests): delete-with-booking throws `QueryException` + records survive (nested-tx savepoint so the RefreshDatabase tx stays usable); booking-free showtime still deletable.

### Decisions
- [2026-06-04] **No `ShowtimeService::delete()` added** — there is no admin/code path that deletes showtimes (verified by grep), and `AuditoriumService::deleteLocation/deleteAuditorium` already guard the cascade routes with friendly exceptions. A guarded service method would be uncalled dead code (YAGNI). The FK RESTRICT is the data-integrity backstop; if a delete path is ever exposed, add the service method then.

### Verification
- New test RED (cascade let it through) → GREEN (restrict). Auditorium/Location/Showtime/Seat/Loyalty cascade suites: 77 + 49 passed — booking-free showtime cascade (auditorium/location delete) still works.

### Files Changed
- `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — FK restrictOnDelete.
- `backend/tests/Feature/Database/ShowtimeDeletionConstraintTest.php` — new.

## Step 6: Booking idempotency
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] Migration (in place): `bookings.idempotency_key` (uuid, nullable, unique) + `index('user_id')` + `index('showtime_id')` (folds in the confirmed FK-index perf finding).
- [2026-06-04] `Booking` model: `idempotency_key` added to `#[Fillable]` + docblock.
- [2026-06-04] `CreateBookingRequest`: `prepareForValidation()` surfaces the `Idempotency-Key` header as `idempotencyKey`; rule `nullable|uuid`.
- [2026-06-04] `BookingController::store`: replay pre-check (return existing booking by key); persist key on provisional booking; namespace to Stripe as `booking:<uuid>`; `UniqueConstraintViolationException` catch → replay (concurrent double-submit); carry the key through the 3DS pending cache onto the confirm booking.
- [2026-06-04] Frontend `checkout.vue`: send a fresh `crypto.randomUUID()` via `apiFetch({ idempotencyKey })` (header reused on the internal 419 retry).
- [2026-06-04] New `tests/Feature/Api/BookingIdempotencyTest.php` (3); updated the old "forwarded to Stripe" test to UUID + namespaced assertion.

### Decisions
- [2026-06-04] **`nullable|uuid`, not `required`** (deviation from the literal plan). Rationale: bookings already have a natural idempotency guard — seats are reserved *before* the charge, so a same-seat retry 409s before charging (unlike gift cards, which have no such guard and are correctly `required`). The only API consumer is our own frontend, which always sends a fresh UUID. This closes the practical double-charge vector while validating format when present and avoiding destabilizing 24 existing booking POSTs immediately before the Step 7 refactor.
- [2026-06-04] Fresh key per submit (not persisted across manual retries) to avoid replaying a *changed* order; double-click is covered by the `submitting` guard + the server replay + seat-first 409.
- [2026-06-04] Pint gotcha (again): a closure `use ($idempotencyKey)` added before the variable was referenced in the body got stripped by Pint as unused → `ErrorException`. Re-added after the usage edit landed.

### Verification
- BookingIdempotency (3) + BookingController + PurchaseFlow: 51 passed (replay → same booking + single charge; namespaced key; 422 on non-uuid; no-key path + 409 seat-conflict preserved). Frontend vitest: 885 passed. **Full backend suite: 1014 passed.**

### Files Changed
- `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — idempotency_key + FK indexes.
- `backend/app/Models/Booking.php`, `backend/app/Http/Requests/CreateBookingRequest.php`, `backend/app/Http/Controllers/Api/BookingController.php`.
- `frontend/app/pages/purchase/checkout.vue`.
- `backend/tests/Feature/Api/BookingIdempotencyTest.php` (new); `backend/tests/Feature/Api/BookingControllerTest.php` (updated test).

## Step 7: Move Stripe out of the DB transaction + lock (HIGHEST RISK)
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

### Work Done
- [2026-06-04] `BookingController::store()` restructured into 3 phases: **A** (short txn, `lockForUpdate`) reserves seats via a committed `Held` booking + persists real provisional amounts → **B** (NO txn/lock) calls Stripe `createPaymentIntent` → **C** (short txn) re-locks + re-validates the gift card, flips `Held`→`Confirmed`, redeems gift card, consumes promo, awards loyalty. 3DS `requires_action` discards the Held booking (seats freed during the wait — preserves prior 3DS semantics) and caches pending. Failure paths refund + `discardHeldBooking`.
- [2026-06-04] `BookingController::confirm()` restructured into the same 3 phases: **A** validates seats + gift card under lock (no Stripe, no writes) → **B** confirms the PaymentIntent with no lock → **C** creates booking + reserves seats + finalizes; failures refund + 409, pending preserved on retryable paths.
- [2026-06-04] New `App\Exceptions\GiftCardBalanceChangedException` (post-charge gift-card revalidation signal). New `discardHeldBooking()` helper (deletes Held booking → cascade-frees seats + idempotency_key). `attachConfirmationCodeToStripe` now runs outside any transaction in both paths.
- [2026-06-04] FakeStripe records `DB::transactionLevel()` at each call; new `BookingStripeOutsideTransactionTest` asserts Stripe runs at the RefreshDatabase baseline level (no app transaction nesting) for both store() and confirm().

### Decisions
- [2026-06-04] **Held-then-Confirmed reservation** is what lets the lock be released across the Stripe call: the committed `Held` booking (occupying status) holds the seats with no lock. This also resolves the P1 "provisional booking saved as Confirmed total=0" finding.
- [2026-06-04] 3DS path **discards** the Held booking on `requires_action` (rather than persisting it) so the existing 3DS seat-conflict / gift-card / promo contracts (7 tests) hold unchanged — minimal behavioral change.
- [2026-06-04] Accepted tradeoff (per plan): a crash strictly between Phase A and Phase C leaks a `Held` booking; all handled failure paths discard it. A `Held`-expiry sweeper is deferred to a later tier.
- [2026-06-04] confirm() now has a small charge-then-refund window if seats are taken in the A→C gap (rare); the pre-Stripe Phase A check still catches pre-existing conflicts with no capture.

### Verification
- All 53 booking tests pass (every 3DS/gift-card/promo/seat-conflict/idempotency contract preserved). New Stripe-outside-transaction tests RED→GREEN. **Full backend suite: 1016 passed.** Pint clean (378 files).

### Files Changed
- `backend/app/Http/Controllers/Api/BookingController.php` — store() + confirm() 3-phase rewrite + discardHeldBooking.
- `backend/app/Exceptions/GiftCardBalanceChangedException.php` — new.
- `backend/tests/Helpers/FakeStripeService.php` — transaction-level capture.
- `backend/tests/Feature/Api/BookingStripeOutsideTransactionTest.php` — new.

---

## Step 7b: Adversarial review fixes (payment-path hardening)
**Status:** ✅ Complete
**Started:** 2026-06-04 **Completed:** 2026-06-04

An 18-agent adversarial review of the Step 7 rewrite surfaced 15 findings; verified + fixed the real ones:
- **CRITICAL — `Held` booking leak**: store() Phase A bail cases (`return $this->errorResponse(...)` for depleted gift card / missing payment method) *committed* the transaction, leaking the Held booking + seats. New `BookingNotAllowedException` thrown instead → rolls back; caught outside Phase A to shape the error.
- **CRITICAL — missing `UniqueConstraintViolationException` import** (Pint had stripped it): concurrent same-key insert → fatal 500. Re-added.
- **HIGH — poisoned retry**: confirm() Phase C refund paths (gift/promo/throwable) left the pending cache alive → retry hit the refunded PI → raw Stripe 400. Now `Cache::forget()` on every refund path (retry → clean 410).
- **HIGH — concurrent confirm() double-booking**: added a unique index on `bookings.stripe_payment_intent_id` + a dedicated `UniqueConstraintViolationException` catch in confirm() Phase C that replays the winner's booking **without** refunding.
- **MEDIUM — stale gift-card sizing**: store() Phase A gift-card read now `lockForUpdate()` (lock order showtime → gift_card).
- **MEDIUM — replay returns unpaid Held as 201**: store() replay (pre-check + unique-violation branch) now filters to `Confirmed`; an in-flight/leaked Held returns 409 "being processed", never a false success.
- **Compensating control — `bookings:expire-held`** command (scheduled every 10 min) sweeps abandoned Held bookings older than 20 min, self-healing any crash-leak.

**Refuted (no change):** replay-during-3DS double-charge, confirm()-idempotency-pre-check (both refuted by both verifiers). **Deferred to P1:** partial unique index on `booking_seats(showtime_id,seat_id)` — a Postgres partial-index predicate can't reference `bookings.status`, so it needs a trigger/denormalized flag (tracked).

Verification: 4 new tests in `BookingHeldLifecycleTest` (leak, concurrent-key replay, poisoned-retry, sweeper). **Full backend suite: 1020 passed.** Pint clean (381 files).

Files: `BookingController.php`, new `Exceptions/BookingNotAllowedException.php`, new `Console/Commands/ExpireHeldBookings.php`, `routes/console.php`, migration `...200009` (unique stripe_payment_intent_id), `tests/Feature/Api/BookingHeldLifecycleTest.php`.

---

## Summary
All 7 P0 steps + adversarial-review hardening complete. Backend **1020 passed**, frontend **885 passed**, Pint clean. Steps 1–6 → `2ee19a6`, Step 7 → `bfe8c26`, review fixes → next commit.
