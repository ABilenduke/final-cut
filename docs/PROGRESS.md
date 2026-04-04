# Progress Journal — Plan 01: Project Setup & Configuration

## Step 2: Service Configuration
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Added TMDB and Stripe config blocks to `config/services.php`
- [2026-04-04] Added `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `TMDB_API_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` to `.env.example`

### Decisions
- [2026-04-04] Kept existing service config entries (postmark, resend, ses, slack) — no reason to remove them

### Files Changed
- `backend/config/services.php` — added tmdb and stripe config blocks
- `backend/.env.example` — added project-specific env vars

---

## Step 3: CORS Configuration
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Published `config/cors.php` via `php artisan config:publish cors`
- [2026-04-04] Configured allowed origins from `FRONTEND_URL` env var, restricted methods, enabled credentials

### Decisions
- [2026-04-04] Used `config:publish` rather than manually creating the file, so it stays in sync with Laravel's framework defaults

### Files Changed
- `backend/config/cors.php` — published and configured for finalcut.test origin

---

## Step 4: Middleware Configuration
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Installed Laravel Sanctum via `composer require laravel/sanctum`
- [2026-04-04] Ran `php artisan install:api` which added API routing to `bootstrap/app.php` and created `routes/api.php`
- [2026-04-04] Configured middleware: `EnsureFrontendRequestsAreStateful` prepended to API stack, 60 req/min throttle, stateful API enabled

### Decisions
- [2026-04-04] Used `install:api` (Laravel 11+ standard) instead of manual Sanctum setup — this handles route registration and config publishing automatically
- [2026-04-04] Using Sanctum SPA cookie auth (stateful) per plan recommendation, not token-based

### Files Changed
- `backend/composer.json` / `composer.lock` — added `laravel/sanctum`
- `backend/bootstrap/app.php` — API routing + middleware config
- `backend/config/sanctum.php` — published by `install:api`

---

## Step 5: Base API Controller & Response Format
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created `App\Http\Controllers\Api\Controller` with `successResponse`, `errorResponse`, `paginatedResponse` helpers

### Files Changed
- `backend/app/Http/Controllers/Api/Controller.php` — new base controller

---

## Step 1: API Routes File with Stubs
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created 11 stub controllers in `app/Http/Controllers/Api/`, all extending the base `Api\Controller`
- [2026-04-04] Defined all routes in `routes/api.php` matching DATA_MODELS.md Section 2
- [2026-04-04] `php artisan route:list` confirms 27 routes registered

### Decisions
- [2026-04-04] Included `POST /api/bookings/confirm` (for 3DS payment confirmation per PURCHASE_FLOW.md) — plan lists it in route stubs
- [2026-04-04] Read-only public stubs return `successResponse([])`. Auth, mutation, and account stubs were later changed to return `notImplementedResponse()` (501) — see Adversarial Review Fixes below

### Files Changed
- `backend/routes/api.php` — all route definitions
- `backend/app/Http/Controllers/Api/MovieController.php`
- `backend/app/Http/Controllers/Api/ShowtimeController.php`
- `backend/app/Http/Controllers/Api/BookingController.php`
- `backend/app/Http/Controllers/Api/CalendarEventController.php`
- `backend/app/Http/Controllers/Api/FoodMenuController.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Api/AccountController.php`
- `backend/app/Http/Controllers/Api/PaymentMethodController.php`
- `backend/app/Http/Controllers/Api/GiftCardController.php`
- `backend/app/Http/Controllers/Api/RentalController.php`
- `backend/app/Http/Controllers/Api/ContactController.php`

---

## Testing
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created `tests/Feature/Api/RouteStubsTest.php` with 31 Pest tests
  - 17 public route stubs return 200 with `{"data": ...}` envelope
  - 10 protected routes return 401 without auth
  - 2 service config tests (TMDB, Stripe)
  - 2 CORS tests (preflight + response headers)
- [2026-04-04] All 31 tests pass (58 assertions)

### Decisions
- [2026-04-04] Pre-existing `ExampleTest` (GET /) fails with 500 — this is a scaffold issue (no web view), not related to our API work. Left as-is.

### Files Changed
- `backend/tests/Feature/Api/RouteStubsTest.php` — new test file

---

## Adversarial Review Fixes
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Added `Accept` and `X-XSRF-TOKEN` to CORS `allowed_headers` — Sanctum SPA auth requires `X-XSRF-TOKEN` in preflight
- [2026-04-04] Changed all auth stubs (register, login, forgotPassword) and mutation stubs (bookings, gift-cards, rentals, contact) to return `501 Not Implemented` with `{"message": "Not implemented"}`
- [2026-04-04] Changed account and payment-method stubs to return `501` as well (behind auth middleware, but honest about implementation status)
- [2026-04-04] Added `notImplementedResponse()` helper to base `Api\Controller`
- [2026-04-04] Added CORS integration test verifying `X-XSRF-TOKEN` is allowed in preflight
- [2026-04-04] Updated all test assertions: 7 read-only routes expect 200, 10 mutation/auth routes expect 501, 10 protected routes expect 401
- [2026-04-04] All 32 tests pass (61 assertions)

### Decisions
- [2026-04-04] Read-only public endpoints (movies, showtimes, calendar, food-menu) stay at 200 — they return empty data which is semantically correct for "no results"
- [2026-04-04] Booking show (`GET /api/bookings/{id}`) returns 501 — even reads here imply a booking exists, which is misleading as a 200
- [2026-04-04] Gift card balance check returns 501 — same reasoning, implies the system can look up balances

### Files Changed
- `backend/config/cors.php` — added `Accept`, `X-XSRF-TOKEN` to allowed headers
- `backend/app/Http/Controllers/Api/Controller.php` — added `notImplementedResponse()` helper
- `backend/app/Http/Controllers/Api/AuthController.php` — all methods return 501
- `backend/app/Http/Controllers/Api/BookingController.php` — all methods return 501
- `backend/app/Http/Controllers/Api/AccountController.php` — all methods return 501
- `backend/app/Http/Controllers/Api/PaymentMethodController.php` — all methods return 501
- `backend/app/Http/Controllers/Api/GiftCardController.php` — all methods return 501
- `backend/app/Http/Controllers/Api/RentalController.php` — store returns 501
- `backend/app/Http/Controllers/Api/ContactController.php` — store returns 501
- `backend/tests/Feature/Api/RouteStubsTest.php` — updated assertions, added XSRF-TOKEN test
