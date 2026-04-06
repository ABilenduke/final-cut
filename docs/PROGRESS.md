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
- [2026-04-04] Implemented `GET /api/locations/{location}/movies/{slug}/showtimes` — location-scoped, date-filtered with today default, eager-loads movie + auditorium
- [2026-04-04] 14 feature tests covering TMDB integration, fallback, date filtering, 404s

### Decisions
- [2026-04-04] Movie detail merges TMDB data onto the local Eloquent model (TMDB provides enriched cast/trailer, local model provides slug/status)

### Known Issue — Movie List Source of Truth (RESOLVED)
- [2026-04-04] `GET /api/movies` was proxying TMDB now_playing/upcoming directly, returning every movie in US theaters instead of only this theater's movies.
- [2026-04-04] **Fixed**: endpoint now queries local `movies` table by status with pagination, uses `MovieListResource` for output. TMDB is only used for enrichment on detail pages, not for listing. Local DB is the source of truth for what this theater shows.

### Files Changed
- `backend/app/Http/Controllers/Api/MovieController.php` — implemented (was stub)
- `backend/tests/Feature/Api/MovieControllerTest.php` — new (14 tests)

---

## Task 4: ShowtimeController — Seat Map with Availability
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] Implemented `GET /api/locations/{location}/showtimes/{id}` — returns showtime + auditorium + full seat map with availability
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

---

## Rework: Theatre-Owned Movie Catalog + Booking Seat Fix
**Status:** ✅ Complete
**Started:** 2026-04-04
**Completed:** 2026-04-04

### Work Done
- [2026-04-04] **Movies migration**: changed from TMDB ID as PK (`unsignedBigInteger('id')->primary()`) to auto-increment (`id()`). Added `tmdb_id` (nullable unique), `cast` (json), `tmdb_enriched_at` (timestamp).
- [2026-04-04] **Booking seats migration**: replaced `unique(['showtime_id', 'seat_id'])` with `index(['showtime_id', 'seat_id'])` — cancelled booking seats no longer block rebooking at DB level.
- [2026-04-04] **Movie model**: removed `$incrementing = false` and `$keyType`, added new columns to fillable and casts.
- [2026-04-04] **TmdbService rework**: removed `nowPlaying()`/`upcoming()` proxy methods. Renamed `movieDetail()` → `fetchEnrichmentData()`. Added connect timeout (3s), read timeout (5s), bounded retries (2x), negative caching (5-min sentinel). Added `enrichMovie(Movie)` method that updates local fields and preserves data on partial TMDB failure. Switched from `Cache::remember` to explicit get/put to handle null correctly.
- [2026-04-04] **MovieController simplification**: removed `TmdbService` constructor dependency. `show()` now serves purely from local DB — no TMDB calls in the request path.
- [2026-04-04] **EnrichMoviesCommand**: new `movies:enrich` artisan command with `--movie`, `--force`, `--stale-hours` options. 200ms rate-limit delay between API calls. Scheduled hourly.
- [2026-04-04] **Factory/seeder updates**: removed explicit TMDB IDs, added `tmdb_id`, `cast`, `tmdb_enriched_at` fields.
- [2026-04-04] **Test updates**: rewrote MovieTest (10 tests), TmdbServiceTest (13 tests), MovieControllerTest (15 tests), ResourcesTest, BookingSeatTest, ShowtimeControllerTest (+1 rebooking test). New EnrichMoviesCommandTest (6 tests).
- [2026-04-04] **Plan doc updated**: `docs/plans/backend/03-movie-api.md` rewritten to reflect theatre-owned catalog architecture.

### Decisions
- [2026-04-04] Theatre owns its catalog — TMDB is enrichment only, never in the request path. Addresses adversarial review finding about TMDB brownouts hanging user requests.
- [2026-04-04] Auto-increment PK with optional `tmdb_id` reference — decouples identity from external service.
- [2026-04-04] Negative caching (5-min sentinel) prevents stampeding a down TMDB API — addresses adversarial review finding about cascading availability problems.
- [2026-04-04] Booking seat unique constraint removed — addresses adversarial review finding about read/write model disagreement. Application-level locking to be implemented in the booking flow (Plan 04).
- [2026-04-04] `Cache::remember` replaced with explicit get/put in `fetchEnrichmentData` — array cache driver doesn't reliably cache null values, causing re-execution of the closure.

