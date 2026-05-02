# Content Architecture

How public content surfaces are composed, fetched, rendered, and curated. Companion to `SITE_ARCHITECTURE.md` — that file describes the whole app; this one is scoped to the cross-location content tier (home, movies, food, events, calendar, blog, locations, static pages).

The customer-facing booking funnel (`/purchase/**`), account area (`/account/**`), auth (`/auth/**`), and admin panel are out of scope here.

---

## 1. Principle: Static-First, Cross-Location

The content tier renders the brand's full slate — every movie playing across every venue, every upcoming event, the complete food story, every theater location — server-side, with the same payload every visitor sees. There is no per-visitor location filter applied at the document level.

**Three reasons this matters:**

1. **SEO.** Search engines see the complete catalog, not a thin per-location slice gated behind a client-side selector. Every movie, event, and location has a single canonical URL.
2. **Perceived speed.** First paint contains real content. No hydration-wait, no "loading menu for Downtown…" shimmer, no localStorage round-trip before the user sees what's playing.
3. **Cross-location discovery.** A patron deciding *what* to see often hasn't decided *where*. Surfacing the full slate (and presenting venue choice at the moment of intent) keeps the same film visible whether it's playing at one theater or both.

The only browse-time location signaling is **optional** — a `?location=` query string filter on `/movies` and a geolocation-driven default expansion on `/movies/:slug`. SSR always renders the full slate first; filters and defaults are URL-driven (cacheable) or hydration-time enhancements (not load-blocking).

---

## 2. The Location-at-Intent Pattern

Physical location is a property of the **purchase intent**, not of the **browse session**.

**Old model (deprecated):**

```
App boot → fetch /api/locations → set activeLocation
        → all browse pages fetch via /api/locations/{slug}/...
        → checkout uses activeLocation implicitly
```

**New model:**

```
App boot → (no location work; useLocations not invoked)
Browse pages → render the full slate via cross-location endpoints
            → optional ?location=slug filter on /movies
            → geolocation (opt-in, post-hydration) reorders defaults
Booking intent → user picks a showtime → showtime carries its location
              → /purchase/{showtimeId} renders a Location Confirmation banner
              → checkout dims food items not stocked at that venue
```

`useLocations.activeLocation` is **demoted** from "the location for everything" to "the user's preferred default for the booking-time picker." It no longer gates content visibility, no longer drives food-menu fetches, and is no longer required for any browse page to render.

**Decision log:** This shift was specified in `docs/plans/frontend/v1/13-content-refactor.md` and motivated by SEO, first-paint performance, and cross-location discovery. The move is non-breaking from the user perspective — guests who never set a preferred location keep getting the same checkout flow; geolocation is opt-in; the SiteHeader switcher (if retained) becomes a preference control with no effect on browse pages.

---

## 3. Page Tier Map

Every public content route, classified by rendering strategy and location coupling:

| Tier | Routes | Rendering | Location coupling |
|---|---|---|---|
| **Static-prerendered** | `/contact`, `/faq`, `/accessibility`, `/careers`, `/private-screenings` | Build-time | None |
| **ISR cross-location** | `/`, `/movies/:slug`, `/whats-on`, `/events`, `/events/:slug`, `/food-drink`, `/locations`, `/locations/:slug`, `/blog`, `/blog/:slug`, `/gift-cards` | ISR (10–30 min) | None at the document level — per-location data is *inlined* (e.g. showtimes grouped by location, menu items carry availability arrays) |
| **ISR with optional location filter** | `/movies` | ISR + per-`?location=` cache key | Optional via query string; default is "all locations" |
| **Client-only (intent/auth)** | `/purchase/**`, `/account/**`, `/auth/**` | `ssr: false` | Booking flow uses the showtime's location explicitly |

The full `routeRules` block in `nuxt.config.ts` is the source of truth for revalidation windows. This table classifies the *intent* behind each window, not the window itself.

---

## 4. Data Sources for Browse

What each ISR/prerendered page fetches at SSR time. All endpoints are public; none require auth.

