# Component Inventory

Every component in the system, organized by tier. Global primitives know nothing about movies. Domain components know about movies but not pages. Pages compose domain components into layout compositions.

All components use Vue 3 Composition API with `<script setup lang="ts">` and `<style scoped>`. Styling uses CSS custom properties from the design system — no Tailwind, no component library.

**Accessibility baseline:** All components follow `DESIGN_SYSTEM_STRUCTURE.md` § 7 — double-ring gold focus indicators, 3rem touch targets below `screen-md`, standard ARIA patterns (`for`/`id` label pairing, `aria-invalid`, `aria-describedby`, `aria-required`). Only component-specific deviations are listed below.

---

## Tier 1: Global Primitives

Located in `app/components/ui/`. Auto-imported globally by Nuxt. These are the design system made tangible — they accept props for variants and emit events. They never fetch data. They never know what page they are on.

---

### CvButton

**File:** `app/components/ui/CvButton.vue`

**Purpose:** Primary interactive element across the entire site.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'primary' \| 'secondary' \| 'tertiary'` | No | `'primary'` | Visual style variant |
| `size` | `'sm' \| 'default' \| 'lg'` | No | `'default'` | Height: sm (2.25rem, pointer-fine only), default (3rem), lg (3.5rem) |
| `disabled` | `boolean` | No | `false` | Disables interaction and reduces opacity |
| `loading` | `boolean` | No | `false` | Shows loading spinner, disables interaction |
| `type` | `'button' \| 'submit' \| 'reset'` | No | `'button'` | Native button type |
| `href` | `string` | No | — | Renders as `<NuxtLink>` instead of `<button>` |

**Slots:** `default` (label), `icon-left`, `icon-right`.

**Events:** `click` (`MouseEvent`) — not emitted when disabled or loading.

**Variants:** Primary: `primary-container` bg, `secondary` text, `0.125rem` radius. Secondary: `surface-container-high` bg, `on-surface` text. Tertiary: transparent, `secondary` text, animated underline from center on hover.

**Accessibility:** `aria-disabled="true"` when disabled. `aria-busy="true"` when loading. Loading spinner is `aria-hidden="true"`.

---

### CvCard

**File:** `app/components/ui/CvCard.vue`

**Purpose:** Surface-tier container for grouping related content.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'low' \| 'default' \| 'high'` | No | `'default'` | Surface tier: low, default, high |
| `interactive` | `boolean` | No | `false` | Enables hover lift and cursor pointer |
| `href` | `string` | No | — | Makes entire card a clickable link via `<NuxtLink>` |

**Slots:** `default` (body), `header`, `footer`.

**Events:** `click` (`MouseEvent`) — when interactive.

**Behavior:** Decorative edge catch via `outline_variant` at 15% opacity. Radius: 0.25rem. Hover lift when interactive: `translateY(-0.125rem)`. Internal padding: `space-md`. When `interactive` without `href`: receives `tabindex="0"` and `role="button"`.

---

### CvInput

**File:** `app/components/ui/CvInput.vue`

**Purpose:** Text input field with underline-only styling.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `modelValue` | `string \| number` | Yes | — | v-model binding |
| `type` | `'text' \| 'email' \| 'password' \| 'number' \| 'tel' \| 'search'` | No | `'text'` | Input type |
| `label` | `string` | Yes | — | Visible label text |
| `placeholder` | `string` | No | — | Placeholder text |
| `error` | `string` | No | — | Error message displayed below input |
| `disabled` | `boolean` | No | `false` | Disables input |
| `required` | `boolean` | No | `false` | Marks as required |
| `size` | `'sm' \| 'default'` | No | `'default'` | Height: sm (2.25rem, pointer-fine only), default (3rem) |

**Events:** `update:modelValue`, `focus` (`FocusEvent`), `blur` (`FocusEvent`).

**Behavior:** Underline-only (no border on top/left/right). Unfocused: `outline` color. Focused: `secondary` color with gold outer glow. Label: `label-md`, `tertiary`, Newsreader. Value: `body-md`, `on-surface`. Error: `label-md`, `primary`.

**Accessibility:** Focus indicator is the gold underline + glow (no additional ring needed per design system spec).

---

### CvTextarea

**File:** `app/components/ui/CvTextarea.vue`

**Purpose:** Multi-line text input with underline styling.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `modelValue` | `string` | Yes | — | v-model binding |
| `label` | `string` | Yes | — | Visible label text |
| `placeholder` | `string` | No | — | Placeholder text |
| `error` | `string` | No | — | Error message |
| `rows` | `number` | No | `4` | Visible rows |
| `disabled` | `boolean` | No | `false` | Disables textarea |
| `required` | `boolean` | No | `false` | Marks as required |

**Events:** Same as CvInput (`update:modelValue`, `focus`, `blur`).

**Behavior:** Same underline and focus styling as CvInput.

---

### CvSelect

**File:** `app/components/ui/CvSelect.vue`

