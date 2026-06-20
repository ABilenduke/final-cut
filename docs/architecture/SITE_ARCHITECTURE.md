# Site Architecture

Technical architecture reference for the movie theatre web application. The frontend is a Nuxt 4 SPA; the backend is a Laravel 13 API.

---

## Project Structure

The application has two codebases: a Nuxt 4 frontend (`frontend/`) and a Laravel 13 backend (`backend/`), orchestrated via Docker Compose. The frontend calls the Laravel API directly — there is no Nuxt server-side BFF layer.

### Frontend (`frontend/app/`)

```
app/
├── assets/css/
│   ├── tokens.css
│   ├── reset.css
│   ├── typography.css
│   ├── layouts.css
│   ├── utilities.css
│   ├── print.css
│   └── main.css
├── components/
│   ├── ui/              CvButton, CvCard, CvInput, CvTextarea, CvSelect,
│   │                    CvModal, CvAccordion, CvBadge, CvSkeletonLoader,
│   │                    CvToast, CvIcon
│   ├── layout/          SiteHeader, SiteFooter, NeuralTicker, MobileNav,
│   │                    SkipNav, SidebarNav
│   ├── movie/           MovieCard, MovieHero, MovieDetail, MovieCastList,
│   │                    MovieTrailerEmbed, MovieRatingBadge, ShowtimeSelector
│   ├── booking/         AuditoriumGrid, AuditoriumSeat, AuditoriumLegend,
│   │                    AuditoriumScreenBar, CartSummary, CheckoutForm,
│   │                    BookingConfirmation, FoodPreOrderPanel, PromoCode,
│   │                    PurchaseStepIndicator
│   ├── calendar/        BridgeProgrammeToolbar, BridgeFilterRibbon,
│   │                    BridgeMonthGrid, BridgeDayCell, BridgeDetailRail,
│   │                    BridgeDetailHero, BridgeAlsoToday,
│   │                    BridgeCinemaReadout, BridgeMiniPoster,
│   │                    BridgeDetailDrawer
│   ├── account/         OrderHistoryList, LoyaltyPointsCard,
│   │                    SavedPaymentMethods, UpcomingBookings, ProfileForm
│   └── content/         FaqAccordionGroup, ContactForm, ContactMap, MenuItem,
│                        MenuCategoryTabs, GiftCardPurchase, BalanceChecker,
│                        RentalInquiryForm, PackageCard, BlogPostCard,
│                        EventListCard, EventDetail
├── composables/
│   ├── useMovies.ts
│   ├── useShowtimes.ts
│   ├── useCalendarEvents.ts
│   ├── useCart.ts
│   ├── useAuth.ts
│   ├── useAccount.ts
│   ├── useSeatSelection.ts
│   ├── useGiftCards.ts
│   └── useToast.ts
├── data/
│   ├── faq.ts           Static FAQ content as structured TypeScript
│   └── menu.ts          Static food/drink menu data
├── layouts/
│   ├── default.vue
│   ├── account.vue
│   ├── purchase.vue
│   └── blank.vue
├── middleware/
│   ├── auth.ts          Redirects unauthenticated users to /auth/login
│   └── guest.ts         Redirects authenticated users away from auth pages
├── pages/               See Route Map below
├── plugins/
│   └── toast.client.ts  Client-only toast notification system
├── types/
│   ├── movie.ts
│   ├── showtime.ts
│   ├── booking.ts
│   └── user.ts
└── utils/
    ├── formatCurrency.ts
    ├── formatDate.ts
    ├── formatRuntime.ts
    ├── slugify.ts
    └── seatLabel.ts
```

### Backend (`backend/`)

Laravel 13 API. See `backend/routes/api.php` for all route definitions. Key directories:

