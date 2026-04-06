# Plan 06: Movie Feature Domain

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts), Plan 05 (useMovies, useShowtimes composables)
> **Unlocks:** Plan 08 (Purchase Flow — needs ShowtimeSelector linking to /purchase)

## Overview

Build the movie browsing experience: 7 domain components and 3 pages. This is the primary content discovery path — users browse movies, view details, and navigate to the purchase flow via showtime selection. The home page is also included here as it is primarily movie-focused.

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — Tier 2: Domain Components — Movie
- `docs/PAGE_SPECS.md` — Home (`/`), Movie Listings (`/movies`), Movie Detail (`/movies/:slug`)
- `docs/DATA_MODELS.md` — Movie, Genre, CastMember, Showtime interfaces

---

## Tasks

### Task 1: MovieRatingBadge

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/movie/MovieRatingBadge.vue`
  - `frontend/app/components/movie/MovieRatingBadge.stories.ts`
- **Details:**
  Composes CvBadge with accent variant. Displays TMDB score formatted to one decimal (e.g., `7.8`).

  **Props:** `rating: number` (0-10)

- **Acceptance Criteria:**
  - [ ] Renders rating to one decimal place
  - [ ] Uses CvBadge accent variant (primary-container bg, primary text)
  - [ ] Handles edge cases: 0, 10, decimal values

---

### Task 2: MovieCard

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/movie/MovieCard.vue`
  - `frontend/app/components/movie/MovieCard.stories.ts`
- **Details:**
  Movie listing card used in Ensemble grids. Composes CvCard.

  **Props:** `movie: Movie`, `showShowtimes: boolean` (default true)

  **Structure:** Poster image (2:3 aspect ratio), title (headline-sm, Noto Serif), MovieRatingBadge, genre badges (CvBadge), showtime pills or "Notify Me" CTA. Entire card links to `/movies/:slug`.

  **Design tokens:** Poster `aspect-ratio: 2/3`, `object-fit: cover`. Title: `headline-sm`, `--on-surface`. Card padding: `--space-md`.

  **Accessibility:** Entire card is a link. Poster: `alt="[Movie Title] poster"`. Showtime pills: `<time datetime="...">`.

- **Acceptance Criteria:**
  - [ ] Poster renders with 2:3 aspect ratio
  - [ ] Title, rating badge, and genre badges display
  - [ ] Showtime pills link to `/purchase/:showtimeId`
  - [ ] "Notify Me" shown when `showShowtimes` is false
  - [ ] Card links to movie detail page

---

### Task 3: MovieHero

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/movie/MovieHero.vue`
  - `frontend/app/components/movie/MovieHero.stories.ts`
- **Details:**
  Full-bleed hero with movie backdrop. Uses Wide Frame composition.

  **Props:** `movie: Movie`

  **Design tokens:**
  - Backdrop with vignette bloom gradient: radial gradient from `--primary-container` to `--surface-container-lowest`
  - Title: `display-lg`, Noto Serif, `--on-surface`
  - Tagline: `body-lg`, Newsreader, `--tertiary`
  - Hero reveal: `duration-cinematic` (700ms), `ease-enter`

  **Accessibility:** Backdrop: `aria-hidden="true"`, empty `alt=""`. Reduced motion: no reveal animation.

- **Acceptance Criteria:**
  - [ ] Full-bleed backdrop image with vignette gradient overlay
  - [ ] Title and tagline display with correct typography
  - [ ] Cinematic reveal animation on load
  - [ ] Reduced motion: content visible immediately, no animation

---

### Task 4: MovieDetail, MovieCastList, MovieTrailerEmbed

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/movie/MovieDetail.vue`
  - `frontend/app/components/movie/MovieCastList.vue`
  - `frontend/app/components/movie/MovieTrailerEmbed.vue`
  - Stories for each component
