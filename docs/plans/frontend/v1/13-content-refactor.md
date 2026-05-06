# Plan 13: Content Page Refactor — Static-First, Cross-Location

> **Priority:** Should Have
> **Complexity:** L
> **Depends On:** 06 (Movie Domain), 10 (Content Domain), and the backend feature plan `2026-05-02-cross-location-content-api.md` for the new endpoints
> **Unlocks:** Local-SEO surface (`/locations`), editor-curated home hero, cross-location food page, geolocation-aware defaults

## Overview

Content surfaces (home, movies, food, events, locations) currently treat physical location as a global runtime selector via `useLocations.activeLocation` (set at app boot, persisted in localStorage, read by `useFoodMenu` and the movie-detail showtime selector). This plan flips the model: every content page becomes a cross-location, server-rendered surface, and location becomes a property of the booking *intent* — surfaced explicitly via a new `BookingLocationBanner` on the seat-selection page, dimmed item warnings on the checkout food panel, and an opt-in geolocation enhancement that reorders defaults without gating content.

In addition, this plan ships three new editorial concepts:

- **Featured Hero Carousel** on the home page, sourced from the new admin-curated `featured_slides` resource.
- **Shared cross-location food menu** with per-item availability arrays (`available_at: string[]`) so a single menu page can render the full slate and the checkout can dim items not stocked at the booking's venue.
- **`/locations` and `/locations/:slug`** as first-class crawlable content pages with `LocalBusiness` JSON-LD.

At the end of this plan, `useLocations.activeLocation` is read by exactly two surfaces: the booking-time location picker (as the default highlight when geolocation is unavailable) and a small `LocationPreferenceSwitcher` UI that exists only to write the preference. Every other content page renders the full slate at SSR time with no location dependency.

## Reference Documents

- `docs/architecture/CONTENT_ARCHITECTURE.md` — the architectural spec this plan implements
- `docs/architecture/SITE_ARCHITECTURE.md` — route map, composables table, sitemap section
- `docs/specs/PAGE_SPECS.md` — per-page section specs and data requirements
- `docs/plans/backend/features/2026-05-02-cross-location-content-api.md` — backend endpoints this plan consumes
- `docs/plans/admin/features/2026-05-02-content-curation-admin.md` — admin work that produces `featured_slides` and per-location menu availability
- `frontend/app/composables/useLocations.ts` — current full implementation; scope is reduced here
- `frontend/app/composables/useFoodMenu.ts` — refactor target
- `frontend/app/composables/useShowtimes.ts` — refactor target
- `frontend/nuxt.config.ts` — `routeRules` block to extend
- `frontend/app/plugins/locations.ts` — boot-time fetch; will be split

---

## Tasks

### Task 1: Add `/locations` and `/locations/:slug` pages

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/locations/index.vue` (new)
  - `frontend/app/pages/locations/[slug].vue` (new)
  - `frontend/app/composables/usePublicLocations.ts` (new)
  - `frontend/app/components/content/LocationCard.vue` (new)
  - `frontend/app/components/content/LocationDetailPanel.vue` (new)
  - `frontend/app/components/content/LocationHero.vue` (new)
  - `frontend/app/types/location.ts` (modify — add hours field if backend Plan #5 added it)
  - `frontend/nuxt.config.ts` (modify — add `'/locations': { isr: 1800 }, '/locations/**': { isr: 1800 }`)
  - `frontend/app/components/layout/SiteFooter.vue` (modify — add a "Visit" link to `/locations`)
- **Details:**
  - `usePublicLocations()` is an SSR-friendly wrapper over `GET /api/locations` and `GET /api/locations/:slug`. Returns refs only. Distinct from `useLocations` (which owns the activeLocation preference).
  - `/locations` renders a Wide Frame hero + Ensemble grid of `LocationCard` components, alphabetical at SSR. After hydration, if `useGeolocation.status === 'granted'`, sort by distance and add "X mi away" caption to each card.
  - `/locations/:slug` renders Establishing Shot 65/35: left = address/hours/phone/email/accessibility/transit/parking/embedded-map; right = "Now Showing Here" strip + "Upcoming Events Here" strip. Cross-references `/api/movies?location=:slug` and `/api/calendar/events?location=:slug` (or client-side filter if the events endpoint doesn't yet support `?location=`).
  - Both pages emit per-page meta and JSON-LD. `/locations/:slug` emits a full `LocalBusiness` schema with `address` (`PostalAddress`), `geo` (`GeoCoordinates`), `openingHoursSpecification`, `telephone`, `email`, `image`, `priceRange`, `url`.
  - Map links: build `https://maps.google.com/?q=<lat>,<lng>` client-side from the locations payload. No env var, no API key.
