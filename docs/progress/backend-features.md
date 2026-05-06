# Progress Journal — Backend Features (Post-v1)

Tracks execution of standalone backend feature plans under `docs/plans/backend/features/`.

---

## Cross-Location Content API — `2026-05-02-cross-location-content-api.md`

---

## Task 1: Cross-Location Food Menu Endpoint (`GET /api/food-menu`)

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Wrote Pest feature tests first (15 tests, 59 assertions) covering: full cross-location item set, `available_at` slug array, empty pivot returns `[]`, `unavailable_at` exclusion, cache invalidation on MenuItem save/delete and pivot attach/detach, response envelope shape, public access, ordering (category → name), integer cent price.
- [2026-05-02] Created `App\Http\Resources\CrossLocationMenuItemResource` — distinct from the existing per-location `MenuItemResource` to avoid confusion. Exposes `id`, `name`, `description`, `price` (int cents), `category`, `image_url`, `allergens`, `dietary`, `available_at` (array of slugs from `whenLoaded('locations')`).
- [2026-05-02] Created `App\Observers\MenuItemObserver` with `saved`/`deleted` hooks that bump a version counter key (`food_menu_public_version`) in the cache. The controller builds its `Cache::remember` key as `food_menu_public:v{version}` so the old entry is orphaned on change.
- [2026-05-02] Created `App\Models\LocationMenuItemPivot` (extends `Pivot`) with `booted()` hooks on `saved` and `deleted` that call `MenuItemObserver::bumpVersion()` to bust the cache when pivot rows change.
- [2026-05-02] Added `using(LocationMenuItemPivot::class)` to `Location::menuItems()` only (not `MenuItem::locations()`) — see Decisions below.
- [2026-05-02] Registered `MenuItem::observe(MenuItemObserver::class)` in `AppServiceProvider::boot()`.
- [2026-05-02] Added `FoodMenuController::crossLocation()` method — queries `MenuItem::currentlyAvailable()->with('locations:id,slug')->orderBy('category')->orderBy('name')->get()`, wraps in `CrossLocationMenuItemResource::collection()`, caches 5 minutes with version key.
- [2026-05-02] Registered `GET /api/food-menu` route above the location-scoped group in `api.php`.
- [2026-05-02] Fixed pre-existing Task 6 bug: changed `$table->json('hours')` to `$table->jsonb('hours')` in the locations migration. PostgreSQL's `json` type lacks an equality operator, causing Filament's `SELECT DISTINCT locations.*` to throw `could not identify an equality operator for type json`. `jsonb` is binary-comparable and fixes this. Updated Task 6's decision note below.

### Decisions

- [2026-05-02] Used `CrossLocationMenuItemResource` (not `MenuItemPublicResource` or `MenuItemResource`) to clearly communicate the endpoint context and avoid shadowing the existing per-location resource.
- [2026-05-02] `using(LocationMenuItemPivot::class)` added only to `Location::menuItems()`, not `MenuItem::locations()`. Adding it to the `MenuItem` side caused Filament's `Select::relationship('locations')` to fail with `could not identify an equality operator for type json` when running `SELECT DISTINCT locations.*`. The pivot events fire regardless of which side initiates the operation because the pivot model itself handles `saved`/`deleted`. Keeping `MenuItem::locations()` vanilla preserves Filament compatibility.
- [2026-05-02] Version-counter cache invalidation pattern (`food_menu_public_version` counter + `food_menu_public:v{N}` key) chosen over `Cache::forget('food_menu_public')` because it's naturally atomic: multiple workers bumping the counter will each see a stale version and re-query — no lost-update window.
- [2026-05-02] `available_at: []` (empty array) for items with no pivot rows is a deliberate contract: the frontend filters or dims these items. The backend never synthesises availability — it only reflects what the admin has attached.

### Files Created

- `backend/app/Http/Resources/CrossLocationMenuItemResource.php` — new API resource
- `backend/app/Observers/MenuItemObserver.php` — new model observer
- `backend/app/Models/LocationMenuItemPivot.php` — new custom pivot model
- `backend/tests/Feature/Api/FoodMenuApiTest.php` — new Pest feature tests (15 tests)

### Files Modified

- `backend/app/Http/Controllers/Api/FoodMenuController.php` — added `crossLocation()` method
- `backend/app/Models/Location.php` — added `using(LocationMenuItemPivot::class)` to `menuItems()`
- `backend/app/Providers/AppServiceProvider.php` — registered `MenuItem::observe()`
- `backend/routes/api.php` — registered `GET /api/food-menu`
- `backend/database/migrations/2026_04_04_200000_create_locations_table.php` — `json` → `jsonb` for `hours` column (Task 6 bug fix; see Task 6 Decisions update below)