**Purpose:** Dropdown selection with underline styling.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `modelValue` | `string \| number` | Yes | — | v-model binding |
| `options` | `Array<{ value: string \| number; label: string }>` | Yes | — | Available options |
| `label` | `string` | Yes | — | Visible label text |
| `placeholder` | `string` | No | `'Select…'` | Placeholder when no value |
| `error` | `string` | No | — | Error message |
| `disabled` | `boolean` | No | `false` | Disables select |
| `required` | `boolean` | No | `false` | Marks as required |

**Events:** `update:modelValue`.

**Behavior:** Same underline styling as CvInput. Uses native `<select>` for maximum screen reader compatibility. Dropdown panel: `surface-container-high`, `z-dropdown`.

---

### CvModal

**File:** `app/components/ui/CvModal.vue`

**Purpose:** Dialog overlay with focus trap and glassmorphism backdrop.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `modelValue` | `boolean` | Yes | — | v-model for open/close |
| `title` | `string` | Yes | — | Modal title (rendered as h2, used as aria-labelledby) |
| `size` | `'sm' \| 'default' \| 'lg'` | No | `'default'` | Max-width: sm (25rem), default (35rem), lg (50rem) |
| `closeable` | `boolean` | No | `true` | Shows close button, enables Escape to close |

**Slots:** `default` (body), `footer` (action buttons).

**Events:** `update:modelValue` (`boolean`), `close`.

**Behavior:** Glassmorphism backdrop at `z-modal-backdrop`. Content panel: `surface-container-high` at `z-modal`. Header height: 3.5rem. Radius: 0.125rem. Rendered via `<Teleport to="body">`.

**Accessibility:** `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing to title. Focus trap with wrap. On open: focus to first focusable element. On close: focus returns to trigger. Background receives `inert`.

---

### CvAccordion

**File:** `app/components/ui/CvAccordion.vue`

**Purpose:** Expandable/collapsible content section.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `title` | `string` | Yes | — | Accordion header text |
| `open` | `boolean` | No | `false` | Initial open state |
| `id` | `string` | Yes | — | Unique ID for ARIA relationships |

**Slots:** `default` (collapsible body content).

**Events:** `toggle` (`boolean`).

**Behavior:** Header: `on-surface`, Newsreader `body-lg`. Expand/collapse via `grid-template-rows: 0fr → 1fr`. No divider lines — spacing separates items (`space-sm`).

**Accessibility:** Header is a `<button>` with `aria-expanded`, `aria-controls`. Content panel: `role="region"`, `aria-labelledby`.

---

### CvBadge

**File:** `app/components/ui/CvBadge.vue`

**Purpose:** Small label for ratings, tags, status indicators.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'default' \| 'accent' \| 'warning'` | No | `'default'` | Color treatment |
| `size` | `'sm' \| 'default'` | No | `'default'` | sm uses label-sm, default uses label-md |

**Slots:** `default` (badge text).

**Variants:** Default: `surface-container-high` bg, `tertiary` text. Accent: `primary-container` bg, `primary` text. Warning: `secondary-container` bg, `on-surface` text (contrast remediation). Padding: `space-xs` horizontal, `space-2xs` vertical. Radius: 0.125rem.

---

### CvSkeletonLoader

**File:** `app/components/ui/CvSkeletonLoader.vue`