- **Acceptance Criteria:**
  - [ ] `/locations` renders SSR with both venues alphabetical and a "Get Directions" link per card
  - [ ] `/locations/:slug` renders SSR with full venue payload and emits valid `LocalBusiness` JSON-LD (verified via a snapshot test or `schema.org` validator)
  - [ ] After granting geolocation in the browser, `/locations` reorders cards by distance and displays "X mi away" captions; SSR output is unchanged (alphabetical, no captions)
  - [ ] SiteFooter links to `/locations`
  - [ ] `routeRules` includes `'/locations'` and `'/locations/**'` ISR entries
  - [ ] Vitest covers `usePublicLocations` (fetch, by-slug, error path)
  - [ ] Playwright e2e renders `/locations` and `/locations/:slug` and asserts visible address content

---

### Task 2: Demote `useLocations.activeLocation`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useLocations.ts` (modify — keep state, narrow consumers)
  - `frontend/app/plugins/locations.ts` (modify — split: catalog fetch stays for `usePublicLocations`; activeLocation rehydration becomes lazy)
  - `frontend/app/composables/useFoodMenu.ts` (modify — strip activeLocation watcher; see Task 7)
  - `frontend/app/pages/movies/[slug].vue` (modify — strip activeLocation usage; see Task 6)
  - `frontend/app/components/layout/SiteHeader.vue` (modify — replace inline switcher)
  - `frontend/app/components/layout/LocationPreferenceSwitcher.vue` (new) OR remove the switcher from the header entirely (decision: header gets a "Find a cinema" link to `/locations` instead — simpler and removes the localStorage dependency from the global chrome)
  - `frontend/app/components/movie/ShowtimeSelector.vue` (modify — see Task 6)
- **Details:**
  - `useLocations` retains `locations`, `activeLocation`, `setLocation`, `fetchLocations`, `initializeLocations` exactly as today — but the rehydration on app boot is removed from `plugins/locations.ts`. The plugin still hydrates the catalog (so `usePublicLocations` and the booking flow have it), but no longer touches localStorage at boot.
  - `setLocation` and the localStorage write happen lazily — only when the user explicitly picks a venue (in the booking-time picker or the future preference switcher).
  - SiteHeader: drop the `<select>` switcher. Replace with a "Find a cinema" link to `/locations`. This removes location-as-global-state from the persistent chrome and aligns with the location-at-intent pattern.
- **Acceptance Criteria:**
  - [ ] `grep -r "activeLocation" frontend/app/` returns hits only in: `useLocations.ts`, the booking flow files (Tasks 6, 8, 9), and the (future) preference switcher
  - [ ] Loading any content page with `localStorage.clear()` in the browser console does not produce a network request to `/api/locations/{slug}/...`
  - [ ] SiteHeader no longer contains a location `<select>` in either desktop or mobile templates
  - [ ] Existing component tests for SiteHeader pass (with the switcher assertion removed/replaced)

---

### Task 3: Add `useGeolocation` composable

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useGeolocation.ts` (new)
  - `frontend/tests/composables/useGeolocation.test.ts` (new)
- **Details:**
  - Browser Geolocation API wrapper:

    ```ts
    type GeolocationStatus = 'idle' | 'prompting' | 'granted' | 'denied' | 'unsupported'

    function useGeolocation() {
      const status: Ref<GeolocationStatus>
      const coords: Ref<{ lat: number; lng: number } | null>
      function request(): Promise<void>
      function distanceTo(loc: { latitude: number | null; longitude: number | null }): number  // miles; Infinity if no coords or location lacks lat/lng
      return { status, coords, request, distanceTo }
    }
    ```

  - SSR returns `status: 'idle'` and `coords: null` always (no `navigator` on the server).
  - On client mount: read `sessionStorage['geolocation:coords']`. If present and not stale (< 1 hour), set `coords` and `status: 'granted'`. Otherwise `idle`.
  - `request()` calls `navigator.geolocation.getCurrentPosition` with a 5s timeout and `enableHighAccuracy: false`. On success: set `coords`, `status: 'granted'`, write `sessionStorage`. On error: set `status: 'denied'` (or `'unsupported'` if `navigator.geolocation` is missing).
  - `distanceTo` uses Haversine; returns miles (US default).
- **Acceptance Criteria:**
  - [ ] Vitest covers `granted`, `denied`, `unsupported`, and `sessionStorage`-cached paths with mocked `navigator.geolocation`
  - [ ] Vitest covers `distanceTo` against a known fixture (Downtown vs. Uptown) within 0.1 mile of the expected value
  - [ ] SSR-rendered output of any consuming component shows the `idle` fallback (asserted via `@nuxt/test-utils` SSR snapshot)
  - [ ] No call to `navigator.geolocation` happens before user gesture or before the consuming component is mounted

---

### Task 4: Add Featured Hero Carousel to home

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/components/home/HomeFeaturedCarousel.vue` (new)
  - `frontend/app/composables/useFeaturedSlides.ts` (new)
  - `frontend/app/types/featured-slide.ts` (new)
  - `frontend/app/pages/index.vue` (modify — replace existing hero with carousel)
  - `frontend/tests/components/home/HomeFeaturedCarousel.spec.ts` (new)
  - `frontend/tests/composables/useFeaturedSlides.test.ts` (new)
