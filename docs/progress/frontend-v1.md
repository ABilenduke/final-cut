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

## Plan 09: Auth & Account Domain
**Status:** ✅ Complete
**Started:** 2026-04-08
**Completed:** 2026-04-08

### Work Done
- [2026-04-08] Created 5 account domain components: ProfileForm (avatar + form fields + password change with diff-only emit), OrderHistoryList (accordion per order with pagination), UpcomingBookings (card list with poster/seats/screen), LoyaltyPointsCard (tier badge, points, perks, upgrade CTA), SavedPaymentMethods (card list with remove, add CTA)
- [2026-04-08] Created 4 auth pages: login, register, forgot-password, reset-password — all using blank layout, guest middleware, noindex, field-level error extraction from API error response
- [2026-04-08] Created 6 account pages: dashboard (65/35 grid with profile card + loyalty + quick links), profile (ProfileForm with save/refresh), orders (paginated via URL query), loyalty (points card + history + upgrade), bookings (UpcomingBookings), payment-methods (SavedPaymentMethods with add/remove)
- [2026-04-08] Created Storybook stories for all 5 account components
- [2026-04-08] Created Vitest tests for all 5 account components

### Decisions
- [2026-04-08] Auth pages use `error.value?.errors.find(e => e.field === field)?.message` pattern for field-level error extraction — composable already stores full ApiErrorResponse
- [2026-04-08] ProfileForm diffs changed fields against original props to avoid sending unchanged data — password fields only included when newPassword is non-empty
- [2026-04-08] Dashboard uses 65/35 Establishing Shot layout on desktop, stacks on mobile
- [2026-04-08] Account pages use `await profile()` / `await orders()` etc. from useAccount — returns useApiFetch (SSR-compatible)
- [2026-04-08] Payment methods add flow is a stub (calls addPaymentMethod which returns clientSecret) — actual Stripe SetupIntent flow deferred until Stripe keys configured
- [2026-04-08] Forgot-password shows inline success message (not toast) per plan spec — user stays on page to see confirmation

### Files Changed
- `frontend/app/components/account/ProfileForm.vue` — created
- `frontend/app/components/account/OrderHistoryList.vue` — created
- `frontend/app/components/account/UpcomingBookings.vue` — created
- `frontend/app/components/account/LoyaltyPointsCard.vue` — created
- `frontend/app/components/account/SavedPaymentMethods.vue` — created
- `frontend/app/pages/auth/login.vue` — created
- `frontend/app/pages/auth/register.vue` — created
- `frontend/app/pages/auth/forgot-password.vue` — created
- `frontend/app/pages/auth/reset-password.vue` — created
- `frontend/app/pages/account/index.vue` — created (dashboard)
- `frontend/app/pages/account/profile.vue` — created
- `frontend/app/pages/account/orders.vue` — created
- `frontend/app/pages/account/loyalty.vue` — created
- `frontend/app/pages/account/bookings.vue` — created
- `frontend/app/pages/account/payment-methods.vue` — created
- `frontend/app/components/account/ProfileForm.stories.ts` — created
- `frontend/app/components/account/OrderHistoryList.stories.ts` — created
- `frontend/app/components/account/UpcomingBookings.stories.ts` — created
- `frontend/app/components/account/LoyaltyPointsCard.stories.ts` — created
- `frontend/app/components/account/SavedPaymentMethods.stories.ts` — created
- `frontend/tests/components/account/ProfileForm.test.ts` — created
- `frontend/tests/components/account/OrderHistoryList.test.ts` — created
- `frontend/tests/components/account/UpcomingBookings.test.ts` — created
- `frontend/tests/components/account/LoyaltyPointsCard.test.ts` — created
- `frontend/tests/components/account/SavedPaymentMethods.test.ts` — created

---

## Plan 08: Purchase Flow Domain
**Status:** 🟡 In Progress
**Started:** 2026-04-08

### Design port — Seat Selection (2026-04-18)
Ported the `Final Cut Seat Selection.html` Claude Design handoff artifact into the existing `/purchase/[showtimeId]` route. Visual-fidelity rewrite that preserves the ARIA grid + roving-tabindex keyboard navigation and the `useCart` contract.

