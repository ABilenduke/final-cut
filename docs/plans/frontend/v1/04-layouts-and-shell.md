# Plan 04: Layouts & Shell Components

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 02 (CSS layouts/tokens), Plan 03 (UI primitives — CvButton, CvIcon, CvBadge)
> **Unlocks:** Plans 06, 07, 08, 09, 10 (all page-level plans)

## Overview

Build the four Nuxt layouts and six layout components that form the persistent site shell. Layouts define the page chrome (header, footer, navigation) and layout components are the reusable pieces within them. Every page in the application renders inside one of these layouts.

## Reference Documents

- `docs/SITE_ARCHITECTURE.md` — Layouts section (default, account, purchase, blank)
- `docs/COMPONENT_INVENTORY.md` — Tier 2: Layout Components (SiteHeader, SiteFooter, NeuralTicker, MobileNav, SkipNav, SidebarNav)
- `docs/PURCHASE_FLOW.md` — Purchase layout details, PurchaseStepIndicator

---

## Tasks

### Task 1: SkipNav

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/layout/SkipNav.vue`
- **Details:**
  First focusable element in DOM. Hidden via `transform: translateY(-100%)`, revealed on `:focus-visible`. Links to `#main-content`. z-index: `--z-skip-nav` (900). Background: `--primary-container`, text: `--secondary`.

- **Acceptance Criteria:**
  - [ ] Hidden until focused via keyboard Tab
  - [ ] Visible with correct styling on focus
  - [ ] Links to `#main-content` anchor
  - [ ] First focusable element in the DOM

---