- **Details:**
  - `useFeaturedSlides()` calls `GET /api/featured-slides` (returns `{ data: FeaturedSlide[] }` of currently active, in-window, ordered slides).
  - `HomeFeaturedCarousel` consumes the array. Auto-advances every 7s, pauses on hover/focus and when `document.hidden`, supports prev/next buttons and dot indicators, swipe via `scroll-snap-type: x mandatory` on the slide container.
  - Transition: crossfade at `duration-emphasis` (400ms) `ease-standard`.
  - **Empty-state fallback:** when the API returns zero slides, render a single hardcoded brand slide (poster collage image + tagline + CTA → `/movies`). The home page never renders an empty hero.
  - Reduced motion: auto-advance disabled, all slides stacked statically with the first visible; indicators repurposed as nav buttons.
  - Accessibility per WAI-ARIA Carousel pattern: `role="region"`, `aria-roledescription="carousel"`, each panel `role="group" aria-roledescription="slide"`, `aria-live="polite"` while paused / `"off"` while auto-advancing, prev/next labelled, dots labelled "Go to slide N".
- **Acceptance Criteria:**
  - [ ] Carousel auto-advances every 7s in a Playwright headed test; pauses on hover
  - [ ] Empty API response renders the hardcoded brand slide (verified with a Vitest mocked `useFeaturedSlides` returning `[]`)
  - [ ] `prefers-reduced-motion: reduce` disables auto-advance (Playwright with the media-emulation flag)
  - [ ] Keyboard: Tab focuses prev, next, then each dot in order; Enter on a dot jumps to that slide
  - [ ] Screen-reader smoke test: `aria-live` toggles correctly between auto and paused

---

### Task 5: Movies index `?location=` filter

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/movies/index.vue` (modify)
  - `frontend/app/components/movie/LocationFilterChips.vue` (new)
  - `frontend/app/composables/useMovies.ts` (modify — accept optional `location` param)
  - `frontend/tests/pages/movies/index.spec.ts` (modify or new)
- **Details:**
  - URL query string is the source of truth. `useFetch` key includes the `?location=` value so each filtered URL is independently ISR-cacheable.
  - "All Locations" chip is the SSR default (no `?location=`).
  - Post-hydration, if `useGeolocation` resolves with `granted` and the user has not already filtered, surface a non-binding "Filter to nearest: {Location Name}" suggestion chip below the row. A click on the chip applies the filter (writes `?location=...`).
  - Chips emit `aria-pressed` and use the `secondary` (gold) accent for the active state.
- **Acceptance Criteria:**
  - [ ] `/movies` (no query) SSRs with all movies; `/movies?location=downtown` SSRs with only movies that have a showtime at Downtown
  - [ ] Each filter URL is hit independently — verifying ISR cache separation by changing `?location=` and asserting a fresh fetch
  - [ ] Geolocation-driven suggestion chip appears only after `granted` and only when no manual filter is set; clicking it applies the filter
  - [ ] Vitest covers chip render and click → query write

---

### Task 6: Cross-location showtimes on movie detail

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/composables/useShowtimes.ts` (modify — add `fetchByMovie(slug)` cross-location method against the new endpoint; keep `fetchSeatMap(location, id)` for the booking flow)
  - `frontend/app/components/movie/ShowtimeSelector.vue` (modify — group by location, default-expand closest when geolocation granted)
  - `frontend/app/pages/movies/[slug].vue` (modify — strip `activeLocation` usage; pass cross-location showtimes to selector)
  - `frontend/app/types/showtime.ts` (modify — `Showtime.location` becomes a populated `{ slug, name, latitude?, longitude? }` payload, not just `screenId`)
  - `frontend/tests/composables/useShowtimes.test.ts` (modify — replace location-scoped URL assertion with cross-location URL assertion; add geolocation-default-expand test against the component)
  - `frontend/tests/components/movie/ShowtimeSelector.spec.ts` (new or modify)