| Page | Endpoint(s) | Notes |
|---|---|---|
| `/` | `GET /api/featured-slides`, `GET /api/movies?status=now_showing&per_page=12`, `GET /api/movies?status=coming_soon&per_page=8`, `GET /api/calendar/events?range=week`, `GET /api/locations` | Hero carousel + 4 strips |
| `/movies` | `GET /api/movies?status=now_showing` and `?status=coming_soon`. With `?location=slug`: append `&location=slug` (filters to movies with a showtime at that venue) | Filter is server-side; each filter URL is a distinct ISR cache entry |
| `/movies/:slug` | `GET /api/movies/:slug`, `GET /api/movies/:slug/showtimes` (cross-location, returns showtimes grouped by `location: { slug, name }`) | Replaces the old `/api/locations/{slug}/movies/{slug}/showtimes` for the public path |
| `/whats-on` | `GET /api/calendar/events?month=&year=&type=&accessibility=` | Already cross-location |
| `/events`, `/events/:slug` | `GET /api/calendar/events?type=special_event`, `GET /api/calendar/events/:slug` | Already cross-location |
| `/food-drink` | `GET /api/food-menu` (new — returns every item with `available_at: string[]`) | Replaces `GET /api/locations/{slug}/food-menu` for the public path; the per-location endpoint stays for admin/internal use |
| `/locations` | `GET /api/locations` | All venues, alphabetical |
| `/locations/:slug` | `GET /api/locations/:slug`, plus `?location=slug` against movies and events for the right-column "now showing here" / "events here" strips | Single venue detail |
| `/gift-cards` | None at SSR — interactive form | Page itself is ISR for shell + SEO |
| `/blog`, `/blog/:slug` | `@nuxt/content` queryContent | Static markdown |

**Cache TTLs** mirror the existing `routeRules` (ISR 600s for detail pages, 900s for events/calendar, 1800s for listings/home/food/locations, prerender for true statics). New endpoints (`/api/food-menu`, `/api/featured-slides`, cross-location showtimes) follow the same backend caching pattern as their per-location predecessors (Redis, 5-min default for menu/slides, no cache for showtime queries beyond per-request memoization).

---

## 5. `useLocations` Reduced Role

After the refactor, `useLocations` exposes the same surface but is invoked from far fewer places.

**State retained:**
- `locations: Ref<Location[]>` — the catalog of venues, fetched once and cached.
- `activeLocation: Ref<Location | null>` — the user's *preferred default* (localStorage-backed). Used only as the booking-time picker default when geolocation is not granted.
- `setLocation(slug)` — writes the preference.

