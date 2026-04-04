# Site Architecture

Technical architecture reference for the movie theatre web application, built on Nuxt 4.

---

## Project Structure

The application follows Nuxt 4 conventions with the `app/` source directory and a co-located `server/` directory for backend-for-frontend (BFF) routes.

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
│   │                    BookingConfirmation, FoodPreOrderPanel, PromoCode
│   ├── calendar/        CalendarGrid, CalendarDayCell, CalendarEventList,
│   │                    CalendarFilters
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

server/
├── api/
│   ├── auth/            Login, register, logout, session
│   ├── movies/          Now playing, coming soon, detail by slug
│   ├── showtimes/       Showtimes by movie/date, seat availability
│   ├── bookings/        Create booking, confirm payment
│   ├── calendar/        Calendar events, filtering
│   ├── account/         Profile, order history, loyalty points
│   ├── gift-cards/      Purchase, check balance
│   ├── rentals/         Rental inquiry submission
│   └── contact.post.ts  Contact form handler
├── middleware/
│   └── auth.ts          Server-side session verification
└── utils/
    ├── tmdb.ts          TMDB API client and response mapping
    └── auth.ts          Session helpers, password hashing
```

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

- Header shows only the logo and a step indicator
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
| `/` | Home | ISR | 30 min | Content changes infrequently, SEO important |
| `/movies` | Now Playing / Coming Soon | ISR | 30 min | Movie listings update a few times daily |
| `/movies/:slug` | Movie Detail | ISR | 10 min | Detail pages, SEO critical |
| `/whats-on` | Calendar View | ISR | 15 min | Calendar data changes moderately |
| `/events` | Events Listing | ISR | 15 min | Event schedule changes moderately |
| `/food-drink` | Food and Drink Menu | ISR | 30 min | Menu rarely changes |
| `/blog/:slug` | Blog Post | ISR | 10 min | Content updates, SEO critical |
| `/contact` | Contact Page | Prerendered | Build time | Static content |
| `/faq` | FAQ | Prerendered | Build time | Static content |
| `/accessibility` | Accessibility Statement | Prerendered | Build time | Static content |
| `/careers` | Careers | Prerendered | Build time | Static content |
| `/purchase/**` | Seat Selection, Checkout | Client-only | — | Real-time seat data, auth context |
| `/account/**` | Profile, Orders, Loyalty | Client-only | — | User-specific data |
| `/auth/**` | Login, Register, Reset | Client-only | — | Auth forms, no SEO value |

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
    '/blog/**':           { isr: 600 },
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

---

## Dependencies

| Package | Purpose | Why This One |
|---|---|---|
| `nuxt` (4.x) | Meta-framework | File-based routing, SSR/ISR, server routes, auto-imports — a single tool that replaces a dozen decisions |
| `@nuxt/content` | Blog and FAQ content | Markdown authoring with frontmatter, query API, zero infrastructure required |
| `@stripe/stripe-js` | Client-side payments | PCI-compliant card collection via Stripe Elements without handling raw card data |
| `stripe` | Server-side payments | PaymentIntent creation, webhook handling, customer management |
| `nuxt-auth-utils` | Authentication | Session-based auth with encrypted cookies, OAuth-ready, minimal configuration |

---

## Environment Variables

All environment variables use the `NUXT_` prefix for automatic mapping to Nuxt runtime config. Variables prefixed with `NUXT_PUBLIC_` are exposed to the client bundle; all others remain server-only.

```bash
# TMDB
NUXT_TMDB_API_KEY=                        # TMDB API v3 key for movie data

# Stripe
NUXT_STRIPE_SECRET_KEY=                   # Stripe secret key (server only)
NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=       # Stripe publishable key (client)

# Auth
NUXT_SESSION_PASSWORD=                    # 32+ char secret for session encryption

# App
NUXT_PUBLIC_SITE_URL=                     # Base URL for SEO, OG tags, emails
```

These map to `useRuntimeConfig()` in code:

```ts
const config = useRuntimeConfig()

// Server-only
config.tmdbApiKey
config.stripeSecretKey
config.sessionPassword

// Client-accessible
config.public.stripePublishableKey
config.public.siteUrl
```

---

## BFF Pattern

All external API calls route through Nuxt server routes in `server/api/`. The frontend never calls TMDB, Stripe, or any third-party API directly.

### Why

- **Key protection.** API keys stay on the server. The client bundle contains zero secrets.
- **Data merging.** A single `/api/movies/:slug` response combines TMDB movie metadata with local showtime and pricing data, eliminating client-side waterfall requests.
- **Caching.** Server routes use `cachedEventHandler` for in-memory and ISR-compatible caching, reducing upstream API calls.
- **Stable contract.** Frontend composables depend on our own API shape, not a third party's. If TMDB changes their response format, only `server/utils/tmdb.ts` needs updating.
- **SSR compatibility.** `useFetch` calls to `/api/*` work identically during server-side rendering and client-side navigation.

### Data Flow

```
┌─────────┐     useFetch('/api/movies')     ┌──────────────┐     fetch()     ┌──────────┐
│ Browser  │ ──────────────────────────────► │ Server Route │ ──────────────► │ TMDB API │
│          │ ◄────────────────────────────── │              │ ◄────────────── │          │
└─────────┘     Merged JSON response        │  + merge     │   Movie data   └──────────┘
                                            │  local data  │
                                            └──────────────┘

┌─────────┐     useFetch('/api/showtimes')  ┌──────────────┐
│ Browser  │ ──────────────────────────────► │ Server Route │ ──► Local mock JSON
│          │ ◄────────────────────────────── │              │
└─────────┘     Showtime data               └──────────────┘

┌─────────┐     Stripe Elements             ┌──────────┐
│ Browser  │ ──────────────────────────────► │ Stripe.js│  (tokenizes card client-side)
│          │                                 └──────────┘
│          │     POST /api/bookings           ┌──────────────┐     Stripe SDK  ┌────────────┐
│          │ ──────────────────────────────► │ Server Route │ ──────────────► │ Stripe API │
│          │ ◄────────────────────────────── │              │ ◄────────────── │            │
└─────────┘     Booking confirmation        └──────────────┘   PaymentIntent └────────────┘
```

---

## Composable Responsibilities

Each composable owns a specific data domain and wraps the corresponding `/api/*` calls.

| Composable | Domain | Key Methods |
|---|---|---|
| `useMovies` | Movie listings and detail | `fetchNowPlaying`, `fetchComingSoon`, `fetchBySlug` |
| `useShowtimes` | Showtime schedules | `fetchByMovie`, `fetchByDate`, `fetchSeatMap` |
| `useCalendarEvents` | Calendar and events | `fetchByMonth`, `fetchByDateRange` |
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
