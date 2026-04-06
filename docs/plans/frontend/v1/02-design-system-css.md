# Plan 02: Design System CSS Foundation

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (nuxt.config.ts registers `main.css`)
> **Unlocks:** Plans 03, 04, 05, 06, 07, 08, 09, 10 (all component plans)

## Overview

Implement the complete CSS foundation for "The Cinematic Void Framework" — all design tokens as CSS custom properties, the browser reset, typography system, six named layout compositions, utility classes, and print stylesheet. Every component in the system depends on these files.

## Reference Documents

- `docs/DESIGN_SYSTEM.md` — Creative vision, color palette, principles
- `docs/DESIGN_SYSTEM_IMPLEMENTATION.md` — CSS implementation guide (primary reference)
- `docs/DESIGN_SYSTEM_STRUCTURE.md` — Layout compositions, spacing, z-index, motion

---

## Tasks

### Task 1: `tokens.css` — CSS Custom Properties

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/assets/css/tokens.css` — create new
- **Details:**
  All design tokens from DESIGN_SYSTEM_IMPLEMENTATION.md Section 2, defined on `:root`:

  **Colors:**
  - Fills: `--primary-container: #550000`, `--secondary-container: #675900`, `--tertiary-container: #29261b`, `--surface: #131313`, `--surface-variant: #3B3636`, `--surface-container-lowest: #0e0e0e`, `--surface-container-low: #1c1b1b`, `--surface-container: #201f1f`, `--surface-container-high: #2a2a2a`
  - Text: `--primary: #FFB4A8`, `--secondary: #DAC769`, `--tertiary: #CCC6B6`, `--on-surface: #E5E2E1`, `--on-tertiary-fixed-variant: #A89F91`
  - Borders: `--outline-variant: #57423E`, `--outline: #A58B86`

  **Spacing:** `--space-2xs` (0.125rem) through `--space-5xl` (8rem)

  **Z-Index:** `--z-recessed` (-1) through `--z-skip-nav` (900)

  **Easing:** `--ease-standard`, `--ease-enter`, `--ease-exit`, `--ease-emphasis`, `--ease-linear`

  **Duration:** `--duration-micro` (100ms), `--duration-standard` (250ms), `--duration-emphasis` (400ms), `--duration-cinematic` (700ms)

  **Breakpoints:** `--screen-sm` (40rem), `--screen-md` (60rem), `--screen-lg` (80rem), `--screen-xl` (100rem) — reference only, cannot use in `@media`

  **Icons:** `--icon-sm` (1rem), `--icon-md` (1.5rem), `--icon-lg` (3rem), `--icon-xl` (4rem)

- **Acceptance Criteria:**
  - [ ] Every token from DESIGN_SYSTEM_IMPLEMENTATION.md Section 2 is defined
  - [ ] All values exactly match the spec (no approximations)
  - [ ] No `#FFFFFF` or `white` anywhere in the file
  - [ ] CSS file parses without errors

---

### Task 2: `reset.css` — Browser Reset

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/assets/css/reset.css` — create new
- **Details:**
  Minimal reset (box-sizing, margin, padding) plus the global `prefers-reduced-motion` reset from DESIGN_SYSTEM_IMPLEMENTATION.md Section 6:

  ```css
  html {
    scrollbar-gutter: stable;
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }
  }
  ```

  `scrollbar-gutter: stable` reserves consistent space for the scrollbar across all pages — required for the Wide Frame (`100vw`) composition to work without horizontal overflow on platforms with visible scrollbars (Windows, Linux).

  Also set `body` to use `--surface` background, `--on-surface` text, and `--font-body` font family.

- **Acceptance Criteria:**
  - [ ] `box-sizing: border-box` applied universally
  - [ ] Default margins and paddings reset
  - [ ] `scrollbar-gutter: stable` on `<html>`
  - [ ] Body uses design system surface color and font
  - [ ] Reduced motion global reset is present
  - [ ] No `#FFFFFF` or `white` values

---