- **Details:**
  **MovieDetail:** Renders within Establishing Shot (65%). Title, tagline, synopsis, genre badges, runtime (use `formatRuntime`), rating. Includes MovieCastList and MovieTrailerEmbed.

  **MovieCastList:** Horizontally scrollable grid. Avatar: 3rem circular crop. Name: `label-lg`, `--on-surface`. Character: `label-md`, `--tertiary`. Cast limited to 12 per DATA_MODELS.md.

  **MovieTrailerEmbed:** Responsive YouTube iframe (16:9). `loading="lazy"`. `<iframe title="[Movie Title] trailer">`.

- **Acceptance Criteria:**
  - [ ] MovieDetail renders all movie fields with correct typography
  - [ ] Genre badges use CvBadge
  - [ ] Runtime formatted via `formatRuntime` utility
  - [ ] CastList scrolls horizontally with circular avatar photos
  - [ ] TrailerEmbed is responsive 16:9 with lazy loading
  - [ ] TrailerEmbed has accessible title on iframe

---

### Task 5: ShowtimeSelector

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/movie/ShowtimeSelector.vue`
  - `frontend/app/components/movie/ShowtimeSelector.stories.ts`
- **Details:**
  Date tabs and time slot buttons. Used in movie detail page (Establishing Shot right column, 35%).

  **Props:** `showtimes: Showtime[]`, `movieSlug: string`

  **Structure:**
  - Date tabs: horizontally scrollable, today highlighted. `role="tablist"`, each tab `role="tab"`, `aria-selected`.
  - Time slots: CvButton tertiary variant, link to `/purchase/:showtimeId`. `<time datetime="...">` with `aria-label` including full date and time.
  - Keyboard: arrow keys navigate dates, Tab moves to time slots.

  **Design tokens:** Active date: `--primary-container` bg, `--primary` text. Time slots: CvButton tertiary. Spacing: `--space-sm`.

- **Acceptance Criteria:**
  - [ ] Dates display as horizontal tabs with today highlighted
  - [ ] Selecting a date shows that day's time slots
  - [ ] Time slots link to `/purchase/:showtimeId`
  - [ ] Keyboard navigation with arrow keys for dates
  - [ ] Proper ARIA tab pattern (tablist, tab, aria-selected)

---

### Task 6: Home Page (`/`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/index.vue`
- **Details:**
  Per PAGE_SPECS.md. Layout: `default`. Rendering: ISR (30 min).

  **Sections:**
  1. **Hero** (Wide Frame) — Featured now-showing film via MovieHero. "Get Tickets" CTA.
  2. **Now Showing** (Ensemble grid) — MovieCards with showtime pills. The core funnel: what's playing → when → buy (single click from time chip to purchase).
  3. **What's On This Week** — Compact event preview list via EventListCard (from Plan 07). Placeholder if Plan 07 not yet built. Fetch current month's events via `useCalendarEvents().getEvents(month, year)` and filter to the current week client-side. **Cross-month edge case:** When the week spans two months (e.g., April 28 -- May 4), for v1 accept that the week view may miss events from the next month. This is a deliberate v1 trade-off -- fetch both months if accuracy is critical later.
  4. **Coming Soon** (Ensemble grid) — MovieCards with `showShowtimes=false`, "Notify Me" action.

  **Data:** `GET /api/movies?status=now_showing`, `GET /api/movies?status=coming_soon`, `GET /api/calendar/events?month=M&year=Y` — filtered to current week client-side. Note: backend only supports month/year, not range=week.

  **SEO:** Title: `Final Cut — Now Showing & Tickets`. Structured data: `ItemList` (Movie).

- **Acceptance Criteria:**
  - [ ] Hero displays featured movie with backdrop
  - [ ] Now Showing grid with showtime pills linking to purchase
  - [ ] Coming Soon grid with Notify Me buttons
  - [ ] Data fetches via useMovies composable
  - [ ] Page renders via ISR with 30-min revalidation
  - [ ] SEO meta tags set correctly

---