- **Details:**
  - Each time button still routes to `/purchase/{showtimeId}` — the showtime ID encodes the location, so no intermediate confirmation is required at click time. The `BookingLocationBanner` (Task 8) is where the user sees the venue commitment.
  - Group rendering: one heading per venue (alphabetical when no geolocation, closest-first when granted), with the time-slot buttons listed beneath.
  - Default-expanded: closest group expanded, others collapsed-but-visible (header visible with one-click expand) — only when `useGeolocation.status === 'granted'`. Without geolocation, every group renders expanded.
  - Distance caption per group when `granted` ("Downtown · 2.3 mi away").
  - Empty states: zero showtimes anywhere → "Showtimes coming soon" + "Notify Me"; showtimes at one venue but not another → only the venue with showtimes renders.
- **Acceptance Criteria:**
  - [ ] `/movies/:slug` SSR renders showtimes from both venues with venue headings alphabetical, all expanded
  - [ ] After granting geolocation, the closest venue's group is expanded and others collapse (Playwright with mocked geolocation)
  - [ ] Each time button's `href` points at `/purchase/{showtimeId}` and routing succeeds end-to-end against a seeded showtime
  - [ ] `useShowtimes.test.ts` no longer asserts the per-location URL on the public path (the per-location URL assertion remains for `fetchSeatMap`)

---

### Task 7: Shared food menu

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useFoodMenu.ts` (modify — call `/api/food-menu`, drop activeLocation watcher, return `available_at` arrays)
  - `frontend/app/types/menu-item.ts` (modify — add `available_at: string[]`)
  - `frontend/app/pages/food-drink.vue` (modify — render every item with availability captions)
  - `frontend/app/components/content/MenuItem.vue` (modify — add availability caption)
  - `frontend/tests/composables/useFoodMenu.test.ts` (modify — drop location-scoped URL assertion, add availability-array assertion)
- **Details:**
  - `useFoodMenu()` becomes a single-shot fetch of `/api/food-menu`. No watcher, no fallback to static menu data (the API is now authoritative; the static fallback existed only because the activeLocation watcher could fire before the catalog was hydrated).
  - Items whose `available_at` is the full set of location slugs render no caption.
  - Items whose `available_at` is a strict subset render an inline caption: "Available at Downtown only" or "Available at Downtown · Uptown".
  - Items whose `available_at` is `[]` are excluded from render entirely (treated as fully unavailable; admin should mark `unavailable_at` instead, but the frontend defends).
- **Acceptance Criteria:**
  - [ ] `/food-drink` renders every item across both venues
  - [ ] Items with restricted `available_at` show the correct caption
  - [ ] `useFoodMenu.test.ts` asserts the fetch URL is `/api/food-menu` (no location segment) and that response items expose `available_at`
  - [ ] Playwright covers a Downtown-only item rendering its caption

---

### Task 8: Location confirmation banner on `/purchase/:showtimeId`

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/booking/BookingLocationBanner.vue` (new)
  - `frontend/app/pages/purchase/[showtimeId].vue` (modify — render banner above seat grid)
  - `frontend/app/types/showtime.ts` (modify — confirm seatmap response carries `location: { slug, name, street, city, phone }`)
  - `frontend/tests/components/booking/BookingLocationBanner.spec.ts` (new)
- **Details:**
  - "You're booking at **{Location Name}** — {street}, {city}. {phone}." with a "[Change location]" link routing back to `/movies/{movieSlug}`.
  - Reads `showtime.location` from the seatmap fetch response — no separate request.
  - On mobile: collapses into a single-line summary with an expand control.
- **Acceptance Criteria:**
  - [ ] Banner renders the venue's name, street, city, and phone
  - [ ] "[Change location]" link routes back to the movie detail page (verified by Playwright click → URL assertion)
  - [ ] Component test snapshot matches design system tokens (no inline styles)

---

