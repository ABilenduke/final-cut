# Component Inventory

Every component in the system, organized by tier. Global primitives know nothing about movies. Domain components know about movies but not pages. Pages compose domain components into layout compositions.

All components use Vue 3 Composition API with `<script setup lang="ts">` and `<style scoped>`. Styling uses CSS custom properties from the design system — no Tailwind, no component library.

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

**Slots:**

| Name | Description |
| ---- | ----------- |
| `default` | Button label content |
| `icon-left` | Icon before label |
| `icon-right` | Icon after label |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `click` | `MouseEvent` | Emitted on click (not emitted when disabled or loading) |

**Design Tokens:**

- Primary: `background: var(--primary-container)` (#550000), `color: var(--secondary)` (#DAC769), `border-radius: 0.125rem`
- Secondary: `background: var(--surface-container-high)` (#2a2a2a), `color: var(--on-surface)` (#E5E2E1)
- Tertiary: `background: transparent`, `color: var(--secondary)` (#DAC769), animated underline extends from center on hover
- Hover: `duration-micro` (100ms), `ease-standard`
- Active press: `transform: scale(0.98)`, `duration-micro`
- Focus: double-ring glow (`var(--secondary)` outer ring)
- Floating shadow on primary: `box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6)`

**Accessibility:**

- Small variant (2.25rem) gated behind `@media (pointer: fine)` — never renders on touch devices
- Minimum touch target: 3rem (48px) on all devices below `screen-md`
- `aria-disabled="true"` when disabled (not just the `disabled` attribute — ensures screen reader announcement)
- `aria-busy="true"` when loading
- Loading spinner is `aria-hidden="true"` with `aria-label="Loading"` on the button

---

### CvCard

**File:** `app/components/ui/CvCard.vue`

**Purpose:** Surface-tier container for grouping related content.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'low' \| 'default' \| 'high'` | No | `'default'` | Surface tier: low (#1c1b1b), default (#201f1f), high (#2a2a2a) |
| `interactive` | `boolean` | No | `false` | Enables hover lift and cursor pointer |
| `href` | `string` | No | — | Makes entire card a clickable link via `<NuxtLink>` |

**Slots:**

| Name | Description |
| ---- | ----------- |
| `default` | Card body content |
| `header` | Optional header area |
| `footer` | Optional footer area |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `click` | `MouseEvent` | Emitted when interactive card is clicked |

**Design Tokens:**

- Background: surface tier per variant
- Edge catch: `outline_variant` (#57423E) at 15% opacity — decorative only, not accessible boundary
- Border radius: 0.25rem (default card radius)
- Hover lift (interactive): `transform: translateY(-0.125rem)`, `duration-standard` (250ms), `ease-standard`
- No divider lines between sections — use spacing or surface shifts
- Internal padding: `var(--space-md)` (1rem)

**Accessibility:**

- When `href` is set: rendered as `<NuxtLink>`, receives focus ring
- When `interactive` without `href`: receives `tabindex="0"` and `role="button"`
- Focus: double-ring glow on entire card

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

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `update:modelValue` | `string \| number` | v-model update |
| `focus` | `FocusEvent` | Input focused |
| `blur` | `FocusEvent` | Input blurred |

**Design Tokens:**

- Underline (unfocused): `border-bottom: 0.0625rem solid var(--outline)` (#A58B86)
- Underline (focused): `border-bottom-color: var(--secondary)` (#DAC769) with subtle gold outer glow (glassmorphism)
- Label: `var(--type-label-md)`, `color: var(--tertiary)` (#CCC6B6), Newsreader
- Value text: `var(--type-body-md)`, `color: var(--on-surface)` (#E5E2E1), Newsreader
- Error text: `var(--type-label-md)`, `color: var(--primary)` (#FFB4A8)
- Transition: `duration-standard` (250ms), `ease-standard`
- No border on top/left/right — underline only per design system

**Accessibility:**

- Label associated via `for`/`id` pairing
- `aria-invalid="true"` when error is set
- `aria-describedby` pointing to error message element
- `aria-required="true"` when required
- Focus indicator is the gold underline + glow (no additional ring needed per design system spec)

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

**Design Tokens:** Same underline and focus styling as CvInput.

**Accessibility:** Same pattern as CvInput.

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

**Design Tokens:** Same underline styling as CvInput. Dropdown panel uses `surface_container_high` (#2a2a2a), `z-dropdown` (300).

**Accessibility:**

- Uses native `<select>` for maximum screen reader compatibility, styled with underline treatment
- `aria-invalid`, `aria-describedby`, `aria-required` as with CvInput

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

**Slots:**

| Name | Description |
| ---- | ----------- |
| `default` | Modal body content |
| `footer` | Optional action buttons area |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `update:modelValue` | `boolean` | v-model update on close |
| `close` | — | Emitted when modal closes (Escape, backdrop click, close button) |

**Design Tokens:**

- Backdrop: `surface_variant` at 60% opacity + `backdrop-filter: blur(20px)`, `z-modal-backdrop` (400)
- Content panel: `surface_container_high` (#2a2a2a), `z-modal` (500)
- Shadow: `box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6)`
- Header height: 3.5rem
- Border radius: 0.125rem
- Enter: `opacity` + `transform: scale(0.95→1)`, `duration-emphasis` (400ms), `ease-enter`
- Exit: `opacity` + `transform: scale(1→0.95)`, `duration-emphasis`, `ease-exit`
- Rendered via `<Teleport to="body">`

**Accessibility:**

- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing to title
- Focus trap: Tab cycles within modal, Shift+Tab reverse cycles
- On open: focus moves to first focusable element
- On close: focus returns to trigger element
- Background content receives `inert` attribute
- Escape closes modal (when `closeable` is true)

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

**Slots:**

| Name | Description |
| ---- | ----------- |
| `default` | Collapsible body content |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `toggle` | `boolean` | Emitted with new open state |

**Design Tokens:**

- Header: `color: var(--on-surface)`, Newsreader `body-lg`
- Expand/collapse: `grid-template-rows: 0fr → 1fr` animation, `duration-emphasis` (400ms), `ease-standard`
- No divider lines — spacing separates accordion items (`var(--space-sm)`)

**Accessibility:**

- Header is a `<button>` with `aria-expanded`, `aria-controls` pointing to content panel ID
- Content panel has `role="region"`, `aria-labelledby` pointing to header button
- Enter/Space toggles open/close

---

### CvBadge

**File:** `app/components/ui/CvBadge.vue`

**Purpose:** Small label for ratings, tags, status indicators.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `variant` | `'default' \| 'accent' \| 'warning'` | No | `'default'` | Color treatment |
| `size` | `'sm' \| 'default'` | No | `'default'` | sm uses label-sm, default uses label-md |

**Slots:**

| Name | Description |
| ---- | ----------- |
| `default` | Badge text content |

**Design Tokens:**

- Default: `background: var(--surface-container-high)`, `color: var(--tertiary)`
- Accent: `background: var(--primary-container)`, `color: var(--primary)`
- Warning: `background: var(--secondary-container)`, `color: var(--on-surface)` (not secondary text — contrast remediation per design system audit)
- Padding: `var(--space-xs)` horizontal, `var(--space-2xs)` vertical
- Border radius: 0.125rem
- Font: Newsreader, `label-md` or `label-sm`

**Accessibility:**

- Purely visual — no interactive behavior
- If conveying status, parent context must make the meaning clear to screen readers

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

**Design Tokens:**

- Background: `var(--surface-container-low)` (#1c1b1b)
- Shimmer: gradient sweep, 1500ms infinite, `ease-linear`
- Border radius: 0.125rem (text/card), 50% (circle)
- Reduced motion: solid fill, no shimmer

**Accessibility:**

- Container has `aria-busy="true"`
- Screen readers announce "Loading" on the containing region

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

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `dismiss` | — | Emitted when toast is dismissed |

**Design Tokens:**

- Background: `var(--surface-container-high)` (#2a2a2a)
- Text: `var(--on-surface)` (#E5E2E1)
- Success accent: left border `var(--secondary)` (#DAC769)
- Error accent: left border `var(--primary-container)` (#550000)
- z-index: `var(--z-toast)` (600)
- Enter: `translateY(100%→0)` + opacity, `duration-standard`, `ease-enter`
- Exit: `translateY(0→100%)` + opacity, `duration-standard`, `ease-exit`

**Accessibility:**

- `role="status"`, `aria-live="polite"` for info/success
- `role="alert"`, `aria-live="assertive"` for errors
- Dismiss button has `aria-label="Dismiss notification"`

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

**Accessibility:**

- When `label` is provided: `role="img"`, `aria-label` set
- When no `label`: `aria-hidden="true"` (decorative)

---

## Tier 2: Layout Components

Located in `app/components/layout/`. These form the persistent site shell.

---

### SiteHeader

**File:** `app/components/layout/SiteHeader.vue`

**Purpose:** Fixed top navigation bar.

**Props:** None — reads auth state from `useAuth()` composable.

**Slots:** None.

**Structure:**

- Logo (links to `/`)
- Primary navigation: Movies, What's On, Food & Drink, Events, Gift Cards
- Auth controls: "Sign In" link (guest) or user avatar dropdown (authenticated)
- Mobile: hamburger menu toggle below `screen-md`

**Design Tokens:**

- Height: 4rem (64px), fixed at all breakpoints
- Background: `var(--surface-container)` (#201f1f)
- z-index: `var(--z-sticky)` (200)
- Nav links: `var(--on-surface)` (#E5E2E1), Newsreader `body-md`
- Active link: `var(--secondary)` (#DAC769) underline
- Logo: Noto Serif, `headline-sm`

**Accessibility:**

- `<header role="banner">` containing `<nav role="navigation" aria-label="Primary">`
- Mobile menu: `aria-expanded` on toggle, focus trap when open
- Skip nav link precedes header in DOM

---

### SiteFooter

**File:** `app/components/layout/SiteFooter.vue`

**Purpose:** Site footer with secondary navigation and legal.

**Props:** None.

**Structure:**

- Secondary nav links: Contact, FAQ, Accessibility, Careers, Private Screenings
- Social media links
- Legal: copyright, terms, privacy policy
- Theater address and phone

**Design Tokens:**

- Background: `var(--surface-container-lowest)` (#0e0e0e)
- Min height: 15rem (240px)
- Text: `var(--tertiary)` (#CCC6B6), `body-sm`
- Links: `var(--on-surface)` (#E5E2E1), gold underline on hover

**Accessibility:**

- `<footer role="contentinfo">`
- Social links have `aria-label` describing destination

---

### NeuralTicker

**File:** `app/components/layout/NeuralTicker.vue`

**Purpose:** Horizontally scrolling ambient data feed.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `items` | `Array<{ text: string; href?: string }>` | Yes | — | Ticker content items |

**Design Tokens:**

- Height: 2rem (32px)
- Background: `var(--surface-container)` (#201f1f)
- Text: `label-sm`, Newsreader
- Scroll speed: 2.5rem/second (configurable via `--ticker-speed`)
- z-index: `var(--z-ticker)` (201)
- Animation: `ease-linear`, continuous

**Accessibility:**

- Visual scrolling content: `aria-hidden="true"`
- Static screen reader alternative: `<div class="sr-only">` with full text
- Pause/play button: only interactive element, `aria-label="Pause ticker"` / `"Play ticker"`, `aria-pressed`
- `<aside aria-label="Now showing updates" aria-live="off">`
- Reduced motion: stops scrolling, displays content statically

---

### MobileNav

**File:** `app/components/layout/MobileNav.vue`

**Purpose:** Fixed bottom navigation bar for mobile (below `screen-md`).

**Props:** None — reads current route for active state.

**Structure:** 5 items maximum: Home, Movies, What's On, Account, More (hamburger).

**Design Tokens:**

- Height: `calc(3.5rem + env(safe-area-inset-bottom, 0px))`
- Background: `var(--surface-container)` (#201f1f)
- z-index: `var(--z-sticky)` (200)
- Active icon: `var(--secondary)` (#DAC769)
- Inactive icon: `var(--tertiary)` (#CCC6B6)

**Accessibility:**

- `<nav role="navigation" aria-label="Mobile navigation">`
- Each item has `aria-label` and `aria-current="page"` when active
- Items meet 3rem minimum touch target

---

### SkipNav

**File:** `app/components/layout/SkipNav.vue`

**Purpose:** Hidden skip navigation link, visible on keyboard focus.

**Design Tokens:**

- z-index: `var(--z-skip-nav)` (900)
- Background: `var(--primary-container)` (#550000)
- Text: `var(--secondary)` (#DAC769)
- Hidden via `transform: translateY(-100%)`, revealed on `:focus-visible`
- Transition: `duration-standard`, `ease-standard`

**Accessibility:**

- First focusable element in DOM
- Links to `#main-content`
- `focus-visible` override: no additional ring — the element itself is the indicator

---

### SidebarNav

**File:** `app/components/layout/SidebarNav.vue`

**Purpose:** Persistent side navigation for account pages.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `items` | `Array<{ label: string; href: string; icon: string }>` | Yes | — | Nav items |

**Design Tokens:**

- Desktop (above `screen-lg`): 15rem rail width
- Tablet (`screen-md` to `screen-lg`): 4rem icon-only rail
- Mobile (below `screen-md`): collapses to MobileNav bottom bar
- Active item: `var(--secondary)` (#DAC769) left edge accent (vertical gradient, per "no divider" rule)

**Accessibility:**

- `<nav role="navigation" aria-label="Account">`
- `aria-current="page"` on active item

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

**Slots:** None.

**Structure:** Composes CvCard. Poster image (2:3 aspect ratio), title (headline-sm, Noto Serif), MovieRatingBadge, genre badges, showtime pills or "Notify Me" CTA. Links to `/movies/:slug`.

**Design Tokens:**

- Poster: `aspect-ratio: 2/3`, `object-fit: cover`, `object-position: top center`
- Title: `headline-sm`, Noto Serif, `var(--on-surface)`
- Card padding: `var(--space-md)`

**Accessibility:**

- Entire card is a link to movie detail
- `alt="[Movie Title] poster"` on poster image
- Showtime pills are `<time datetime="...">` elements

---

### MovieHero

**File:** `app/components/movie/MovieHero.vue`

**Purpose:** Full-bleed hero section with movie backdrop.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `movie` | `Movie` | Yes | — | Movie data (backdrop, title, tagline) |

**Design Tokens:**

- Uses Wide Frame composition (full viewport width)
- Backdrop image with vignette bloom gradient: radial gradient from `primary_container` (#550000) to `surface_container_lowest` (#0e0e0e)
- Title: `display-lg`, Noto Serif, `var(--on-surface)`
- Tagline: `body-lg`, Newsreader, `var(--tertiary)`
- Hero reveal animation: `duration-cinematic` (700ms), `ease-enter`

**Accessibility:**

- Backdrop image: `aria-hidden="true"`, empty `alt=""` (decorative — title and tagline carry the content)
- Reduced motion: no reveal animation, content visible immediately

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

**Design Tokens:**

- Avatar: 3rem (48px), circular crop (avatar exception to radius rule)
- Name: `label-lg`, `var(--on-surface)`
- Character: `label-md`, `var(--tertiary)`

**Accessibility:**

- `alt="[Actor name]"` on profile photos
- Scrollable container: accessible via arrow keys or touch

---

### MovieTrailerEmbed

**File:** `app/components/movie/MovieTrailerEmbed.vue`

**Purpose:** Responsive YouTube video embed.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `trailerKey` | `string` | Yes | — | YouTube video ID from TMDB |
| `title` | `string` | Yes | — | Movie title for accessible label |

**Design Tokens:**

- Aspect ratio: 16:9
- Border radius: 0.125rem

**Accessibility:**

- `<iframe title="[Movie Title] trailer">`
- `loading="lazy"`

---

### MovieRatingBadge

**File:** `app/components/movie/MovieRatingBadge.vue`

**Purpose:** Visual rating indicator (TMDB score).

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `rating` | `number` | Yes | — | Rating out of 10 |

**Design Tokens:** Composes CvBadge with accent variant. Displays score formatted to one decimal.

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

**Design Tokens:**

- Active date tab: `var(--primary-container)` background, `var(--primary)` text
- Time slot buttons: CvButton tertiary variant
- Spacing: `var(--space-sm)` between time slots

**Accessibility:**

- Date tabs: `role="tablist"`, each tab has `role="tab"`, `aria-selected`
- Time slots: `<time datetime="...">` with `aria-label` including full date and time
- Keyboard: arrow keys navigate dates, Tab moves to time slots

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

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `seat-toggled` | `{ seatId: string; selected: boolean }` | Emitted when a seat is selected/deselected |

**Structure:**

Two-column wrapper: pinned row labels (left) + scrollable seat matrix (right). Screen indicator bar (AuditoriumScreenBar) above the grid.

**Design Tokens:**

- Desktop cells: 2.5rem (40px), gap 0.25rem
- Mobile cells (below `screen-md`): 3rem (48px), gap 0.25rem
- Seat visual states (server `status` + client `selectedSeatIds`): available (#2a2a2a), selected (client-only — #550000 + check icon in #FFB4A8), taken (#1c1b1b at 0.4 opacity), held (#1c1b1b at 0.4 opacity, non-interactive), accessible (#2a2a2a + wheelchair icon in #DAC769), premium (#2a2a2a + #675900 bottom edge)
- Row labels: pinned left, `label-md`, `var(--tertiary)`
- Horizontal scroll on mobile with `scroll-snap-type: x proximity`

**Accessibility:**

- `role="grid"`, `aria-label="Theater seating chart, [Screen Name]"`
- Each row: `role="row"`, `aria-label="Row [letter]"`
- Each seat: `role="gridcell"`, `aria-label="Seat [ID], [status]. [Section]. [Price tier]."`
- Selected: `aria-selected="true"`
- Taken: `aria-disabled="true"`
- Keyboard: roving tabindex — arrow keys move between seats, Enter/Space toggles selection, Home/End for first/last in row, Escape deselects all, Tab exits grid
- Screen reader announcements on selection: "Seat [ID] selected. [N] seats selected, [price] total."

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

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `toggle` | — | Seat was clicked/activated |

**Design Tokens:**

- Selection animation: background-color change + `transform: scale(1→1.05→1)`, `duration-standard`, `ease-emphasis`
- Focus: 0.125rem inset `var(--secondary)` outline

---

### AuditoriumScreenBar

**File:** `app/components/booking/AuditoriumScreenBar.vue`

**Purpose:** Visual indicator of the movie screen position above the seat grid.

**Design Tokens:**

- Height: 0.25rem, width: 60% of grid, centered
- Color: `var(--primary-container)` (#550000)

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

**File:** `app/components/booking/CheckoutForm.vue`

**Purpose:** Payment form with Stripe Elements integration.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `total` | `number` | Yes | — | Amount to charge |
| `isAuthenticated` | `boolean` | Yes | — | Whether user is logged in |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `submit` | `{ paymentMethodId: string; email?: string }` | Payment info ready for server |
| `error` | `string` | Payment error occurred |

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

**Structure:** Booking reference, movie/showtime details, seat numbers, food orders, total, QR code, "Add to Calendar" button (.ics), "Print Tickets" button.

**Accessibility:** Optimized for print stylesheet. QR code has `alt="Booking QR code for [reference]"`.

---

### FoodPreOrderPanel

**File:** `app/components/booking/FoodPreOrderPanel.vue`

**Purpose:** Inline food/drink selection during checkout.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `menuItems` | `Array<MenuItem>` | Yes | — | Available menu items |
| `selectedItems` | `Array<{ itemId: string; quantity: number }>` | Yes | — | Currently selected items |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `update` | `Array<{ itemId: string; quantity: number }>` | Items changed |

**Structure:** Compact grid of menu items with quantity selectors. Category tabs for filtering.

---

### PromoCode

**File:** `app/components/booking/PromoCode.vue`

**Purpose:** Promotional code input with apply action.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `appliedCode` | `string \| null` | Yes | — | Currently applied code |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `apply` | `string` | Code submitted for validation |
| `remove` | — | Applied code removed |

---

## Tier 2: Domain Components — Calendar

Located in `app/components/calendar/`.

---

### CalendarGrid

**File:** `app/components/calendar/CalendarGrid.vue`

**Purpose:** Month/week calendar grid with keyboard navigation.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `events` | `Array<CalendarEvent>` | Yes | — | Events for current month/range |
| `selectedDate` | `string` | Yes | — | ISO date string of selected day |
| `view` | `'month' \| 'week' \| 'list'` | No | `'month'` | Display mode |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `select-date` | `string` | Date selected (ISO string) |
| `navigate` | `{ month: number; year: number }` | Month/year changed |

**Design Tokens:**

- Day cell: 3rem (48px) minimum at all breakpoints
- Event dots: color-coded by type (showtime: tertiary, special event: secondary, loyalty: primary-container)

**Accessibility:**

- `role="grid"`, `aria-labelledby` calendar heading
- Roving tabindex: arrow keys for day navigation, Page Up/Down for month, Home/End for first/last day
- Each cell: `aria-label="[Full date]. [N] events."`, `aria-selected` on current selection
- Column headers: `role="columnheader"` with day names

---

### CalendarDayCell

**File:** `app/components/calendar/CalendarDayCell.vue`

**Purpose:** Individual day cell within calendar grid.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `date` | `string` | Yes | — | ISO date |
| `events` | `Array<CalendarEvent>` | Yes | — | Events for this day |
| `selected` | `boolean` | Yes | — | Whether this day is selected |
| `today` | `boolean` | Yes | — | Whether this is today |

---

### CalendarEventList

**File:** `app/components/calendar/CalendarEventList.vue`

**Purpose:** List of events for a selected day.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `events` | `Array<CalendarEvent>` | Yes | — | Events to display |
| `date` | `string` | Yes | — | Selected date for heading |

**Structure:** Grouped by event type. Each event shows time, title, type badge, and link to detail.

---

### CalendarFilters

**File:** `app/components/calendar/CalendarFilters.vue`

**Purpose:** View toggle and event type filter controls.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `activeView` | `'month' \| 'week' \| 'list'` | Yes | — | Current view mode |
| `activeFilters` | `Array<string>` | Yes | — | Active event type filters |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `view-change` | `string` | View mode changed |
| `filter-change` | `Array<string>` | Filters updated |

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
| `tier` | `string` | Yes | — | Current loyalty tier |
| `nextTierAt` | `number` | Yes | — | Points needed for next tier |

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

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `save` | `Partial<User>` | Updated profile fields |

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

**Props:** None — self-contained form.

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `submit` | `{ name: string; email: string; subject: string; message: string }` | Form submitted |

**Structure:** Name (CvInput), email (CvInput), subject (CvInput), message (CvTextarea), submit (CvButton).

---

### ContactMap

**File:** `app/components/content/ContactMap.vue`

**Purpose:** Embedded map with dark theme styling.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `coordinates` | `{ lat: number; lng: number }` | Yes | — | Theater location |

**Accessibility:** `<iframe title="Theater location map">` or static image with `alt` text describing location.

---

### MenuItem

**File:** `app/components/content/MenuItem.vue`

**Purpose:** Food/drink menu item card.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `item` | `MenuItem` | Yes | — | Menu item data |

**Structure:** Image (4:3 aspect), name (headline-sm), description (body-sm), price, dietary badges (CvBadge — vegan, GF, contains nuts, etc.).

---

### MenuCategoryTabs

**File:** `app/components/content/MenuCategoryTabs.vue`

**Purpose:** Horizontally scrolling category filter tabs.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `categories` | `Array<string>` | Yes | — | Category names |
| `active` | `string` | Yes | — | Currently selected category |

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `select` | `string` | Category selected |

**Accessibility:** `role="tablist"`, each tab has `role="tab"`, `aria-selected`.

---

### GiftCardPurchase

**File:** `app/components/content/GiftCardPurchase.vue`

**Purpose:** Gift card purchase form.

**Props:** None — self-contained.

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `purchase` | `{ amount: number; recipientEmail: string; recipientName: string; message: string }` | Purchase submitted |

**Structure:** Amount selector (preset buttons + custom input), recipient name, recipient email, personal message, purchase CTA.

---

### BalanceChecker

**File:** `app/components/content/BalanceChecker.vue`

**Purpose:** Gift card balance lookup.

**Props:** None — self-contained.

**Structure:** Card code input (CvInput), "Check Balance" button, balance display area.

---

### RentalInquiryForm

**File:** `app/components/content/RentalInquiryForm.vue`

**Purpose:** Private screening/rental inquiry form.

**Events:**

| Name | Payload | Description |
| ---- | ------- | ----------- |
| `submit` | `RentalInquiry` | Inquiry submitted |

**Structure:** Event type (CvSelect), date (CvInput type date), guest count (CvInput type number), name, email, message (CvTextarea), submit.

---

### PackageCard

**File:** `app/components/content/PackageCard.vue`

**Purpose:** Private screening package display.

**Props:**

| Name | Type | Required | Default | Description |
| ---- | ---- | -------- | ------- | ----------- |
| `package` | `{ name: string; description: string; startingPrice: number; features: Array<string> }` | Yes | — | Package data |

**Structure:** CvCard with package name (headline-sm), description, feature list, starting price.

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
