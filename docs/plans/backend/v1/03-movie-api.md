# Plan 03: Movie & Showtime API

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 02 (Movie, Showtime, Auditorium, Seat models)
> **Unlocks:** Plan 04 (Booking API needs showtime/seat data)

## Overview

Implement the movie and showtime API endpoints. The theatre owns its movie catalog — movies are local records in our database. TMDB is an optional enrichment service used to populate metadata (poster, backdrop, cast, trailer, synopsis) via a background artisan command, never in the user-facing request path.

### Architecture: Theatre-Owned Catalog

- **Movies table** uses auto-increment PK. TMDB ID stored as an optional `tmdb_id` reference column.
- **All movie data served from local DB** — no external API calls during HTTP requests.
- **TMDB enrichment** happens via `php artisan movies:enrich`, scheduled hourly. Fetches detail + credits + videos from TMDB, updates local fields, sets `tmdb_enriched_at` timestamp.
- **Graceful degradation** — if TMDB is down, local data is preserved. Negative caching (5-minute sentinel) prevents stampeding a failed API.

## Reference Documents

- `docs/DATA_MODELS.md` — Sections 2 (API routes) and 3 (TMDB integration)
- `docs/SITE_ARCHITECTURE.md` — Frontend-backend architecture, caching strategy

---

## Tasks

### Task 1: TmdbService (Enrichment Only)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/TmdbService.php`
- **Details:**
  HTTP client wrapping TMDB API v3 for background enrichment only. Uses `config('services.tmdb')` for configuration.

  **Methods:**
  - `fetchEnrichmentData(int $tmdbId): ?array` — fetches movie detail + credits + videos from TMDB, transforms to app format. Cached for 24 hours. Returns null on failure.
  - `enrichMovie(Movie $movie): bool` — high-level method that fetches TMDB data and updates the local model. Only updates fields where TMDB returned non-empty values (preserves local data on partial failure). Sets `tmdb_enriched_at` to now.

  **Transform function:** `tmdbToMovie(detail, credits, videos)` per DATA_MODELS.md Section 3:
  - Cast limited to 12
  - Trailer extracted (type=Trailer, site=YouTube)
  - Image URLs built with correct size prefixes (poster: w500, backdrop: w1280, profile: w185)
  - Slug generated from title

  **Resilience:**
  - HTTP connect timeout: 3s, read timeout: 5s
  - Bounded retries: 2 attempts with 500ms delay
  - Negative caching: 5-minute sentinel on failure prevents repeated calls to a down API
  - Redis cache for TMDB responses (24-hour TTL)

- **Acceptance Criteria:**
  - [x] Enrichment fetches detail + credits + videos
  - [x] Transform function matches DATA_MODELS.md exactly
  - [x] Caching works (24-hour TTL)
  - [x] Negative caching prevents stampede on failure
  - [x] Graceful error handling when TMDB is unavailable
  - [x] Works without API key (returns null)
  - [x] `enrichMovie()` preserves local data when TMDB is down

---

### Task 2: MovieController (Local DB Only)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Http/Controllers/Api/MovieController.php`
  - `backend/app/Http/Resources/MovieResource.php`
  - `backend/app/Http/Resources/MovieListResource.php`
  - `backend/app/Http/Resources/ShowtimeResource.php`
- **Details:**
  All endpoints serve data exclusively from the local database. No TMDB calls in the request path.

  **`index` — GET `/api/movies`:**
  - Query params: `status` (now_showing|coming_soon), `per_page`, `page`
  - Query local movies table filtered by status
  - Return: `{ data: Movie[], meta: { total, page, per_page } }`

  **`show` — GET `/api/movies/{slug}`:**
  - Look up movie by slug in local DB
  - Return: `{ data: Movie }` (includes persisted cast, trailer_key, all metadata)
  - No TMDB dependency — all data served from local columns

  **`showtimes` — GET `/api/locations/{location}/movies/{slug}/showtimes`:**
  - Query params: `date` (YYYY-MM-DD, defaults to today)
  - Fetch local showtimes for this movie at the given location, filtered by date
  - Return: `{ data: Showtime[] }`

- **Acceptance Criteria:**
  - [x] Status filter returns correct movie lists
  - [x] Movie detail returns full data with locally persisted cast and trailer
  - [x] Returns empty cast array when cast is null (not enriched yet)
  - [x] Showtimes filtered by date
  - [x] API resources format output consistently
  - [x] No TMDB dependency — controller has no TmdbService injection

---