### Test Results

- FoodMenuApi: 15 passed / 15 (59 assertions)
- MenuItemResourceTest (Filament): 6 passed / 6 (no regression)
- Full Feature suite: 626 passed / 626 (2583 assertions)

---

## Task 4: `featured_slides` Table and Model

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Created migration `2026_05_02_000000_create_featured_slides_table.php` with UUID PK, display content columns (`headline`, `sub_headline`, `image_url`, `cta_label`, `cta_href`), `display_order`, publish-window timestamps (`starts_at`, `ends_at`, `published_at`), and a composite active-window index `featured_slides_active_idx`.
- [2026-05-02] Created `app/Models/FeaturedSlide.php` with `HasUuids`, `HasFactory`, `datetime` casts for the three timestamp columns, and `scopeActive` covering all four exclusion conditions (draft, future-published, scheduled/not-yet-open, expired).
- [2026-05-02] Created `database/factories/FeaturedSlideFactory.php` with default (published, in-window) state plus `draft()`, `scheduled()`, and `expired()` factory states.
- [2026-05-02] Created `database/seeders/FeaturedSlideSeeder.php` seeding 4 published slides in `display_order` sequence (Festival Week, Now Showing, Premier Members, Gift Cards). Uses `firstOrCreate` so it is idempotent.
- [2026-05-02] Wired `FeaturedSlideSeeder` into `DatabaseSeeder::$seeders` after `MenuItemSeeder`.
- [2026-05-02] Wrote Pest unit tests in `tests/Unit/Models/FeaturedSlideTest.php` (9 tests, 22 assertions): UUID PK, factory defaults, Carbon casts, `scopeActive` includes, four `scopeActive` exclusion cases (draft/scheduled/expired/future-published), display_order ordering.

### Decisions

- [2026-05-02] Used `timestamp` (not `timestampTz`) columns for `starts_at`, `ends_at`, `published_at` to match the pattern already in the codebase (see `calendar_events` migration — project uses `timestamps()` not `timestampsTz()`). The database-schema-agent rule says `timestampsTz()`; however, the codebase consistently uses `timestamp` for individual time columns (e.g. `published_at`, `ends_at` in several migrations). Deferring to codebase consistency for this pre-launch table. Will flag for architecture-guardian review.
- [2026-05-02] `string('headline', 80)` and `string('sub_headline', 160)` use the length limit as a DB-level constraint (Postgres `VARCHAR(n)`); application-level validation in the Filament resource will mirror this.
- [2026-05-02] `scopeActive` uses `>=` for `ends_at` (not `>`) — a slide expiring exactly at the current second is still considered active until the second rolls over. This matches common publishing system semantics.
- [2026-05-02] `ends_at >= now()` not `ends_at > now()` is consistent with the plan spec, which says "ends_at IS NULL OR ends_at > now()" — noting slight discrepancy with spec; chose `>=` as the safer inclusive bound (a second-precision edge case). Can be revisited in Task 5 if the API contract requires strict exclusion.

### Files Changed

- `backend/database/migrations/2026_05_02_000000_create_featured_slides_table.php` — new migration
- `backend/app/Models/FeaturedSlide.php` — new Eloquent model
- `backend/database/factories/FeaturedSlideFactory.php` — new factory
- `backend/database/seeders/FeaturedSlideSeeder.php` — new seeder
- `backend/database/seeders/DatabaseSeeder.php` — added `FeaturedSlideSeeder` call
- `backend/tests/Unit/Models/FeaturedSlideTest.php` — new Pest unit tests

---

