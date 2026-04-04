# Plan 03: Movie & Showtime API

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 02 (Movie, Showtime, Auditorium, Seat models)
> **Unlocks:** Plan 04 (Booking API needs showtime/seat data)

## Overview

Implement the movie and showtime API endpoints with TMDB integration. The movie endpoints proxy TMDB data and merge it with local showtime information. The showtime endpoint returns seat maps with real-time availability for the purchase flow.

## Reference Documents

- `docs/DATA_MODELS.md` — Sections 2 (API routes) and 3 (TMDB integration)
- `docs/SITE_ARCHITECTURE.md` — BFF pattern, caching strategy

---

## Tasks

### Task 1: TMDB Service

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/TmdbService.php`
- **Details:**
  HTTP client wrapping TMDB API v3. Uses `config('services.tmdb')` for configuration.

  **Methods:**
  - `nowPlaying(page, region)` — `/movie/now_playing`
  - `upcoming(page, region)` — `/movie/upcoming`
  - `movieDetail(id)` — `/movie/{id}`
  - `movieCredits(id)` — `/movie/{id}/credits`
  - `movieVideos(id)` — `/movie/{id}/videos`

  **Transform function:** `tmdbToMovie(detail, credits, videos)` per DATA_MODELS.md Section 3:
  - Cast limited to 12
  - Trailer extracted (type=Trailer, site=YouTube)
  - Image URLs built with correct size prefixes (poster: w500, backdrop: w1280, profile: w185)
  - Slug generated from title

  **Caching:** Results cached using Laravel Cache:
  - Movie list: 30 minutes
  - Movie detail: 1 hour

- **Acceptance Criteria:**
  - [ ] All TMDB endpoints callable
  - [ ] Transform function matches DATA_MODELS.md exactly
  - [ ] Caching works with configurable TTL
  - [ ] Graceful error handling when TMDB is unavailable
  - [ ] Works without API key in test mode (returns empty arrays)

---

### Task 2: MovieController

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Http/Controllers/Api/MovieController.php`
  - `backend/app/Http/Resources/MovieResource.php`
  - `backend/app/Http/Resources/ShowtimeResource.php`
- **Details:**
  **`index` — GET `/api/movies`:**
  - Query params: `status` (now_showing|coming_soon), `genre`, `page`
  - Fetch from TMDB (now_playing or upcoming)
  - Merge with local showtimes by TMDB movie ID
  - Return: `{ data: Movie[], meta: { total, page } }`

  **`show` — GET `/api/movies/{slug}`:**
  - Look up TMDB movie ID from local slug mapping or slug match
  - Fetch TMDB detail + credits + videos
  - Transform via `tmdbToMovie`
  - Return: `{ data: Movie }`

  **`showtimes` — GET `/api/movies/{slug}/showtimes`:**
  - Query params: `date` (YYYY-MM-DD, defaults to today)
  - Fetch local showtimes for this movie, filtered by date
  - Return: `{ data: Showtime[] }`

- **Acceptance Criteria:**
  - [ ] Status filter returns correct movie lists
  - [ ] Genre filter works
  - [ ] Movie detail returns full data with cast and trailer
  - [ ] Showtimes filtered by date
  - [ ] API resources format output consistently

---

### Task 3: ShowtimeController

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Http/Controllers/Api/ShowtimeController.php`
  - `backend/app/Http/Resources/SeatResource.php`
  - `backend/app/Http/Resources/AuditoriumResource.php`
- **Details:**
  **`show` — GET `/api/showtimes/{id}`:**
  - Return showtime details + full auditorium layout + seat map with current availability
  - Seat availability determined by: seat exists and has no confirmed booking for this showtime
  - Return: `{ data: Showtime & { auditorium: Auditorium, seats: Seat[] } }`

  This is the entry point for the seat selection page. The response must include every seat with its current status (available, taken, held).

  **Seat status logic:**
  - `available`: seat has no BookingSeat record for this showtime
  - `taken`: seat has a BookingSeat record with a confirmed booking
  - `held`: reserved for future use (server-side holds, not in MVP)

- **Acceptance Criteria:**
  - [ ] Returns showtime with full auditorium and seat map
  - [ ] Seat availability reflects current bookings
  - [ ] Response shape matches frontend Showtime + Auditorium + Seat[] interface
  - [ ] Performance: seat map query executes in <100ms

---

### Task 4: Slug-to-TMDB-ID Mapping

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Services/SlugMappingService.php` (or method on TmdbService)
- **Details:**
  The frontend uses slugs in URLs, but TMDB uses integer IDs. Need a mapping strategy:

  **Option A (Recommended):** Maintain local `movies` table (from Plan 02) with TMDB ID as primary key and slug. When a slug is requested, look up the TMDB ID from the local table.

  **Option B:** Slugify TMDB movie titles on-the-fly and match. Less reliable (title changes, duplicates).

  Since movies are already seeded in the local database (Plan 02), Option A is the natural fit.

- **Acceptance Criteria:**
  - [ ] Slug resolves to correct TMDB ID
  - [ ] 404 returned for unknown slugs
  - [ ] No ambiguity in slug-to-ID mapping

---

## Testing Requirements

- **Pest Feature Tests:**
  - `GET /api/movies` — returns movie list, respects status filter, genre filter
  - `GET /api/movies/{slug}` — returns movie detail, 404 for invalid slug
  - `GET /api/movies/{slug}/showtimes` — returns showtimes, date filter works
  - `GET /api/showtimes/{id}` — returns seat map with correct availability
  - Seat availability: create a booking for a showtime, verify those seats show as "taken"
- **Unit Tests:**
  - `TmdbService::tmdbToMovie` transform function
  - Slug mapping resolution
- **HTTP Fake:** Use Laravel's `Http::fake()` to mock TMDB responses in tests

## Dependencies Map

```
Task 1 (TmdbService) ← independent
Task 4 (Slug Mapping) ← uses Movie model from Plan 02
Task 2 (MovieController) ← uses Task 1, Task 4
Task 3 (ShowtimeController) ← uses Showtime, Auditorium, Seat models from Plan 02
```

## Risks & Open Questions

1. **TMDB rate limits** — TMDB allows ~40 requests/10 seconds. Caching mitigates this, but initial seeding may hit limits. Add retry logic with exponential backoff.
2. **Seat availability performance** — For a large auditorium (300 seats), computing availability requires joining showtimes, bookings, and seats. Optimize with a single query using left joins. Consider denormalizing seat status on the showtime level if queries become slow.
3. **TMDB movie IDs as PKs** — Using TMDB IDs as the movie primary key ties us to TMDB. Acceptable for MVP since TMDB IDs are stable, but consider adding a local auto-increment ID if needed later.