**Added components:**
- `SeatSelectionHero.vue` — Reel 01 eyebrow + italic-split headline (`<em>Pick your</em> vantage point.`) + right-aligned meta block with showtime + italic editorial lede
- `SeatSelectionControls.vue` — party stepper (± with disabled min/max), preference chips (Seat together / Aisle / Centre frame / Wheelchair — visual toggle only for v1), gold-outline `◉ Pick for me` CTA. Emits `update:partySize`, `update:preferences`, `auto-pick`
- `SeatAuditoriumStage.vue` — theatre panel: § 01 · Auditorium header with italic house name + seat-count info, clip-path screen bar with gold gradient, radial screen-bloom with italic `— house lights at 25% — the picture begins —` caption, floor-foot with entrance/exit arrows. Wraps `<AuditoriumGrid>`
- `SeatSelectionLegend.vue` — 4-up grid (Standard / Premiere / Companion / Accessible) with tier swatch + title + description + `from $X.XX`, plus dashed-top states row (Selected ✓ / Sold / Held) and prices-include-tax caption. Accepts `standardFrom`, `premiereFrom`, `accessibleFrom` in cents (min price per tier from real seat data); companion shows a static informational entry since the backend has no companion tier
- `SeatSightlineDiagram.vue` — § 02 · Sightline bay with editorial copy about the 1.4× sweet-spot rule, distance list, and inline SVG floor-plan diagram (screen, dashed sightline cone, arced rows, highlighted sweet-spot row, 22m vertical marker)
- `SeatSelectionHouseRules.vue` — 4-column footer: Hold / Late arrival cutoff / Move within the tier / Exchanges. Static editorial copy with gold emphasis spans
- `SeatSelectionRail.vue` — § Ω right rail: mini-poster (radial gradient hashed from movieId) + show title / screen / seats, `N of M` counter, perforated `SeatStub` list with empty state, totals (Seats / Booking fee Waived / Ticket total in gold), pay CTA with contextual label (`Pick seats to continue` / `Pick N more` / `Continue to concessions`) and right-aligned amount, hold note with mm:ss timer
- `SeatStub.vue` — perforated ticket stub row (`::before`/`::after` cut-out dots), gold tag, seat label + italic tier sublabel, price + Remove link. Maps `premium` section → `Premiere recliner` copy
- `SeatProjectionistPick.vue` — editorial bay: "Projectionist's pick" tag with gold underline rule, italic heading computed from passed seat ids, pilcrow paragraph, outline "Take these seats" CTA, M. Varga byline. Hidden when no pair can be found

**Rewritten existing components (props / emits preserved):**
- `AuditoriumSeat.vue` — new tier-aware swatches: standard (default grey), premiere (maroon-tinted, rounded-top), accessible (deep surface + ♿ glyph). Sold state now draws a decorative diagonal X overlay via `::before`; held state uses dashed warm-orange border. Visible seat numbers, hover-lifted `translateY(-0.125rem)` with gold border + tooltip on hover/focus showing `Row · Num` + price/status
- `AuditoriumGrid.vue` — dual row labels (letter on both ends), mid-row aisle gap computed per-row (inserts a `1.5rem` spacer at `Math.floor(count/2)` for rows ≥6 seats), compact-mobile cells (1.5rem at ≤40rem via `--seat-size` custom property). ARIA grid + roving tabindex + full keyboard nav untouched

