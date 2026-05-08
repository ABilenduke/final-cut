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
- `app/Services/` — Business logic (TmdbService, StripeService, SeatAvailabilityService, etc.)
- `app/Filament/Resources/` — Admin panel resources (movies, showtimes, locations, auditoriums, bookings, customers, menu, promo codes, gift cards, calendar events)
- `app/Filament/Pages/` — Admin custom pages (BookingLookup, CancelledShowtimeFollowup)
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
| `/whats-on` | Calendar View | ISR | 15 min | Calendar data changes moderately |
| `/events` | Events Listing | ISR | 15 min | Event schedule changes moderately |
| `/events/:slug` | Event Detail | ISR | 15 min | Detail pages, SEO important |
| `/food-drink` | Food and Drink Menu (shared, cross-location, with per-item availability arrays) | ISR | 30 min | Menu rarely changes |
| `/locations` | All Locations (alphabetical, geolocation re-orders post-hydration) | ISR | 30 min | Venue list rarely changes |
| `/locations/:slug` | Location Detail (LocalBusiness JSON-LD, now-showing-here strip) | ISR | 30 min | Detail pages, local-SEO critical |
| `/blog/:slug` | Blog Post | ISR | 10 min | Content updates, SEO critical |
| `/gift-cards` | Gift Cards (composer + live preview, balance lookup strip) | ISR | 30 min | Editorial content rarely changes; suppresses global `NeuralTicker` via `definePageMeta({ hideTicker: true })` so the balance strip can occupy the chrome slot |
| `/gift-cards/bulk` | Bulk Gifting placeholder (corporate concierge CTA) | Prerendered | Build time | Static placeholder until the bulk-gifting form ships |
| `/contact` | Contact Page | Prerendered | Build time | Static content |
| `/faq` | FAQ | Prerendered | Build time | Static content |
| `/accessibility` | Accessibility Statement | Prerendered | Build time | Static content |
| `/careers` | Careers | Prerendered | Build time | Static content |
| `/purchase/**` | Seat Selection, Checkout | Client-only | — | Real-time seat data, auth context |
| `/account/**` | Profile, Orders, Loyalty | Client-only | — | User-specific data |
| `/auth/**` | Login, Register, Reset | Client-only | — | Auth forms, no SEO value |
| `admin.${APP_DOMAIN}/**` | Filament admin panel | Server-rendered (Laravel) | — | Auth-gated, IP-allowlisted, no Nuxt involvement |

All rendering strategies are configured via `routeRules` in `nuxt.config.ts`:

```ts
export default defineNuxtConfig({
  routeRules: {
    '/':                  { isr: 1800 },
    '/movies':            { isr: 1800 },
    '/movies/**':         { isr: 600 },
    '/food-drink':        { isr: 1800 },
    '/whats-on':          { isr: 900 },
    '/events':            { isr: 900 },
    '/events/**':         { isr: 900 },
    '/locations':         { isr: 1800 },
    '/locations/**':      { isr: 1800 },
    '/blog/**':           { isr: 600 },
    '/gift-cards':        { isr: 1800 },
    '/gift-cards/bulk':   { prerender: true },
    '/contact':           { prerender: true },
    '/faq':               { prerender: true },
    '/accessibility':     { prerender: true },
    '/careers':           { prerender: true },
    '/purchase/**':       { ssr: false },
    '/account/**':        { ssr: false },
    '/auth/**':           { ssr: false },
  },
})
```

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
| `nuxt-auth-utils` | Frontend SSR auth state | Stores user state in a sealed encrypted cookie for Nuxt SSR hydration. Complements Laravel Sanctum, which handles actual API authentication via session cookies. These are two complementary systems: Sanctum authenticates, nuxt-auth-utils hydrates. |

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
NUXT_SESSION_PASSWORD=                    # 32+ char secret for nuxt-auth-utils cookie encryption
```

Map links use raw `https://maps.google.com/?q=<lat>,<lng>` URLs constructed client-side from the `latitude`/`longitude` columns on the locations payload. No API key, no env var, no third-party SDK.

---

## Sitemap

The app emits `sitemap.xml` at the root of the public domain via `@nuxtjs/sitemap`. It must list every public content URL so search engines can discover the full slate without crawling derivation:

- `/`
- `/movies`, every movie's `/movies/:slug`
- `/whats-on`
- `/events`, every event's `/events/:slug`
- `/food-drink`
- `/locations`, every location's `/locations/:slug`
- `/faq`, `/contact`, `/accessibility`, `/careers`, `/gift-cards`, `/gift-cards/bulk`, `/private-screenings`
- `/blog`, every post's `/blog/:slug`

Excluded: `/purchase/**`, `/account/**`, `/auth/**` (these carry `X-Robots-Tag: noindex` from `routeRules` and `<meta name="robots" content="noindex">` in their templates). The admin subdomain has its own robots policy.

Dynamic URLs are sourced at sitemap generation time from `/api/movies`, `/api/calendar/events`, and `/api/locations`; blog posts come from `@nuxt/content`'s `queryContent`. Per-URL `lastmod` is derived from the underlying record's `updated_at`. The sitemap is regenerated on every ISR revalidation tick (`@nuxtjs/sitemap` handles this transparently when configured against dynamic sources).

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
| `usePublicLocations` | Public locations catalog (used by `/locations`, `/locations/:slug`, home strip, movie detail distance captions). SSR-friendly wrapper around `/api/locations`. | `fetchLocations`, `fetchBySlug` |
| `useLocations` | **Reduced role.** Owns `locations` catalog + `activeLocation` *preferred default* (localStorage). No longer drives content fetches; readers limited to the booking-time location picker and the `LocationPreferenceSwitcher` UI. | `setLocation`, `fetchLocations` |
| `useGeolocation` | **New.** Browser Geolocation API wrapper. Strict opt-in. Caches granted coords in `sessionStorage`. Provides `distanceTo(location)` Haversine helper. SSR returns `status: 'idle'`. | `request`, `distanceTo` |
| `useCart` | Shopping cart state | `addTicket`, `removeTicket`, `addFoodItem`, `applyPromo`, `clear` |
| `useAuth` | Authentication | `login`, `register`, `logout`, `session` |
| `useAccount` | User profile and history | `fetchProfile`, `updateProfile`, `fetchOrders`, `fetchLoyaltyPoints` |
| `useSeatSelection` | Auditorium seat picking | `selectSeat`, `deselectSeat`, `selectedSeats`, `totalPrice` |
| `useGiftCards` | Gift card operations | `purchase`, `checkBalance` |
| `useToast` | Toast notifications | `show`, `success`, `error`, `dismiss` |

---

## Middleware

Two route middleware guards protect page access.

### `auth.ts`

Applied to `/account/**` routes only. Checks for an active session via `useAuth`. Redirects unauthenticated users to `/auth/login` with a `redirect` query parameter so they return after signing in.

Purchase routes (`/purchase/**`) are intentionally public — guest checkout is supported. Authenticated users get enhanced features (loyalty points, saved payment methods, order history) but authentication is not required to buy tickets.

### `guest.ts`

Applied to `/auth/**` routes. Redirects already-authenticated users to `/account` to prevent re-login.
