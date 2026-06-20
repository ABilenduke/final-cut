# Live component catalog

The Final Cut design system is implemented as Vue components in `frontend/app/components/`. They are auto-imported by Nuxt — no `import` statements needed. Read the scoped styles inside any `.vue` file to see how tokens are applied.

> **Token discipline reminder:** `--primary` (#FFB4A8 salmon) is a **text color** — only use it on `--primary-container` (#550000 maroon) fills. If you're picking a button background, you want `--primary-container`. If you're picking the label color on top of it, that's `--primary`. The full do/don't table is in `docs/design-system/DESIGN_SYSTEM.md` § "CRITICAL: Token Mapping".

---

## UI primitives — `frontend/app/components/ui/`

| Component | File | Purpose |
|---|---|---|
| `<CvButton>` | `CvButton.vue` | Primary interactive element. Variants: `primary` (maroon fill / gold text), `secondary` (`surface-container-high`), `tertiary` (transparent + animated gold underline). Sizes: `sm` (pointer-fine only), `default`, `lg`. Supports `loading`, `disabled`, `href` (renders as `NuxtLink`). |
| `<CvCard>` | `CvCard.vue` | Surface-tier container. Variants: `low`, `default`, `high`. Optional `interactive` (cursor + lift on hover) and `href`. Decorative edge catch at 15% opacity, `0.25rem` radius, no shadows. |
| `<CvInput>` | `CvInput.vue` | Underline-only text input. v-model, `type`, `label`, `placeholder`, `error`, `required`. Focus → gold underline + outer glow. |
| `<CvTextarea>` | `CvTextarea.vue` | Multi-line variant of CvInput. Same underline + focus styling. |
| `<CvSelect>` | `CvSelect.vue` | Dropdown with native `<select>` for screen-reader compatibility. Same underline styling. |
| `<CvCheckbox>` | `CvCheckbox.vue` | Box-style checkbox with maroon fill + salmon check icon when checked. Native input visually hidden; focus ring on the visual indicator. |
| `<CvBadge>` | `CvBadge.vue` | Small label. Variants: `default` (surface-high / tertiary), `accent` (maroon / salmon), `warning` (gold-container / on-surface). Sizes: `sm`, `default`. |
| `<CvIcon>` | `CvIcon.vue` | Renders an SVG path from `icons.ts`. Sizes: `sm` (1rem), `md` (1.5rem), `lg` (3rem), `xl` (4rem). Inherits `currentColor`. Pass `label` for accessible name; defaults to `aria-hidden`. |
| `<CvModal>` | `CvModal.vue` | Dialog with focus trap, glassmorphism backdrop, `role="dialog"` + `aria-modal`. v-model controls open state. Sizes: `sm` (25rem), `default` (35rem), `lg` (50rem). Teleported to `body`. |
| `<CvAccordion>` | `CvAccordion.vue` | Expandable section. Header is a `<button>` with `aria-expanded`. Expand via `grid-template-rows: 0fr → 1fr`. |
| `<CvSkeletonLoader>` | `CvSkeletonLoader.vue` | Shimmer placeholder. Variants: `text`, `card`, `image`, `circle`. Reduced motion → solid fill, no shimmer. |
| `<CvToast>` | `CvToast.vue` | Notification toast. Variants: `info`, `success`, `error`. `aria-live="polite"` (info/success) or `assertive` (error). Read from the `useToast()` queue, not invoked directly. |

**Internal helpers** (`frontend/app/components/ui/_internal/`):
- `CvFormField.vue` — shared label/error/help layout used by Input/Textarea/Select/Checkbox.
- `CvToastContainer.vue` — root container that renders the toast queue.

**Icons:** `frontend/app/components/ui/icons.ts` — `iconPaths` object + `IconName` type. 34 Material Design Icons (Apache 2.0). Adding a new icon: paste its 24×24 path, add to the `iconPaths` const, the type updates automatically.

---

## Layout shell — `frontend/app/components/layout/`

| Component | File | Purpose |
|---|---|---|
| `<SiteHeader>` | `SiteHeader.vue` | Fixed 4rem top bar. Crest logo (`/final-cut-logo.webp`, 3rem tall, links home), 5-item primary nav, location switcher pill, auth controls. Mobile: hamburger with focus-trapped drawer. |
| `<SiteFooter>` | `SiteFooter.vue` | Standard footer — secondary nav, social, address, legal. `surface-container-lowest` background. |
| `<NeuralTicker>` | `NeuralTicker.vue` | Bridge-console telemetry feed. Sticks below header at `top: 4rem`, `z-ticker`. Pulsing "On Air" reactor badge on the leading edge, scrolling label-sm content (text-uppercase, letter-spacing 0.1em), pause/play button. Reduced motion → wraps to static text. |
| `<SidebarNav>` | `SidebarNav.vue` | Account-area side rail. Desktop: 15rem labeled. Tablet: 4rem icon-only. Mobile: collapses to MobileNav. Active item gets gold left-edge accent. |
| `<MobileNav>` | `MobileNav.vue` | Fixed bottom bar below `screen-md`. 5 items max (Home, Movies, What's On, Account, More). `aria-current="page"` for active. |
| `<SkipNav>` | `SkipNav.vue` | Hidden skip link, visible on `:focus-visible`. `primary-container` background, `secondary` text, `z-skip-nav` (900). Links to `#main-content`. |

---

## Layouts — `frontend/app/layouts/`

| Layout | File | Use |
|---|---|---|
| `default` | `default.vue` | Header + Neural Ticker + main + footer. Most public pages. |
| `account` | `account.vue` | Adds `<SidebarNav>` rail for `/account/*` routes. |
| `purchase` | `purchase.vue` | Stripped-down chrome — logo, `<PurchaseStepIndicator>`, session timer. No footer. For `/purchase/*` flow. |
| `blank` | `blank.vue` | Logo only. Used for `/auth/*`. |

---

## Layout compositions — CSS classes in `frontend/app/assets/css/layouts.css`

These are the six named compositions that replace 12-column grid thinking. Apply to a wrapping element:

| Class | Ratio / structure | Use |
|---|---|---|
| `.composition-establishing-shot` | 65% / 35% on screen-md+, stacked below | Movie detail (poster + synopsis), feature editorial. |
| `.composition-rack-focus` | 35% / 65% on screen-md+, primary-first stacked below | Alternating content, reverse split. |
| `.composition-wide-frame` | Full-bleed `100vw` | Hero sections, vignette bloom backgrounds. Pair with `.vignette-bloom`. |
| `.composition-close-up` | Centered, max-width 40rem | Article body, FAQ, legal, confirmation. |
| `.composition-ensemble` | `repeat(auto-fill, minmax(17.5rem, 1fr))`, gap `--space-lg` | Movie card grid, menu items, blog archive. |
| `.composition-auditorium` | Two-column wrapper: pinned row labels + scrollable seat matrix | Seat selection only — see `AuditoriumGrid.vue`. |

---

## Feature components

These are the domain-specific compositions built on top of the primitives. Use them — don't rebuild them.

### Movie — `frontend/app/components/movie/`

`MovieHero.vue` (Wide Frame backdrop + vignette bloom + display-lg title) · `MovieDetail.vue` (Establishing Shot 65/35) · `MovieCard.vue` (Ensemble grid item) · `MovieCastList.vue` (horizontal scroll, circular avatars) · `MovieTrailerEmbed.vue` (16:9 YouTube iframe) · `MovieRatingBadge.vue` · `ShowtimeSelector.vue` (date tabs + time slots → `/purchase/:showtimeId`) · `MovieBreadcrumb.vue` · `MovieRelated.vue` · `MovieSeatPreview.vue` · `MoviePress.vue`.

### Booking / Purchase — `frontend/app/components/booking/`

Seat selection: `AuditoriumGrid.vue`, `AuditoriumSeat.vue`, `AuditoriumScreenBar.vue`, `AuditoriumLegend.vue`, `SeatSelectionHero.vue`, `SeatSelectionControls.vue`, `SeatSelectionRail.vue`, `SeatSelectionLegend.vue`, `SeatSelectionHouseRules.vue`, `SeatSightlineDiagram.vue`, `SeatAuditoriumStage.vue`, `SeatProjectionistPick.vue`, `SeatStub.vue`.

Checkout: `CheckoutPaymentBay.vue`, `CheckoutTotalsRail.vue`, `CheckoutContactBay.vue`, `CheckoutHoldTimer.vue`, `CheckoutOrderCard.vue`, `CartSummary.vue`, `PromoCode.vue`, `BookingConfirmation.vue`, `PurchaseStepIndicator.vue`, `ProgrammePairingCard.vue`.

Concessions: `ConcessionsCatalog.vue`, `ConcessionsCollectionInfo.vue`, `ConcessionsTrayRail.vue`, `ConcessionItemCard.vue`, `ConcessionsAllergenNotice.vue`.

### Calendar — `frontend/app/components/calendar/`

`CalendarGrid.vue` (roving tabindex grid) · `CalendarDayCell.vue` · `CalendarEventList.vue` · `CalendarFilters.vue` (event-type + accessibility filters).

### Account — `frontend/app/components/account/`

`ProfileForm.vue` · `SavedPaymentMethods.vue` · `UpcomingBookings.vue` · `LoyaltyPointsCard.vue` · `OrderHistoryList.vue`.

### Home — `frontend/app/components/home/`

`HomeCinemaHero.vue` · `HomeNowShowingReel.vue` · `HomeFoodDrink.vue` · `HomeMembership.vue` · `HomeRetrospectiveSplit.vue` · `HomeCalendarStrip.vue`.

### Content — `frontend/app/components/content/`

`ContactForm.vue` · `ContactMap.vue` · `BlogPostCard.vue` · `EventListCard.vue` · `EventDetail.vue` · `PackageCard.vue` · `MenuCategoryTabs.vue` · `FaqAccordionGroup.vue` · `GiftCardPurchase.vue` · `RentalInquiryForm.vue` · `BalanceChecker.vue`.

---

## Composables — `frontend/app/composables/`

Auto-imported. Use these instead of writing custom state/fetch logic.

| Composable | Purpose |
|---|---|
| `useAuth()` | Session state. `user`, `isAuthenticated`, `login`, `register`, `logout`, `fetchUser`. |
| `useLocations()` | Active theater location. `locations`, `activeLocation`, `setLocation` (localStorage-backed). |
| `useCart()` | Ephemeral purchase cart. `showtime`, `seats`, `foodItems`, `total`, `addSeat`, etc. |
| `useToast()` | `show({ message, type })`, `dismiss(id)`. CvToast renders the queue. |
| `useMovies()` | `nowShowing`, `comingSoon`, `getMovie(slug)`. |
| `useShowtimes()` | `getShowtimes(movieSlug, date?)`, `getShowtime(id)`. |
| `useCalendarEvents()` | `getEvents(month, year, type?)`, `getEvent(slug)`. |
| `useAccount()` | `profile`, `orders`, `bookings`, `loyalty`, `updateProfile`. |
| `useGiftCards()` | `purchase(data)`, `checkBalance(code)`. |
| `useSeatSelection()` | Local seat-grid state (selection + roving focus), separate from server availability. |

---

## Token canon

| File | Contents |
|---|---|
| `frontend/app/assets/css/tokens.css` | All CSS custom properties: colors, RGB channels, spacing, z-index, easing, durations, breakpoints, icon sizes. |
| `frontend/app/assets/css/typography.css` | Font stacks + the 13-step type scale (`--type-display-*` … `--type-label-*`) + usage classes. |
| `frontend/app/assets/css/utilities.css` | `.aspect-poster`, `.aspect-hero`, `.aspect-video`, `.aspect-event`, `.glass`, `.vignette-bloom`, `.edge-catch`, `.shadow-float`, `.sr-only`, focus-indicator helpers. |
| `frontend/app/assets/css/layouts.css` | The six named compositions (`.composition-*`). |
| `frontend/app/assets/css/main.css` | Composes all of the above + page-specific overrides. |

---

## Where to look first by task

| Task | Start here |
|---|---|
| Add a button | `<CvButton>` in `CvButton.vue`. Compose, don't restyle. |
| Lay out a hero section | `.composition-wide-frame` + `.vignette-bloom` + `<MovieHero>` for reference. |
| Add a card grid | `.composition-ensemble` + `<CvCard>`, mirror `<HomeNowShowingReel>` or `<MovieCard>`. |
| Build a form | `<CvInput>`, `<CvTextarea>`, `<CvSelect>`, `<CvCheckbox>`, `<CvButton>`. See `<ContactForm>` for layout. |
| New page route | `frontend/app/pages/`, set strategy in `nuxt.config.ts` `routeRules`. |
| New icon | Add path to `frontend/app/components/ui/icons.ts`. Re-derive `assets/icons/icons.js` + `sprite.svg` if you want the skill's offline copy current. |
| Style with no available class | Add a class to the appropriate stylesheet. **Never inline styles** (`style="…"` is forbidden). |