### Task 7: Movie Listings Page (`/movies`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/movies/index.vue`
- **Details:**
  Per PAGE_SPECS.md. Layout: `default`. Rendering: ISR (30 min).

  **Sections:**
  1. Tab bar: Now Showing / Coming Soon toggle
  2. Filter controls: genre and rating filters (URL query params: `?status=now_showing&genre=28`)
  3. Ensemble grid of MovieCards

  **URL as state:** Filters stored in query params for bookmarkability. Reads from `useRoute().query`.

  **SEO:** Title: `Now Showing — Final Cut` or `Coming Soon — Final Cut`. Structured data: Movie.

- **Acceptance Criteria:**
  - [ ] Tab toggle switches between now showing and coming soon
  - [ ] Genre filter works via URL query params
  - [ ] Ensemble grid displays MovieCards
  - [ ] Filter state is bookmarkable/shareable via URL
  - [ ] Page title updates based on active tab

---

### Task 8: Movie Detail Page (`/movies/:slug`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/movies/[slug].vue`
- **Details:**
  Per PAGE_SPECS.md. Layout: `default`. Rendering: ISR (10 min).

  **Sections:**
  1. Wide Frame hero (MovieHero with backdrop)
  2. Establishing Shot — Left (65%): MovieDetail (title, tagline, synopsis, genres, runtime, rating), MovieTrailerEmbed, MovieCastList
  3. Establishing Shot — Right (35%): ShowtimeSelector, MovieRatingBadge

  **Data:** `GET /api/movies/:slug`, `GET /api/locations/{location}/movies/{slug}/showtimes` — location from `useLocations().activeLocation`

  **SEO:** Title: `[Movie Title] — Showtimes & Tickets — Final Cut`. Structured data: `Movie`, `VideoObject` for trailer.

- **Acceptance Criteria:**
  - [ ] Hero renders with movie backdrop
  - [ ] Establishing Shot 65/35 layout on desktop
  - [ ] Collapses to single column on mobile
  - [ ] Showtime selector displays dates and times
  - [ ] Time slots link to `/purchase/:showtimeId`
  - [ ] Trailer embeds when available
  - [ ] Cast list displays up to 12 members
  - [ ] Structured data renders in HTML head

---

## Testing Requirements

- **Storybook:** Stories for all 7 components: MovieRatingBadge, MovieCard, MovieHero, MovieDetail, MovieCastList, MovieTrailerEmbed, ShowtimeSelector
  - All prop variants
  - With and without showtime data
  - Responsive states (desktop/mobile)
  - Loading states with CvSkeletonLoader
- **E2E Tests:**
  - Navigate from Home → Movie Listings → Movie Detail
  - Verify showtime links navigate to `/purchase/:showtimeId`
  - Genre filter updates URL and grid content
  - Tab toggle switches movie list
- **SEO Verification:**
  - Structured data in page source for home and movie detail
  - Meta tags (title, description, OG) render correctly

## Dependencies Map

```
Task 1 (MovieRatingBadge) ← uses CvBadge
Task 2 (MovieCard) ← uses CvCard, MovieRatingBadge, CvBadge
Task 3 (MovieHero) ← uses Wide Frame CSS
Task 4 (MovieDetail, CastList, TrailerEmbed) ← uses MovieRatingBadge, CvBadge
Task 5 (ShowtimeSelector) ← uses CvButton
Task 6 (Home Page) ← uses Tasks 2, 3 + EventListCard (Plan 07, optional)
Task 7 (Listings Page) ← uses Task 2
Task 8 (Detail Page) ← uses Tasks 3, 4, 5
```

## Risks & Open Questions

1. **TMDB image loading** — Poster and backdrop images come from TMDB CDN. Consider adding blur placeholder (`placeholder` prop on `<NuxtImg>`) or skeleton loaders during load.
2. **Featured movie selection** — Home page hero needs to pick a "featured" now-showing movie. Logic: most recent release with a backdrop, or first in the list. Could be manual selection later via admin.
3. **EventListCard dependency** — Home page "What's On This Week" section uses EventListCard from Plan 07. If building Plan 06 before Plan 07, use a placeholder component or skip the section initially.
4. **Showtime grouping** — ShowtimeSelector needs to group showtimes by date. The API returns a flat array; grouping logic should live in the component or a utility function.