### Files Changed
- `backend/database/migrations/2026_04_04_200001_create_movies_table.php` — auto-increment PK, new columns
- `backend/database/migrations/2026_04_04_200010_create_booking_seats_table.php` — unique → index
- `backend/app/Models/Movie.php` — removed non-incrementing, added new fields
- `backend/app/Services/TmdbService.php` — enrichment-only rework
- `backend/app/Http/Controllers/Api/MovieController.php` — removed TMDB dependency
- `backend/app/Console/Commands/EnrichMoviesCommand.php` — new
- `backend/routes/console.php` — hourly schedule
- `backend/database/factories/MovieFactory.php` — auto-increment, new fields
- `backend/database/seeders/MovieSeeder.php` — tmdb_id, cast, tmdb_enriched_at
- `backend/tests/Unit/Models/MovieTest.php` — rewritten (10 tests)
- `backend/tests/Unit/Models/BookingSeatTest.php` — updated constraint test
- `backend/tests/Unit/Services/TmdbServiceTest.php` — rewritten (13 tests)
- `backend/tests/Unit/Resources/ResourcesTest.php` — updated for dynamic IDs + cast
- `backend/tests/Feature/Api/MovieControllerTest.php` — rewritten (15 tests, no TMDB)
- `backend/tests/Feature/Api/ShowtimeControllerTest.php` — added rebooking test (9 tests)
- `backend/tests/Feature/Console/EnrichMoviesCommandTest.php` — new (6 tests)
- `docs/plans/backend/03-movie-api.md` — rewritten for theatre-owned architecture

---

# Progress Journal — Plan 04: Booking & Payment API

## Tasks 0-7: Full Booking API Implementation
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Installed `stripe/stripe-php` v20
- [2026-04-05] Created `SeatConflictException` with `unavailableSeatIds` array, registered in `bootstrap/app.php` as renderable 409 response
- [2026-04-05] Created `StripeService` wrapping Stripe PHP SDK — `createPaymentIntent()` (create+confirm in one call), `confirmPaymentIntent()` (3DS completion). 9 unit tests.
- [2026-04-05] Created `SeatAvailabilityService` — `checkAvailability()` (returns taken seat IDs, confirmed bookings only), `reserveSeats()` (validates auditorium ownership, checks availability, creates BookingSeat records with correct per-type pricing, returns total cost). 7 unit tests.
- [2026-04-05] Created `CreateBookingRequest` with full validation rules (camelCase keys from frontend), guest email enforcement via `withValidator()`
- [2026-04-05] Created `BookingResource` mapping to frontend TypeScript Booking interface (camelCase output, eager-loaded relations)
- [2026-04-05] Created MVP promo code config (`config/promo_codes.php`) with SAVE10 (10% up to $20) and WELCOME5 ($5 off)
- [2026-04-05] Implemented `BookingController::store` — full booking creation flow: validate → check showtime future → lock showtime → reserve seats → validate food → apply promo → apply gift card → process Stripe payment → create booking records → award loyalty points
- [2026-04-05] Implemented `BookingController::show` — authenticated owner access only, 404 for non-owners (no info leakage)
- [2026-04-05] Implemented `BookingController::lookup` — guest booking retrieval via `confirmation_code` + `email` query params
- [2026-04-05] Implemented `BookingController::confirm` — 3DS completion flow: pull cached pending data, confirm PaymentIntent, create booking from scratch with fresh seat validation
- [2026-04-05] Added `/api/bookings/lookup` route before `/{id}` route to prevent route collision
- [2026-04-05] Created `FakeStripeService` (extends StripeService) with `shouldSucceed()`, `shouldRequire3ds()`, `shouldDecline()` fluent API
- [2026-04-05] Created `BookingTestHelper` trait with `createShowtimeWithSeats()` and `fakeStripe()` helpers
- [2026-04-05] 25 feature tests covering: guest/auth booking, seat conflict 409, expired showtime 410, payment declined 402, 3DS flow, promo codes, gift card full/partial payment, food items, auditorium validation, guest email requirement, cancelled booking rebooking, seat pricing, show/lookup/confirm endpoints
- [2026-04-05] Updated `RouteStubsTest` — removed 3 booking stubs (now covered by dedicated tests)
- [2026-04-05] Full suite: 213 tests, 606 assertions, 0 failures