- `app/Http/Controllers/` — API controllers
- `app/Models/` — Eloquent models
- `app/Services/` — Business logic. The customer API and the admin panel share one service layer: `TmdbService`, `StripeService`, `SeatAvailabilityService`, `ShowtimeService`, `ShowtimeCalendarProjector`, `MovieService`, `AuditoriumService`, `LoyaltyService`, `GiftCardService`, `PromoCodeService`, `PromoRedemptionIdentity`, `WalkUpBookingService`, `BookingAmendmentService`, `BookingRefundService`, `BookingFlagService`, `BookingNotificationService`, `OutboxRetryService`, `ContactSubmissionService`, `RentalInquiryService`, `SiteSettingsService`, `AdminUserService` (plus shared traits under `Services/Concerns/`)
- `app/Filament/Resources/` — Admin panel resources (20, on a shared `BaseResource`): Movie, Showtime, Location, Auditorium, Booking, User, MenuItem, PromoCode, GiftCard, CalendarEvent, FeaturedSlide, TickerItem, BlogPost, FaqItem, JobOpening, ScreeningPackage, RentalInquiry, ContactSubmission, AdminUser, DispatchOutbox
- `app/Filament/Pages/` — Admin custom pages (12): BookingLookup, CancellationFollowupQueue, SchedulePlanner, ActivityLog, and the CMS content editors (HomePageContent, NavigationContent, ContactContent, SiteContacts, CareersContent, AccessibilityContent, PrivateScreeningsContent, GiftCardsContent)
- `app/Outbox/` — Dispatch-outbox processor (`OutboxDispatcher` mapping `event_type` → queued job)
- `app/Console/Commands/` — Artisan commands including `outbox:dispatch`, `outbox:prune`, `admin:create-user`
- `database/migrations/` — PostgreSQL schema
- `database/factories/` and `database/seeders/` — Test data
- `tests/Feature/` — Pest feature tests
- `tests/Feature/Admin/` — Admin-namespaced Pest tests (Resource CRUD + permission matrices, services, hardening)

### Admin Subdomain (`admin.${APP_DOMAIN}`)

The Filament 5 admin panel runs in the same Laravel app as the customer API but is reached at a dedicated subdomain. Same nginx service, same backend PHP-FPM container, same database — separated at three layers:

1. **Nginx vhost** (`nginx/templates/conf.d/admin.conf.template`): no `proxy_pass` to the Nuxt frontend on this host, so customer routes can't answer here. The vhost also carries the Layer-1 IP allowlist (`${ADMIN_ALLOWLIST_BLOCK}` rendered from `ADMIN_IP_ALLOWLIST` by the entrypoint) and a stricter `admin_login` rate-limit zone (5 r/min, burst 3) on `location = /login`.
2. **Laravel route-domain scoping** (`bootstrap/app.php` + `AdminPanelProvider->domain(config('filament.admin_domain'))`): customer api/web routes are wrapped in `Route::domain(config('app.primary_domain'))->...`; admin routes register only on the admin domain. The `RouteDomainScopingTest` Pest case asserts no route leaks across domains.
3. **Session cookie + Redis DB scoping** (`ScopeAdminSession` middleware + `AdminIpAllowlist` middleware ahead of it): admin sessions use a dedicated cookie name, no leading dot on the domain, and Redis DB 3. An IP-rejected request short-circuits before any session machinery runs.

---

## Layouts

Four layouts cover the full range of page contexts.

### `default.vue` — Full Chrome

The standard layout used by most public-facing pages.

- **SiteHeader** with logo, primary navigation, and auth controls
- **NeuralTicker** scrolling banner below the header
- **SkipNav** link for keyboard/screen-reader users
- Main content slot
- **SiteFooter** with secondary navigation, social links, and legal
- **MobileNav** replaces footer navigation below the `screen-md` breakpoint

### `account.vue` — Sidebar Navigation

Extends the default layout with a persistent sidebar for account pages.

- Desktop: 15rem sidebar rail with labels and icons
- Tablet: 4rem icon-only rail
- Mobile: bottom navigation bar
- Used for all `/account/*` routes

### `purchase.vue` — Minimal Purchase Flow

