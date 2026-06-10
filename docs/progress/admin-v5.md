# Admin v5 Progress Journal

Execution journal for [`docs/plans/admin/v5/`](../plans/admin/v5/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 5.3: Pay with saved card
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Checkout picker for stored cards (radio list + "Use a different card";
  Elements stays mounted via v-show). Saved-card submits emit
  `{paymentMethodId, usingSavedCard: true}` and skip `createPaymentMethod`; backend
  attaches the user's Stripe customer to the intent (Stripe enforces PM ownership),
  rejects no-customer/guest attempts with 400 before any Stripe call. Suites:
  **backend 1310, frontend 963 (+5 skipped)**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] No server-side PM-ownership lookup — a customer-attached intent already
  makes Stripe reject foreign PaymentMethods; one less Stripe round trip.
- [2026-06-10] Saved-path failure modes degrade to the card element (lookup error) or a
  clean 400 with the Held booking discarded (no customer).

### Blockers
- none

### Files Changed
- `backend/app/Http/Controllers/Api/BookingController.php`, `backend/app/Http/Requests/CreateBookingRequest.php` — usingSavedCard path
- `backend/tests/Feature/Api/BookingPayWithSavedCardTest.php` — 3 tests
- `frontend/app/components/booking/CheckoutPaymentBay.vue` — picker + saved submit
- `frontend/app/pages/purchase/checkout.vue` — forwarding
- `frontend/tests/components/booking/CheckoutPaymentBay{,Submit}.test.ts` — mocks + 3 tests
- `docs/plans/admin/v5/03-pay-with-saved-card.md` — step spec

## Step 5.2: TMDB crew enrichment
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] `TmdbService::mapCrewCredits()` maps the crew payload onto the editorial
  credit fields (Director/Screenplay+Writer/DoP/Editor/Composer; comma-join for multiple
  holders; unmapped jobs ignored; aspect/advisory stay admin-only). Merge rule on the
  non-partial enrichment path: TMDB fills blanks, admin-authored values win (blank
  strings count as unfilled). Backend **1307 passed**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Admin-wins merge (not TMDB-wins) — enrichment is backfill, the Editorial
  form is the source of truth; matches the v4 Plan 03 division of ownership.

### Blockers
- none

### Files Changed
- `backend/app/Services/TmdbService.php` — `mapCrewCredits()` + merge in the update path
- `backend/tests/Unit/Services/TmdbServiceTest.php` — 2 tests + crew fixture
- `docs/plans/admin/v5/02-tmdb-crew.md` — step spec

## Step 5.1: CI reliability — composer source fallback
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] `git` added to the three composer-running backend Dockerfile stages
  (`vendor`, `e2e-seeder`, `development`) so composer's source fallback works when a
  packagist dist download flakes — the diagnosed cause of one of today's three CI
  failures ("git was not found in your PATH, skipping source download"). Production
  runtime stage untouched. Local image rebuild clean; backend suite **1305 passed**
  on the rebuilt container.

### Decisions
- [2026-06-10] git scoped to build stages only — no prod image growth.
- [2026-06-10] Docker Hub pull timeouts (the other two flakes) left alone: registry-side,
  rerun-fixable; a mirror would be speculative infra (per the no-speculative-changes rule).

### Blockers
- [2026-06-10] Recreated dev container 404'd the suite — the documented boot race
  (entrypoint `optimize` finishing after a manual `optimize:clear`); second clear fixed it.

### Files Changed
- `backend/Dockerfile` — git in vendor/e2e-seeder/development stages
- `docs/plans/admin/v5/{00-index,01-ci-reliability}.md` — plan docs