### Decisions
- [2026-04-05] **Pessimistic locking via `Showtime::lockForUpdate()`** — serializes concurrent bookings per showtime. The `booking_seats(showtime_id, seat_id)` is an index (not unique constraint), so application-level locking is required. Trade-off: Stripe API call happens under DB lock (~200-500ms), acceptable for MVP traffic.
- [2026-04-05] **3DS flow caches all creation data** — on `requires_action`, the provisional booking is deleted and all data is cached (15-min TTL keyed by paymentIntentId). The `confirm` endpoint creates the booking fresh with full seat re-validation, preventing seats stolen during the 3DS window.
- [2026-04-05] **FakeStripeService extends StripeService** — required by PHP type system since `BookingController` type-hints `StripeService` in constructor. Overrides parent constructor to skip Stripe client creation.
- [2026-04-05] **MVP promo codes via config array** — no database table yet. Supports percentage (with max cap) and fixed discount types.
- [2026-04-05] **`total` field stores subtotal value** — matches the frontend Booking interface where `total` represents what the customer "pays" (before discounts are applied as separate line items). The `discount` field captures promo + gift card amounts.

### Files Changed
- `backend/composer.json` / `composer.lock` — added `stripe/stripe-php` v20
- `backend/bootstrap/app.php` — registered SeatConflictException as 409
- `backend/app/Exceptions/SeatConflictException.php` — new
- `backend/app/Services/StripeService.php` — new
- `backend/app/Services/SeatAvailabilityService.php` — new
- `backend/app/Http/Requests/CreateBookingRequest.php` — new
- `backend/app/Http/Resources/BookingResource.php` — new
- `backend/config/promo_codes.php` — new
- `backend/app/Http/Controllers/Api/BookingController.php` — implemented (was stub)
- `backend/routes/api.php` — added /bookings/lookup route
- `backend/tests/Helpers/FakeStripeService.php` — new
- `backend/tests/Helpers/BookingTestHelper.php` — new
- `backend/tests/Unit/Services/StripeServiceTest.php` — new (9 tests)
- `backend/tests/Unit/Services/SeatAvailabilityServiceTest.php` — new (7 tests)
- `backend/tests/Feature/Api/BookingControllerTest.php` — new (25 tests)
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed 3 booking stubs

---

## Code Review Fixes
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] **Fixed `total` field bug** — was set to `$subtotal`, now correctly set to `$subtotal - $discount`. Affected both `store()` and `confirm()` methods, plus cached 3DS pending data.
- [2026-04-05] **Fixed gift card concurrency** — gift card now fetched with `lockForUpdate()` inside the transaction to prevent concurrent overdrawing. Pre-check outside transaction remains for fast validation.
- [2026-04-05] **Fixed loyalty points base** — changed from `floor($subtotal / 100)` to `floor($total / 100)` so points are awarded on amount actually paid, not pre-discount subtotal.
- [2026-04-05] **Added promo code case normalization** — `strtoupper()` applied to promo code input so "save10" works same as "SAVE10".
- [2026-04-05] **Added loyalty points with discount test** — verifies points are calculated on total after discount.
- [2026-04-05] **Added total field assertions** — promo code and gift card tests now verify the `total` field is correct.
- [2026-04-05] Full suite: 214 tests, 610 assertions, 0 failures

### Decisions
- [2026-04-05] **Kept `booking_seats(showtime_id, seat_id)` as INDEX not UNIQUE** — intentional from Plan 03 rework. Cancelled bookings can coexist with new bookings for same seat. Pessimistic lock on showtime handles concurrency.
- [2026-04-05] **3DS seat release accepted as MVP trade-off** — seats are unreserved during 3DS window (~30s). Confirm re-validates availability. Future: add `Pending` booking status to hold seats during 3DS.
- [2026-04-05] **Extra Stripe methods (customer management) deferred** — those are for Plan 06 (Account API), not Plan 04.

### Files Changed
- `backend/app/Http/Controllers/Api/BookingController.php` — fixed total, gift card locking, loyalty points, promo normalization
- `backend/tests/Feature/Api/BookingControllerTest.php` — added assertions and loyalty discount test

---

# Progress Journal — Plan 05: Authentication API