### Task 2: SiteHeader

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/layout/SiteHeader.vue`
- **Details:**
  Fixed top nav bar. Height: 4rem. Background: `--surface-container`. z-index: `--z-sticky` (200).

  **Structure:**
  - Logo (links to `/`, Noto Serif `headline-sm`)
  - Primary nav: Movies, What's On, Food & Drink, Events, Gift Cards
  - Auth controls: "Sign In" (guest) or user avatar dropdown (authenticated) — reads from `useAuth()`
  - Location switcher: dropdown showing available theater locations (from `useLocations()`). Displays active location name. On change: updates localStorage, triggers refresh of location-scoped data. Default to first location or localStorage value.
  - Mobile: hamburger toggle below `screen-md`

  **Accessibility:** `<header role="banner">` containing `<nav role="navigation" aria-label="Primary">`. Mobile menu: `aria-expanded` on toggle, focus trap when open.

- **Acceptance Criteria:**
  - [ ] Fixed at top with correct height and background
  - [ ] Logo links to home
  - [ ] Nav links with active state (gold underline for current route)
  - [ ] Auth controls switch between guest/authenticated state
  - [ ] Hamburger menu on mobile with focus trap
  - [ ] `aria-label="Primary"` on nav element
  - [ ] Location switcher displays active location
  - [ ] Dropdown lists all available locations
  - [ ] Selection persists to localStorage
  - [ ] Location change triggers data refresh

---

### Task 3: SiteFooter

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/layout/SiteFooter.vue`
- **Details:**
  Background: `--surface-container-lowest` (#0e0e0e). Min height: 15rem.

  **Structure:**
  - Secondary nav: Contact, FAQ, Accessibility, Careers, Private Screenings
  - Social media links with `aria-label`
  - Legal: copyright, terms, privacy
  - Theater address and phone

- **Acceptance Criteria:**
  - [ ] Correct background color and minimum height
  - [ ] All secondary nav links present and functional
  - [ ] Social links have descriptive `aria-label`
  - [ ] `<footer role="contentinfo">` wrapper

---

### Task 4: NeuralTicker

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/layout/NeuralTicker.vue`
- **Details:**
  Horizontally scrolling ambient data feed. Height: 2rem. Background: `--surface-container`. Text: `label-sm`, Newsreader. Scroll speed: 2.5rem/second. z-index: `--z-ticker` (201).

  **Props:** `items: Array<{ text: string; href?: string }>`

  **Accessibility:**
  - Visual content: `aria-hidden="true"`
  - Static SR alternative: `.sr-only` div with full text
  - Pause/play button: `aria-label="Pause ticker"` / `"Play ticker"`, `aria-pressed`
  - Wrapper: `<aside aria-label="Now showing updates" aria-live="off">`
  - Reduced motion: stops scrolling, displays statically

- **Acceptance Criteria:**
  - [ ] Smooth horizontal scroll animation
  - [ ] Pause/play button works
  - [ ] Screen reader gets static text, not scrolling content
  - [ ] Stops scrolling when `prefers-reduced-motion: reduce` is active
  - [ ] Links within items are clickable

---

### Task 5: MobileNav

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/layout/MobileNav.vue`
- **Details:**
  Fixed bottom bar below `screen-md`. Height: `calc(3.5rem + env(safe-area-inset-bottom, 0px))`. Background: `--surface-container`. 5 items: Home, Movies, What's On, Account, More.

  Active icon: `--secondary` (#DAC769). Inactive: `--tertiary` (#CCC6B6).

  **Accessibility:** `<nav aria-label="Mobile navigation">`. Each item has `aria-label` and `aria-current="page"` when active. 3rem minimum touch targets.

- **Acceptance Criteria:**
  - [ ] Only visible below `screen-md` breakpoint
  - [ ] Fixed to bottom with safe area inset
  - [ ] Active route highlighted in gold
  - [ ] All items meet 3rem touch target
  - [ ] Hidden on desktop

---

### Task 6: SidebarNav

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/layout/SidebarNav.vue`
- **Details:**
  **Props:** `items: Array<{ label: string; href: string; icon: string }>`

  Three responsive states:
  - Desktop (above `screen-lg`): 15rem rail with labels + icons
  - Tablet (`screen-md` to `screen-lg`): 4rem icon-only rail
  - Mobile (below `screen-md`): collapses to MobileNav bottom bar

  Active item: `--secondary` left edge accent (vertical gradient).

  **Accessibility:** `<nav aria-label="Account">`, `aria-current="page"` on active item.

- **Acceptance Criteria:**
  - [ ] Three responsive states at correct breakpoints
  - [ ] Active item highlighted with gold accent
  - [ ] Icon-only mode on tablet shows tooltips
  - [ ] Collapses to bottom bar on mobile

---

### Task 7: PurchaseStepIndicator

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/booking/PurchaseStepIndicator.vue`
- **Details:**
  Per PURCHASE_FLOW.md and COMPONENT_INVENTORY.md.

  **Props:** `currentStep` (1|2|3), `completedSteps` (Array), `navigableSteps` (Array, defaults to completedSteps)
  **Events:** `navigate` (step number)

  Three labeled steps: "Pick Your Seats" → "Add Food & Pay" → "You're In"

  - Completed steps: clickable links (when in `navigableSteps`)
  - Current step: gold underline (`--secondary`)
  - Future steps: disabled, greyed (`--outline-variant`)
  - Confirmation page: `navigableSteps=[]`, all completed but non-clickable

  **Accessibility:** `<nav aria-label="Purchase steps">`. Current step: `aria-current="step"`.

- **Acceptance Criteria:**
  - [ ] 3 steps display with correct labels
  - [ ] Current step has gold underline
  - [ ] Completed steps are clickable when navigable
  - [ ] On confirmation: all steps non-clickable
  - [ ] `aria-current="step"` on active step

---

### Task 8: `default.vue` Layout

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/layouts/default.vue`
- **Details:**
  Standard layout for public pages.

  **Structure (top to bottom):**
  1. SkipNav
  2. SiteHeader
  3. NeuralTicker
  4. `<main id="main-content"><slot /></main>`
  5. SiteFooter
  6. MobileNav (mobile only)
  7. CvToast container (reads from useToast queue)

- **Acceptance Criteria:**
  - [ ] All shell components render in correct order
  - [ ] `#main-content` anchor exists for skip nav
  - [ ] Toast notifications render above all content
  - [ ] Layout applies to pages without explicit `definePageMeta`

---

### Task 9: `account.vue` Layout

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/layouts/account.vue`
- **Details:**
  Extends default layout with SidebarNav. Used for all `/account/*` routes.

  **Structure:** Same as default but main content area is split: SidebarNav on left + page slot on right. Responsive: sidebar collapses per SidebarNav spec.

  Account nav items: Dashboard (`/account`), Profile, Orders, Loyalty, Bookings, Payment Methods.

- **Acceptance Criteria:**
  - [ ] Sidebar renders with all account nav items
  - [ ] Active route highlighted
  - [ ] Responsive collapse at tablet/mobile breakpoints
  - [ ] Inherits header, footer, ticker from default layout pattern

---

### Task 10: `purchase.vue` Layout

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/layouts/purchase.vue`
- **Details:**
  Minimal layout for purchase flow. Per PURCHASE_FLOW.md.

  **Structure:**
  - Header: logo only + PurchaseStepIndicator + session timer display
  - CartSummary sidebar (desktop) / bottom sheet (mobile)
  - Main content slot
  - No footer
  - Route-leave middleware to clear cart when leaving `/purchase/*` tree

- **Acceptance Criteria:**
  - [ ] Minimal header with logo and step indicator
  - [ ] No footer or full navigation
  - [ ] Cart sidebar/bottom sheet renders
  - [ ] Cart clears when navigating away from purchase routes

---

### Task 11: `blank.vue` Layout

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/layouts/blank.vue`
- **Details:**
  No chrome. Centered content. Used for `/auth/*` routes.

  **Structure:** Logo centered at top, content slot centered vertically and horizontally. No header, footer, or navigation.

- **Acceptance Criteria:**
  - [ ] No header, footer, or navigation elements
  - [ ] Content centered on page
  - [ ] Logo links to home

---

## Testing Requirements

- **E2E Tests:**
  - Skip nav: Tab to reveal, Enter to jump to main content
  - Mobile nav: visible below breakpoint, hidden above
  - Header responsive: full nav on desktop, hamburger on mobile
- **Accessibility:**
  - Landmark roles verified (banner, navigation, contentinfo, main)
  - Skip nav keyboard flow works end-to-end
  - Focus management on mobile menu open/close

## Dependencies Map

```
Task 1 (SkipNav) ← independent
Task 2 (SiteHeader) ← needs CvButton, CvIcon
Task 3 (SiteFooter) ← needs CvIcon
Task 4 (NeuralTicker) ← independent
Task 5 (MobileNav) ← needs CvIcon
Task 6 (SidebarNav) ← needs CvIcon
Task 7 (PurchaseStepIndicator) ← independent

Task 8 (default.vue) ← needs Tasks 1-5
Task 9 (account.vue) ← needs Task 8, Task 6
Task 10 (purchase.vue) ← needs Task 7
Task 11 (blank.vue) ← independent
```

## Risks & Open Questions

1. **Layout nesting** — `account.vue` effectively extends `default.vue`. In Nuxt 4, layouts don't nest. May need to duplicate the shell structure or use a shared composable/component for the common chrome.
2. **Cart in purchase layout** — CartSummary component (from Plan 08) doesn't exist yet. Use a placeholder slot or stub during this plan; wire up the real component in Plan 08.
3. **NeuralTicker data source** — What populates the ticker? Options: hardcoded strings, next few showtimes from API, or a dedicated endpoint. Start with hardcoded strings; data integration comes in Plan 05/06.
4. **NeuralTicker data source and location** — When ticker becomes data-driven (Plan 05/06), its content will need location context to show relevant showtimes and events for the user's selected location.