### Task 9: Checkout food-availability dimming

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/booking/FoodPreOrderPanel.vue` (modify — accept `bookingLocationSlug` prop; dim items whose `available_at` excludes it)
  - `frontend/app/pages/purchase/checkout.vue` (modify — pass the prop from the booking context)
  - `frontend/app/composables/useCart.ts` (modify — refuse `addFoodItem` for an unavailable item; throw or noop with a toast)
  - `frontend/tests/components/booking/FoodPreOrderPanel.spec.ts` (new or modify)
- **Details:**
  - Items where `available_at` excludes the booking's location render with the quantity stepper hidden, opacity 0.4, and a `CvBadge variant="warning"` overlay: "Not available at {Location Name}".
  - Cart-level guard is defense-in-depth; the server also rejects.
- **Acceptance Criteria:**
  - [ ] An item with `available_at: ['uptown']` is dimmed when booking is at Downtown, with the warning badge visible
  - [ ] Programmatically calling `useCart.addFoodItem` with an unavailable item returns/throws and emits a toast
  - [ ] Vitest covers the dimming logic and the cart guard

---

### Task 10: Sitemap

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/package.json` (modify — add `@nuxtjs/sitemap`)
  - `frontend/nuxt.config.ts` (modify — register the module + dynamic source URLs)
  - `frontend/server/api/sitemap-routes.get.ts` (new — composes URLs from `/api/movies`, `/api/calendar/events`, `/api/locations`, and `@nuxt/content`)
  - `frontend/tests/server/sitemap-routes.test.ts` (new)
  - `frontend/tests/e2e/sitemap.spec.ts` (new — Playwright fetches `/sitemap.xml` and asserts expected entries)
- **Details:**
  - Exclude `/purchase/**`, `/account/**`, `/auth/**` (already `noindex`).
  - Per-URL `lastmod` from the underlying record's `updated_at`.
  - Module config respects ISR: regenerated on revalidation tick.
- **Acceptance Criteria:**
  - [ ] `/sitemap.xml` returns 200 with valid XML
  - [ ] Sitemap contains every static page, every movie slug, every event slug, every location slug, and every blog post slug
  - [ ] Sitemap excludes `/purchase/*`, `/account/*`, `/auth/*`
  - [ ] e2e snapshot test catches regressions (one entry-count assertion + one per-section presence check)

---

### Task 11: Test updates and additions

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/tests/composables/useFoodMenu.test.ts` (modify — see Task 7)
  - `frontend/tests/composables/useShowtimes.test.ts` (modify — see Task 6)
  - `frontend/tests/composables/useGeolocation.test.ts` (new — see Task 3)
  - `frontend/tests/composables/useFeaturedSlides.test.ts` (new — see Task 4)
  - `frontend/tests/composables/usePublicLocations.test.ts` (new — see Task 1)
  - `frontend/tests/e2e/home-carousel.spec.ts` (new)
  - `frontend/tests/e2e/movies-location-filter.spec.ts` (new)
  - `frontend/tests/e2e/movie-detail-cross-location.spec.ts` (new — mocks geolocation, asserts closest-group expanded)
  - `frontend/tests/e2e/food-availability-captions.spec.ts` (new)
  - `frontend/tests/e2e/locations-page.spec.ts` (new)
  - `frontend/tests/e2e/checkout-food-dimming.spec.ts` (new)
- **Acceptance Criteria:**
  - [ ] `make test-frontend` passes
  - [ ] `make e2e` passes with all new e2e specs included
  - [ ] No skipped or `.only` tests committed

---

### Task 12: Progress journal entry

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `docs/progress/frontend-v1.md` (modify — append `## Plan 13: Content Refactor` section)
  - `docs/plans/frontend/v1/00-index.md` (modify — add the row for Plan 13)
- **Details:**
  - Use the standard progress journal format. Status starts at 🔲 Not Started; flip to 🟡 In Progress when the first task starts.
  - Add a row to the v1 index with Plan 13's MoSCoW (Should Have), Complexity (L), and Depends On (06, 10, plus the backend feature plan).
- **Acceptance Criteria:**
  - [ ] Plan 13 row exists in `00-index.md` Plan Summary table
  - [ ] `frontend-v1.md` has a `## Plan 13: Content Refactor` section in the standard format

---

## Out of Scope

- IP-based geolocation fallback (only browser API in this round)
- Admin work for `featured_slides` and per-location menu availability — see `docs/plans/admin/features/2026-05-02-content-curation-admin.md`
- Backend endpoints for cross-location food menu, cross-location showtimes, featured slides — see `docs/plans/backend/features/2026-05-02-cross-location-content-api.md`
- Multi-currency / regional pricing
- Personalized home strips ("Recently viewed", "Recommended for you")