### Task 3: `typography.css` — Type Scale & Font Stacks

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/assets/css/typography.css` — create new
- **Details:**
  Per DESIGN_SYSTEM_IMPLEMENTATION.md Section 3:

  **Font loading:** Uses `@nuxt/fonts` module (configured in Plan 01 `nuxt.config.ts`). Fonts are self-hosted at build time — no Google Fonts `<link>` tags. Font stacks provide serif fallbacks that preserve tone during loading or failure.

  **Font stacks:**
  - `--font-display: 'Noto Serif', Georgia, 'Times New Roman', serif`
  - `--font-body: 'Newsreader', Georgia, 'Times New Roman', serif`

  **Fluid type tokens:** All `clamp()` values exactly as specified:
  - Display: `--type-display-lg` through `--type-display-sm`
  - Headline: `--type-headline-lg` through `--type-headline-sm`
  - Title: `--type-title-lg`, `--type-title-md`
  - Body: `--type-body-lg`, `--type-body-md`, `--type-body-sm`
  - Label: `--type-label-lg`, `--type-label-md`, `--type-label-sm`

  **Usage classes:** `.display-lg` through `.label-sm` with correct font-family, font-size, letter-spacing, and line-height per token.

- **Acceptance Criteria:**
  - [ ] Font stack variables defined on `:root`
  - [ ] All 15 type tokens defined with exact `clamp()` values from spec
  - [ ] Usage classes for every token (display-lg, display-md, display-sm, headline-lg, headline-md, headline-sm, title-lg, title-md, body-lg, body-md, body-sm, label-lg, label-md, label-sm)
  - [ ] Display/headline use Noto Serif with `-0.02em` letter-spacing
  - [ ] Body/label use Newsreader

---

### Task 4: `layouts.css` — Named Compositions

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/assets/css/layouts.css` — create new
- **Details:**
  Per DESIGN_SYSTEM_IMPLEMENTATION.md Section 4 and DESIGN_SYSTEM_STRUCTURE.md Section 2:

  **Global container:**
  ```css
  .container {
    width: 100%; max-width: 90rem; margin-inline: auto;
    padding-inline: var(--space-2xl);
  }
  ```
  With responsive padding reductions at `screen-md` and `screen-sm`.

  **Six named compositions:**
  1. `.establishing-shot` — `grid-template-columns: 65fr 35fr`, gap `var(--space-xl)`, collapses to single column at `59.999rem`
  2. `.rack-focus` — `grid-template-columns: 35fr 65fr`, collapses with `order: -1` on primary
  3. `.wide-frame` — `width: 100vw; margin-inline: calc(-50vw + 50%)` full-bleed
  4. `.close-up` — `max-width: 40rem; margin-inline: auto`
  5. `.ensemble` — `grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr))`
  6. `.auditorium` — Two-column: pinned labels + scrollable grid

  **Sidebar layout** for account pages.

- **Acceptance Criteria:**
  - [ ] All six compositions render correctly at desktop and mobile breakpoints
  - [ ] Establishing Shot collapses to single column below 60rem
  - [ ] Rack Focus reorders columns on collapse (primary content first)
  - [ ] Wide Frame bleeds edge-to-edge regardless of parent container
  - [ ] Close-Up centers at 40rem max-width
  - [ ] Ensemble grid auto-fills with minimum 17.5rem columns
  - [ ] Auditorium grid scrolls horizontally on mobile with pinned row labels

---