**Purpose:** Shimmer placeholder while content loads.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'text' \| 'card' \| 'image' \| 'circle'` | No | `'text'` | Shape of the skeleton |
| `width` | `string` | No | `'100%'` | CSS width |
| `height` | `string` | No | `'1rem'` | CSS height (ignored for card/image which use aspect ratio) |
| `lines` | `number` | No | `1` | Number of text lines (text variant only) |

**Behavior:** `surface-container-low` bg. Shimmer gradient sweep, 1500ms infinite. Radius: 0.125rem (text/card), 50% (circle). Reduced motion: solid fill, no shimmer.

**Accessibility:** Container has `aria-busy="true"`.

---

### CvToast

**File:** `app/components/ui/CvToast.vue`

**Purpose:** Notification toast displayed at bottom of viewport.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `message` | `string` | Yes | — | Toast message text |
| `type` | `'info' \| 'success' \| 'error'` | No | `'info'` | Visual treatment |
| `duration` | `number` | No | `5000` | Auto-dismiss time in ms (0 for persistent) |

**Events:** `dismiss`.

**Behavior:** `surface-container-high` bg, `on-surface` text. Success: left border `secondary`. Error: left border `primary-container`. At `z-toast`. Slides up from bottom on enter, down on exit.

**Accessibility:** Info/success: `role="status"`, `aria-live="polite"`. Errors: `role="alert"`, `aria-live="assertive"`. Dismiss button: `aria-label="Dismiss notification"`.

---

### CvIcon

**File:** `app/components/ui/CvIcon.vue`

**Purpose:** Icon wrapper with standardized sizing.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `name` | `string` | Yes | — | Icon identifier |
| `size` | `'sm' \| 'md' \| 'lg' \| 'xl'` | No | `'md'` | sm (1rem), md (1.5rem), lg (3rem), xl (4rem) |
| `label` | `string` | No | — | Accessible label (when icon is meaningful) |

**Accessibility:** With `label`: `role="img"`, `aria-label`. Without: `aria-hidden="true"`.

---

### CvChip

**File:** `app/components/ui/CvChip.vue`

**Purpose:** Outlined toggle pill with optional colored dot. Powers the `BridgeFilterRibbon`; usable anywhere a single-value toggle should read like a tag rather than a checkbox.

**Props:** `label: string`, `active?: boolean`, `color?: string` (CSS color powers the 7px dot + active glow), `compact?: boolean`, `disabled?: boolean`.

**Events:** `update:active` (`boolean`), `toggle`.

**Behavior:** Default — transparent bg, `outline-variant` border at 40% opacity, ambient text. Active — gold-tinted bg, gold-50 border, on-surface text, dot at full opacity with 6px box-shadow glow. Compact — tighter padding + `label-sm` text. `aria-pressed` reflects active state.

---

### CvSegmentedControl

**File:** `app/components/ui/CvSegmentedControl.vue`

**Purpose:** Generic segmented selector. Powers the Month/Week/List switch in `BridgeProgrammeToolbar`.

**Props:** `modelValue: string`, `options: Array<{ value: string; label: string; disabled?: boolean; hint?: string }>`, `label?: string`.

**Events:** `update:modelValue` (`string`).

**Behavior:** `surface-container-low` track, `radius-sm`. Active option is `primary-container` bg + `primary` text. Disabled options carry an `aria-disabled` and a tooltip via `hint`. Roving tabindex; ArrowLeft / ArrowRight navigate, skipping disabled options. `role="tablist"` with `role="tab"` per button.

---

### CvIconButton

**File:** `app/components/ui/CvIconButton.vue`

**Purpose:** Square 2.25rem icon button. Used for prev/next month controls in `BridgeProgrammeToolbar`; reusable wherever a labelled icon affordance fits.

**Props:** `icon: IconName` (CvIcon name), `label: string` (required accessible name), `size?: 'sm' | 'default' | 'lg'`, `disabled?: boolean`, `href?: string`.

**Events:** `click` (`MouseEvent`).

**Behavior:** Renders as a `<button>` by default; switches to `<NuxtLink>` when `href` is provided and not disabled. Hover lifts background to `surface-container-high` and tints icon to `secondary` (gold). Same gold double-ring focus indicator as the rest of the Cv primitives.

---

## Tier 2: Layout Components

Located in `app/components/layout/`. These form the persistent site shell.

---

### SiteHeader

**File:** `app/components/layout/SiteHeader.vue`

**Purpose:** Fixed top navigation bar.

**Props:** None — reads auth state from `useAuth()`.

**Structure:** Wordmark logo image (links to `/`), primary nav (Movies, What's On, Food & Drink, Events, Gift Cards), auth controls (Sign In or avatar dropdown). Mobile: hamburger menu below `screen-md`.

**Behavior:** Height: 5.5rem (`--layout-header-height`), fixed. Background: `surface-container`. At `z-sticky`. Nav links: `on-surface`, Newsreader `body-md`. Active link: `secondary` underline. Logo: wordmark image (`public/final-cut-logo-wordmark.webp`, 4rem tall) — the `<NuxtLink>` carries `aria-label="Final Cut — home"` and the `<img>` is decorative (`alt=""`).

**Accessibility:** `<header role="banner">` with `<nav aria-label="Primary">`. Mobile menu: `aria-expanded` on toggle, focus trap when open.

---

### SiteFooter

**File:** `app/components/layout/SiteFooter.vue`

**Purpose:** Site footer with secondary navigation and legal.

**Props:** None.

**Structure:** Secondary nav (Contact, FAQ, Accessibility, Careers, Private Screenings), social links, legal (copyright, terms, privacy), theater address and phone.

**Behavior:** Background: `surface-container-lowest`. Min height: 15rem. Text: `tertiary`, `body-sm`. Links: `on-surface`, gold underline on hover.

**Accessibility:** `<footer role="contentinfo">`. Social links have `aria-label` describing destination.

---

### NeuralTicker

**File:** `app/components/layout/NeuralTicker.vue`

**Purpose:** Horizontally scrolling ambient data feed.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `items` | `Array<{ text: string; href?: string }>` | Yes | — | Ticker content items |

**Behavior:** Height: 2rem. Background: `surface-container`. Text: `label-sm`, Newsreader. Scroll speed: 2.5rem/second (configurable via `--ticker-speed`). At `z-ticker`.

**Accessibility:** Scrolling content: `aria-hidden="true"`. Static `sr-only` alternative for screen readers. Pause/play button: `aria-label="Pause ticker"` / `"Play ticker"`, `aria-pressed`. `<aside aria-label="Now showing updates" aria-live="off">`. Reduced motion: stops scrolling, displays content statically.

---

### MobileNav

**File:** `app/components/layout/MobileNav.vue`

**Purpose:** Fixed bottom navigation bar for mobile (below `screen-md`).

**Props:** None — reads current route for active state.

**Structure:** 5 items maximum: Home, Movies, What's On, Account, More.

**Behavior:** Height: `calc(3.5rem + env(safe-area-inset-bottom))`. Background: `surface-container`. At `z-sticky`. Active: `secondary`. Inactive: `tertiary`.

**Accessibility:** `<nav aria-label="Mobile navigation">`. `aria-current="page"` when active.

---

### SkipNav

**File:** `app/components/layout/SkipNav.vue`

**Purpose:** Hidden skip navigation link, visible on keyboard focus.

**Behavior:** At `z-skip-nav` (900). Background: `primary-container`, text: `secondary`. Hidden via `translateY(-100%)`, revealed on `:focus-visible`. Links to `#main-content`.

