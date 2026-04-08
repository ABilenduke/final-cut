# Frontend v1 Progress

> **Plans:** [Frontend v1 Index](../plans/frontend/v1/00-index.md)
> **Status:** In Progress

---

## Plan 01: Project Setup & Types
**Status:** ✅ Complete
**Completed:** 2026-04-06

---

## Plan 03: UI Primitive Components
**Status:** ✅ Complete
**Completed:** 2026-04-06

---

## Plan 05: Composables & API Integration
**Status:** ✅ Complete
**Started:** 2026-04-07
**Completed:** 2026-04-07

### Work Done
- [2026-04-07] Created `api.ts` — centralized API client with `apiFetch` (imperative) and `useApiFetch` (SSR-compatible). CSRF auto-bootstrap before first mutation. XSRF token read from `document.cookie`. Error envelope parsing. Idempotency-Key header support.
- [2026-04-07] Completed `useLocations` — added `fetchLocations()` calling `GET /api/locations`, populating locations state which triggers existing localStorage rehydration
- [2026-04-07] Created `useMovies` — `nowShowing`, `comingSoon`, `getMovie` via `useApiFetch`
- [2026-04-07] Created `useShowtimes` — location-scoped `getShowtimes`, `getShowtime` via `useApiFetch`
- [2026-04-07] Completed `useAuth` — `login`, `register`, `logout`, `fetchUser`, `forgotPassword`, `resetPassword` via `apiFetch`. Loading/error refs. Logout always clears user.
- [2026-04-07] Created `useCart` — ephemeral `useState` cart with 15-minute session timer (10-min warning), seat/food/promo/gift card management, computed subtotal/total in cents
- [2026-04-07] Created `useSeatSelection` — local `ref` state with grid keyboard navigation (wrap/clamp), conflict resolution on availability refresh with toast notifications
- [2026-04-07] Created `useCalendarEvents` — `getEvents`, `getEvent` via `useApiFetch`
- [2026-04-07] Created `useAccount` — profile CRUD, paginated orders, bookings, loyalty, payment methods. Mix of `useApiFetch` (reads) and `apiFetch` (mutations)
- [2026-04-07] Created `useGiftCards` — purchase with idempotency key, confirm, balance check via `apiFetch`
- [2026-04-07] `useToast` was already complete from Plan 04 — no changes needed

