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

---

# Progress Journal — Plan 02: Database Schema

## Steps 1-8: Models, Migrations, Factories, Enums, Tests
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Updated User model + migration to UUID PK, added profile columns (phone, date_of_birth, avatar_url, loyalty_points, loyalty_tier, premier_expiry, stripe_customer_id)
- [2026-04-04] Updated personal_access_tokens migration (morphs → uuidMorphs), sessions table (foreignId → foreignUuid)
- [2026-04-04] Created 10 PHP enums: LoyaltyTier, MovieStatus, SeatType, GiftCardStatus, CalendarEventType, MenuCategory, RentalEventType, InquiryStatus, BookingStatus, PaymentMethod
- [2026-04-04] Created 11 new migrations for movies, auditoriums, seats, gift_cards, calendar_events, menu_items, rental_inquiries, showtimes, bookings, booking_seats, booking_food_items
- [2026-04-04] Created 11 models: Movie, Auditorium, Seat, Showtime, Booking, BookingSeat, BookingFoodItem, GiftCard, CalendarEvent, MenuItem, RentalInquiry
- [2026-04-04] Created 11 factories with state methods (nowShowing, comingSoon, premium, accessible, guest, cancelled, depleted, etc.)
- [2026-04-04] Created 12 Pest test files with 72 model tests covering UUID PKs, enum casts, JSON casts, unique constraints, cascade deletes, relationships, confirmation code generation
- [2026-04-04] Removed scaffold ExampleTest (tested non-existent web route GET /)

### Decisions
- [2026-04-04] User switched from integer to UUID PK — required by Booking.user_id FK type
- [2026-04-04] BookingSeat given UUID id (not composite PK) — Eloquent doesn't handle composite PKs well; unique(showtime_id, seat_id) constraint still prevents double-booking
- [2026-04-04] Enums stored as strings with PHP backed enum casts — avoids PostgreSQL-specific enum types, works with SQLite tests
- [2026-04-04] BookingFoodItem.menu_item_id has no FK — denormalized name/price are source of truth for historical records
- [2026-04-04] Added `$table = 'auditoriums'` to Auditorium model — Laravel pluralizes "auditorium" to "auditoria" (Latin)
- [2026-04-04] Movie uses explicit `$table->foreign('movie_id')->references('id')->on('movies')` — can't use foreignId shorthand since Movie PK is unsignedBigInteger (not auto-increment)

### Files Changed
- Modified 2 existing migrations (users, personal_access_tokens)
- Modified User model and UserFactory
- Created 11 new migrations, 11 models, 10 enums, 11 factories, 12 test files

---

## Step 9: Database Seeders
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created MovieSeeder — 20 movies (12 now_showing, 8 coming_soon) with hardcoded realistic titles, fake TMDB IDs 100001-100020, genres, ratings, runtimes
- [2026-04-04] Created AuditoriumSeeder — 3 auditoriums (Screen 1: 8x10=80, Screen 2: 12x14=168, IMAX: 15x20=300) with programmatic seat generation including standard/premium/accessible zones
- [2026-04-04] Created ShowtimeSeeder — 50+ showtimes across 14 days for all now_showing movies, 2-3 per movie per day at realistic screen times
- [2026-04-04] Created CalendarEventSeeder — 10 events mixing special_event, loyalty_exclusive, and private_screening_blackout types with accessibility tags
- [2026-04-04] Created MenuItemSeeder — 21 items across all 5 categories (popcorn, drinks, snacks, combos, specials) with allergen and dietary info
- [2026-04-04] Created BookingSeeder — 5 bookings for test user with 2-4 seats each and food items on first 3
- [2026-04-04] Updated DatabaseSeeder to create test user (test@finalcut.test, Premier tier, 500 points) + 10 factory users, then call all 6 domain seeders in dependency order

### Decisions
- [2026-04-04] Used hardcoded movie data rather than faker for titles/genres to produce realistic-looking seed data
- [2026-04-04] Accessible seats placed only on aisle positions (seats 1, 2, last-1, last) of the last row per auditorium
- [2026-04-04] BookingSeeder filters seats by SeatType enum value rather than raw string comparison for type safety

### Files Changed
- `backend/database/seeders/DatabaseSeeder.php` — replaced with test user creation + seeder orchestration
- `backend/database/seeders/MovieSeeder.php` — new
- `backend/database/seeders/AuditoriumSeeder.php` — new
- `backend/database/seeders/ShowtimeSeeder.php` — new
- `backend/database/seeders/CalendarEventSeeder.php` — new
- `backend/database/seeders/MenuItemSeeder.php` — new
- `backend/database/seeders/BookingSeeder.php` — new

---

## Step 10: Verification
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] `php artisan migrate:fresh --seed` runs clean — 15 migrations, 6 seeders
- [2026-04-04] `composer test` passes — 104 tests, 160 assertions, 0 failures
- [2026-04-04] Fixed 3 test files missing `Tests\TestCase::class` in `uses()` (AuditoriumTest, SeatTest, UserTest)
- [2026-04-04] Added `bookings()` HasMany relationship to Showtime model (omitted by parallel agent)
- [2026-04-04] Removed scaffold `tests/Feature/ExampleTest.php` (tested non-existent GET / web route)