---

### SidebarNav

**File:** `app/components/layout/SidebarNav.vue`

**Purpose:** Persistent side navigation for account pages.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `items` | `Array<{ label: string; href: string; icon: string }>` | Yes | — | Nav items |

**Behavior:** Desktop: 15rem rail. Tablet: 4rem icon-only rail. Mobile: collapses to MobileNav bottom bar. Active item: `secondary` left edge accent (vertical gradient).

**Accessibility:** `<nav aria-label="Account">`. `aria-current="page"` on active item.

---

## Tier 2: Domain Components — Movie

Located in `app/components/movie/`.

---

### MovieCard

**File:** `app/components/movie/MovieCard.vue`

**Purpose:** Movie listing card used in Ensemble grids.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `movie` | `Movie` | Yes | — | Movie data object |
| `showShowtimes` | `boolean` | No | `true` | Show next showtime pills (false for coming soon) |

**Structure:** Composes CvCard. Poster (2:3, `object-fit: cover`, `object-position: top center`), title (`headline-sm`, Noto Serif), MovieRatingBadge, genre badges, showtime pills or "Notify Me" CTA. Links to `/movies/:slug`.

**Accessibility:** Entire card is a link. `alt="[Movie Title] poster"`. Showtime pills are `<time datetime="...">`.

---

### MovieHero

**File:** `app/components/movie/MovieHero.vue`

**Purpose:** Full-bleed hero section with movie backdrop.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `movie` | `Movie` | Yes | — | Movie data (backdrop, title, tagline) |

**Behavior:** Wide Frame composition. Vignette bloom gradient: `primary-container` to `surface-container-lowest`. Title: `display-lg`, Noto Serif, `on-surface`. Tagline: `body-lg`, Newsreader, `tertiary`. Hero reveal: `duration-cinematic`, `ease-enter`.

**Accessibility:** Backdrop: `aria-hidden="true"`, empty `alt=""`. Reduced motion: no reveal animation.

---

### MovieDetail

**File:** `app/components/movie/MovieDetail.vue`

**Purpose:** Full movie information layout for detail page.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `movie` | `Movie` | Yes | — | Full movie data |

**Structure:** Renders within Establishing Shot (65/35). Left: title, tagline, synopsis, genre badges (CvBadge), runtime, rating. Right: ShowtimeSelector.

---

### MovieCastList

**File:** `app/components/movie/MovieCastList.vue`

**Purpose:** Horizontally scrollable cast member grid.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `cast` | `Array<{ name: string; character: string; profileUrl: string }>` | Yes | — | Cast members from TMDB |

**Behavior:** Avatar: 3rem, circular crop. Name: `label-lg`, `on-surface`. Character: `label-md`, `tertiary`.

**Accessibility:** `alt="[Actor name]"` on profile photos. Scrollable via arrow keys or touch.

---

### MovieTrailerEmbed

**File:** `app/components/movie/MovieTrailerEmbed.vue`

**Purpose:** Responsive YouTube video embed.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `trailerKey` | `string` | Yes | — | YouTube video ID from TMDB |
| `title` | `string` | Yes | — | Movie title for accessible label |

**Behavior:** 16:9 aspect ratio. Radius: 0.125rem.

**Accessibility:** `<iframe title="[Movie Title] trailer">`, `loading="lazy"`.

---

### MovieRatingBadge

**File:** `app/components/movie/MovieRatingBadge.vue`

**Purpose:** Visual rating indicator (TMDB score).

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `rating` | `number` | Yes | — | Rating out of 10 |

**Behavior:** Composes CvBadge with accent variant. Score formatted to one decimal.

---

### ShowtimeSelector

**File:** `app/components/movie/ShowtimeSelector.vue`

**Purpose:** Date and time selection panel linking to seat purchase.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `showtimes` | `Array<Showtime>` | Yes | — | Available showtimes grouped by date |
| `movieSlug` | `string` | Yes | — | For constructing purchase URLs |

**Structure:** Date tabs (horizontally scrollable, today highlighted), time slot buttons below selected date. Each time slot links to `/purchase/:showtimeId`.

**Behavior:** Active date tab: `primary-container` bg, `primary` text. Time slots: CvButton tertiary. Spacing: `space-sm`.