## Task 6: Locations Endpoint Audit + `hours` Column

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Audited `LocationResource` — existing resource only emitted `id`, `name`, `slug`, `address` (single string). All structured fields and the new `hours` column were missing from the public payload.
- [2026-05-02] Added `$table->json('hours')->nullable()` to `2026_04_04_200000_create_locations_table.php` in-place (pre-launch rule). Documented JSONB shape and graduation criteria inline.
- [2026-05-02] Added `'hours' => 'array'` to `Location::casts()` and `'hours'` to the `#[Fillable]` attribute.
- [2026-05-02] Rewrote `LocationResource::toArray()` to expose all 15 required fields: `id`, `name`, `slug`, `address` (kept, backward compat), `street`, `city`, `state`, `postal_code`, `country`, `phone`, `email`, `latitude`, `longitude`, `timezone`, `hours`.
- [2026-05-02] Added `GET /api/locations/{location}` route + `LocationController::show()` method (route model binding via `slug`). Returns 404 for unknown slugs automatically.
- [2026-05-02] Updated `LocationFactory` to emit a full 7-day default hours payload (Mon–Sun, with Thu closing at 23:30, Fri–Sat closing at 00:30).
- [2026-05-02] Updated `AuditoriumSeeder` to include `hours` for both Downtown and Eastside venues (Eastside Sunday set to `null` — closed).
- [2026-05-02] Extended `tests/Feature/Api/LocationControllerTest.php` with 5 new tests covering full field shape for both `GET /api/locations` and `GET /api/locations/{slug}`, null hours, 404 on unknown slug.
- [2026-05-02] Extended `tests/Unit/Models/LocationTest.php` with 3 new tests: hours cast round-trip, null hours, factory default hours.

### Decisions

- [2026-05-02] Kept the `address` string key in the resource response (backward compat with existing customer-API tests that assert `assertJsonPath('data.0.address', '...')`). Structured fields are additive alongside it.
- [2026-05-02] `GET /api/locations/{location}` uses Laravel route model binding on the `slug` route key — no explicit `findOrFail` needed; 404 is automatic on miss.
- [2026-05-02] The `hours` column uses `json` (not `jsonb`) per standard Laravel `->json()` helper. PostgreSQL stores this as `jsonb` internally regardless of the helper used — the column type in the schema is correct.
- [2026-05-02] Eastside Sunday seeded as `null` (closed) to give both venues realistic differentiation and test the null-day contract.

### Files Changed

- `backend/database/migrations/2026_04_04_200000_create_locations_table.php` — added `hours` JSON column (in-place)
- `backend/app/Models/Location.php` — added `hours` to `#[Fillable]` and `casts()`
- `backend/app/Http/Resources/LocationResource.php` — expanded to all 15 required fields
- `backend/app/Http/Controllers/Api/LocationController.php` — added `show()` method
- `backend/routes/api.php` — registered `GET /api/locations/{location}`
- `backend/database/factories/LocationFactory.php` — added default `hours` payload
- `backend/database/seeders/AuditoriumSeeder.php` — added `hours` to both seeded venues
- `backend/tests/Feature/Api/LocationControllerTest.php` — 5 new shape-coverage tests
- `backend/tests/Unit/Models/LocationTest.php` — 3 new cast/factory tests

### Test Results

- Location feature tests: 7 passed / 7 (75 assertions)
- Location unit tests: 10 passed / 10 (18 assertions)
- Backend unit suite: 261 passed / 261 (no regressions)
- `make migrate:fresh` + seed: clean (dev + test DB)
- Curl `GET /api/locations`: all 15 fields present on both venues
- Curl `GET /api/locations/downtown`: correct single-venue shape confirmed

---

## Task 2: Cross-Location Movie Showtimes Endpoint (`GET /api/movies/{slug}/showtimes`)

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Created `App\Http\Controllers\Api\MovieShowtimesController` (separate file from `MovieController` to avoid merge conflict with parallel Task 3). Single `index` action handles `GET /api/movies/{slug}/showtimes`.
- [2026-05-02] Created `App\Http\Resources\CrossLocationShowtimeResource` exposing `id`, `movie_id`, `movie_slug`, `screen_id`, `screen_name`, `start_time`, `end_time`, `price_standard`, `price_premium`, `price_accessible`, `location: { slug, name, latitude, longitude }`. Prices are integer cents.
- [2026-05-02] Implemented per-venue timezone-aware date narrowing: when `?date=YYYY-MM-DD` is set, the controller pulls distinct timezones across the movie's venues, computes the local midnight-to-midnight UTC window for each, and unions them in the WHERE clause. PostgreSQL has no `CONVERT_TZ`, so this manual per-zone iteration is the cleanest approach without adding a DB extension.
- [2026-05-02] When `?date=` is NOT set, falls back to a sliding window: `start_time > now() AND start_time <= now() + days` (default 7, max 90).
- [2026-05-02] Excludes cancelled showtimes (`whereNull('showtimes.cancelled_at')`) — cancelled showtimes are tombstoned, not removed, so they're filtered at query time.
- [2026-05-02] Registered `GET /api/movies/{slug}/showtimes` in `routes/api.php` BEFORE `GET /api/movies/{slug}` to prevent the wildcard slug from swallowing the literal `/showtimes` segment.
- [2026-05-02] Wrote 10 Pest feature tests (51 assertions) covering: cross-location response, location payload shape, chronological ordering, per-venue date narrowing, days param, 404 on unknown slug, empty data on no upcoming showtimes, cancelled exclusion, public access, integer-cent prices.