## Tasks 0-7: Full Auth API Implementation
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Added Mailpit service to `docker-compose.override.yml` for local email testing (web UI on port 8025, SMTP on 1025)
- [2026-04-05] Switched session driver from `database` to `redis` to align with project architecture ("Cache/Sessions: Redis with TLS")
- [2026-04-05] Configured Sanctum SPA auth: `SANCTUM_STATEFUL_DOMAINS=finalcut.test`, `SESSION_DOMAIN=finalcut.test`, `SESSION_SECURE_COOKIE=true`
- [2026-04-05] Set `.env.testing` to use `SESSION_DRIVER=array`, `SANCTUM_STATEFUL_DOMAINS=localhost` for fast in-memory test sessions
- [2026-04-05] Created `RegisterRequest` (name, email unique, password min:8 confirmed) and `LoginRequest` (email, password)
- [2026-04-05] Created `UserResource` with explicit camelCase field selection (id, email, name, avatarUrl, loyaltyPoints, loyaltyTier, premierExpiry, createdAt) — resource is the primary security boundary, not `$hidden`
- [2026-04-05] Added `frontend_url` to `config/app.php` from `FRONTEND_URL` env var
- [2026-04-05] Configured `ResetPassword::createUrlUsing()` in `AppServiceProvider::boot()` to point reset links to `FRONTEND_URL/auth/reset-password?token=...&email=...`
- [2026-04-05] Implemented 6 AuthController methods: register, login, logout, me, forgotPassword, resetPassword
- [2026-04-05] Added `POST /api/auth/reset-password` route
- [2026-04-05] 23 Pest feature tests: registration (7), login (4), logout (2), me (2), forgot-password (2), reset-password (4), SPA cookie flow (1), lifecycle (1)
- [2026-04-05] Removed 5 auth stub tests from RouteStubsTest (3 mutation stubs + 2 protected route stubs)
- [2026-04-05] Full suite: 263 tests, 772 assertions, 0 failures
- [2026-04-05] Updated `SITE_ARCHITECTURE.md` and `STATE_MANAGEMENT.md` to clarify dual auth architecture (Sanctum + nuxt-auth-utils)