**Accessibility:** Date tabs: `role="tablist"`, each `role="tab"`, `aria-selected`. Time slots: `<time datetime="...">` with full date/time `aria-label`. Arrow keys navigate dates, Tab moves to time slots.

---

## Tier 2: Domain Components — Booking/Purchase

Located in `app/components/booking/`.

---

### AuditoriumGrid

**File:** `app/components/booking/AuditoriumGrid.vue`

**Purpose:** Interactive seat selection map for a theater auditorium.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `auditorium` | `Auditorium` | Yes | — | Auditorium configuration (rows, seats per row) |
| `seats` | `Array<Seat>` | Yes | — | Current seat data with availability status |
| `selectedSeatIds` | `Array<string>` | Yes | — | Currently selected seat IDs |

**Events:** `seat-toggled` (`{ seatId: string; selected: boolean }`).

**Structure:** Two-column wrapper: pinned row labels (left) + scrollable seat matrix (right). AuditoriumScreenBar above the grid.

**Behavior:** Desktop cells: 2.5rem, gap 0.25rem. Mobile: 3rem, gap 0.25rem. Row labels: pinned left, `label-md`, `tertiary`. Horizontal scroll on mobile with `scroll-snap-type: x proximity`. Seat visual states defined in `DESIGN_SYSTEM_STRUCTURE.md` § 2.6.

**Accessibility:** `role="grid"`, `aria-label="Theater seating chart, [Screen Name]"`. Each row: `role="row"`. Each seat: `role="gridcell"` with descriptive `aria-label`. Selected: `aria-selected="true"`. Taken: `aria-disabled="true"`. Roving tabindex keyboard navigation — see `DESIGN_SYSTEM_STRUCTURE.md` § 7. Screen reader announcements on selection: "Seat [ID] selected. [N] seats selected, [price] total."

---

### AuditoriumSeat

**File:** `app/components/booking/AuditoriumSeat.vue`

**Purpose:** Individual seat cell within the auditorium grid.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `seat` | `Seat` | Yes | — | Seat data |
| `selected` | `boolean` | Yes | — | Whether this seat is currently selected |
| `focused` | `boolean` | Yes | — | Whether this seat has roving tabindex focus |

**Events:** `toggle`.

**Behavior:** Selection animation: color change + `scale(1→1.05→1)`, `duration-standard`, `ease-emphasis`. Focus: 0.125rem inset `secondary` outline.

---

### PurchaseStepIndicator

**File:** `app/components/booking/PurchaseStepIndicator.vue`

**Purpose:** Horizontal step indicator rendered in the `purchase` layout.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `currentStep` | `1 \| 2 \| 3` | Yes | — | Active step |
| `completedSteps` | `Array<number>` | Yes | — | Steps the user has completed |
| `navigableSteps` | `Array<number>` | No | Same as `completedSteps` | Steps the user can click to navigate to. Pass `[]` on confirmation page |

**Events:** `navigate` (`number`).

**Behavior:** Completed/active: `secondary` with gold underline. Future: `outline_variant`. Connector: `outline_variant` at 15% opacity. Font: `label-lg`, Newsreader.

**Accessibility:** `<nav aria-label="Purchase steps">`. Current: `aria-current="step"`. Navigable steps: `<a>`. Non-navigable: `<span>` (or `aria-disabled="true"` for future).

---

### AuditoriumScreenBar

**File:** `app/components/booking/AuditoriumScreenBar.vue`

**Purpose:** Visual indicator of the movie screen position above the seat grid.

**Behavior:** Height: 0.25rem, width: 60% of grid, centered. Color: `primary-container`.

**Accessibility:** `aria-hidden="true"` (decorative).

---

### AuditoriumLegend

**File:** `app/components/booking/AuditoriumLegend.vue`

**Purpose:** Key explaining seat state colors and icons.

**Structure:** Row of labeled color swatches: Available, Selected, Taken, Accessible, Premium.

---

### CartSummary

**File:** `app/components/booking/CartSummary.vue`

**Purpose:** Running order total shown during purchase flow.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `items` | `Array<{ label: string; price: number }>` | Yes | — | Line items |
| `total` | `number` | Yes | — | Computed total |

**Structure:** Desktop: sidebar panel. Mobile: collapsible bottom sheet. Lists selected seats, food add-ons, and total.

**Accessibility:** `aria-live="polite"` on total — announces updates when seats are added/removed.

---

### CheckoutForm

> **⚠️ Removed (Plan 08 redesign).** `CheckoutForm.vue` no longer exists — its Stripe Elements logic was absorbed into `CheckoutPaymentBay`, and submit/terms moved to `CheckoutConfirmBay`. See the concise inventory at the end of this doc. The spec below is retained for historical context.

**File:** `app/components/booking/CheckoutForm.vue`

**Purpose:** Payment form with Stripe Elements integration.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `total` | `number` | Yes | — | Amount to charge |
| `isAuthenticated` | `boolean` | Yes | — | Whether user is logged in |

