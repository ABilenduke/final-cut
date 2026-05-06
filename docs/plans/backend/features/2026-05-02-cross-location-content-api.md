# Cross-Location Content API Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add the backend surface that lets every public content page render the brand's full slate (movies across all venues, food menu with per-location availability, admin-curated home hero carousel) without requiring a per-request location selection. This unlocks the static-first cross-location frontend refactor in `docs/plans/frontend/v1/13-content-refactor.md`.

**Architecture:** Three new public endpoints (`GET /api/food-menu`, `GET /api/movies/{slug}/showtimes`, `GET /api/featured-slides`) and one new table (`featured_slides`). The existing per-location endpoints (`/api/locations/{location}/food-menu`, `/api/locations/{location}/movies/{slug}/showtimes`) stay in place — they remain the booking-flow path and the admin/internal path. The locations endpoint is verified for the fields the new `/locations` and `/locations/:slug` frontend pages need; an `hours` JSON column is added to `locations` if missing. No changes to the booking, payment, auth, or account surfaces.

**Tech Stack:** Laravel 13 (PHP 8.4), Pest, Redis cache, PostgreSQL

**Reference docs:**
- `docs/architecture/CONTENT_ARCHITECTURE.md` — sections 7, 8, 9 define the contracts this plan ships
- `docs/plans/frontend/v1/13-content-refactor.md` — the consumer plan for these endpoints
- `docs/plans/admin/features/2026-05-02-content-curation-admin.md` — Filament resources that produce data for `featured_slides` and `location_menu_item`
- `backend/routes/api.php` — current route inventory
- `backend/database/migrations/2026_04_04_200000_create_locations_table.php` — `locations` schema
- `backend/database/migrations/2026_04_04_200006_create_menu_items_table.php` — `menu_items` schema
- `backend/database/migrations/2026_04_04_200012_create_location_menu_item_table.php` — per-location pivot; this plan reads through it

---

## Task 1: Cross-location food menu endpoint

Add a single shared menu endpoint that returns every menu item with a `available_at` array of location slugs.

**Files:**
- New: `backend/app/Http/Controllers/Api/FoodMenuController.php`
- New: `backend/app/Http/Resources/MenuItemResource.php` (or extend an existing Resource)
- Modify: `backend/routes/api.php` — register `GET /api/food-menu`
- New: `backend/tests/Feature/FoodMenuApiTest.php`

**Endpoint contract:**

```
GET /api/food-menu

Response 200:
{
  "data": [
    {
      "id": "01HZ...",
      "name": "Cinematic Popcorn Bucket",
      "description": "...",
      "price": 1499,
      "category": "popcorn",
      "image_url": "https://...",
      "allergens": ["dairy"],
      "dietary": [],
      "available_at": ["downtown", "uptown"]
    }
  ]
}
```

**Implementation notes:**
- Query: `MenuItem::query()->available()->with('locations:id,slug')->orderBy('category')->orderBy('name')->get()`. The `available()` scope already excludes globally `unavailable_at IS NOT NULL` items.
- `available_at` is built from the `locations` relation (already wired via `belongsToMany`). Map to `pluck('slug')->all()`.
- Response cached in Redis 5 minutes (`Cache::remember('food-menu:public', 300, ...)`); cache key includes a `MenuItem`-table version stamp incremented by an observer on save/delete.
- The existing `GET /api/locations/{location}/food-menu` is left untouched — both the per-location admin/booking-internal consumer and any test fixtures keep working.

**Acceptance Criteria:**
- [ ] Pest test asserts the JSON shape, the `available_at` correctness against the `location_menu_item` pivot, and the `unavailable_at` exclusion
- [ ] Pest test asserts items with empty pivot rows still appear with `available_at: []` (frontend filters these out — this is the contract)
- [ ] Cache invalidation: changing a `MenuItem` (save/delete) invalidates the cache; verified by hitting the endpoint, mutating an item via factory, and asserting the next response includes the change
- [ ] Endpoint is public (no auth)
- [ ] `make test-backend` passes

---

## Task 2: Cross-location movie showtimes endpoint

Add a movie-detail endpoint that returns upcoming showtimes across every location for a given movie, each entry stamped with its venue.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/MovieController.php` (or add `MovieShowtimesController.php`)
- New or modify: `backend/app/Http/Resources/ShowtimeResource.php` — ensure the `location` payload includes `slug`, `name`, `latitude`, `longitude`
- Modify: `backend/routes/api.php` — register `GET /api/movies/{slug}/showtimes`
- New: `backend/tests/Feature/MovieShowtimesApiTest.php`

**Endpoint contract:**

```
GET /api/movies/{slug}/showtimes?date=YYYY-MM-DD&days=7