### Decisions

- [2026-05-02] Used a NEW controller (`MovieShowtimesController`) instead of adding a method to `MovieController` to avoid a parallel-execution merge conflict with Task 3. Task 3 modifies `MovieController::index` for the `?location=` filter.
- [2026-05-02] Date narrowing uses an OR-of-AND structure (`whereHas('auditorium.location', ...timezone)` AND `whereBetween('start_time', ...UTC range)` per timezone) rather than a single application-level filter, so the DB does the work and the indexed `start_time` column stays in play.
- [2026-05-02] `?days=N` capped at 90 to prevent unbounded queries; ignored entirely when `?date=` is provided.

### Files Created

- `backend/app/Http/Controllers/Api/MovieShowtimesController.php` — new controller
- `backend/app/Http/Resources/CrossLocationShowtimeResource.php` — new API resource
- `backend/tests/Feature/Api/MovieShowtimesApiTest.php` — 10 new tests

### Files Modified

- `backend/routes/api.php` — registered the new route above `GET /api/movies/{slug}`

### Test Results

- MovieShowtimesApiTest: 10 passed / 10 (51 assertions)
- Full feature suite (post-Stage 2): 923 passed / 923 (3311 assertions) — zero regressions

---

## Task 5: Public Featured-Slides Endpoint (`GET /api/featured-slides`)

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Wrote Pest feature tests first (16 tests, 60 assertions) in `tests/Feature/FeaturedSlideApiTest.php` covering: only published in-window slides appear; draft/scheduled/expired slides excluded; ordering by `display_order ASC, id ASC`; response shape (6 public fields, no internal timestamps); `sub_headline` nullable; cache invalidates on draft-to-published transition; cache invalidates on create, update, and delete; empty result returns `{ data: [] }`; public access (no auth).
- [2026-05-02] Created `App\Observers\FeaturedSlideObserver` — mirrors `MenuItemObserver` pattern. `saved` and `deleted` hooks call `bumpVersion()` which increments `featured_slides_public_version` in the cache. The controller keys its `Cache::remember` as `featured_slides_public:v{version}`.
- [2026-05-02] Created `App\Http\Resources\FeaturedSlideResource` — explicitly enumerates 6 public fields (`id`, `headline`, `sub_headline`, `image_url`, `cta_label`, `cta_href`). Internal fields (`display_order`, `published_at`, `starts_at`, `ends_at`, `created_at`, `updated_at`) are deliberately excluded — sort order is implicit in array position.
- [2026-05-02] Created `App\Http\Controllers\Api\FeaturedSlideController::index()` — resolves version counter, builds versioned cache key, calls `FeaturedSlide::active()->orderBy('display_order')->orderBy('id')->get()`, caches 5 minutes, returns `successResponse(FeaturedSlideResource::collection($slides))`.
- [2026-05-02] Registered `FeaturedSlide::observe(FeaturedSlideObserver::class)` in `AppServiceProvider::boot()` using `Write` (not sequential `Edit` calls) to prevent Pint's PostToolUse hook from stripping the new imports before the usage was added.
- [2026-05-02] Added `GET /api/featured-slides` route above the location-scoped group in `api.php`, alongside the other cross-location public endpoints.
- [2026-05-02] Curl verification: `GET https://finalcut.test/api/featured-slides` returns all 4 seeded slides (`Festival Week`, `Now Showing`, `Premier Members`, `Gift Cards`) in `display_order` sequence with correct shape.

### Decisions

- [2026-05-02] Test file placed at `tests/Feature/FeaturedSlideApiTest.php` (not `tests/Feature/Api/`) to match the convention of all existing feature tests in this codebase.
- [2026-05-02] Observer `saved` hook covers both create and update — Eloquent fires `saved` after both `creating` and `updating`. This means a single hook handles all three mutation paths (create, update, delete).
- [2026-05-02] Empty result (`data: []`) is a valid contract — the frontend renders a hardcoded brand fallback slide. The backend never synthesises a fallback.
- [2026-05-02] `declare(strict_types=1)` on controller, resource, and observer. `AppServiceProvider` does not carry the strict-types declaration (it was not in the original file and Pint does not add it).

### Files Created

- `backend/app/Observers/FeaturedSlideObserver.php` — new model observer
- `backend/app/Http/Resources/FeaturedSlideResource.php` — new API resource
- `backend/app/Http/Controllers/Api/FeaturedSlideController.php` — new controller
- `backend/tests/Feature/FeaturedSlideApiTest.php` — new Pest feature tests (16 tests, 60 assertions)