**Events:** `submit` (`{ paymentMethodId: string; email?: string }`), `error` (`string`).

**Structure:** Stripe Elements card input, guest email field (if not authenticated), billing name, "Complete Purchase" CTA.

**Accessibility:** Stripe Elements handles its own ARIA internally.

---

### BookingConfirmation

**File:** `app/components/booking/BookingConfirmation.vue`

**Purpose:** Post-purchase confirmation display.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `booking` | `Booking` | Yes | — | Completed booking data |

**Structure:** Booking reference, movie/showtime details, seat numbers, food orders, total, QR code, "Add to Calendar" (.ics), "Print Tickets".

**Accessibility:** Optimized for print stylesheet. QR code: `alt="Booking QR code for [reference]"`.

---

### FoodPreOrderPanel

> **⚠️ Replaced (Concessions redesign).** `FoodPreOrderPanel.vue` was removed — concessions moved to a dedicated `/purchase/snacks` step built from the `Concessions*` components (`ConcessionsCatalog`, `ConcessionItemCard`, …). See the concise inventory at the end of this doc.

**File:** `app/components/booking/FoodPreOrderPanel.vue`

**Purpose:** Inline food/drink selection during checkout.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `menuItems` | `Array<MenuItem>` | Yes | — | Available menu items |
| `selectedItems` | `Array<{ itemId: string; quantity: number }>` | Yes | — | Currently selected items |

**Events:** `update` (`Array<{ itemId: string; quantity: number }>`).

**Structure:** Compact grid of menu items with quantity selectors. Category tabs for filtering.

---

### PromoCode

**File:** `app/components/booking/PromoCode.vue`

**Purpose:** Promotional code input with apply action.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `appliedCode` | `string \| null` | Yes | — | Currently applied code |

**Events:** `apply` (`string`), `remove`.

---

## Tier 2: Domain Components — Calendar (Bridge Console)

Located in `app/components/calendar/`. The `/whats-on` page composes these into the "Bridge Console" split layout — dense month grid on the left, persistent detail rail on the right (collapses to a slide-up drawer below `screen-lg`). The original `CalendarGrid` / `CalendarDayCell` / `CalendarEventList` / `CalendarFilters` components were retired in the redesign.

The chip-based filter model and event-type color palette are owned by the `useBridgeFilters` composable (`app/composables/useBridgeFilters.ts`). Six toggleable chips collapse the backend's two-axis filter model (event type + accessibility tags) into a single ribbon; rentals are always visible regardless of chip state.

| Component | File | Role |
| --- | --- | --- |
| `BridgeProgrammeToolbar` | `BridgeProgrammeToolbar.vue` | Eyebrow + h1 (`What's <em>On</em>, May 2026`) + Month/Week/List segmented control + prev/today/next icon buttons |
| `BridgeFilterRibbon` | `BridgeFilterRibbon.vue` | Six `CvChip` toggles + 3-item type legend; reads/writes `useBridgeFilters` |
| `BridgeMonthGrid` | `BridgeMonthGrid.vue` | 5-or-6-week month grid with grid-toolbar header (Mon-start, screening + special counters); `role="grid"`, roving tabindex; emits `select-date` |
| `BridgeDayCell` | `BridgeDayCell.vue` | Day number, type-color flag dots (cap 4), up to 2 event lines + `+N more`; states: default / today / selected / muted / has-rental (135° corner stripe) |
| `BridgeDetailRail` | `BridgeDetailRail.vue` | Sticky right column (`top: 5.5rem`); composes the three detail cards; hidden below `screen-lg` |
| `BridgeDetailHero` | `BridgeDetailHero.vue` | Selected-day card: 4rem day numeral + meta + hero film (`BridgeMiniPoster`) + 4-up showtime tile grid (`is-soldout` strikethrough state) |
| `BridgeAlsoToday` | `BridgeAlsoToday.vue` | "Also Today" list of remaining events; rentals render with × badge + muted treatment |
| `BridgeCinemaReadout` | `BridgeCinemaReadout.vue` | 4-stat readout (members tonight / bar opens / late showing / valet); static stub for v1 |
| `BridgeMiniPoster` | `BridgeMiniPoster.vue` | Poster thumbnail with image fallback to a hashed-hue gradient + initials mark + grain overlay |
| `BridgeDetailDrawer` | `BridgeDetailDrawer.vue` | Slide-up sheet that wraps the same three detail cards; only visible below `screen-lg`; teleports to `<body>`, focus-trapped, dismisses on Escape and backdrop click |

Hero film selection rule (shared between rail and drawer via `pickHeroEvent`): prefer the first `special_event` or `loyalty_exclusive`; otherwise the first non-rental event; otherwise null.

The Bridge components do not call `new Date()` for "today" comparisons — `pages/whats-on.vue` derives the SSR-safe today key once via `Intl.DateTimeFormat({ timeZone: appTimeZone })` and passes it down. This contract is locked by `tests/architecture/whats-on-date-hydration.test.ts`.

---

## Tier 2: Domain Components — Account