Response 200:
{
  "data": [
    {
      "id": "01HZ...",
      "movie_id": 123,
      "movie_slug": "the-brutalist",
      "screen_id": "01HZ...",
      "screen_name": "Screen 1",
      "start_time": "2026-05-04T19:30:00-04:00",
      "end_time": "2026-05-04T22:45:00-04:00",
      "price_standard": 1899,
      "price_premium": 2499,
      "price_accessible": 1899,
      "location": {
        "slug": "downtown",
        "name": "Downtown",
        "latitude": 40.712776,
        "longitude": -74.005974
      }
    }
  ]
}
```

**Implementation notes:**
- Query joins through `auditoriums` to `locations`. Default window: from `now()` through `now() + 7 days`. `?date=YYYY-MM-DD` narrows to that calendar date in each location's local timezone (use `Location.timezone`); `?days=N` overrides the window.
- Order by `start_time ASC` regardless of location — the frontend handles per-location grouping.
- `location.latitude` / `location.longitude` are exposed because the frontend uses them for distance computation. They are public-business data.
- The existing `GET /api/locations/{location}/movies/{slug}/showtimes` stays as the per-location path used by `?location=` filtered listings and admin/internal flows.

**Acceptance Criteria:**
- [ ] Pest test seeds showtimes at both venues and asserts the cross-location response includes both, ordered chronologically
- [ ] Pest test asserts each entry carries the venue's lat/lng
- [ ] Pest test asserts `?date=` narrows correctly across timezones (seed Downtown EST + Uptown PST, query each)
- [ ] Endpoint is public (no auth)
- [ ] `make test-backend` passes

---

## Task 3: Add `?location=` filter to existing movies and events endpoints

Support the frontend's `/movies?location=slug` filter and `/locations/:slug` "now showing here" / "events here" strips by adding a `?location=` query param to the existing `GET /api/movies` and `GET /api/calendar/events` endpoints.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/MovieController.php` — accept `?location=` and filter to movies with at least one upcoming showtime at that location
- Modify: `backend/app/Http/Controllers/Api/CalendarEventController.php` — accept `?location=` and filter calendar events to that location (events table needs a `location_id` nullable column if not present; see schema check below)
- Modify (if needed): `backend/database/migrations/*create_calendar_events_table.php` — add `location_id` if missing (pre-launch in-place edit per project convention; null means "all locations")
- Modify: `backend/tests/Feature/MovieApiTest.php` — add `?location=` cases
- Modify: `backend/tests/Feature/CalendarEventApiTest.php` — add `?location=` cases

**Implementation notes:**
- For movies: `Movie::whereHas('showtimes', fn($q) => $q->upcoming()->whereHas('auditorium.location', fn($q) => $q->where('slug', $slug)))`.
- For events: nullable `location_id` lets venue-agnostic events (e.g. brand-wide promos) appear in every location's strip.
- `?location=` value validation: must match an existing slug, otherwise return 422.

**Acceptance Criteria:**
- [ ] `GET /api/movies?location=downtown` returns only movies playing at Downtown
- [ ] `GET /api/movies?location=invalid` returns 422
- [ ] `GET /api/calendar/events?location=uptown` returns events with `location_id` matching Uptown OR `location_id IS NULL` (venue-agnostic)
- [ ] `make test-backend` passes

---

## Task 4: `featured_slides` table and model

Create the schema and model for the admin-curated home hero carousel.

**Files:**
- New: `backend/database/migrations/2026_05_02_000000_create_featured_slides_table.php`
- New: `backend/app/Models/FeaturedSlide.php`
- New: `backend/database/factories/FeaturedSlideFactory.php`
- New: `backend/database/seeders/FeaturedSlideSeeder.php` (3-4 sample slides for dev)
- Modify: `backend/database/seeders/DatabaseSeeder.php` — call the new seeder

**Migration columns:**

```php
Schema::create('featured_slides', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('headline');                 // 1-80 chars (validated at write)
    $table->string('sub_headline')->nullable(); // ≤ 160 chars
    $table->string('image_url');                 // public URL; uploaded via Filament `disk('public')`
    $table->string('cta_label');                 // ≤ 24 chars
    $table->string('cta_href');                  // URL or internal route path
    $table->unsignedInteger('display_order')->default(0);
    $table->timestamp('starts_at')->nullable();  // null = no lower bound
    $table->timestamp('ends_at')->nullable();    // null = no upper bound
    $table->timestamp('published_at')->nullable(); // null = draft
    $table->timestamps();
    $table->index(['published_at', 'starts_at', 'ends_at', 'display_order'], 'featured_slides_active_idx');
});
```

**Model concerns:**
- `protected $casts` for the timestamps.
- `protected $keyType = 'string'` and `public $incrementing = false` (UUID PK).
- A `scopeActive` query scope: `whereNotNull('published_at')->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))`.
- Image upload handled in the admin via Filament `FileUpload`; the model just stores the URL.

**Acceptance Criteria:**
- [ ] Migration runs cleanly via `make migrate` from a fresh DB
- [ ] Factory produces valid rows with all field constraints respected
- [ ] Seeder inserts 3-4 sample slides on `make fresh`
- [ ] `scopeActive` is covered by a Pest test (slides outside the publish window are excluded)

---

## Task 5: Public featured-slides endpoint

Expose the active slides to the frontend.