### Task 5: `utilities.css` — Utility Classes

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/assets/css/utilities.css` — create new
- **Details:**
  Per DESIGN_SYSTEM_IMPLEMENTATION.md Sections 4-6:

  - **Aspect ratios:** `.aspect-poster` (2/3), `.aspect-video` (16/9), `.aspect-square` (1/1), `.aspect-menu-item` (4/3)
  - **Touch targets:** `.touch-target` (min 3rem, gated below screen-md)
  - **Glassmorphism:** `.glass` — base: `surface_variant` (#3B3636) at 85% opacity (solid fallback); enhanced via `@supports (backdrop-filter: blur(1px))`: 60% opacity + `backdrop-filter: blur(20px)` + `-webkit-backdrop-filter: blur(20px)`
  - **Skeleton shimmer:** `@keyframes shimmer` (1500ms, linear, infinite)
  - **Screen reader only:** `.sr-only` (visually hidden but accessible)
  - **Edge catch:** `.edge-catch` — `outline_variant` at 15% opacity decorative border
  - **Focus indicator:** Global `:focus-visible` double-ring gold glow per Section 8

- **Acceptance Criteria:**
  - [ ] `.sr-only` hides content visually but keeps it accessible
  - [ ] `.glass` produces glassmorphism effect on dark backgrounds
  - [ ] `:focus-visible` applies double-ring gold glow globally
  - [ ] Skeleton shimmer animation respects `prefers-reduced-motion` (solid fill, no animation)
  - [ ] Touch target enforces 3rem minimum below screen-md

---

### Task 6: `print.css` — Print Stylesheet

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/assets/css/print.css` — create new
- **Details:**
  Per PURCHASE_FLOW.md Section 5: Print layout for booking confirmation. Suppress all navigation, interactive elements, and non-essential content. Show: theater name, booking details, QR code, seats, total.

  ```css
  @media print {
    body { background: white; color: black; }
    nav, footer, .mobile-nav, .neural-ticker, button:not(.print-visible) { display: none; }
    /* Allow #FFFFFF ONLY in print stylesheet */
  }
  ```

- **Acceptance Criteria:**
  - [ ] Navigation, footer, and chrome elements hidden in print
  - [ ] Booking confirmation content remains visible
  - [ ] QR code renders as static image in print
  - [ ] Text is black on white for readability
  - [ ] `#FFFFFF` usage is confined to this file only

---

### Task 7: `main.css` — Import Aggregator

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/assets/css/main.css` — create new
- **Details:**
  Single entry point importing all CSS files in correct order:
  ```css
  @import './reset.css';
  @import './tokens.css';
  @import './typography.css';
  @import './layouts.css';
  @import './utilities.css';
  @import './print.css';
  ```

- **Acceptance Criteria:**
  - [ ] All 6 CSS files imported in correct order (reset before tokens before typography)
  - [ ] No CSS outside of imports in this file
  - [ ] Nuxt loads `main.css` via the `css` array in `nuxt.config.ts`

---

## Testing Requirements

- **Visual Verification:** Load the dev server and verify:
  - Background is `#131313` (void black)
  - Text is `#E5E2E1` (warm off-white)
  - Fonts load (Noto Serif for headings, Newsreader for body)
  - Layout compositions work at desktop (>60rem) and mobile (<60rem)
- **Guardrail Check:** Search entire CSS directory for violations:
  - No `#FFFFFF`, `#fff`, `white`, or `rgb(255` outside `print.css`
  - No `border: 1px solid` for layout boundaries
  - No `border-radius` values other than `0.125rem`, `0.25rem`, `0`, or `50%` (avatar exception)
  - No `rounded-full`, `rounded-lg` or similar Tailwind-like values
- **Reduced Motion:** Enable `prefers-reduced-motion: reduce` in browser devtools and verify all animations stop

## Dependencies Map

```
Task 1 (tokens.css) ← all other tasks reference tokens
Task 2 (reset.css) ← sets up base styles
Task 3 (typography.css) ← needs font stack tokens from Task 1
Task 4 (layouts.css) ← needs spacing tokens from Task 1
Task 5 (utilities.css) ← needs color + spacing tokens from Task 1
Task 6 (print.css) ← independent, but ordered last
Task 7 (main.css) ← aggregates all above, write last
```

## Risks & Open Questions

1. **Tailwind CSS 4** — CLAUDE.md lists Tailwind CSS 4 in the tech stack, but DESIGN_SYSTEM_IMPLEMENTATION.md Section 7 explicitly says "No Tailwind." Decision: follow the design system docs — use CSS custom properties only. Tailwind may be used for rapid prototyping of non-component styles if needed, but component styling must use the token system.
2. **Container queries** — Mentioned in DESIGN_SYSTEM_IMPLEMENTATION.md Section 5 for variable-width contexts. Browser support is good but verify Nuxt's SSR handles them correctly.
3. **`calc(-50vw + 50%)` scrollbar issue** — Resolved. `scrollbar-gutter: stable` on `<html>` (in `reset.css`, Task 2) reserves consistent scrollbar space, preventing the `100vw` technique from causing horizontal overflow. If edge cases arise on specific platforms, `overflow-x: clip` on the Wide Frame's parent container is the targeted fallback.