Located in `app/components/account/`.

---

### OrderHistoryList

**File:** `app/components/account/OrderHistoryList.vue`

**Purpose:** Paginated list of past orders with expandable details.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `orders` | `Array<Booking>` | Yes | — | Order data |

**Structure:** Each order row shows date, movie title, total. Expands via CvAccordion to show seats, food items, booking reference.

---

### LoyaltyPointsCard

**File:** `app/components/account/LoyaltyPointsCard.vue`

**Purpose:** Loyalty program summary card.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `points` | `number` | Yes | — | Current points balance |
| `tier` | `'member' \| 'premier'` | Yes | — | Current loyalty tier |
| `premierExpiry` | `string \| null` | No | `null` | Premier tier expiry date (ISO). Null for member tier |

---

### SavedPaymentMethods

**File:** `app/components/account/SavedPaymentMethods.vue`

**Purpose:** List of saved payment cards from Stripe.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `methods` | `Array<{ id: string; brand: string; last4: string; expMonth: number; expYear: number }>` | Yes | — | Payment methods |

**Events:** `add`, `remove` (with method ID).

---

### UpcomingBookings

**File:** `app/components/account/UpcomingBookings.vue`

**Purpose:** List of future bookings.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `bookings` | `Array<Booking>` | Yes | — | Upcoming booking data |

**Structure:** Each booking shows movie poster thumbnail, title, date, time, seats. Links to confirmation page.

---

### ProfileForm

**File:** `app/components/account/ProfileForm.vue`

**Purpose:** Profile editing form.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `profile` | `User` | Yes | — | Current user data |

**Events:** `save` (`Partial<User>`).

**Structure:** Avatar upload area, name (CvInput), email (CvInput), password change section (current, new, confirm). Save button (CvButton).

---

## Tier 2: Domain Components — Content

Located in `app/components/content/`.

---

### FaqAccordionGroup

**File:** `app/components/content/FaqAccordionGroup.vue`

**Purpose:** FAQ category with multiple accordion items.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `category` | `string` | Yes | — | Category title |
| `items` | `Array<{ question: string; answer: string }>` | Yes | — | FAQ items |

**Structure:** Category heading + multiple CvAccordion instances.

---

### ContactForm

**File:** `app/components/content/ContactForm.vue`

**Purpose:** Contact inquiry form.

**Props:** None — self-contained.

**Events:** `submit` (`{ name: string; email: string; subject: string; message: string }`).

**Structure:** Name (CvInput), email (CvInput), subject (CvInput), message (CvTextarea), submit (CvButton).

---

### ContactMap

**File:** `app/components/content/ContactMap.vue`

**Purpose:** Embedded map with dark theme styling.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `coordinates` | `{ lat: number; lng: number }` | Yes | — | Theater location |

**Accessibility:** `<iframe title="Theater location map">` or static image with `alt` text.

---

### MenuItem

**File:** `app/components/content/MenuItem.vue`

**Purpose:** Food/drink menu item card.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `item` | `MenuItem` | Yes | — | Menu item data |

**Structure:** Image (4:3 aspect), name (`headline-sm`), description (`body-sm`), price, dietary badges (CvBadge).

---

### MenuCategoryTabs

**File:** `app/components/content/MenuCategoryTabs.vue`

**Purpose:** Horizontally scrolling category filter tabs.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `categories` | `Array<string>` | Yes | — | Category names |
| `active` | `string` | Yes | — | Currently selected category |

**Events:** `select` (`string`).

**Accessibility:** `role="tablist"`, each tab `role="tab"`, `aria-selected`.

---

### GiftCardPurchase

> **⚠️ Renamed.** Now `GiftCardComposer.vue` (with `GiftCardVisual` / `GiftCardPreview` for the live preview and `GiftCardPaymentModal` for payment). See the concise inventory at the end of this doc.

**File:** `app/components/content/GiftCardPurchase.vue`

**Purpose:** Gift card purchase form.

**Props:** None — self-contained.

**Events:** `purchase` (`{ amount: number; recipientEmail: string; recipientName: string; message: string }`).

**Structure:** Amount selector (preset buttons + custom input), recipient name, recipient email, personal message, purchase CTA.

---

### BalanceChecker

> **⚠️ Renamed.** Now `GiftCardBalanceStrip.vue`. See the concise inventory at the end of this doc.

**File:** `app/components/content/BalanceChecker.vue`

**Purpose:** Gift card balance lookup.

**Props:** None — self-contained.

**Structure:** Card code input (CvInput), "Check Balance" button, balance display area.

---

### RentalInquiryForm

**File:** `app/components/content/RentalInquiryForm.vue`

**Purpose:** Private screening/rental inquiry form.

**Events:** `submit` (`RentalInquiry`).

**Structure:** Event type (CvSelect), date (CvInput type date), guest count (CvInput type number), name, email, message (CvTextarea), submit.

---

### PackageCard

**File:** `app/components/content/PackageCard.vue`