**Files:**
- New: `backend/app/Http/Controllers/Api/FeaturedSlideController.php`
- New: `backend/app/Http/Resources/FeaturedSlideResource.php`
- Modify: `backend/routes/api.php` — register `GET /api/featured-slides`
- New: `backend/tests/Feature/FeaturedSlideApiTest.php`

**Endpoint contract:**

```
GET /api/featured-slides

Response 200:
{
  "data": [
    {
      "id": "01HZ...",
      "headline": "Festival Week: Final Cut Selects",
      "sub_headline": "Six restored prints, one weekend.",
      "image_url": "https://.../uploads/festival-2026.jpg",
      "cta_label": "See the lineup",
      "cta_href": "/events/final-cut-selects-2026"
    }
  ]
}
```

**Implementation notes:**
- Query: `FeaturedSlide::active()->orderBy('display_order')->orderBy('id')->get()`.
- Cache 5 min in Redis with a key versioned by `FeaturedSlide` table mtime (or invalidated by an observer on save/delete).
- Empty array is a valid response — the frontend renders a hardcoded brand fallback slide. Never return a fallback from the backend.

**Acceptance Criteria:**
- [ ] Pest test seeds a mix of published / draft / scheduled / expired slides and asserts only currently-active ones return
- [ ] Pest test asserts ordering by `display_order ASC, id ASC`
- [ ] Endpoint is public (no auth)
- [ ] Cache invalidates on `FeaturedSlide` save/delete
- [ ] `make test-backend` passes

---

## Task 6: Locations endpoint shape verification (and `hours` column if missing)

The frontend `/locations` and `/locations/:slug` pages need every venue field. Verify the existing `GET /api/locations` returns them; add an `hours` JSON column to `locations` if it's not already there (the customer `LocationResource` collapses some fields for the wire contract — this task ensures the public payload exposes what the new pages need).

**Files (worst case if `hours` is missing):**
- Modify: `backend/database/migrations/2026_04_04_200000_create_locations_table.php` — add `$table->json('hours')->nullable();` after `longitude` (pre-launch in-place edit)
- Modify: `backend/app/Models/Location.php` — add `'hours' => 'array'` cast
- Modify: `backend/app/Http/Resources/LocationResource.php` — expose `hours`, `phone`, `email`, structured address fields, `latitude`, `longitude`, `timezone`
- Modify: `backend/database/factories/LocationFactory.php` — supply realistic default hours
- Modify: `backend/database/seeders/LocationSeeder.php` (or DatabaseSeeder) — seed hours for both venues

**Hours JSON shape:**

```json
{
  "monday":    { "open": "11:00", "close": "23:00" },
  "tuesday":   { "open": "11:00", "close": "23:00" },
  "wednesday": { "open": "11:00", "close": "23:00" },
  "thursday":  { "open": "11:00", "close": "23:00" },
  "friday":    { "open": "11:00", "close": "00:30" },
  "saturday":  { "open": "10:00", "close": "00:30" },
  "sunday":    { "open": "10:00", "close": "23:00" }
}
```

Closed days use `null` for the day key. Hours are local to the venue's timezone (the `timezone` column is the resolver).

**Acceptance Criteria:**
- [ ] `GET /api/locations` response includes: `slug`, `name`, `phone`, `email`, `street`, `city`, `state`, `postal_code`, `country`, `timezone`, `latitude`, `longitude`, `hours`
- [ ] `GET /api/locations/{slug}` returns the same shape for a single venue
- [ ] Pest test snapshot covers the response shape for both endpoints
- [ ] If `hours` was newly added: factories and seeders provide valid hours for every seeded venue
- [ ] `make test-backend` passes

---

## Task 7: Optional sitemap data endpoint

The frontend sitemap composes URLs from `/api/movies`, `/api/calendar/events`, and `/api/locations` directly. Skip this task unless the frontend implementation determines a dedicated endpoint is materially simpler. If added, scope is small:

**Files (only if needed):**
- New: `backend/app/Http/Controllers/Api/SitemapDataController.php`
- Modify: `backend/routes/api.php` — register `GET /api/sitemap-data`

**Endpoint contract (only if implemented):**

```
GET /api/sitemap-data

Response 200:
{
  "movies":    [{ "slug": "the-brutalist", "lastmod": "2026-04-30T..." }],
  "events":    [{ "slug": "final-cut-selects-2026", "lastmod": "..." }],
  "locations": [{ "slug": "downtown", "lastmod": "..." }]
}
```

Cache 10 min.

**Acceptance Criteria (only if implemented):**
- [ ] Pest test asserts the shape and that `lastmod` is each entity's `updated_at`

---

## Out of Scope

- Booking flow changes (booking endpoints already accept the location segment as part of the showtime ID URL contract)
- Admin-side resources for `featured_slides` and per-location menu availability — see `docs/plans/admin/features/2026-05-02-content-curation-admin.md`
- Geolocation server-side (the frontend computes distance client-side from the lat/lng exposed in the locations endpoint)
- Multi-currency or regional pricing