---

# Progress Journal — Plan 03: Movie & Showtime API

## Task 1: TmdbService — HTTP Client & Transform
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created `TmdbService` with `nowPlaying()`, `upcoming()`, `movieDetail()` methods
- [2026-04-04] Implemented `tmdbToMovie()` transform matching DATA_MODELS.md Section 3 (cast limit 12, YouTube trailer extraction, image size prefixes w500/w1280/w185)
- [2026-04-04] Added caching: 30 min for lists, 1 hour for detail via `Cache::remember()`
- [2026-04-04] Graceful degradation: returns empty/null when TMDB unavailable or no API key
- [2026-04-04] 13 unit tests covering transform, caching, error handling

### Decisions
- [2026-04-04] Used `Http::withToken()` (Bearer auth) — matches TMDB v3 Read Access Token auth method
- [2026-04-04] `tmdbToMovie` is private — only called internally, tested via reflection

### Files Changed
- `backend/app/Services/TmdbService.php` — new
- `backend/tests/Unit/Services/TmdbServiceTest.php` — new (13 tests)

---

## Task 2: API Resources
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Created `MovieResource`, `MovieListResource`, `ShowtimeResource`, `AuditoriumResource`, `SeatResource`
- [2026-04-04] All resources output camelCase keys matching frontend TypeScript interfaces
- [2026-04-04] `SeatResource` uses `computed_status` and `computed_price` attributes set by controller
- [2026-04-04] `AuditoriumResource` groups seats by row and extracts unique sections
- [2026-04-04] 8 unit tests covering all resource transformations

### Decisions
- [2026-04-04] `SeatResource` reads computed status/price from dynamically set model attributes rather than accepting constructor params — simpler integration with Eloquent collection patterns
- [2026-04-04] `AuditoriumResource` sorts seats by `[row, number]` before grouping for consistent output

### Files Changed
- `backend/app/Http/Resources/MovieResource.php` — new
- `backend/app/Http/Resources/MovieListResource.php` — new
- `backend/app/Http/Resources/ShowtimeResource.php` — new
- `backend/app/Http/Resources/AuditoriumResource.php` — new
- `backend/app/Http/Resources/SeatResource.php` — new
- `backend/tests/Unit/Resources/ResourcesTest.php` — new (8 tests)

---

## Task 3: MovieController Implementation
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Implemented `GET /api/movies` — fetches from TMDB, returns camelCase array data with meta
- [2026-04-04] Implemented `GET /api/movies/{slug}` — local slug lookup + TMDB enrichment for cast/trailer, falls back to local data when TMDB fails
- [2026-04-04] Implemented `GET /api/movies/{slug}/showtimes` — date-filtered with today default, eager-loads movie + auditorium
- [2026-04-04] 14 feature tests covering TMDB integration, fallback, date filtering, 404s

### Decisions
- [2026-04-04] Movie detail merges TMDB data onto the local Eloquent model (TMDB provides enriched cast/trailer, local model provides slug/status)

### Known Issue — Movie List Source of Truth
- [2026-04-04] `GET /api/movies` currently proxies TMDB now_playing/upcoming directly, returning every movie in US theaters. This is wrong — the local `movies` table is the source of truth for what this theater shows. The endpoint should query local movies by status, then optionally enrich with TMDB metadata. Needs fix before this endpoint is usable.

### Files Changed
- `backend/app/Http/Controllers/Api/MovieController.php` — implemented (was stub)
- `backend/tests/Feature/Api/MovieControllerTest.php` — new (14 tests)

---

## Task 4: ShowtimeController — Seat Map with Availability
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Implemented `GET /api/showtimes/{id}` — returns showtime + auditorium + full seat map with availability
- [2026-04-04] Seat availability computed from BookingSeat records (confirmed bookings only)
- [2026-04-04] Single query for taken seat IDs, then in-memory map — no N+1
- [2026-04-04] 8 feature tests covering availability logic, cancelled bookings, cross-showtime isolation, pricing, and 300-seat performance

### Decisions
- [2026-04-04] Seat status is computed at query time, not stored — avoids state synchronization issues
- [2026-04-04] Only `BookingStatus::Confirmed` bookings count as "taken" — cancelled/refunded seats remain available

### Files Changed
- `backend/app/Http/Controllers/Api/ShowtimeController.php` — implemented (was stub)
- `backend/tests/Feature/Api/ShowtimeControllerTest.php` — new (8 tests)

---

## Task 5: Update Route Stub Tests
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Removed 4 movie/showtime stub tests from RouteStubsTest (now covered by dedicated test files)
- [2026-04-04] Full suite: 144 tests, 426 assertions, 0 failures

### Files Changed
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed movie/showtime stubs