**Touched:**
- `pages/purchase/[showtimeId].vue` — template rewritten as `<NuxtLayout name="purchase">` wrapper composing the new bays, with `#below-header` (hold timer, shown once first seat selected), `#header-extras` (location pill), and `#rail` slots (rail + projectionist's pick). Local `partySize` ref (default 2) + preferences ref. Auto-pick greedy-scans row preference order (center-out) for a contiguous run of `partySize` available, non-accessible seats. Projectionist's pick is a live `computed` that finds the first pair of adjacent available seats in the center-most row. Mobile viewports get an inline rail below the main column (rendered in main, hidden at ≥60rem where the layout rail takes over)

**Decisions:**
- Kept the existing `Seat.type` union (`standard | premium | accessible`) — added `premiere`/`companion` tiers would require backend (Laravel `AuditoriumSeeder` + `SeatType` enum) changes out of scope for this visual port. The design's "Premiere recliner" visual maps cleanly to `premium`; "Companion pair" appears in the legend as an informational-only entry (no selectable seats on the map)
- Preference chips are visual-only in v1 — they do not filter the seat map. Follow-up: wire `together`/`aisle`/`center` into the auto-pick search (`together` is already implicit since auto-pick requires a contiguous run; `aisle` should prefer seats adjacent to the computed mid-row gap; `center` should shrink the `rowPreferenceOrder` to the middle third)
- Per-seat ticket type dropdown (Adult / Senior / Student / Child / Member multipliers from the prototype) was **cut from v1** — introducing ticket-type pricing requires a backend pricing API and booking POST shape change. Each stub shows a static `Adult` sublabel
- Companion-pair forced selection (select both halves of a pair atomically) is **deferred** until the backend exposes a companion tier
- Projectionist's pick is runtime-computed (not hard-coded `F9/F10` as in the prototype) so it adapts to whichever auditorium is fetched. If no contiguous pair exists, the bay is hidden
- The hold strip is only rendered once `cart.seats.value.length > 0` (cart's existing timer starts on first `addSeat`). Existing `CheckoutHoldTimer` component is reused — no new timer UI needed
- Sightline diagram SVG copy was made auditorium-agnostic ("middle row" / "front" / "back" instead of specific row letters) so it reads correctly for any auditorium layout

**Files Changed**
- `frontend/app/components/booking/SeatSelectionHero.vue` — created
- `frontend/app/components/booking/SeatSelectionControls.vue` — created
- `frontend/app/components/booking/SeatAuditoriumStage.vue` — created
- `frontend/app/components/booking/SeatSelectionLegend.vue` — created
- `frontend/app/components/booking/SeatSightlineDiagram.vue` — created
- `frontend/app/components/booking/SeatSelectionHouseRules.vue` — created
- `frontend/app/components/booking/SeatSelectionRail.vue` — created
- `frontend/app/components/booking/SeatStub.vue` — created
- `frontend/app/components/booking/SeatProjectionistPick.vue` — created
- `frontend/app/components/booking/AuditoriumSeat.vue` — rewrote visuals (tier swatches, sold-X overlay, hover tooltip, ♿ glyph, visible seat number)
- `frontend/app/components/booking/AuditoriumGrid.vue` — dual row labels + mid-row aisle gap + compact-mobile sizing (keeps ARIA + keyboard behaviour)
- `frontend/app/pages/purchase/[showtimeId].vue` — full rewrite composing the new bays via `<NuxtLayout>` wrapper

---

### Design port — Checkout (2026-04-18)
Ported the `Final Cut Checkout.html` Claude Design handoff artifact into the existing `/purchase/checkout` route. Visual-fidelity rewrite that preserves all existing Stripe / 3DS / error-handling business logic.

**Added components:**
- `CheckoutHoldTimer.vue` — fixed strip under header: pulsing gold dot, live `{mm:ss}` countdown driven by `useCart.timeRemaining`, auditorium/row/seats summary, order ref, Change / Release links
- `CheckoutOrderCard.vue` — poster placeholder with gradient + glyph, italic-accent title split (`Dune<em>: Part Three</em>`), 6-cell meta grid (Date / Time / Auditorium / Runtime / Doors / Seats), large gold seat callout with Change seats link
- `CheckoutContactBay.vue` — §01 bay with sign-in / guest buttons (hidden when authenticated), divider, 2×2 field grid (name, email, phone, Reel Society ID); v-model-friendly
- `CheckoutPaymentBay.vue` — §02 bay with 4 method tabs (Card active, PayPal / Gift Card / Pay on Arrival visually disabled), Stripe card element in designed chrome, billing ZIP + country, context-aware save-card / loyalty opt-in; exposes `submit()` via `defineExpose`
- `CheckoutTotalsRail.vue` — § Ω right rail: itemized line items (tickets, concessions with live count, subtotal, booking fee, tax, discount, grand total in gold), disclosed booking fee (`$1.50`) + estimated CA tax (7.25%), gold `Confirm & pay` CTA, authorization note mirroring hold timer, TLS / PCI-DSS / 3-D Secure trust badges, Reel Society upsell card

**Rewritten existing components (props / emits preserved):**
- `FoodPreOrderPanel.vue` — §03 concessions grid: 3-column responsive card layout with gradient thumbnails, qty steppers, selected-item highlight. Dropped the collapsed teaser + category-tab flow per the design
- `PromoCode.vue` — §04 inset bay with applied chip, Remove link, plus optional 2-checkbox terms block wired via `v-model:acceptTerms` / `v-model:subscribeReel`

**Removed:**
- `CheckoutForm.vue` — logic absorbed into `CheckoutPaymentBay.vue`; delete was safe (single consumer was `checkout.vue`)

**Touched:**
- `pages/purchase/checkout.vue` — template rewritten to compose the new bays + order card + hold timer via `<NuxtLayout name="purchase">` wrapper with named slots (`below-header`, `header-extras`, `rail`). Script-side logic unchanged except for adding a template-ref trigger path so the rail's gold CTA calls `paymentBay.value?.submit()` while Stripe state lives in the payment bay
- `layouts/purchase.vue` — added `#below-header`, `#header-extras`, and `#rail` slots (`rail` defaults to the existing `<CartSummary>` so seat-selection page behavior is unchanged); cart aside width now responsive (`20rem` at ≥60rem, `25rem` at ≥68.75rem) so checkout can render the wider totals rail
- `composables/useCart.ts` — added reactive `timeRemaining` (seconds) that ticks every 1s while the 15-minute session is active; cleanup in `stopTimers()`
- `assets/css/checkout.css` — new page-scoped stylesheet for shared bay / field-row primitives; imported from `main.css`

**Decisions:**
- Wallet express row (Apple Pay / Google Pay) and functional PayPal / Gift Card / Pay on Arrival tabs are **out of scope** — they require Stripe Payment Request API work and a new booking-API shape. Tabs render as visually disabled with `Coming soon` brand marks to preserve the designed chrome
- Live card-mockup preview was **cut** — the Stripe Card Element iframe does not expose keystrokes, so a mirroring card preview would require building our own card form and losing PCI-DSS compliance. Kept the Stripe Element inside the designed bay frame instead
- Confirmation page ticket-style perforated layout is **deferred** — existing `BookingConfirmation.vue` is functional and retained
- CheckoutPaymentBay owns Stripe state; the rail CTA triggers submit via `defineExpose({ submit })` + a template ref on the page. This keeps Card Element ownership clean and avoids lifting Stripe state into the page
- Contact bay fields are rendered for design parity but not yet wired into the booking POST body (only `email` flows through to Stripe `billing_details`); a follow-up would map phone + Reel Society ID into a pre-purchase contact payload when the backend supports it
- Booking fee (`$1.50`) and tax (7.25%) in the rail are **display-only estimates** — the backend remains authoritative for final pricing at `POST /api/locations/:location/bookings`

**Tests added / updated:**
- Added Vitest specs: `CheckoutHoldTimer.test.ts`, `CheckoutOrderCard.test.ts`, `CheckoutContactBay.test.ts`, `CheckoutPaymentBay.test.ts` (Stripe mocked via `vi.mock('@stripe/stripe-js')`), `CheckoutTotalsRail.test.ts`
- Rewrote `PromoCode.test.ts` for the new bay-inset markup (selectors changed, props / emits unchanged plus new `update:acceptTerms` coverage)
- `FoodPreOrderPanel` has no existing unit spec (only exercised via the Playwright E2E)

**Files Changed**
- `frontend/app/composables/useCart.ts` — added `timeRemaining` reactive ref + tick interval
- `frontend/app/layouts/purchase.vue` — added slots, responsive rail width
- `frontend/app/pages/purchase/checkout.vue` — template rewrite via `<NuxtLayout>` wrapper
- `frontend/app/components/booking/CheckoutHoldTimer.vue` — created
- `frontend/app/components/booking/CheckoutOrderCard.vue` — created
- `frontend/app/components/booking/CheckoutContactBay.vue` — created
- `frontend/app/components/booking/CheckoutPaymentBay.vue` — created
- `frontend/app/components/booking/CheckoutTotalsRail.vue` — created
- `frontend/app/components/booking/FoodPreOrderPanel.vue` — rewrote for concessions grid
- `frontend/app/components/booking/PromoCode.vue` — rewrote for bay-inset chrome + terms
- `frontend/app/components/booking/CheckoutForm.vue` — deleted (absorbed into `CheckoutPaymentBay`)
- `frontend/app/assets/css/checkout.css` — created
- `frontend/app/assets/css/main.css` — import checkout.css
- `frontend/tests/components/booking/CheckoutHoldTimer.test.ts` — created
- `frontend/tests/components/booking/CheckoutOrderCard.test.ts` — created
- `frontend/tests/components/booking/CheckoutContactBay.test.ts` — created
- `frontend/tests/components/booking/CheckoutPaymentBay.test.ts` — created
- `frontend/tests/components/booking/CheckoutTotalsRail.test.ts` — created
- `frontend/tests/components/booking/PromoCode.test.ts` — rewrote

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
- [2026-04-08] Food pre-order fetches from `GET /api/locations/{slug}/food-menu`. Uses `watch(activeLocation)` to handle async location init. Response grouped by category is flattened to `MenuItem[]` for the panel
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

## Plan 10: Content Domain
**Status:** ✅ Complete
**Started:** 2026-04-08
**Completed:** 2026-04-08

### Work Done
- [2026-04-08] Created FaqAccordionGroup — renders category heading + CvAccordion per FAQ item, auto-generated IDs from category name
- [2026-04-08] Created ContactForm — self-contained form with client-side validation, apiFetch POST to /api/contact, toast on success/error, loading state
- [2026-04-08] Created ContactMap — styled placeholder div with location pin icon, address text, coordinates display (no third-party map dependency)
- [2026-04-08] Created MenuItemCard — 4:3 image, name, description, dietary badges (CvBadge), allergen badges (warning variant for nuts), price via formatCurrency
- [2026-04-08] Created MenuCategoryTabs — horizontal scrollable tabs with roving tabindex, ARIA tablist, arrow key navigation
- [2026-04-08] Created GiftCardPurchase — preset amount buttons ($25/$50/$75/$100) + custom amount, recipient form, Stripe payment stub with info toast
- [2026-04-08] Created BalanceChecker — gift card code input, calls useGiftCards().checkBalance(), displays balance with status badge
- [2026-04-08] Created RentalInquiryForm — event type select, date/guest count/contact fields, apiFetch POST to /api/rentals/inquiry, validation, success toast
- [2026-04-08] Created PackageCard — CvCard with package name, description, feature checklist with check icons, starting price
- [2026-04-08] Created /faq page — Close-Up layout, FaqAccordionGroup per category from data/faq.ts, FAQPage JSON-LD structured data
- [2026-04-08] Created /contact page — Establishing Shot 65/35, ContactMap + directions/accessibility left, hours/phone/ContactForm right, LocalBusiness JSON-LD
- [2026-04-08] Created /food-drink page — MenuCategoryTabs + Ensemble grid of MenuItemCards, URL query param category state, static data from data/menu.ts
- [2026-04-08] Created /gift-cards page — Establishing Shot 65/35, GiftCardPurchase left, BalanceChecker right
- [2026-04-08] Created /private-screenings page — Rack Focus 35/65, RentalInquiryForm left, PackageCards (4 packages) right

### Decisions
- [2026-04-08] ContactMap uses a styled placeholder with coordinates instead of third-party map embed — avoids API key dependency for MVP
- [2026-04-08] MenuItemCard named to avoid HTML `<menuitem>` element conflict (per plan)
- [2026-04-08] GiftCardPurchase payment is a stub (shows info toast) — actual Stripe Elements integration deferred until keys are configured
- [2026-04-08] Food & Drink page uses static menuData from data/menu.ts with URL query param for category — API integration can be wired later
- [2026-04-08] Private screening packages are hardcoded in page component (static content per PAGE_SPECS)
- [2026-04-08] MenuCategoryTabs uses `@media (pointer: coarse)` for 3rem touch targets on touch devices, 2.25rem on pointer-fine

### Files Changed
- `frontend/app/components/content/FaqAccordionGroup.vue` — created
- `frontend/app/components/content/ContactForm.vue` — created
- `frontend/app/components/content/ContactMap.vue` — created
- `frontend/app/components/content/MenuItemCard.vue` — created
- `frontend/app/components/content/MenuCategoryTabs.vue` — created
- `frontend/app/components/content/GiftCardPurchase.vue` — created
- `frontend/app/components/content/BalanceChecker.vue` — created
- `frontend/app/components/content/RentalInquiryForm.vue` — created
- `frontend/app/components/content/PackageCard.vue` — created
- `frontend/app/pages/faq.vue` — created
- `frontend/app/pages/contact.vue` — created
- `frontend/app/pages/food-drink.vue` — created
- `frontend/app/pages/gift-cards.vue` — created
- `frontend/app/pages/private-screenings.vue` — created
- Storybook stories for all 9 components (9 files)

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

---

## Plan 11: Blog & Static Content Pages
**Status:** ✅ Complete
**Started:** 2026-04-08
**Completed:** 2026-04-08

### Work Done
- [2026-04-08] Created `BlogPostCard` component composing CvCard with 16:9 image, title, excerpt (3-line clamp), date + author footer
- [2026-04-08] Created static blog data in `app/data/blog.ts` with 3 sample posts (grand opening, behind-the-screens, summer lineup)
- [2026-04-08] Created blog listing page (`/blog`) with Ensemble grid, Blog structured data
- [2026-04-08] Created blog detail page (`/blog/[slug]`) with Close-Up composition, paragraph rendering, related posts section, Article structured data
- [2026-04-08] Created Careers page with job openings (Projectionist, Front of House, Kitchen & Bar), benefits list, application instructions, JobPosting structured data
- [2026-04-08] Created Accessibility page with all 7 sections (assisted listening, wheelchair, open captions, audio description, sensory-friendly, service animals, contact) and calendar filter links
- [2026-04-08] Added routeRules for `/blog/**` (ISR 600s), `/contact`, `/faq`, `/accessibility`, `/careers` (prerender)

### Decisions
- [2026-04-08] Initially used `@nuxt/content` v3 but dropped it — its `better-sqlite3` native addon crashes under Deno, and blog content will be managed dynamically via admin area in the future. Using static TypeScript data (`app/data/blog.ts`) as placeholder, consistent with FAQ and menu data patterns.

### Blockers
- None

### Files Changed
- `frontend/nuxt.config.ts` — added routeRules for blog/static pages
- `frontend/app/data/blog.ts` — new, static blog post data
- `frontend/app/components/content/BlogPostCard.vue` — new component
- `frontend/app/pages/blog/index.vue` — new, blog listing page
- `frontend/app/pages/blog/[slug].vue` — new, blog post detail page
- `frontend/app/pages/careers.vue` — new, static careers page
- `frontend/app/pages/accessibility.vue` — new, static accessibility page
- `frontend/tests/components/content/BlogPostCard.test.ts` — new, 8 tests
- `frontend/tests/pages/blog.test.ts` — new, 3 tests
- `frontend/tests/pages/static-pages.test.ts` — new, 13 tests

---

## Storybook Removal
**Status:** ✅ Complete
**Completed:** 2026-04-10

### Work Done
- [2026-04-10] Removed Storybook entirely from the project — deleted 48 story files, `.storybook/` config directory, Docker service/volume, nginx proxy block, Makefile targets, npm/deno dependencies, and all documentation references
- [2026-04-10] Renumbered Plan 13 (E2E & Polish) to Plan 12 to fill the gap
- [2026-04-10] Scrubbed remaining `.stories.ts` file references from plan task "Files:" sections (36 lines across 8 plan docs)

### Decisions
- [2026-04-10] Storybook was adding infrastructure overhead without proportional value at this stage — removed to simplify the stack

### Blockers
- None

### Files Changed
- `frontend/app/components/**/*.stories.ts` — deleted (48 files)
- `frontend/.storybook/` — deleted (main.ts, preview.ts, preview-head.html)
- `frontend/package.json` — removed storybook and @storybook/vue3-vite devDependencies
- `frontend/deno.json` — removed storybook task
- `docker-compose.override.yml` — removed storybook service, volume, nginx port/dependency
- `nginx/templates/conf.d/default.conf.template` — removed Storybook proxy server block
- `Makefile` — removed storybook and storybook-logs targets
- `CLAUDE.md` — removed Storybook references
- `.github/copilot-instructions.md` — removed .storybook/* watch pattern
- `scripts/check-docs-staleness.sh` — removed .storybook/* glob
- `docs/plans/frontend/v1/00-index.md` — removed plan 12 (Storybook), renumbered plan 13 → 12
- `docs/plans/frontend/v1/12-storybook-comprehensive.md` — deleted
- `docs/plans/frontend/v1/13-e2e-and-polish.md` — renamed to 12-e2e-and-polish.md
- `docs/plans/frontend/v1/03-ui-primitives.md` — removed Storybook checklist items
- `docs/plans/frontend/v1/04-layouts-and-shell.md` — removed Storybook testing section
- `docs/plans/frontend/v1/06-movie-domain.md` — removed Storybook testing section
- `docs/plans/frontend/v1/07-calendar-events-domain.md` — removed Storybook testing section
- `docs/plans/frontend/v1/08-purchase-flow-domain.md` — removed Storybook testing section
- `docs/plans/frontend/v1/09-auth-account-domain.md` — removed Storybook testing section
- `docs/plans/frontend/v1/10-content-domain.md` — removed Storybook testing section
- `docs/plans/frontend/v1/11-blog-and-static-pages.md` — removed .stories.ts file reference

---

## Movie Detail Visual Refresh
**Status:** 🟡 In Progress
**Started:** 2026-04-18
**Completed:** —

### Context
A Claude Design (`claude.ai/design`) handoff bundle landed with a richly atmospheric "Final Cut Movie Detail" prototype. The v1 page was a functional-but-minimal MovieHero + establishing-shot split with a sticky ShowtimeSelector sidebar. This refresh re-composes the page as a sequence of editorial bay sections matching the design's cinematic direction.

### Work Done
- [2026-04-18] Added `--primary-container-rgb` and `--secondary-rgb` channel tokens so rgba() variants of the fill colors are available for translucent effects
- [2026-04-18] Created `frontend/app/assets/css/movie-detail.css` — page-scoped primitives (`.bay`, `.bay-eyebrow`, `.bay-title`, `.chip`, `.chip.gold`, `.chip.score`, `.btn-primary`, `.btn-gold`, `.btn-ghost`, `.icon-btn`, `.film-grain`). Imported from `main.css`. All selectors nested under `.movie-page` to prevent global leakage.
- [2026-04-18] Created `useClock` composable — SSR-safe live clock (renders `--:--:--` on server, ticks every second on client)
- [2026-04-18] New `MovieBreadcrumb.vue` — thin strip below header with Home/Now Showing/title trail + Share/Print actions
- [2026-04-18] Rebuilt `MovieHero.vue` — full atmospheric layout (radial vignette backgrounds, film-grain overlay, grain-label top-left, live telemetry clock top-right, 2-col grid with poster + meta card and title column with big italic-accent title, chips, 5-col stubbed crew stats, CTA row)
- [2026-04-18] Refactored `MovieDetail.vue` — synopsis lead + body paragraphs (first sentence split) and 9-row credits fact table; removed trailer/cast blocks
- [2026-04-18] Rebuilt `MovieTrailerEmbed.vue` — 2fr/1fr grid with styled trailer stage (glass play button, film grain, reel-loaded indicator, scrub bar) that swaps to YouTube iframe on play; clip list sidebar with 5 entries (trailer + 4 stub featurettes)
- [2026-04-18] Refactored `MovieCastList.vue` — 6-col portrait grid (→ 3 at 1100px, → 2 at 640px) with 3:4 portraits, grain overlay, photo or seeded-gradient + first-letter glyph fallback
- [2026-04-18] Rebuilt `ShowtimeSelector.vue` — full-width section with eyebrow/title header, location pill, 7 format filter chips (visual only), 7-day date strip with today dot + empty-day disabled state, showtime matrix grouped by stub format (4 formats, deterministic assignment by id hash), slot cards linking to `/purchase/:id` with stubbed availability + Members badge on first slot
- [2026-04-18] New `MovieSeatPreview.vue` — static auditorium visualization (12 rows A-M with varying widths, center gap, deterministic taken/member/selected states) + order summary card with stub totals + Continue-to-Checkout button
- [2026-04-18] New `MoviePress.vue` — 3-col quote grid + 4-cell scores row with progress bars (all quotes/scores from design HTML verbatim, stubbed)
- [2026-04-18] New `MovieRelated.vue` — 4-col poster grid via `useMovies().nowShowing()`, filters current slug, seeded gradient posters with first-letter glyphs
- [2026-04-18] Restructured `pages/movies/[slug].vue` — dropped establishing-shot+sidebar pattern, now renders MovieBreadcrumb → MovieHero → 6 bay sections (synopsis, trailer, cast, showtimes+seatpreview, press, related). SEO/JSON-LD block preserved.

### Decisions
- [2026-04-18] Decided **stub with placeholder data** over "drop the section" for design elements that lack backend support — user accepted the trade-off: ship the full design now, mark each stub site with a `TODO(backend)` comment so replacements are greppable when the API gains crew/format/review fields.
- [2026-04-18] Showtimes section promoted from sidebar to full-width bay — matches the design, and the richer date strip + matrix needs the width to breathe.
- [2026-04-18] Skipped adding a `gold` variant to `CvButton`; used page-scoped `.btn-primary`/`.btn-gold`/`.btn-ghost` in movie-detail.css instead. Keeps CvButton API stable and scopes the design's CTA treatments to this page.
- [2026-04-18] `useClock` ticks client-only — server renders placeholder to avoid hydration mismatch, mirroring the home page's established pattern.
- [2026-04-18] Dropped the prototype's "Cold Dawn / Void" hero vibe variants and the dev-only Tweaks panel; one reactor vibe only.

### Stub registry
All sites marked `TODO(backend)` — grep `rg 'TODO\(backend\)' frontend/app/` to enumerate:
- `MovieHero.vue` — crew stats, grain label, poster format badge
- `MovieDetail.vue` — credits fact rows (Director, Screenplay, Cinematography, Editor, Composer, Aspect, Advisory)
- `MovieTrailerEmbed.vue` — clip/featurette list beyond the single real trailer
- `ShowtimeSelector.vue` — format groups, format filter chips, pseudo-availability counts, Members badge assignment
- `MovieSeatPreview.vue` — seat grid availability, order summary line items
- `MoviePress.vue` — press quotes (3) and aggregate scores (4)

### Blockers
- None

### Files Changed
- `frontend/app/assets/css/main.css` — added `@import './movie-detail.css'`
- `frontend/app/assets/css/tokens.css` — added `--primary-container-rgb` and `--secondary-rgb`
- `frontend/app/assets/css/movie-detail.css` — new
- `frontend/app/composables/useClock.ts` — new
- `frontend/app/pages/movies/[slug].vue` — restructured as bay sections
- `frontend/app/components/movie/MovieHero.vue` — full rebuild
- `frontend/app/components/movie/MovieDetail.vue` — synopsis + credits table
- `frontend/app/components/movie/MovieTrailerEmbed.vue` — trailer stage + clips
- `frontend/app/components/movie/MovieCastList.vue` — 6-col portrait grid
- `frontend/app/components/movie/ShowtimeSelector.vue` — date strip + matrix
- `frontend/app/components/movie/MovieBreadcrumb.vue` — new
- `frontend/app/components/movie/MovieSeatPreview.vue` — new
- `frontend/app/components/movie/MoviePress.vue` — new
- `frontend/app/components/movie/MovieRelated.vue` — new
- [2026-04-18] Updated Vitest tests for refactored components (MovieDetail, MovieHero, MovieCastList, MovieTrailerEmbed, ShowtimeSelector) — old tests asserted on the V1 skeleton's DOM structure; new tests assert on the design's structure (synopsis lead/body split, credits fact rows, hero CTAs, clip sidebar, format chips, 7-day date strip). All 61 movie component tests pass.
- [2026-04-18] Updated `e2e/responsive.spec.ts` movie detail assertions from `.establishing-shot` to `.movie-hero__inner` — the movie detail page no longer uses the establishing-shot composition (it's a sequence of bay sections instead).
- [2026-04-18] Full Vitest suite green: 60 test files passed. No regressions outside the explicitly refactored set.

**Status:** ✅ Complete
**Completed:** 2026-04-18

---

## Plan: Snacks & Concessions — Concessions design split + redesign
**Status:** ✅ Complete
**Started:** 2026-04-18
**Completed:** 2026-04-18

### Work Done
- [2026-04-18] Implemented the Claude Design "Final Cut Concessions.html" handoff in two surfaces: a new dedicated `/purchase/snacks` checkout step and an editorial redesign of the public `/food-drink` page.
- [2026-04-18] Split the purchase flow from 3 steps to 4: `Seats → Snacks & Bar → Payment → Confirmation`. Updated `PurchaseStepIndicator.vue` (4 steps, two-digit numeric badges, new label set), widened `usePurchaseStep` to `1 | 2 | 3 | 4`, updated step calls in all three existing purchase pages plus the layout's navigation handler.
- [2026-04-18] New booking components: `ConcessionItemCard.vue`, `ConcessionsCatalog.vue`, `ProgrammePairingCard.vue`, `ConcessionsAllergenNotice.vue`, `ConcessionsCollectionInfo.vue`, `ConcessionsTrayRail.vue`. Composes the editorial design verbatim — film-reel `§ NN` numbering, italic display headlines, gradient thumb cards with category tag + flag + curator note, gold CTA buttons with `--shadow-float` only on floating elements, gold-on-dark filter chips with item counts.
- [2026-04-18] New `Programme Pairing` concept — frontend-only static data (`app/data/pairings.ts`), keyed by movie slug, with editorial bundle (3 courses, savings vs à la carte). Two pairings seeded: Interstellar ("The Endurance flight"), The Dark Knight ("The Gotham short"). Tied into `useCart` via `pairing` state + `setPairing`/`clearPairing` + `pairingPrice`/`pairingSavings` computed.
- [2026-04-18] Extended `MenuItem` type with optional editorial fields (`size`, `curator`, `flag`, `gradient`, `glyph`). Backfilled `app/data/menu.ts` with these fields; new `editorialOverlayFor(name)` lookup enriches live API data with frontend-only display metadata.
- [2026-04-18] New `useFoodMenu` composable wraps `/api/locations/{slug}/food-menu` with location-aware fetch + graceful fallback to static `menuData` when the API is unreachable. Enriches each item with the editorial overlay before exposing.
- [2026-04-18] Updated `/purchase/checkout.vue` — removed `FoodPreOrderPanel` (food moved to its own step), added a read-only "Your tray" snacks summary section with an "Edit snacks" link back to `/purchase/snacks`. Updated eyebrow from "Reel 02 · Checkout" to "Reel 03 · Payment".
- [2026-04-18] Replaced `/food-drink.vue` with the editorial Concessions catalog in browse-only mode (no Add buttons, no rail), wrapped in a Reel-styled page-top + allergen notice + collection-info footer.
- [2026-04-18] Tests: 4 new Vitest specs (`ConcessionItemCard`, `ConcessionsCatalog`, `ProgrammePairingCard`, `ConcessionsTrayRail`); extended `PurchaseStepIndicator`, `useCart`, and `usePurchaseStep` specs for 4-step flow + pairing methods. Full suite: **582/582 passing** (+21 from baseline 561).

### Decisions
- [2026-04-18] **Pairing excluded from `cart.subtotal`/`cart.total`** for backend correctness — the pairing has no backend representation yet. The snacks-step rail composes its own display total locally (seats + food + pairingPrice − savings) for the editorial moment; checkout's totals revert to seats + foodItems so the Stripe charge matches what the user is shown there. The pairing line is rendered on checkout's read-only snacks summary as informational ("pay at the bar on collection"). Promotion to a proper backend `programme_pairings` model is the obvious next step if the feature earns its keep.
- [2026-04-18] **Pairings per-film, not per-location** — for the MVP, pairings are tied to `movieSlug` only. If/when this moves to a backend table it should also gain `location_id` so each theater can curate independently.
- [2026-04-18] **Filter chip border-radius `999px` accepted** as a deliberate design exception to the "sm/none only" radius rule. The chips read as engineered tokens (with two-digit count badges) rather than soft consumer pills — the design's editorial register holds.
- [2026-04-18] Deleted `FoodPreOrderPanel.vue` and `MenuItemCard.vue` outright (no compat shim) per the project's no-backwards-compat-pre-launch rule.

### Stub registry
- `cart.pairing` — frontend-only, not sent in `POST /api/locations/{location}/bookings`. Backend currently has no `programme_pairings` table. Grep `TODO\(backend\)` after the follow-up plan lands.

### Blockers
- None

### Files Changed
- `frontend/app/types/menu-item.ts` — additive optional editorial fields (size, curator, flag, gradient, glyph)
- `frontend/app/types/programme-pairing.ts` — new
- `frontend/app/data/pairings.ts` — new (Interstellar + Dark Knight pairings)
- `frontend/app/data/menu.ts` — backfilled editorial metadata + new `editorialOverlayFor` lookup
- `frontend/app/composables/useCart.ts` — pairing state + methods + computed values; subtotal documentation
- `frontend/app/composables/useFoodMenu.ts` — new
- `frontend/app/composables/usePurchaseStep.ts` — widened type to `1 | 2 | 3 | 4`
- `frontend/app/components/booking/PurchaseStepIndicator.vue` — 4 steps, two-digit numeric badges, gold-circle styling
- `frontend/app/components/booking/ConcessionItemCard.vue` — new
- `frontend/app/components/booking/ConcessionsCatalog.vue` — new
- `frontend/app/components/booking/ProgrammePairingCard.vue` — new
- `frontend/app/components/booking/ConcessionsAllergenNotice.vue` — new
- `frontend/app/components/booking/ConcessionsCollectionInfo.vue` — new
- `frontend/app/components/booking/ConcessionsTrayRail.vue` — new
- `frontend/app/layouts/purchase.vue` — `handleStepNavigate` now routes to `/purchase/snacks` (step 2) and `/purchase/checkout` (step 3)
- `frontend/app/pages/purchase/snacks.vue` — new
- `frontend/app/pages/purchase/[showtimeId].vue` — `handleContinue` → `/purchase/snacks`
- `frontend/app/pages/purchase/checkout.vue` — `setStep(3, [1, 2], [1, 2])`, removed `FoodPreOrderPanel`, added read-only snacks summary, eyebrow "Reel 03 · Payment"
- `frontend/app/pages/purchase/confirmation/[bookingId].vue` — `setStep(4, [1, 2, 3, 4], [])`
- `frontend/app/pages/food-drink.vue` — swapped MenuItemCard grid for ConcessionsCatalog (browse-only)
- `frontend/app/components/booking/FoodPreOrderPanel.vue` — **deleted**
- `frontend/app/components/content/MenuItemCard.vue` — **deleted**
- `frontend/tests/components/booking/ConcessionItemCard.test.ts` — new (12 tests)
- `frontend/tests/components/booking/ConcessionsCatalog.test.ts` — new (10 tests)
- `frontend/tests/components/booking/ProgrammePairingCard.test.ts` — new (8 tests)
- `frontend/tests/components/booking/ConcessionsTrayRail.test.ts` — new (10 tests)
- `frontend/tests/components/booking/PurchaseStepIndicator.test.ts` — rewritten for 4-step flow (15 tests)
- `frontend/tests/composables/useCart.test.ts` — extended with pairing tests (22 tests total)
- `frontend/tests/composables/usePurchaseStep.test.ts` — extended for steps 2/3/4 (9 tests total)

### Doc follow-ups (out of scope this PR — flagged for next pass)
- Update `docs/specs/PURCHASE_FLOW.md` for the 4-step flow + pairing/snacks step
- Update `docs/specs/PAGE_SPECS.md` to add `/purchase/snacks`
- Update `docs/specs/COMPONENT_INVENTORY.md` to register the six new booking components and remove the `FoodPreOrderPanel`/`MenuItemCard` entries
- Backend `programme_pairings` table + endpoint + Pest tests when the feature graduates from frontend-only