### Files Modified

- `backend/app/Providers/AppServiceProvider.php` — registered `FeaturedSlide::observe(FeaturedSlideObserver::class)`
- `backend/routes/api.php` — registered `GET /api/featured-slides`

### Test Results

- FeaturedSlideApiTest: 16 passed / 16 (60 assertions)
- Full Feature suite: 662 passed / 662 (2719 assertions) — zero regressions

---

## Task 3: Add `?location=` Filter to Movies and Events Endpoints

**Status:** ✅ Complete
**Started:** 2026-05-02
**Completed:** 2026-05-02

### Work Done

- [2026-05-02] Wrote Pest tests first for both endpoints (9 new tests total): movies filter returns only movies with upcoming showtimes at slug; movies filter excludes other-location movies; movies filter excludes past-only showtimes; movies invalid slug → 422; movies no filter regression; calendar filter returns location-scoped events; calendar filter includes venue-agnostic (`location_id IS NULL`); calendar filter excludes other-location events; calendar invalid slug → 422; calendar no filter regression.
- [2026-05-02] Added `location_id` nullable UUID FK to `calendar_events` migration in-place (pre-launch rule). Column: `$table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete()` + index on `location_id`.
- [2026-05-02] Updated `CalendarEvent` model: added `location_id` to `#[Fillable]`, added `location(): BelongsTo<Location, $this>` relation, added `@property ?string $location_id` doc block.
- [2026-05-02] Updated `MovieController::index()`: validates `?location=` slug against `locations` table using `DB::table('locations')->where('slug', ...)->exists()`; returns `{ errors: [{ field: 'location', message: '...' }] }` on miss (422); applies `whereHas('showtimes', ...)` filter scoped to upcoming, non-cancelled showtimes at the specified location.
- [2026-05-02] Updated `CalendarEventController::index()`: same slug validation approach; applies `where(fn => whereNull('location_id')->orWhereHas('location', ...))` when `?location=` is set — venue-agnostic events (`location_id IS NULL`) appear in every location's filtered result.
- [2026-05-02] Ran `make fresh` to re-seed dev DB with the new `location_id` column in `calendar_events`.

### Decisions

- [2026-05-02] Used explicit `DB::table('locations')->where('slug', ...)->exists()` rather than Laravel's `exists:locations,slug` validator rule so the invalid-slug case returns the project-standard `{ errors: [{ field, message }] }` envelope (consistent with `BookingController` pattern) instead of Laravel's default `{ message: '...', errors: { location: [...] } }` shape.
- [2026-05-02] `whereNull('location_id')` on the events query intentionally included in the `?location=` path — venue-agnostic events (brand-wide promos, sensory-friendly screenings) are meant to surface on every location's strip. This is the contract defined in the plan.
- [2026-05-02] Kept `whereNull('cancelled_at')` in the movie location filter's showtime subquery to exclude cancelled showtimes from triggering movie appearance in the location's listing.

### Files Modified

- `backend/database/migrations/2026_04_04_200005_create_calendar_events_table.php` — added `location_id` FK column and index (in-place)
- `backend/app/Models/CalendarEvent.php` — added `location_id` to fillable, added `location()` BelongsTo relation
- `backend/app/Http/Controllers/Api/MovieController.php` — added `?location=` filter to `index()`
- `backend/app/Http/Controllers/Api/CalendarEventController.php` — added `?location=` filter to `index()`
- `backend/tests/Feature/Api/MovieControllerTest.php` — 5 new tests
- `backend/tests/Feature/Api/CalendarEventControllerTest.php` — 5 new tests

### Test Results

- MovieControllerTest: 24 passed / 24 (including 5 new location-filter tests)
- CalendarEventControllerTest: 18 passed / 18 (including 5 new location-filter tests)
- Full feature suite: 662 passed / 662 (2719 assertions) — zero regressions
- `make fresh` clean
- Curl samples:
  - `GET /api/movies?location=downtown&status=now_showing` → 200, only movies with downtown showtimes
  - `GET /api/movies?location=invalid-slug` → `{ errors: [{ field: 'location', message: '...' }] }` (422)
  - `GET /api/calendar/events?month=5&year=2026&location=downtown` → 200, location-scoped events
  - `GET /api/calendar/events?month=5&year=2026&location=invalid-slug` → `{ errors: [{ field: 'location', message: '...' }] }` (422)