Strips away distractions to focus on the booking and checkout process.

- Header shows only the logo, **PurchaseStepIndicator** (three clickable steps: Pick Your Seats → Add Food & Pay → You're In), and session timer display
- **CartSummary** sidebar on desktop, bottom sheet on mobile
- No footer
- Used for all `/purchase/*` routes

### `blank.vue` — No Chrome

No header, no footer, no navigation. Just centered content.

- Used for `/auth/*` routes (login, register, forgot password)

---

## Route Map and Rendering Strategy

Each route group is assigned a rendering strategy based on how frequently its data changes and whether it requires user context.

| Route Pattern | Page | Strategy | Revalidation | Rationale |
|---|---|---|---|---|
| `/` | Home (admin-curated hero carousel + cross-location strips) | ISR | 30 min | Content changes infrequently, SEO important |
| `/movies` | Now Playing / Coming Soon (optional `?location=` filter) | ISR (per-query cache key) | 30 min | Movie listings update a few times daily; each filter URL is independently cacheable |
| `/movies/:slug` | Movie Detail (cross-location showtimes grouped by venue) | ISR | 10 min | Detail pages, SEO critical |
| `/whats-on` | Calendar View (Bridge Console) | ISR | 15 min | Calendar data changes moderately |
| `/events` | Events Listing | ISR | 15 min | Event schedule changes moderately |
| `/events/:slug` | Event Detail | ISR | 15 min | Detail pages, SEO important |
| `/food-drink` | Food and Drink Menu (shared, cross-location, with per-item availability arrays) | ISR | 30 min | Menu rarely changes |
| `/locations` | All Locations (alphabetical, geolocation re-orders post-hydration) | ISR | 30 min | Venue list rarely changes |
| `/locations/:slug` | Location Detail (LocalBusiness JSON-LD, now-showing-here strip) | ISR | 30 min | Detail pages, local-SEO critical |
| `/blog` | Blog Listing | ISR | 10 min | Blog index; posts are the static `app/data/blog.ts` placeholder |
| `/blog/:slug` | Blog Post | ISR | 10 min | Content updates, SEO critical |
| `/gift-cards` | Gift Cards (composer + live preview, balance lookup strip) | ISR | 30 min | Editorial content rarely changes; suppresses global `NeuralTicker` via `definePageMeta({ hideTicker: true })` so the balance strip can occupy the chrome slot |
| `/gift-cards/bulk` | Bulk Gifting placeholder (corporate concierge CTA) | Prerendered | Build time | Static placeholder until the bulk-gifting form ships |
| `/contact` | Contact Page | ISR | 30 min | Venue hours/contacts come from the locations API (admin-v2 Plan 14) |
| `/faq` | FAQ | ISR | 30 min | FAQ items are admin-managed via `/api/faq` (admin-v2 Plan 13) |
| `/accessibility` | Accessibility Statement | ISR | 30 min | Contact line is admin-managed via `/api/site-content/contacts` (admin-v3 Plan 02) |
| `/careers` | Careers | ISR | 30 min | Openings + contact email are admin-managed (admin-v2 Plan 13 / admin-v3 Plan 02) |
| `/private-screenings` | Private Screenings / Rentals | ISR | 30 min | Packages admin-managed via `/api/screening-packages`; intro via `/api/site-content/private-screenings` |
| `/terms` | Terms of Service | ISR | 30 min | Mostly static legal copy; contact line is admin-managed via `/api/site-content/contacts` |
| `/privacy` | Privacy Policy | ISR | 30 min | Mostly static legal copy; contact line is admin-managed via `/api/site-content/contacts` |
| `/purchase/**` | Seat Selection, Checkout | Client-only | — | Real-time seat data, auth context |
| `/account/**` | Profile, Orders, Loyalty | Client-only | — | User-specific data |
| `/auth/**` | Login, Register, Reset | Client-only | — | Auth forms, no SEO value |
| `admin.${APP_DOMAIN}/**` | Filament admin panel | Server-rendered (Laravel) | — | Auth-gated, IP-allowlisted, no Nuxt involvement |

All rendering strategies are configured via `routeRules` in `nuxt.config.ts`:

```ts
export default defineNuxtConfig({
  routeRules: {
    '/': { isr: 1800 },
    '/movies': { isr: 1800 },
    '/movies/**': { isr: 600 },
    '/food-drink': { isr: 1800 },
    '/whats-on': { isr: 900 },
    '/events': { isr: 900 },
    '/events/**': { isr: 900 },
    '/locations': { isr: 1800 },
    '/locations/**': { isr: 1800 },
    '/blog': { isr: 600 },
    '/blog/**': { isr: 600 },
    '/contact': { isr: 1800 },
    '/faq': { isr: 1800 },
    '/accessibility': { isr: 1800 },
    '/careers': { isr: 1800 },
    '/private-screenings': { isr: 1800 },
    '/terms': { isr: 1800 },
    '/privacy': { isr: 1800 },
    '/gift-cards': { isr: 1800 },
    '/gift-cards/bulk': { prerender: true },
    // X-Robots-Tag header keeps these out of search indices. The matching
    // sitemap opt-out lives in server/routes/sitemap.xml.ts EXCLUDED_PREFIXES.
    '/purchase/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/auth/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
  },
})
```

> **Note:** `/terms` and `/privacy` use ISR (not `prerender`) because both render an admin-managed contact line via `useSiteContacts()` — prerendering would freeze it. Both are included in the sitemap.

### Location-at-Intent Contract

Public content surfaces are **cross-location and SSR'd**. Physical location is a property of the *purchase intent*, not the *browse session* — the user picks a location implicitly when they pick a showtime, and explicitly confirms it via the `BookingLocationBanner` on the seat-selection page. Browse pages never gate content behind a location selection. Optional `?location=` URL filters and opt-in browser geolocation re-ordering are the only location-aware affordances on the browse tier; both are cacheable or post-hydration enhancements that never block first paint. See `docs/architecture/CONTENT_ARCHITECTURE.md` for the full pattern.

---

## Dependencies

| Package | Purpose | Why This One |
|---|---|---|
| `nuxt` (4.x) | Meta-framework | File-based routing, SSR/ISR, server routes, auto-imports — a single tool that replaces a dozen decisions |
| `@nuxt/content` | Blog and FAQ content | Markdown authoring with frontmatter, query API, zero infrastructure required |
| `@stripe/stripe-js` | Client-side payments | PCI-compliant card collection via Stripe Elements without handling raw card data |
| `stripe` | Server-side payments | PaymentIntent creation, webhook handling, customer management |


> **Auth note (corrected):** `nuxt-auth-utils` was considered for SSR auth hydration but **never adopted** — it is not a dependency and nothing imports it. Authentication is Laravel **Sanctum** (HTTP-only session cookie) only; the frontend hydrates auth state client-side via `useState('auth:user')` + a `localStorage` marker (`fc:auth:session`) gating the `/api/auth/me` probe. Protected routes are `ssr: false`, so there is no server-side auth hydration. See STATE_MANAGEMENT.md § Auth. Pinned by `frontend/tests/architecture/auth-mechanism.test.ts`.

---

## Environment Variables

### Backend (Laravel `.env`)

Backend environment is standard Laravel. Key variables:

- `TMDB_API_KEY` — TMDB API v3 key for movie enrichment (mapped through `config/services.php`)
- `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` — Stripe API keys (mapped through `config/services.php`)
- `DB_*` — PostgreSQL connection (database: `final_cut`, test: `final_cut_test`)
- `REDIS_*` — Redis connection for cache/sessions

### Frontend (Nuxt runtime config)

Frontend environment variables use the `NUXT_` prefix for automatic mapping to Nuxt runtime config:

```bash
NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=       # Stripe publishable key (client)
NUXT_PUBLIC_SITE_URL=                     # Base URL for SEO, OG tags, emails, sitemap
NUXT_PUBLIC_API_BASE_URL=                 # Laravel API base URL
```

> `NUXT_SESSION_PASSWORD` was previously listed here for `nuxt-auth-utils` cookie encryption. Since that module was never adopted (see the Auth note above), the variable is **not required** and is intentionally omitted.

Map links use raw `https://maps.google.com/?q=<lat>,<lng>` URLs constructed client-side from the `latitude`/`longitude` columns on the locations payload. No API key, no env var, no third-party SDK.

---

## Sitemap

The app emits `sitemap.xml` at the root of the public domain via a **hand-rolled Nitro route** (`frontend/server/routes/sitemap.xml.ts`) — not `@nuxtjs/sitemap`, which was removed in the 2026-05-04 CI fix because its transitive `nuxt-site-config`/`h3@2-rc` chain broke `nuxt build` on the Nuxt 4 + Nitro 2.13 baseline (see `docs/superpowers/plans/2026-05-03-pr-50-ci-fix.md`). It must list every public content URL so search engines can discover the full slate without crawling derivation:

- `/`
- `/movies`, every movie's `/movies/:slug`
- `/whats-on`
- `/events`, every event's `/events/:slug`
- `/food-drink`
- `/locations`, every location's `/locations/:slug`
- `/faq`, `/contact`, `/accessibility`, `/careers`, `/gift-cards`, `/gift-cards/bulk`, `/private-screenings`, `/terms`, `/privacy`
- `/blog`, every post's `/blog/:slug`

Excluded: `/purchase/**`, `/account/**`, `/auth/**` (these carry `X-Robots-Tag: noindex` from `routeRules` and `<meta name="robots" content="noindex">` in their templates). The admin subdomain has its own robots policy.

Implementation (all under `frontend/server/`):

- `routes/sitemap.xml.ts` — entry point. Merges a static route list with dynamic URLs, sets a 15-minute `Cache-Control`, and serialises via the pure `utils/sitemap-builder.ts` (`buildSitemapXml`, unit-tested in `tests/server/sitemap.test.ts`). `EXCLUDED_PREFIXES` (`/purchase/`, `/account`, `/auth/`) mirrors the noindex `routeRules`.
- `api/__sitemap__/urls.get.ts` — dynamic URL source. Fetches `/api/movies`, `/api/calendar/events` (slug'd events only), `/api/locations`, and `/api/blog-posts` from the Laravel API **in parallel via `Promise.allSettled`**, so one failing source degrades gracefully instead of emptying the map. Per-URL `lastmod` is derived from each record's `updated_at` (blog: `date`).
- `routes/robots.txt.ts` — dynamic `robots.txt`: disallows the excluded prefixes and points `Sitemap:` at `${siteUrl}/sitemap.xml` (1-hour cache).

All absolute URLs are built from `NUXT_PUBLIC_SITE_URL` (`runtimeConfig.public.siteUrl`, default `https://finalcut.test`). **This env var must be set in every deployed environment** — if it falls back to the dev default, the sitemap, robots, canonical, and OG URLs will all point at `finalcut.test`.

---

## SEO

Customer-facing SEO is centralised so coverage doesn't drift page-by-page.

- **Global defaults** live in `frontend/app/app.vue`: an idempotent `titleTemplate` (brands any bare page title with `— Final Cut`, leaves titles that already contain "Final Cut" untouched — this lets pages pass bare titles without a flag-day rename), default `og:site_name`/`og:type`/`og:locale`/`twitter:card`, a site-wide `og:image`/`twitter:image` fallback (`public/og-default.png`), and the brand-level **`Organization`** JSON-LD emitted once.
- **Per-page SEO** goes through the **`useSeo()` composable** (`app/composables/useSeo.ts`): pass `{ title (bare), description, path?, image?, type?, jsonLd?, noindex? }` (a ref/getter is accepted so async pages update reactively). It emits the canonical `<link>`, OG/Twitter meta, og:image (with the fallback), and JSON-LD. All logic is in the pure, unit-tested `app/utils/seo.ts` builder (`buildSeoHead`, `absoluteUrl`, `organizationSchema`, `eventSchema`) — same builder-vs-wrapper split as the sitemap. JSON-LD is always serialised through `app/utils/safeJsonLd.ts` (escapes `</script>` + U+2028/9).
- **Structured data in place:** `Organization` (site-wide), `ItemList` (home now-showing, events listing), `Movie` (`/movies/:slug`), `LocalBusiness` + `BreadcrumbList` (`/locations`, `/locations/:slug`), `FAQPage` (`/faq`), `JobPosting` (`/careers`), and `Event` + `Place` (`/events/:slug` — the venue comes from the detail endpoint's structured `location`, which the month listing omits to avoid an N+1).
- **Canonical strategy:** each page's canonical is its own path; `/movies` self-canonicalises the `?status=`/`?location=` filter combination so distinct filtered views aren't deduped. Pre-`useSeo()` pages still hand-roll `useHead` — migrating the long tail of pages onto `useSeo()` is the tracked Phase 2 follow-up.

---

## Frontend-Backend Architecture

The frontend calls the Laravel API directly. There is no Nuxt server-side BFF layer. TMDB and Stripe integrations live entirely in the Laravel backend.

### Why

- **Key protection.** API keys stay in the Laravel backend. The client bundle contains zero secrets.
- **Data merging.** Laravel API responses combine database records with TMDB-enriched metadata, eliminating client-side waterfall requests.
- **Caching.** Laravel uses Redis caching for TMDB enrichment results (24h for success, 5min for failures).
- **Stable contract.** Frontend composables depend on our own API shape, not a third party's. If TMDB changes their response format, only the Laravel `TmdbService` needs updating.
- **TMDB is offline-only.** TMDB enrichment runs via the `movies:enrich` scheduled command (hourly). API responses serve only local database data — TMDB is never in the request path.

### Data Flow

```
┌─────────┐     useFetch('/api/movies')     ┌──────────────┐
│ Browser  │ ──────────────────────────────► │ Laravel API  │ ──► PostgreSQL (movies, showtimes)
│          │ ◄────────────────────────────── │              │
└─────────┘     JSON response               └──────────────┘

                                            ┌──────────────┐     TMDB API    ┌──────────┐
                                            │   Scheduled  │ ──────────────► │   TMDB   │
                                            │ movies:enrich│ ◄────────────── │          │
                                            └──────────────┘   Backfill      └──────────┘
                                                               metadata

┌─────────┐     Stripe Elements             ┌──────────┐
│ Browser  │ ──────────────────────────────► │ Stripe.js│  (tokenizes card client-side)
│          │                                 └──────────┘
│          │     POST /api/bookings          ┌──────────────┐     Stripe SDK  ┌────────────┐
│          │ ──────────────────────────────► │ Laravel API  │ ──────────────► │ Stripe API │
│          │ ◄────────────────────────────── │              │ ◄────────────── │            │
└─────────┘     Booking confirmation        └──────────────┘   PaymentIntent └────────────┘
```

---

## Composable Responsibilities

Each composable owns a specific data domain and wraps the corresponding `/api/*` calls.

| Composable | Domain | Key Methods |
|---|---|---|
| `useMovies` | Movie listings and detail | `fetchNowPlaying`, `fetchComingSoon`, `fetchBySlug` |
| `useShowtimes` | Showtime schedules. **Refactored** for cross-location: public methods now hit `/api/movies/:slug/showtimes` (no location segment), per-entry `location` payload. The per-location method (`fetchSeatMap` against `/api/locations/:loc/showtimes/:id`) is retained for the booking flow only. | `fetchByMovie` (cross-location), `fetchByDate`, `fetchSeatMap` (per-location, booking flow) |
| `useCalendarEvents` | Calendar and events | `fetchByMonth`, `fetchByDateRange` |
| `useFoodMenu` | Food menu. **Refactored** to call `/api/food-menu` (no location segment). Returns items with `available_at: string[]`. No longer reads `useLocations.activeLocation`. | `fetchMenu` |
| `useFeaturedSlides` | Admin-curated home hero carousel slides | `fetchSlides` |
| `useTickerItems` | Neural Ticker items (`/api/ticker-items`); `resolveTickerItems` applies the built-in fallback | `useTickerItems()` (SSR fetch) |
| `useSiteContent.ts` | Admin-managed editorial copy, one composable per surface — `useHomeContent`, `useSiteContacts`, `useCareersContent`, `useContactInfo`, `usePrivateScreeningsCopy` — each paired with a `resolve*` fallback helper (`/api/site-content/*`) | per-surface SSR fetch + `resolve*` |
| `useBlogPosts` | Blog listing + detail (`/api/blog-posts`) | `useBlogPosts()`, `useBlogPost(slug)` |
| `useFaq` | FAQ items (`/api/faq`) | `useFaq()` (SSR fetch) |
| `useJobOpenings` | Careers openings (`/api/job-openings`) | `useJobOpenings()` (SSR fetch) |
| `useScreeningPackages` | Private-screening packages (`/api/screening-packages`) | `useScreeningPackages()` (SSR fetch) |
| `usePublicLocations` | Public locations catalog (used by `/locations`, `/locations/:slug`, home strip, movie detail distance captions). SSR-friendly wrapper around `/api/locations`. | `fetchLocations`, `fetchBySlug` |
| `useLocations` | **Reduced role.** Owns `locations` catalog + `activeLocation` *preferred default* (localStorage). No longer drives content fetches; readers limited to the booking-time location picker and the `LocationPreferenceSwitcher` UI. | `setLocation`, `fetchLocations` |
| `useGeolocation` | **New.** Browser Geolocation API wrapper. Strict opt-in. Caches granted coords in `sessionStorage`. Provides `distanceTo(location)` Haversine helper. SSR returns `status: 'idle'`. | `request`, `distanceTo` |
| `useCart` | Shopping cart state | `addTicket`, `removeTicket`, `addFoodItem`, `applyPromo`, `clear` |
| `useAuth` | Authentication | `login`, `register`, `logout`, `session` |
| `useAccount` | User profile and history | `fetchProfile`, `updateProfile`, `fetchOrders`, `fetchLoyaltyPoints` |
| `useSeatSelection` | Auditorium seat picking | `selectSeat`, `deselectSeat`, `selectedSeats`, `totalPrice` |
| `useGiftCards` | Gift card operations | `purchase`, `checkBalance` |
| `useToast` | Toast notifications | `show`, `success`, `error`, `dismiss` |

Some composables manage UI/interaction state rather than an `/api/*` data domain and so sit outside the table: `useSeo` (per-page canonical/OG/JSON-LD via the `buildSeoHead` builder — see § SEO), `useBridgeFilters` (page-scoped `/whats-on` chip-filter state), `usePurchaseStep` (shared purchase step-indicator state), `useGiftCardComposer` (local form + live-preview state for the `/gift-cards` composer), `useClock` (SSR-safe live clock), and `useFocusTrap` (modal/drawer focus trapping).

---

## Middleware

Two route middleware guards protect page access.

### `auth.ts`

Applied to `/account/**` routes only. Checks for an active session via `useAuth`. Redirects unauthenticated users to `/auth/login` with a `redirect` query parameter so they return after signing in.

Purchase routes (`/purchase/**`) are intentionally public — guest checkout is supported. Authenticated users get enhanced features (loyalty points, saved payment methods, order history) but authentication is not required to buy tickets.

### `guest.ts`

Applied to `/auth/**` routes. Redirects already-authenticated users to `/account` to prevent re-login.