### Task 3: ShowtimeController

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Http/Controllers/Api/ShowtimeController.php`
  - `backend/app/Http/Resources/SeatResource.php`
  - `backend/app/Http/Resources/AuditoriumResource.php`
- **Details:**
  **`show` — GET `/api/locations/{location}/showtimes/{id}`:**
  - Return showtime details + full auditorium layout + seat map with current availability
  - Seat availability determined by: seat has no confirmed booking for this showtime
  - Return: `{ data: { showtime, auditorium, seats } }`

  **Seat status logic:**
  - `available`: seat has no BookingSeat record with a confirmed booking for this showtime
  - `taken`: seat has a BookingSeat record with a confirmed booking
  - `held`: reserved for future use (server-side holds, not in MVP)

  **Booking seat constraint:**
  - `booking_seats` table uses a regular index (not unique constraint) on `(showtime_id, seat_id)`
  - This allows cancelled booking rows to coexist with new bookings for the same seat
  - Uniqueness for active bookings enforced at the application level via DB transactions

- **Acceptance Criteria:**
  - [x] Returns showtime with full auditorium and seat map
  - [x] Seat availability reflects current confirmed bookings only
  - [x] Cancelled bookings do not block seats
  - [x] Cancelled booking's seat can be rebooked
  - [x] Response shape matches frontend Showtime + Auditorium + Seat[] interface
  - [x] Performance: 300-seat auditorium with 50 bookings works efficiently

---

### Task 4: Enrichment Artisan Command

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Console/Commands/EnrichMoviesCommand.php`
  - `backend/routes/console.php`
- **Details:**
  **Signature:** `movies:enrich {--movie= : Enrich a specific movie by slug} {--force : Re-enrich regardless of staleness} {--stale-hours=24 : Hours before data is considered stale}`

  **Default behavior:** Enrich all movies where `tmdb_id IS NOT NULL` and data is stale (enriched > stale-hours ago or never enriched).

  **Rate limiting:** 200ms delay between TMDB API calls to respect rate limits (~40 req/10s).

  **Scheduling:** Registered to run hourly in `routes/console.php`.

- **Acceptance Criteria:**
  - [x] Enriches movies with stale or null `tmdb_enriched_at`
  - [x] Skips movies without `tmdb_id`
  - [x] `--movie` flag enriches a single movie by slug
  - [x] `--force` flag re-enriches regardless of staleness
  - [x] Handles TMDB failures gracefully without crashing
  - [x] Scheduled to run hourly

---

## Testing Requirements

- **Pest Feature Tests:**
  - `GET /api/movies` — returns movie list, respects status filter, pagination
  - `GET /api/movies/{slug}` — returns movie detail with persisted cast, 404 for invalid slug, empty cast when null
  - `GET /api/locations/{location}/movies/{slug}/showtimes` — returns showtimes, date filter works
  - `GET /api/locations/{location}/showtimes/{id}` — returns seat map with correct availability
  - Seat availability: confirmed bookings mark seats taken, cancelled bookings do not
  - Cancelled booking seats can be rebooked (no unique constraint violation)
  - `movies:enrich` command: stale enrichment, skip no tmdb_id, failure handling, --movie, --force
- **Unit Tests:**
  - `TmdbService::tmdbToMovie` transform function (cast limit, trailer extraction, image URLs, missing data)
  - `TmdbService::fetchEnrichmentData` (caching, negative caching, failure handling)
  - `TmdbService::enrichMovie` (updates model, preserves data on failure, returns false for null tmdb_id)
  - Movie model: auto-increment PK, cast/tmdb_enriched_at casts, null tmdb_id
- **HTTP Fake:** Used in TmdbService and enrichment command tests only — MovieController tests have no TMDB mocking

## Dependencies Map

```
Task 1 (TmdbService) ← independent
Task 2 (MovieController) ← uses Movie model from Plan 02
Task 3 (ShowtimeController) ← uses Showtime, Auditorium, Seat models from Plan 02
Task 4 (Enrichment Command) ← uses Task 1
```

## Resolved Risks

1. **TMDB as critical path (RESOLVED)** — TMDB is no longer in the request path. All movie data served from local DB. Enrichment happens in the background.
2. **TMDB rate limits (MITIGATED)** — 200ms delay between enrichment calls + 24-hour cache TTL + negative caching on failure.
3. **TMDB brownouts (RESOLVED)** — Connect timeout 3s, read timeout 5s, 2 retries, negative caching sentinel for 5 minutes. Local data preserved on failure.
4. **TMDB movie IDs as PKs (RESOLVED)** — Movies now use auto-increment PK. TMDB ID stored as optional `tmdb_id` column. Theatre owns its catalog.
5. **Booking seat constraint (RESOLVED)** — Replaced unique constraint with regular index. Cancelled bookings no longer block rebooking.