### Decisions
- [2026-04-07] Two API functions pattern: `apiFetch` for imperative calls (mutations, auth) and `useApiFetch` for SSR-compatible reads. Composables explicitly import from `~/utils/api`
- [2026-04-07] XSRF token read from `document.cookie` (not `useCookie`) — avoids Nuxt auto-import mocking complexity in tests
- [2026-04-07] Cart timer handles are module-scoped (not useState) — non-serializable, process-level
- [2026-04-07] Seat selection uses `new Set()` reassignment on every mutation for Vue reactivity (Vue doesn't track Set mutations)
- [2026-04-07] Cart returns `readonly()` wrappers on state refs — mutations only through named methods
- [2026-04-07] All composables that use `apiFetch`/`useApiFetch` import explicitly (not auto-import) for clean test mocking via `vi.mock('~/utils/api')`
- [2026-04-07] Configured `vitest.config.ts` `environmentOptions` was reverted — caused module resolution failures. Runtime config defaults to empty string in tests.

### Files Changed
- `frontend/app/utils/api.ts` — created (centralized API client)
- `frontend/app/composables/useLocations.ts` — added fetchLocations()
- `frontend/app/composables/useAuth.ts` — replaced stub with full implementation
- `frontend/app/composables/useMovies.ts` — created
- `frontend/app/composables/useShowtimes.ts` — created
- `frontend/app/composables/useCalendarEvents.ts` — created
- `frontend/app/composables/useCart.ts` — created
- `frontend/app/composables/useSeatSelection.ts` — created
- `frontend/app/composables/useAccount.ts` — created
- `frontend/app/composables/useGiftCards.ts` — created
- `frontend/vitest.config.ts` — unchanged (reverted)
- `frontend/tests/utils/api.test.ts` — created (14 tests)
- `frontend/tests/composables/useLocations.test.ts` — extended (9 tests)
- `frontend/tests/composables/useAuth.test.ts` — created (12 tests)
- `frontend/tests/composables/useMovies.test.ts` — created (4 tests)
- `frontend/tests/composables/useShowtimes.test.ts` — created (3 tests)
- `frontend/tests/composables/useCalendarEvents.test.ts` — created (4 tests)
- `frontend/tests/composables/useAccount.test.ts` — created (9 tests)
- `frontend/tests/composables/useGiftCards.test.ts` — created (3 tests)
- `frontend/tests/composables/useCart.test.ts` — created (16 tests)
- `frontend/tests/composables/useSeatSelection.test.ts` — created (14 tests)

---

## Plan 04: Layouts & Shell Components
**Status:** ✅ Complete
**Started:** 2026-04-07
**Completed:** 2026-04-07

### Work Done
- [2026-04-07] Created SkipNav — hidden skip link, visible on :focus-visible, links to #main-content
- [2026-04-07] Created SiteHeader — fixed 4rem header with logo, desktop nav (gold active underline), native `<select>` location switcher, auth controls (guest/authenticated), hamburger + mobile menu with focus trap (teleported to body)
- [2026-04-07] Created SiteFooter — secondary nav, legal section, theater info (social links deferred until real URLs available)
- [2026-04-07] Created NeuralTicker — sticky scrolling feed below header, pause/play, sr-only accessible link list, reduced-motion support
- [2026-04-07] Created MobileNav — fixed bottom bar below screen-md, 5 items, 3rem touch targets
- [2026-04-07] Created SidebarNav — 3-state responsive (15rem rail, 4rem icon rail, bottom bar), gold left edge gradient accent
- [2026-04-07] Created PurchaseStepIndicator — 3 labeled steps, navigable/completed/current states, aria-current="step"
- [2026-04-07] Created default.vue layout — SkipNav + SiteHeader + NeuralTicker + main + SiteFooter + MobileNav + CvToastContainer
- [2026-04-07] Created account.vue layout — duplicated shell (Nuxt 4 no nesting) + SidebarNav with account nav items
- [2026-04-07] Created purchase.vue layout — minimal header with logo, named slots for step-indicator/timer/cart, no footer
- [2026-04-07] Created blank.vue layout — centered logo + content, no chrome
- [2026-04-07] Created useLocations composable stub with localStorage rehydration
- [2026-04-07] Added 14 new icons (menu, home, movie, calendar, account, location, pause, play, etc.)
- [2026-04-07] Updated app.vue to wrap NuxtPage in NuxtLayout

### Decisions
- [2026-04-07] Location switcher uses native `<select>` instead of custom dropdown — full keyboard/AT accessibility out of the box
- [2026-04-07] Mobile menu teleported to `<body>` for proper focus trap isolation (same pattern as CvModal)
- [2026-04-07] NeuralTicker sr-only section renders accessible `<ul>` with real links when items have hrefs, flat text otherwise
- [2026-04-07] Account layout duplicates shell structure rather than nesting — Nuxt 4 layouts don't nest
- [2026-04-07] Social links removed from footer until real URLs and platform-specific icons are available
- [2026-04-07] NeuralTicker data starts hardcoded; data integration comes in Plan 05/06
- [2026-04-07] CartSummary in purchase layout uses named slot placeholder; wired in Plan 08
- [2026-04-07] NeuralTicker made sticky (top: 4rem) below fixed header, making 6rem padding-top on layouts correct

### Files Changed
- `frontend/app/app.vue` — added NuxtLayout wrapper
- `frontend/app/components/ui/icons.ts` — added 14 new icon paths
- `frontend/app/components/layout/SkipNav.vue` — created
- `frontend/app/components/layout/SiteHeader.vue` — created
- `frontend/app/components/layout/SiteFooter.vue` — created
- `frontend/app/components/layout/NeuralTicker.vue` — created
- `frontend/app/components/layout/MobileNav.vue` — created
- `frontend/app/components/layout/SidebarNav.vue` — created
- `frontend/app/components/booking/PurchaseStepIndicator.vue` — created
- `frontend/app/composables/useLocations.ts` — created
- `frontend/app/layouts/default.vue` — created
- `frontend/app/layouts/account.vue` — created
- `frontend/app/layouts/purchase.vue` — created
- `frontend/app/layouts/blank.vue` — created
- Storybook stories for all 7 components

---

## Plan 07: Calendar & Events Domain
**Status:** ✅ Complete
**Started:** 2026-04-08
**Completed:** 2026-04-08

### Work Done
- [2026-04-08] Created CalendarFilters — view toggle (month/week/list) as radiogroup, event type checkboxes, accessibility filter checkboxes with plain language labels
- [2026-04-08] Created CalendarDayCell — day number, event indicator dots (color-coded by type), selected/today/outsideMonth states, accessibility indicator, aria-label with event count
- [2026-04-08] Created CalendarGrid — month view (6×7 grid with weekday headers), week view (7-day strip), list view (events sorted by date grouped by date). Navigation prev/next with year wrapping. Keyboard navigation (arrows, Home/End, PageUp/Down). Roving tabindex.
- [2026-04-08] Created CalendarEventList — events for selected day grouped by type (showtimes, special events, loyalty exclusives), with time, title, type badge, accessibility badges, Members Only badge. NuxtLink for events with URLs, empty state.
- [2026-04-08] Created EventListCard — CvCard with 4:3 event image, date/type/loyalty badges, truncated description, "Learn More" link to /events/:slug
- [2026-04-08] Created EventDetail — full event content with title, formatted date/time range, description, accessibility badges, loyalty badge, includes list, pricing section, Get Tickets/RSVP CTA
- [2026-04-08] Created /whats-on page — CalendarFilters + CalendarGrid + CalendarEventList, URL query param state (month, year, view, date, type, accessibility), deep link support for accessibility filters
- [2026-04-08] Created /events listing page — featured event hero (Wide Frame), upcoming events ensemble grid
- [2026-04-08] Created /events/:slug detail page — Wide Frame hero image, Close-Up EventDetail body, 404 error state

### Decisions
- [2026-04-08] CalendarEventList uses `NuxtLink` with `v-if` instead of dynamic `resolveComponent()` — test environment doesn't resolve components via resolveComponent but auto-imports work in templates
- [2026-04-08] Month grid always renders 42 cells (6 weeks) for consistent layout
- [2026-04-08] Week view calculates Monday-based week from selected date
- [2026-04-08] CalendarDayCell uses UTC parsing for date-only strings to avoid timezone day-shift
- [2026-04-08] Events listing fetches current + next month to show upcoming events across month boundary
- [2026-04-08] whats-on page defaults to current month/year, month view, and today's date when no query params present

### Files Changed
- `frontend/app/components/calendar/CalendarFilters.vue` — created
- `frontend/app/components/calendar/CalendarDayCell.vue` — created
- `frontend/app/components/calendar/CalendarGrid.vue` — created
- `frontend/app/components/calendar/CalendarEventList.vue` — created
- `frontend/app/components/content/EventListCard.vue` — created
- `frontend/app/components/content/EventDetail.vue` — created
- `frontend/app/pages/whats-on.vue` — created
- `frontend/app/pages/events/index.vue` — created
- `frontend/app/pages/events/[slug].vue` — created
- Storybook stories for all 6 components (6 files)
- Vitest tests for all 6 components (6 files)
- Tests: 69 new tests (54 component tests + 15 CalendarGrid tests), 392 total passing

---

## Plan 08: Purchase Flow Domain
**Status:** 🟡 In Progress
**Started:** 2026-04-08

### Work Done
- [2026-04-08] Wave 1 — Created 5 leaf components: AuditoriumScreenBar (decorative screen bar), AuditoriumLegend (5-swatch seat key), AuditoriumSeat (interactive seat cell with 5 visual states, selection animation, roving tabindex), CartSummary (sticky desktop sidebar + collapsible mobile bottom sheet with aria-live total), PromoCode (input + apply/remove flow)
- [2026-04-08] Wave 2 — Created AuditoriumGrid (WAI-ARIA grid with keyboard navigation, row labels, max 10 seat limit, live region announcements), CheckoutForm (Stripe Elements placeholder, billing name, guest email, loyalty opt-in checkbox, validation)
- [2026-04-08] Wave 3 — Created FoodPreOrderPanel (collapsed teaser + expanded category tabs + quantity controls), BookingConfirmation (QR code via `qrcode` npm, .ics calendar download, print tickets, booking details display)
- [2026-04-08] Wave 4 — Created 3 purchase pages: `/purchase/[showtimeId]` (seat selection), `/purchase/checkout` (food + payment), `/purchase/confirmation/[bookingId]` (confirmation + booking lookup)
- [2026-04-08] Created `usePurchaseStep` composable — shared purchase step state (currentStep, completedSteps, navigableSteps) used by pages and rendered by purchase layout
- [2026-04-08] Updated purchase layout — renders PurchaseStepIndicator and CartSummary directly from composable state (no named slots needed)
- [2026-04-08] Added 8 new icons: wheelchair, accessible, star, print, calendar-add, minus, plus, receipt
- [2026-04-08] Installed `qrcode` + `@types/qrcode` for booking confirmation QR generation

### Decisions
- [2026-04-08] Purchase pages use `usePurchaseStep` composable instead of named layout slots — Nuxt pages can't reliably fill layout slots via `definePageMeta({ layout })`. Layout reads reactive composable state directly
- [2026-04-08] CartSummary rendered in layout (not pages) — reads from global useCart state, avoids duplication across 3 pages
- [2026-04-08] Stripe Elements is a placeholder (card input area with styled mount point) — actual Stripe.js integration deferred to when real Stripe keys are configured
- [2026-04-08] Food pre-order uses static `menuData` from `~/data/menu.ts` — API-backed menu deferred to when location-scoped food endpoint is wired
- [2026-04-08] QR code uses dark theme colors (#E5E2E1 on #131313) matching design system tokens
- [2026-04-08] Checkout error handling follows PURCHASE_FLOW.md spec: 409→redirect to seats, 402→stay on page, 410→clear cart, 500→generic toast

### Files Changed
- `frontend/app/components/booking/AuditoriumScreenBar.vue` — created
- `frontend/app/components/booking/AuditoriumLegend.vue` — created
- `frontend/app/components/booking/AuditoriumSeat.vue` — created
- `frontend/app/components/booking/AuditoriumGrid.vue` — created
- `frontend/app/components/booking/CartSummary.vue` — created
- `frontend/app/components/booking/PromoCode.vue` — created
- `frontend/app/components/booking/CheckoutForm.vue` — created
- `frontend/app/components/booking/FoodPreOrderPanel.vue` — created
- `frontend/app/components/booking/BookingConfirmation.vue` — created
- `frontend/app/composables/usePurchaseStep.ts` — created
- `frontend/app/pages/purchase/[showtimeId].vue` — created
- `frontend/app/pages/purchase/checkout.vue` — created
- `frontend/app/pages/purchase/confirmation/[bookingId].vue` — created
- `frontend/app/layouts/purchase.vue` — updated (renders step indicator + cart from composable state)
- `frontend/app/components/ui/icons.ts` — added 8 new icons
- `frontend/package.json` — added qrcode, @types/qrcode
- `frontend/deno.lock` — updated

---

## Plan 06: Movie Feature Domain
**Status:** 🟡 In Progress
**Started:** 2026-04-07

### Work Done
- [2026-04-07] Created MovieRatingBadge — accent CvBadge, rating formatted to one decimal
- [2026-04-07] Created MovieCard — poster, title, genres (capped at 3), rating badge or release date based on `showShowtimes` prop, "View Showtimes" or "Notify Me" CTA
- [2026-04-07] Created MovieHero — full-bleed backdrop with vignette bloom, title, tagline, cinematic reveal animation, reduced-motion safe
- [2026-04-07] Created MovieDetail — Establishing Shot 65/35 layout with movie info left, ShowtimeSelector right
- [2026-04-07] Created MovieCastList — horizontal scroll, circular profile photos, actor/character labels
- [2026-04-07] Created MovieTrailerEmbed — responsive 16:9 YouTube iframe, lazy-loaded
- [2026-04-07] Created ShowtimeSelector — date tabs (horizontally scrollable), time slot buttons, links to `/purchase/:showtimeId`
- [2026-04-07] Created HomeFeaturedHero — backdrop hero with "Get Tickets" CTA linking to movie detail
- [2026-04-07] Created HomeEventStrip — week's special events/loyalty exclusives, cross-month fetch, truncated descriptions, max 5
- [2026-04-07] Created home page (`/`) — hero, now showing ensemble, event strip, coming soon ensemble
- [2026-04-07] Created movies listing page (`/movies`) — tab bar (now showing / coming soon), ensemble grid; genre filtering still TODO
- [2026-04-07] Created movie detail page (`/movies/:slug`) — hero, detail, cast, trailer, showtime selector
- [2026-04-07] Created `selectFeaturedMovie` utility — picks the most recently released now-showing movie with a backdrop for hero
- [2026-04-07] Created `weekRange` utility — computes Mon-Sun week range with cross-month support
- [2026-04-07] Created `formatTime`/`formatWeekdayDate` helpers in formatDate utility
- [2026-04-07] Added locations plugin (`locations.ts`) — calls `initializeLocations()` on app:mounted
- [2026-04-07] Added `locationsReady` + `initializeLocations()` to useLocations composable
- [2026-04-07] Added `resolveApiBaseUrl()` to api.ts — origin fallback for SSR
- [2026-04-07] Added `pathPrefix: false` to nuxt.config.ts components config
- [2026-04-07] Added CvToastContainer component for layout toast rendering
- [2026-04-07] Updated MovieSeeder to use real TMDB IDs for richer dev data
- [2026-04-07] Updated nginx CSP to allow TMDB images and YouTube trailer embeds

### Decisions
- [2026-04-07] HomeFeaturedHero simplified to link to movie detail page — location-dependent "Get Tickets at 7:00 PM" CTA deferred to Plan 08 (purchase flow) when the full location→showtime→purchase pipeline is built
- [2026-04-07] MovieCard uses `showShowtimes: boolean` prop (matches COMPONENT_INVENTORY spec) — showtime time pills require a batched showtimes endpoint not yet available; "View Showtimes" link serves as functional CTA until then
- [2026-04-07] Removed QuickShowtimeStrip (not in any spec, date pills were non-functional UI)
- [2026-04-07] Removed useAppRoutes composable (premature abstraction, broke all layout components) — hardcoded nav items restored; route-enabled filtering deferred to Plan 13
- [2026-04-07] MovieRatingBadge accepts `rating?: number | null` — backend column is nullable (un-enriched movies), badge hides when null
- [2026-04-07] Locations initialized via Nuxt plugin (`app:mounted` hook) instead of watch-based auto-rehydrate — explicit lifecycle, `locationsReady` flag prevents flash of empty state

### Files Changed
- `frontend/app/components/movie/MovieRatingBadge.vue` — created
- `frontend/app/components/movie/MovieCard.vue` — created
- `frontend/app/components/movie/MovieHero.vue` — created
- `frontend/app/components/movie/MovieDetail.vue` — created
- `frontend/app/components/movie/MovieCastList.vue` — created
- `frontend/app/components/movie/MovieTrailerEmbed.vue` — created
- `frontend/app/components/movie/ShowtimeSelector.vue` — created
- `frontend/app/components/home/HomeFeaturedHero.vue` — created
- `frontend/app/components/home/HomeEventStrip.vue` — created
- `frontend/app/components/ui/CvToastContainer.vue` — created
- `frontend/app/pages/index.vue` — created (home page)
- `frontend/app/pages/movies/index.vue` — created (movie listings)
- `frontend/app/pages/movies/[slug].vue` — created (movie detail)
- `frontend/app/utils/selectFeaturedMovie.ts` — created
- `frontend/app/utils/weekRange.ts` — created
- `frontend/app/utils/formatDate.ts` — extended (formatTime, formatWeekdayDate)
- `frontend/app/plugins/locations.ts` — created
- `frontend/app/composables/useLocations.ts` — added locationsReady, initializeLocations
- `frontend/app/utils/api.ts` — added resolveApiBaseUrl
- `frontend/nuxt.config.ts` — added pathPrefix: false
- `frontend/.env.example` — created
- `backend/database/seeders/MovieSeeder.php` — updated to real TMDB IDs
- `nginx/templates/conf.d/default.conf.template` — updated CSP
- `Makefile` — added artisan target
- Storybook stories for all new components
- Tests for MovieRatingBadge, MovieCard, HomeEventStrip, useLocations, api.ts, locations plugin

---

## Plan 02: Design System CSS Foundation
**Status:** ✅ Complete
**Started:** 2026-04-06
**Completed:** 2026-04-06

### Work Done
- [2026-04-06] Created `tokens.css` — all color, spacing, z-index, easing, duration, breakpoint, and icon tokens
- [2026-04-06] Created `reset.css` — browser reset, scrollbar-gutter, body defaults, reduced-motion global kill switch
- [2026-04-06] Created `typography.css` — font stacks, 15 type scale tokens (fluid clamp for display/headline), 14 usage classes
- [2026-04-06] Created `layouts.css` — global container, 6 named compositions (Establishing Shot, Rack Focus, Wide Frame, Close-Up, Ensemble, Auditorium), sidebar layout
- [2026-04-06] Created `utilities.css` — aspect ratios, touch targets, glassmorphism with @supports fallback, skeleton shimmer, sr-only, edge-catch, vignette-bloom, focus indicators (outline baseline with box-shadow enhancement and forced-colors fallback), float shadow
- [2026-04-06] Created `print.css` — suppresses chrome, optimizes booking confirmation, white/black permitted only here
- [2026-04-06] Updated `main.css` — import aggregator in correct order

### Decisions
- [2026-04-06] Tailwind CSS not installed and will not be used — design system uses CSS custom properties only
- [2026-04-06] Line-heights inferred for levels not explicitly specified: display 1.1, headline 1.2, title 1.3, body 1.6, label 1.4
- [2026-04-06] Full auditorium CSS implemented (not deferred) including seat sizing, scroll behavior, pinned labels, responsive breakpoints
- [2026-04-06] Auditorium label background uses `var(--auditorium-bg, var(--surface))` for contextual override by parent components
- [2026-04-06] Screen bar border-radius set to 0 (not 0.125rem) because 0.125rem on 0.25rem height creates pill shape violating design system's "no rounded corners" rule
- [2026-04-06] Codex adversarial review flagged two issues — both accepted and fixed:
  - Global reset no longer strips link/button affordances. Added `.link-reset` and `.button-reset` opt-in classes in utilities.css instead
  - Global `:focus-visible` now uses outline as baseline (safe in clipped containers + forced-colors). Box-shadow layered on top as enhancement only. Added `@media (forced-colors: active)` fallback

### Files Changed
- `frontend/app/assets/css/tokens.css` — created (all design tokens)
- `frontend/app/assets/css/reset.css` — created (browser reset)
- `frontend/app/assets/css/typography.css` — created (type system)
- `frontend/app/assets/css/layouts.css` — created (6 compositions + container + sidebar)
- `frontend/app/assets/css/utilities.css` — created (utility classes)
- `frontend/app/assets/css/print.css` — created (print stylesheet)
- `frontend/app/assets/css/main.css` — updated (import aggregator)