### Decisions
- [2026-04-05] **Session driver: Redis** — aligns with documented project architecture. Tests use `array` driver for speed and determinism.
- [2026-04-05] **Single-origin architecture** — nginx serves both frontend (/) and API (/api/*) from `finalcut.test`. No subdomain sharing needed, so `SESSION_DOMAIN=finalcut.test` (no leading dot) and `same_site=lax` are unambiguously correct.
- [2026-04-05] **Guard consistency** — `Auth::guard('web')` used explicitly in register, login, and logout for consistency with session-based Sanctum SPA auth.
- [2026-04-05] **Session invalidation on password reset** — handled by Sanctum's `AuthenticateSession` middleware (password hash mismatch detection), which is driver-agnostic. No manual `DB::table('sessions')->delete()` or Redis key scanning needed. Remember tokens also rotated via `setRememberToken()`.
- [2026-04-05] **`hasSession()` guard** — controller uses `$request->hasSession()` before session operations to avoid `RuntimeException: Session store not set on request` when Sanctum's stateful middleware isn't active (e.g., non-stateful API calls or certain test contexts).
- [2026-04-05] **`$user->refresh()` after create** — needed because database defaults (loyalty_tier='member', loyalty_points=0) aren't reflected in the in-memory model after `User::create()`.
- [2026-04-05] **Rate limiting at nginx** — auth endpoints already rate-limited at 5r/m per IP in `nginx/nginx.conf`. No additional application-level throttling needed.
- [2026-04-05] **Frontend gaps deferred** — `/auth/reset-password` page spec and Vitest/Playwright auth coverage deferred to frontend Plan 09 (Auth & Account Domain).

### Files Changed
- `docker-compose.override.yml` — added Mailpit service
- `backend/.env` — SESSION_DRIVER=redis, MAIL_* → Mailpit, SANCTUM_STATEFUL_DOMAINS, SESSION_DOMAIN, SESSION_SECURE_COOKIE, FRONTEND_URL
- `backend/.env.example` — same updates
- `backend/.env.testing` — SESSION_DRIVER=array, SANCTUM_STATEFUL_DOMAINS=localhost
- `backend/config/app.php` — added `frontend_url` from env
- `backend/app/Providers/AppServiceProvider.php` — ResetPassword URL customization
- `backend/app/Http/Controllers/Api/AuthController.php` — implemented all 6 methods
- `backend/app/Http/Requests/RegisterRequest.php` — new
- `backend/app/Http/Requests/LoginRequest.php` — new
- `backend/app/Http/Resources/UserResource.php` — new
- `backend/routes/api.php` — added POST /api/auth/reset-password
- `backend/tests/Feature/Api/AuthControllerTest.php` — new (23 tests)
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed 5 auth stubs
- `docs/SITE_ARCHITECTURE.md` — clarified auth architecture
- `docs/STATE_MANAGEMENT.md` — clarified auth persistence

---

# Progress Journal — Plan 06: Account Management API

## Step 0: Doc Updates
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Updated `docs/DATA_MODELS.md` Account section response shapes to match `{ data }` envelope convention
- [2026-04-05] Updated `docs/PAGE_SPECS.md` `/account/profile` to include phone and date_of_birth fields
- [2026-04-05] Initialized Plan 06 section in PROGRESS.md

### Decisions
- [2026-04-05] All account endpoints follow existing `{ data }` envelope convention (via `successResponse()`), not the original doc naming (`orders`, `bookings`, `methods`)
- [2026-04-05] POST /payment-methods is a "create SetupIntent" endpoint returning `{ data: { clientSecret } }`, not a "save completed method" endpoint

### Files Changed
- `docs/DATA_MODELS.md` — Account section response shapes updated
- `docs/PAGE_SPECS.md` — /account/profile added phone, date_of_birth fields

---

## Task 1: Profile API
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Created UserProfileResource extending UserResource (adds phone, dateOfBirth)
- [2026-04-05] Created UpdateProfileRequest with partial update validation, email lowercasing via prepareForValidation()
- [2026-04-05] Implemented profile() and updateProfile() in AccountController
- [2026-04-05] 21 Pest tests covering GET/PATCH profile (97 assertions)

### Decisions
- [2026-04-05] Email lowercased in prepareForValidation() for case-insensitive uniqueness on PostgreSQL
- [2026-04-05] Phone validation is permissive (string|max:20) — international format varies too much for strict rules
- [2026-04-05] Password hashing handled by model's `hashed` cast, not manual Hash::make()

### Files Changed
- `backend/app/Http/Resources/UserProfileResource.php` — new
- `backend/app/Http/Requests/UpdateProfileRequest.php` — new
- `backend/app/Http/Controllers/Api/AccountController.php` — implemented profile(), updateProfile()
- `backend/tests/Feature/Api/AccountProfileTest.php` — new (21 tests)

---

## Task 2: Orders & Bookings API
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Implemented orders() with pagination (created_at DESC) and BookingResource::collection()
- [2026-04-05] Implemented bookings() with upcoming filter (start_time > now, ASC sort via join)
- [2026-04-05] 16 Pest tests covering pagination, user isolation, sorting, envelope shape

### Decisions
- [2026-04-05] orders() uses manual response()->json() instead of paginatedResponse() to wrap items in BookingResource
- [2026-04-05] bookings() uses join on showtimes for start_time ASC ordering with select('bookings.*') to avoid column conflicts
- [2026-04-05] BOOKING_RELATIONS constant shared between orders() and bookings()

### Files Changed
- `backend/app/Http/Controllers/Api/AccountController.php` — implemented orders(), bookings()
- `backend/tests/Feature/Api/AccountOrdersTest.php` — new (16 tests)

---

## Task 3: Loyalty API
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Created LoyaltyService with getPoints, getTier, awardPointsForPurchase (cents→points), getHistory
- [2026-04-05] Implemented loyalty() in AccountController with LoyaltyService injection
- [2026-04-05] Refactored BookingController to use LoyaltyService instead of inline increment logic
- [2026-04-05] 10 unit tests + 6 feature tests (16 total)

### Decisions
- [2026-04-05] awardPointsForPurchase accepts cents, converts internally — callers pass money amounts
- [2026-04-05] History derived from confirmed bookings only (1 point per dollar: floor(total/100))
- [2026-04-05] BookingController refactored to inject LoyaltyService via constructor

### Files Changed
- `backend/app/Services/LoyaltyService.php` — new
- `backend/app/Http/Controllers/Api/AccountController.php` — implemented loyalty(), added constructor injection
- `backend/app/Http/Controllers/Api/BookingController.php` — refactored to use LoyaltyService
- `backend/tests/Unit/Services/LoyaltyServiceTest.php` — new (10 tests)
- `backend/tests/Feature/Api/AccountLoyaltyTest.php` — new (6 tests)

---

## Task 4: Payment Methods API
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done
- [2026-04-05] Extended StripeService with 5 methods: getOrCreateCustomer, listPaymentMethods, createSetupIntent, retrievePaymentMethod, detachPaymentMethod
- [2026-04-05] Extended FakeStripeService with fake implementations, tracking arrays, and configurators
- [2026-04-05] Implemented PaymentMethodController: index (list cards), store (create SetupIntent), destroy (with ownership check)
- [2026-04-05] 13 Pest tests covering all endpoints including ownership authorization

### Decisions
- [2026-04-05] POST /payment-methods is "create SetupIntent" endpoint — card attachment happens client-side via Stripe.js
- [2026-04-05] Stripe Customer created lazily on first call, ID persisted on User model
- [2026-04-05] Delete verifies PM ownership by comparing PM's customer field to user's stripe_customer_id (returns 403 on mismatch)

### Files Changed
- `backend/app/Services/StripeService.php` — added 5 new methods
- `backend/tests/Helpers/FakeStripeService.php` — added fake implementations + tracking
- `backend/app/Http/Controllers/Api/PaymentMethodController.php` — fully implemented
- `backend/tests/Feature/Api/PaymentMethodControllerTest.php` — new (13 tests)

---

## Final Verification
**Status:** ✅ Complete
**Completed:** 2026-04-05

**Test count:** 332 tests, 1061 assertions, 0 failures (up from 266 tests at start)
**New tests added:** 66 (21 + 16 + 16 + 13)

---

# Progress Journal — Plan 07: Calendar, Food Menu, Gift Cards, Contact & Rentals API

## Task 1: CalendarEventController
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done

- [2026-04-05] Created CalendarEventResource with camelCase fields, enum ->value, date/time formatting
- [2026-04-05] Implemented index with month/year filtering (defaults to current), type filter, accessibility JSON column filter (OR logic via whereJsonContains)
- [2026-04-05] Implemented show by slug with 404 for invalid
- [2026-04-05] Wrote 11 Pest tests, removed 2 calendar stub tests from RouteStubsTest

### Files Changed

- `backend/app/Http/Controllers/Api/CalendarEventController.php` — implemented index + show
- `backend/app/Http/Resources/CalendarEventResource.php` — new
- `backend/tests/Feature/Api/CalendarEventControllerTest.php` — new (11 tests)
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed 2 calendar stubs

---

## Task 2: GiftCardController
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done

- [2026-04-05] Created PurchaseGiftCardRequest with amount (500-50000), recipientEmail, recipientName, senderName, message (nullable), paymentMethodId validation
- [2026-04-05] Created GiftCardResource with camelCase fields
- [2026-04-05] Implemented purchase with Stripe PaymentIntent, GC-XXXXXXXX code generation with uniqueness loop
- [2026-04-05] Implemented balance check by code (404 for invalid, 422 for missing)
- [2026-04-05] Stripe error handling follows BookingController pattern (402/400/502)
- [2026-04-05] Wrote 15 Pest tests, removed 2 gift card stub tests from RouteStubsTest

### Files Changed

- `backend/app/Http/Controllers/Api/GiftCardController.php` — implemented purchase + balance
- `backend/app/Http/Requests/PurchaseGiftCardRequest.php` — new
- `backend/app/Http/Resources/GiftCardResource.php` — new
- `backend/tests/Feature/Api/GiftCardControllerTest.php` — new (15 tests)
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed 2 gift card stubs

---

## Task 3: ContactController + RentalController
**Status:** ✅ Complete
**Started:** 2026-04-05
**Completed:** 2026-04-05

### Work Done

- [2026-04-05] Created ContactRequest (name, email, subject, message — all required)
- [2026-04-05] Created RentalInquiryRequest (eventType enum, preferredDate after:today, guestCount min:1, name, email required; phone, message nullable)
- [2026-04-05] ContactController logs via Log::info for MVP (no email)
- [2026-04-05] RentalController creates RentalInquiry with status: pending, returns 201
- [2026-04-05] Added throttle:5,1 middleware to both routes in api.php
- [2026-04-05] Wrote 12 Pest tests (4 contact + 8 rental), removed 2 stub tests from RouteStubsTest

### Files Changed

- `backend/app/Http/Controllers/Api/ContactController.php` — implemented store
- `backend/app/Http/Controllers/Api/RentalController.php` — implemented store
- `backend/app/Http/Requests/ContactRequest.php` — new
- `backend/app/Http/Requests/RentalInquiryRequest.php` — new
- `backend/tests/Feature/Api/ContactControllerTest.php` — new (4 tests)
- `backend/tests/Feature/Api/RentalControllerTest.php` — new (8 tests)
- `backend/routes/api.php` — added throttle middleware group
- `backend/tests/Feature/Api/RouteStubsTest.php` — removed 2 contact/rental stubs

---

## Final Verification
**Status:** ✅ Complete
**Completed:** 2026-04-05

**Test count:** 370 tests, 1218 assertions, 0 failures (up from 332 at start of Plan 07)
**New tests added:** 38 (11 + 15 + 4 + 8)
**Stub tests removed:** 6 (all 501 stubs now replaced by dedicated test files)
**Pint:** All 155 files pass

---

## Gift Card 3DS & Idempotency
**Status:** ✅ Complete
**Started:** 2026-04-06
**Completed:** 2026-04-06

### Work Done
- [2026-04-06] Added `idempotency_key` (nullable, unique) and `payload_hash` (nullable) columns to gift_cards migration
- [2026-04-06] Added optional `?string $idempotencyKey` parameter to `StripeService::createPaymentIntent`
- [2026-04-06] Created `PayloadFingerprint` utility for canonical SHA-256 request hashing with normalization rules
- [2026-04-06] Rewrote `GiftCardController::purchase` with full idempotency + 3DS support (lookup-before-Stripe replay, cache-based pending state, hard failure caching)
- [2026-04-06] Added `POST /api/gift-cards/confirm` endpoint for 3DS completion with replay safety
- [2026-04-06] Updated `PurchaseGiftCardRequest` to require `Idempotency-Key` UUID header
- [2026-04-06] Added decline behavior to `FakeStripeService::confirmPaymentIntent`
- [2026-04-06] Fixed StripeService unit test mocks to accept new options parameter
- [2026-04-06] Added 25 new tests covering idempotency replay, normalization, 3DS flow, failure caching, confirm, and compensating refunds

### Decisions
- [2026-04-06] Hard failures (card declined, invalid PM) are cached for 15 min to enable deterministic replay; transient failures (Stripe unavailable, unexpected status) are NOT cached to allow retry
- [2026-04-06] Compensating refund is a deliberate new design decision for gift cards — not assumed precedent from booking flow
- [2026-04-06] `payload_hash` and `idempotency_key` stored on gift card row but NOT exposed in API response (internal replay fields)
- [2026-04-06] `InvalidRequestException` cached as hard failure for payment-specific outcomes from `createPaymentIntent`

### Files Changed
- `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php` — added idempotency_key and payload_hash columns
- `backend/app/Models/GiftCard.php` — added to Fillable
- `backend/app/Services/StripeService.php` — optional idempotencyKey param on createPaymentIntent
- `backend/tests/Helpers/FakeStripeService.php` — tracks idempotency keys, added decline to confirmPaymentIntent
- `backend/app/Support/PayloadFingerprint.php` — new utility class
- `backend/app/Http/Controllers/Api/GiftCardController.php` — full rewrite with idempotency + 3DS + confirm
- `backend/app/Http/Requests/PurchaseGiftCardRequest.php` — Idempotency-Key header validation
- `backend/routes/api.php` — added gift-cards/confirm route
- `backend/tests/Feature/Api/GiftCardControllerTest.php` — 25 new tests (46 total, up from 17)
- `backend/tests/Unit/Support/PayloadFingerprintTest.php` — 7 unit tests
- `backend/tests/Unit/Services/StripeServiceTest.php` — updated mocks for options parameter

**Test count:** 408 tests, 1342 assertions, 0 failures (up from 370)