**Behavior changes:**
- The `plugins/locations.ts` boot-time fetch is moved into `usePublicLocations` (a thin SSR-friendly wrapper), so the locations *catalog* is available everywhere without invoking the preference machinery.
- `useFoodMenu` no longer reads `activeLocation`.
- `pages/movies/[slug].vue` no longer reads `activeLocation` (it renders all venues' showtimes).
- `SiteHeader`'s location switcher is either (a) replaced by a `LocationPreferenceSwitcher` that only writes the localStorage default, or (b) removed entirely in favor of the `/locations` page as the canonical location surface. This decision lives in the frontend plan, not here.

**Allowed readers of `activeLocation` after the refactor:**
- The booking-time location picker (precedence rule below).
- The `LocationPreferenceSwitcher` UI itself (read-modify-write).

That's the whole list. Any other component reading `activeLocation` is a regression.

---

## 6. Geolocation-Aware Defaults

Browser geolocation is an **opt-in default-picker**, never a content gate.

**What it affects:**
- `/movies/:slug` — the closest location's showtime group is expanded by default; others render collapsed-but-visible.
- `/locations` — cards re-order by distance after hydration; each card gains a "X mi away" caption.
- `/movies` — surfaces a non-binding "Filter to nearest: Downtown" suggestion chip.
- The booking-time location picker — picks the closest as the default highlight.

**What it does not affect:**
- SSR output. Server always renders alphabetical, all-locations content. Geolocation is a hydration-time enhancement.
- Filters. Geolocation never auto-applies a filter; it suggests one.
- Visibility. Locations are never hidden because of distance.

**UX rules:**
- **Permission prompt timing.** Never at app boot. Only on user gesture (clicking a "Use my location" affordance) or on first land-on-`/movies/:slug` (silent permission check; if status is `prompt`, no prompt is shown — only a small "Show closest first" link that triggers the request).
- **Caching.** Granted coordinates cached in `sessionStorage` keyed by `geolocation:coords`. Survives in-tab nav; cleared on tab close. No persistent storage of coordinates.
- **Distances.** Computed client-side via Haversine against `locations[*].latitude/longitude` (already in the schema — no backend dependency). Displayed in miles (US default, `country: 'US'` on every seeded location).

**Composable contract:**

```ts
function useGeolocation() {
  const status: Ref<'idle' | 'prompting' | 'granted' | 'denied' | 'unsupported'>
  const coords: Ref<{ lat: number; lng: number } | null>
  function request(): Promise<void>           // triggers the browser prompt
  function distanceTo(loc: Location): number  // miles; returns Infinity if no coords
  return { status, coords, request, distanceTo }
}
```

SSR returns `status: 'idle'` and `coords: null` always. Mocked `navigator.geolocation` covers tests for the `granted` / `denied` / `unsupported` paths.

**Precedence for the booking-time default location:**

```
1. Explicit query string  ?location=slug   (deep link)
2. Geolocation result     (closest venue)
3. activeLocation         (saved preference)
4. Alphabetical first     (fallback)
```

---

## 7. Featured Slides Contract

The home page hero is an admin-curated carousel, not auto-composed from movie status. Editors manage slides in the Filament admin to drive marketing moments (festivals, premieres, special events, premier-tier promotions).

**Data shape (`FeaturedSlide`):**

| Field | Type | Notes |
|---|---|---|
| `id` | uuid | |
| `headline` | string | Required, 1–80 chars |
| `sub_headline` | string \| null | Optional, ≤ 160 chars |
| `image_url` | string | Public URL; uploaded via Filament `disk('public')` |
| `cta_label` | string | Required, ≤ 24 chars |
| `cta_href` | string | URL or internal route |
| `display_order` | int | Lower renders first |
| `starts_at` | timestamp \| null | Slide invisible before this; null = no lower bound |
| `ends_at` | timestamp \| null | Slide invisible after this; null = no upper bound |
| `published_at` | timestamp \| null | Null = draft. Set at publish time |

**Public endpoint:** `GET /api/featured-slides` returns active slides — `published_at IS NOT NULL AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())` — ordered by `display_order ASC, id ASC`. Cache 5 min.

**Frontend behavior:**
- `useFeaturedSlides()` composable wraps the fetch.
- `HomeFeaturedCarousel` consumes the array. Auto-advances every 7s, pauses on hover/focus, supports prev/next buttons and slide indicator dots, swipe on touch, and full WAI-ARIA Carousel pattern (`aria-roledescription="carousel"`, `aria-roledescription="slide"` on each panel, `aria-live="polite"` when auto-advancing, `aria-live="off"` when paused).
- **Empty-state fallback:** when the API returns zero slides, render a single hardcoded brand slide (image + tagline + CTA → `/movies`). The home page must never render an empty hero.
- Reduced motion: auto-advance disabled, all slides stacked statically (the carousel becomes a static hero with the first slide visible and indicators repurposed as nav buttons).

---

## 8. Cross-Location Menu Contract

The food page is a single shared surface. Per-location availability is data, not routing.

**Endpoint:** `GET /api/food-menu` returns:

```json
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

**Page rendering (`/food-drink`):**
- Every item in the response renders, regardless of `available_at`.
- Items whose `available_at` is a strict subset of all locations carry an inline caption: "Available at Downtown only" (one location) or "Available at Downtown · Uptown" (subset). Items available everywhere carry no caption — that's the default.
- Category tabs and dietary filters operate on the full set.

**Checkout consumption (`/purchase/checkout` → `FoodPreOrderPanel`):**
- The panel receives the booking's location slug (from the showtime).
- Items whose `available_at` excludes that slug render with `disabled` interaction and a `CvBadge variant="warning"` overlay: "Not available at {Location Name}".
- Quantity controls are hidden for unavailable items.
- The cart never accepts an unavailable item — defense in depth: server-side validation rejects them too.

The existing `GET /api/locations/{location}/food-menu` endpoint stays in place for any internal/admin consumer that needs the per-location view; it is no longer the public path.

---

## 9. Locations as Content

`/locations` and `/locations/:slug` are first-class content pages. They turn the brand's physical footprint into discoverable, crawlable, structured-data-emitting content.

**`/locations`** — Wide Frame hero + Ensemble grid. Each card: name, full address, phone, hours, "Get Directions" link (`https://maps.google.com/?q=<lat>,<lng>` URL constructed from the lat/lng columns), thumbnail photo, "See Showtimes" CTA → `/movies?location={slug}`. ISR 1800s. After hydration, `useGeolocation` reorders cards by distance and adds "X mi away" captions.

**`/locations/:slug`** — Establishing Shot 65/35 layout:
- Left (65%): full address, hours, phone/email, accessibility info, transit/parking notes, embedded map (or a static map image with the directions link as the primary CTA — design call in the frontend plan).
- Right (35%): "Now Showing Here" strip (cross-references `/api/movies?location=slug`) and "Upcoming Events Here" strip (cross-references `/api/calendar/events?location=slug` if the backend supports the filter; otherwise a client-side filter on the cross-location response).

**Structured data:** every location detail page emits `LocalBusiness` JSON-LD with `name`, `address` (`PostalAddress` sub-schema), `telephone`, `email`, `geo` (`GeoCoordinates`), `openingHoursSpecification`, `image`, `priceRange`, and `url`. This is a hard SEO win — local search results for "movie theater near me" depend on this.

---

## 10. SEO & Sitemap

**Canonical URLs.** One per content entity. `/movies/the-brutalist` is the only canonical URL for that movie — there is no `/locations/downtown/movies/the-brutalist` content variant. If a per-location movie surface is ever needed, it lives at `/movies/the-brutalist?location=downtown` and emits `<link rel="canonical" href="/movies/the-brutalist">`.

**Sitemap.** The app emits `sitemap.xml` covering:

- `/`
- `/movies`, every movie's `/movies/:slug`
- `/whats-on`
- `/events`, every event's `/events/:slug`
- `/food-drink`
- `/locations`, every location's `/locations/:slug`
- `/faq`, `/contact`, `/accessibility`, `/careers`, `/gift-cards`, `/private-screenings`
- `/blog`, every post's `/blog/:slug`

`@nuxtjs/sitemap` is the implementation; sources are the same endpoints used for SSR (`/api/movies`, `/api/calendar/events`, `/api/locations`) plus `@nuxt/content` for the blog.

**Per-route meta.** Every content page sets a unique title, description, canonical URL, and Open Graph image (movie poster, event image, location photo, or brand fallback). Movie and event pages additionally emit `Movie` and `Event` JSON-LD respectively (already specified in `PAGE_SPECS.md`).

**`robots.txt`.** Allows the public content tier; disallows `/account/*`, `/auth/*`, `/purchase/*` (the latter also carries `X-Robots-Tag: noindex` from `routeRules`). The admin subdomain has its own robots policy.

---

## 11. Open Questions / Future Work

- **IP-based geolocation fallback.** Today: pure browser API, no fallback. Future: optional Cloudflare/Fastly geo headers could pre-seed the closest-location default at SSR time without a permission prompt. Trade-off: harder to cache (one ISR variant per region) and weakens the "every visitor sees the same SSR" property. Defer until measured demand.
- **Multi-currency / regional pricing.** Currently USD-only. If ever expanded, the per-location food menu's `price` would need to vary too, breaking the "one shared menu" contract. This is a non-issue at the project's current scope.
- **Personalized home strips.** The current home page is the same for every visitor. A future "Your Recently Viewed" strip would need to render *post-hydration* to keep SSR cacheable.
- **Editorial collections.** Beyond the FeaturedSlides carousel, an admin might want to curate a "Staff Picks" strip on the home page or a "Currently in Repertory" strip. Out of scope for this round; the architecture (admin-managed content + cross-location render + ISR) generalizes cleanly.

---

## See Also

- `docs/architecture/SITE_ARCHITECTURE.md` — overall app structure, full route map, layouts, dependencies
- `docs/architecture/DATA_MODELS.md` — TypeScript interfaces and database schema inventory
- `docs/architecture/STATE_MANAGEMENT.md` — composable state architecture
- `docs/specs/PAGE_SPECS.md` — per-page section specs and data requirements
- `docs/plans/frontend/v1/13-content-refactor.md` — frontend execution plan that ships this architecture
- `docs/plans/backend/features/2026-05-02-cross-location-content-api.md` — backend API surface for the new contracts
- `docs/plans/admin/features/2026-05-02-content-curation-admin.md` — Filament resources for FeaturedSlides + per-location menu availability