**Purpose:** Private screening package display.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `package` | `{ name: string; description: string; startingPrice: number; features: Array<string> }` | Yes | — | Package data |

**Structure:** CvCard with package name (`headline-sm`), description, feature list, starting price.

---

### BlogPostCard

**File:** `app/components/content/BlogPostCard.vue`

**Purpose:** Blog listing card.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `post` | `{ title: string; slug: string; excerpt: string; date: string; author: string; imageUrl: string }` | Yes | — | Post summary data |

**Structure:** CvCard with featured image (16:9 thumbnail), title, excerpt, date, author. Links to `/blog/:slug`.

---

### EventListCard

**File:** `app/components/content/EventListCard.vue`

**Purpose:** Event listing card for events page and calendar.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `event` | `CalendarEvent` | Yes | — | Event data |

**Structure:** CvCard with event image (4:3), date badge, title, description preview, type badge, "Learn More" link.

---

### EventDetail

**File:** `app/components/content/EventDetail.vue`

**Purpose:** Full event page content.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `event` | `CalendarEvent & { description: string; pricing: string; includes: Array<string> }` | Yes | — | Full event data |

**Structure:** Title, date/time, full description, what's included list, pricing, CTA button.

---

## Concise inventory — components added since the original spec

The detailed specs above predate several redesigns (purchase-flow / concessions / gift-cards) and the cross-location content work. This section completes the catalog so "every component" holds true. Entries here are intentionally concise (file + one-line purpose); they follow the same design-system and accessibility baselines as the rest of this doc.

### Tier 1: Global Primitives — additions

- `CvCheckbox` (`ui/CvCheckbox.vue`) — checkbox control (terms consent, opt-ins).
- `ui/_internal/CvFormField.vue`, `ui/_internal/CvToastContainer.vue` — internal helpers (shared form-field wrapper; toast render host), not used directly by pages.

### Tier 2: Home (`components/home/`)

Page-section components composed by `pages/index.vue`:

- `HomeCinemaHero` — top-of-home atmospheric hero.
- `HomeFeaturedCarousel` — admin-curated featured-slides carousel (`GET /api/featured-slides`); WAI-ARIA carousel pattern, auto-advance with pause-on-hover/focus.
- `HomeNowShowingReel` — cross-location now-showing strip of `MovieCard`s.
- `HomeCalendarStrip` — "What's On This Week" preview strip.
- `HomeFoodDrink` — editorial food & drink teaser → `/food-drink`.
- `HomeMembership` — loyalty/membership pitch (site-content driven).
- `HomeRetrospectiveSplit` — editorial split feature section.

### Tier 2: Movie — additions (`components/movie/`)

- `MovieBreadcrumb` — breadcrumb strip + share/print actions on movie detail.
- `MoviePress` — press-quote grid + aggregate scores (stubbed editorial data).
- `MovieRelated` — "related films" poster grid.
- `LocationFilterChips` — `?location=` filter chips on `/movies`.

### Tier 2: Booking/Purchase — additions (`components/booking/`)

Seat-selection redesign (bay sections wrapping `AuditoriumGrid` on `/purchase/:showtimeId`):

- `SeatSelectionHero`, `SeatSelectionControls`, `SeatAuditoriumStage`, `SeatSelectionLegend`, `SeatSelectionRail`, `SeatSelectionHouseRules`, `SeatSightlineDiagram`, `SeatProjectionistPick`, `SeatStub`.
- `BookingLocationBanner` — "You're booking at {Location}" confirmation band.

Checkout redesign (replaces `CheckoutForm`):

- `CheckoutOrderCard`, `CheckoutContactBay`, `CheckoutPaymentBay` (owns Stripe Elements), `CheckoutTotalsRail` (sticky totals), `CheckoutConfirmBay` (Confirm & Pay + terms, visible on every viewport), `CheckoutHoldTimer` (session countdown).

Concessions step (replaces `FoodPreOrderPanel`, dedicated `/purchase/snacks`):

- `ConcessionsCatalog`, `ConcessionItemCard`, `ConcessionsAllergenNotice`, `ConcessionsCollectionInfo`.

### Tier 2: Calendar (Bridge Console) — additions (`components/calendar/`)

- `BridgeWeekStrip` — week-view strip (admin-v5 Week view).
- `BridgeAgendaList` — list-view agenda (admin-v5 List view).
- `BridgeDetailContent` — shared detail-card body composed by both the rail and the drawer.

### Tier 2: Account — additions (`components/account/`)

- `AddPaymentMethodModal` — Stripe SetupIntent add-card modal.

### Tier 2: Content — additions / renames (`components/content/`)

- `GiftCardComposer` (was `GiftCardPurchase`) — gift-card composer form.
- `GiftCardVisual`, `GiftCardPreview` — live gift-card art / preview.
- `GiftCardPaymentModal` — gift-card Stripe payment modal.
- `GiftCardBalanceStrip` (was `BalanceChecker`) — balance-lookup strip.
- `LocationCard`, `LocationHero`, `LocationDetailPanel` — `/locations` and `/locations/:slug` building blocks.
