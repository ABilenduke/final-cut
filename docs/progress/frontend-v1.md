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
