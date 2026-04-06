# Design System Structure: The Engineering Blueprint

## Companion to `DESIGN_SYSTEM.md` — The Cinematic Void Framework

This document defines the structural rules that govern how The Final Cut is built: spacing, layout, responsiveness, sizing, depth ordering, motion, and accessibility. It is the construction drawing to the design system's storyboard. Every value is a decision. Every decision is final.

Color tokens, typography choices, elevation philosophy, and component styling live in `DESIGN_SYSTEM.md`. This document references them by token name. It does not redefine them.

---

## 1. Spacing System: The Measured Silence

Space in this system is not filler — it is the vacuum between objects, the pause between cuts. Every gap is deliberate. The base unit is **8px**, and the scale is derived from strict multiples of it. No rogue values. No Tailwind defaults leaking in.

### Spacing Scale

| Token | rem | px ref | Role |
|---|---|---|---|
| `space-2xs` | 0.125rem | 2px | Optical adjustment only. Icon nudges, seat grid inner gaps, sub-pixel alignment corrections. |
| `space-xs` | 0.25rem | 4px | Optical adjustment only. Tight icon padding, inline badge gaps, input affix spacing. |
| `space-sm` | 0.5rem | 8px | Compact component internals. Tight list item padding, chip padding, button icon-to-label gap. |
| `space-md` | 1rem | 16px | Default component padding. Form field gaps, card internal padding, inline element spacing. |
| `space-lg` | 1.5rem | 24px | Component group spacing. Card-to-card gaps, list section spacing, sidebar item gaps. |
| `space-xl` | 2rem | 32px | Layout-level margins. Sidebar internal padding, form section breaks, content block margins. |
| `space-2xl` | 3rem | 48px | Section padding. Vertical padding within major page sections, composition gutter width. |
| `space-3xl` | 4rem | 64px | Major section breaks. Hero internal padding, page section vertical separation. |
| `space-4xl` | 6rem | 96px | Page-level vertical rhythm. Separation between top-level page regions. |
| `space-5xl` | 8rem | 128px | Maximum breathing room. Hero vertical margins, landing page section isolation. |

### Usage Rules

**Sub-grid exceptions (2px, 4px):** These values exist exclusively for optical adjustments — situations where the 8px grid produces a visually incorrect result. They are never used as layout spacing, component margins, or section gaps. If you are reaching for `space-2xs` or `space-xs` for anything other than fine-tuning visual alignment, step up to `space-sm`.

**No 12px value exists.** 12px is not a multiple of 8. Its absence is intentional. If a component feels like it needs 12px of padding, use 8px and adjust the element's line-height or visual weight instead. The scale does not accommodate exceptions that break its mathematical basis.

**Page gutters** (horizontal margin between content and viewport edge):

| Breakpoint | Token | Gutter |
|---|---|---|
| Below `screen-sm` | `space-md` | 1rem (16px) |
| `screen-sm` to `screen-md` | `space-xl` | 2rem (32px) |
| `screen-md` and above | `space-2xl` | 3rem (48px) |

**Component internal padding:** `space-sm` (0.5rem) for compact components (chips, badges, tight list items), `space-md` (1rem) for standard components (cards, form fields, buttons).

**Between-section vertical spacing:** `space-3xl` (4rem) as the default. `space-4xl` (6rem) between top-level page regions on the landing page and detail pages. `space-5xl` (8rem) reserved for hero-to-content separation on the landing page only.

**Asymmetric spacing:** Per Section 6 of `DESIGN_SYSTEM.md` ("Use Asymmetry"), spacing between elements within a composition does not need to be symmetrical. A headline may sit `space-lg` (1.5rem) above its body text but `space-3xl` (4rem) below the preceding section. The scale values are the vocabulary; composition is the grammar.

---

## 2. Layout System: Composing the Frame

Per Section 6 of `DESIGN_SYSTEM.md`: "Do not align everything to a 12-column grid. Treat the viewport as a shot — compose it." This layout system provides six named compositions that replace conventional grid frameworks. Each is a reusable framing device — a camera setup that can be applied to different content.

### Global Container

**Max-width:** 90rem (1440px). All contained content is centered horizontally within this boundary. Uses `margin-inline: auto` with responsive `padding-inline` matching the page gutter scale above.

**Beyond max-width:** The `surface` background extends full-bleed to the viewport edge. Content remains centered at 90rem.

**Always full-bleed (ignoring max-width):** Hero sections using the "Wide Frame" composition, the Neural Ticker, and the vignette bloom gradient backgrounds described in `DESIGN_SYSTEM.md` Section 2 ("Glass & Gradient" Rule).

### Named Compositions

#### 2.1 "Establishing Shot" — 65/35 Offset Split

The wide establishing shot. Primary content dominates the left two-thirds; secondary content occupies the right third. The asymmetry creates directional tension — the eye enters from the dominant panel and discovers the secondary.

**Column ratio:** 65% / 35% via `grid-template-columns: 65fr 35fr`
**Gap:** `space-2xl` (3rem)
**Use:** Movie detail (poster + synopsis), feature editorial (image + text), event highlight (info + action panel).
**Responsive collapse:** Below `screen-md` (60rem), stacks to single column. Primary panel renders first; secondary follows. Gap reduces to `space-lg`.

#### 2.2 "Rack Focus" — 35/65 Reverse Split

The true mirror of the Establishing Shot. Secondary content on the left, primary on the right. Used to alternate visual rhythm when stacking multiple split sections down a page.

**Column ratio:** 35% / 65% via `grid-template-columns: 35fr 65fr`
**Gap:** `space-2xl` (3rem)
**Use:** Alternating content sections, reversed movie detail layout, testimonial + image pairings.
**Responsive collapse:** Below `screen-md`, stacks to single column. The primary panel (right column, the 65% side) renders first via `order: -1`, maintaining content hierarchy regardless of visual layout.

#### 2.3 "Wide Frame" — Full-Bleed Cinematic

The panoramic shot. Background fills the entire viewport via `width: 100vw; margin-inline: calc(-50vw + 50%)`.

**Content max-width:** 90rem (1440px) for general content; 45rem (720px) for text-only blocks.
**Use:** Landing page hero, vignette bloom sections, full-width image galleries, event announcement banners.
**Responsive collapse:** Remains full-bleed at all breakpoints. Below `screen-sm`, text block expands to full container width with page gutters. Image galleries switch from multi-column to single-column with horizontal scroll.

#### 2.4 "Close-Up" — Centered Narrow Column

The intimate shot. A single centered column for focused reading. No distractions in the peripheral frame.

**Max-width:** 40rem (640px) via `max-width: 40rem; margin-inline: auto`
**Use:** Article body text, FAQ accordion, legal/terms pages, checkout confirmation, accessibility information page.
**Responsive collapse:** No structural change needed — already single-column.

#### 2.5 "Ensemble" — Fluid Card Grid

The ensemble cast. A fluid grid of equally-weighted cards using `repeat(auto-fill, minmax(17.5rem, 1fr))`.

**Minimum card width:** 17.5rem (280px)
**Gap:** `space-lg` (1.5rem)
**Use:** Now Showing/Coming Soon listings, merch store products, food & drink menu items, blog archive, special events listing.
**Responsive behavior:** Self-adapting. ~4 columns at `screen-lg`, 2–3 at `screen-md`, 1 below `screen-sm`. No explicit breakpoint overrides needed.
**Container query:** When an Ensemble grid appears inside a reduced-width panel, use container queries to ensure cards don't compress below 17.5rem — fall back to single column at `max-width: 37.5rem`.

#### 2.6 "Auditorium" — Seat Selection Grid

The most complex layout on the site. A spatial representation of a physical theater auditorium, rendered as an interactive grid. This composition has unique requirements that no other layout shares.

**Structure:**
- **Screen indicator:** A curved or straight bar at the top of the grid. Height: 0.25rem, width: 60% of grid width, centered, using `primary_container`.
- **Row labels:** Pinned left column that does NOT scroll with the seat matrix. Always visible regardless of horizontal scroll position. Width: `space-xl` (2rem). Font: `label-md`, color: `tertiary`.
- **Section labels:** Optional row grouping headers (e.g., "Premium," "Standard," "Accessible") spanning full grid width.
- **Seat cells:** Square interactive cells in the scrollable grid body.

**Cell sizing:**

| Breakpoint | Cell size | Gap | Notes |
|---|---|---|---|
| `screen-md` and above | 2.5rem (40px) | 0.25rem (`space-xs`) | Desktop pointer precision allows sub-3rem targets |
| Below `screen-md` | 3rem (48px) | 0.25rem (`space-xs`) | Meets 3rem touch target minimum natively — no zoom required |

Mobile cells are 3rem (48px), not smaller. The spec does not rely on pinch-to-zoom to satisfy touch target requirements.

**DOM structure:** Row labels must be separated from the scrollable seat matrix so they remain visible during horizontal panning. The layout uses a two-column grid wrapper (`grid-template-columns: var(--space-xl) 1fr`): a fixed label column with `position: sticky; left: 0` and a scrollable grid with `overflow-x: auto`. The seat grid uses `grid-template-columns: repeat(var(--seats-per-row), var(--seat-size))` with `width: fit-content`.

**Seat states** (visual, managed via data attributes):
- `available` — `surface_container_high` (#2a2a2a)
- `selected` — `primary_container` (#550000) with `primary` (#FFB4A8) check icon
- `taken` — `surface_container_low` (#1c1b1b), non-interactive, reduced opacity (0.4)
- `accessible` — `available` state + wheelchair icon in `secondary` (#DAC769)
- `premium` — `available` state + subtle `secondary_container` (#675900) bottom edge accent

**Overflow and interaction on mobile:** Below `screen-md`, the 3rem cells produce a grid wider than most viewports. The seat matrix scrolls horizontally (`scroll-snap-type: x proximity`) while row labels remain pinned to the left edge. Pinch-to-zoom is available as a supplementary navigation aid, not as a workaround for undersized targets.

Keyboard navigation and accessibility for the Auditorium are specified in Section 7.

### Sidebar Layout

For pages with persistent left-rail navigation (account dashboard, settings, loyalty program management):

- **Desktop (above `screen-lg`):** `grid-template-columns: 15rem 1fr`, gap `space-2xl`
- **Tablet (`screen-md` to `screen-lg`):** `grid-template-columns: 4rem 1fr` (icon-only rail), gap `space-md`
- **Mobile (below `screen-md`):** Single column. Sidebar collapses to a fixed bottom navigation bar at `height: calc(3.5rem + env(safe-area-inset-bottom, 0px))`, `z-index: var(--z-sticky)`, `background: var(--surface-container)`. 5 items maximum. Content area needs `padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px))` to account for the bar.

---

## 3. Responsive Behavior: The Aspect Ratio Shift

A film shot at 2.39:1 doesn't just crop to 16:9 — it recomposes. Responsive behavior in this system follows the same principle. Layouts don't shrink; they recompose at defined thresholds with enough distance between them to justify the transformation.

### Breakpoints

| Token | rem | px ref | Target Context |
|---|---|---|---|
| `screen-sm` | 40rem | 640px | Large phones, small tablets (portrait) |
| `screen-md` | 60rem | 960px | Tablets (landscape), small laptops |
| `screen-lg` | 80rem | 1280px | Standard desktop monitors |
| `screen-xl` | 100rem | 1600px | Large desktop, ultrawide, lobby kiosks |

**20rem (320px) of separation** between each breakpoint. This spacing is deliberate — it provides enough viewport distance to justify distinct layout rules at each tier.

**Design-first breakpoint, not device-first:** `screen-md` at 60rem is the primary layout pivot. Most split compositions (Establishing Shot, Rack Focus) collapse here. Most components shift to touch-optimized sizing here.

### Fluid Typography

Display and headline type tokens scale fluidly between `screen-sm` (40rem) and `screen-lg` (80rem) using `clamp()`. Body and label sizes remain fixed — they are already optimized for readability and do not benefit from scaling.

| Token | Min (mobile) | Max (desktop) | Fluid rule |
|---|---|---|---|
| `display-lg` | 2.25rem | 3.5rem | `clamp(2.25rem, 1.25rem + 2.5vw, 3.5rem)` |
| `display-md` | 1.875rem | 2.8125rem | `clamp(1.875rem, 1.125rem + 1.875vw, 2.8125rem)` |
| `display-sm` | 1.5rem | 2.25rem | `clamp(1.5rem, 0.9rem + 1.5vw, 2.25rem)` |
| `headline-lg` | 1.375rem | 2rem | `clamp(1.375rem, 0.875rem + 1.25vw, 2rem)` |
| `headline-md` | 1.25rem | 1.75rem | `clamp(1.25rem, 0.85rem + 1vw, 1.75rem)` |
| `headline-sm` | 1.125rem | 1.5rem | `clamp(1.125rem, 0.825rem + 0.75vw, 1.5rem)` |
| `title-lg` | 1rem | 1.375rem | `clamp(1rem, 0.7rem + 0.75vw, 1.375rem)` |
| `title-md` | — | 1rem | Fixed — scaling range too small |
| `body-lg` | — | 1.125rem | Fixed |
| `body-md` | — | 1rem | Fixed |
| `body-sm` | — | 0.875rem | Fixed |
| `label-lg` | — | 0.875rem | Fixed |
| `label-md` | — | 0.75rem | Fixed |
| `label-sm` | — | 0.6875rem | Fixed |

All display and headline tokens retain `letter-spacing: -0.02em` as specified in `DESIGN_SYSTEM.md` Section 3. This value does not scale.

### Layout Collapse Summary

| Composition | screen-xl (1600+) | screen-lg (1280–1600) | screen-md (960–1280) | screen-sm (640–960) | Below screen-sm (<640) |
|---|---|---|---|---|---|
| Establishing Shot | 65/35 | 65/35 | Single column, stacked | Single column, stacked | Single column, stacked |
| Rack Focus | 35/65 | 35/65 | Single column, primary first | Single column, primary first | Single column, primary first |
| Wide Frame | Full-bleed, inset text 45rem | Full-bleed, inset text 45rem | Full-bleed, inset text 45rem | Full-bleed, text full-width | Full-bleed, text full-width |
| Close-Up | Centered 40rem | Centered 40rem | Centered 40rem | Full-width + gutters | Full-width + gutters |
| Ensemble | 4–5 columns | 3–4 columns | 2–3 columns | 1–2 columns | 1 column |
| Auditorium | Native grid (2.5rem cells) | Native grid (2.5rem cells) | 3rem cells, horizontal scroll | 3rem cells, horizontal scroll | 3rem cells, horizontal scroll |
| Sidebar | 15rem rail + content | 15rem rail + content | 4rem icon rail + content | Bottom bar + full content | Bottom bar + full content |

### Touch Targets

Below `screen-md` (60rem), all interactive elements must meet a **3rem (48px) minimum** for both height and width. This applies to: buttons, seat grid cells, navigation links (increase tap area with padding), icon buttons (1.5rem icon within 3rem container), calendar day cells, and dropdown items.

The seat grid meets touch target requirements natively on mobile: cells are 3rem with 0.25rem gaps. No pinch-to-zoom workaround is needed for selection accuracy.

---

## 4. Sizing Rules: The Scale of Things

Every element has a defined size. No component should rely on content alone to determine its dimensions — that produces inconsistency across pages. These are the measurements.

### Component Heights

| Component | Default | Small | Large | Notes |
|---|---|---|---|---|
| Button | 3rem (48px) | 2.25rem (36px) | 3.5rem (56px) | Small variant: **pointer-device only** (see rule below) |
| Input field | 3rem (48px) | 2.25rem (36px) | — | Small variant: **pointer-device only** (see rule below) |
| Nav bar | 4rem (64px) | — | — | Fixed height at all breakpoints |
| Footer | auto, min 15rem (240px) | — | — | Content-driven, minimum enforced |
| Card thumbnail | 11.25rem (180px) | 7.5rem (120px) | 15rem (240px) | Non-interactive — touch target rule does not apply |
| Seat grid cell | 2.5rem (40px) | 3rem (48px) | — | Mobile variant is larger to meet touch target minimum |
| Bottom nav bar (mobile) | 3.5rem (56px) | — | — | Fixed, mobile only |
| Neural Ticker | 2rem (32px) | — | — | Non-interactive single-line height, `label-sm` text |
| Modal header | 3.5rem (56px) | — | — | Consistent across all modal types |
| Calendar day cell | 3rem (48px) | — | — | No small variant — 3rem at all breakpoints |

**Sub-3rem interactive sizing rule:** Small variants for buttons (2.25rem) and input fields (2.25rem) exist exclusively for desktop contexts where a fine pointer is available. Gate them behind `@media (pointer: fine)`. Below `screen-md`, all interactive elements are 3rem minimum regardless of variant class. No exceptions.

### Icon Sizes

| Context | Size | Token |
|---|---|---|
| Inline (within body text) | 1rem (16px) | `icon-sm` |
| UI controls (buttons, nav, inputs) | 1.5rem (24px) | `icon-md` |
| Feature callouts, empty states | 3rem (48px) | `icon-lg` |
| Hero decorative, landing page | 4rem (64px) | `icon-xl` |

Icons at `icon-sm` and `icon-md` are functional. Icons at `icon-lg` and `icon-xl` are decorative or illustrative — they carry visual weight but should not be the sole means of conveying information (see Section 7: Accessibility).

### Image Aspect Ratios

| Content Type | Ratio | Dimensions (reference) | Use |
|---|---|---|---|
| Movie poster | 2:3 | 400×600, 200×300 | Listings, detail page hero, ticket confirmation |
| Hero banner | 21:9 | 2100×900, 1260×540 | Landing page, event heroes, wide promotional |
| Thumbnail | 16:9 | 320×180, 480×270 | Blog cards, editorial features, video previews |
| Event card | 4:3 | 400×300, 280×210 | Calendar view, special events, private rentals |
| Avatar | 1:1 | 120×120, 80×80 | Account UI, user reviews, staff profiles |

All images use `object-fit: cover` to fill their aspect-ratio container without distortion. Posters use `object-position: top center` to prioritize faces and titles. Hero banners use `object-position: center`.

### Avatar Sizes

| Context | Size | Notes |
|---|---|---|
| Inline mention | 2rem (32px) | Within text or metadata lines |
| List item | 3rem (48px) | Account dashboard lists, review bylines |
| Profile header | 5rem (80px) | Top of account dashboard, user profile |
| Account settings | 7.5rem (120px) | Editable avatar in settings page |

All avatars are clipped to `border-radius: 50%`. This is the single exception to the `sm`-or-`none` radius rule in `DESIGN_SYSTEM.md` Section 6 — avatars are not UI chrome, they are content, and circular cropping is a photographic convention, not a design softness.

---

## 5. Z-Index Scale: Depth of Field

Depth in this system is managed through surface tiers (see `DESIGN_SYSTEM.md` Section 4), but stacking order requires explicit z-index assignment. Without a defined scale, z-index values drift into the thousands. This scale provides named layers with 100 units of headroom between each.

### Layer Scale

| Token | Value | Usage |
|---|---|---|
| `z-recessed` | -1 | Decorative pseudo-elements behind their parent (vignette bloom gradients, background textures) |
| `z-base` | 0 | Default document flow. All static content. |
| `z-card` | 100 | Elevated cards (`surface_container_high`), hover-lifted elements |
| `z-sticky` | 200 | Sticky nav bar, sticky section headers, sticky table headers |
| `z-ticker` | 201 | Neural Ticker — renders just above sticky elements but behind all overlays |
| `z-dropdown` | 300 | Dropdown menus, autocomplete panels, select option lists, date picker popover |
| `z-modal-backdrop` | 400 | Modal overlay scrim (glassmorphism: `surface_variant` at 60% opacity + `backdrop-filter: blur(20px)`) |
| `z-modal` | 500 | Modal dialog content (ticket purchase confirmation, seat selection detail, image lightbox) |
| `z-toast` | 600 | Toast notifications ("Added to cart," "Seat selected," booking confirmations) |
| `z-tooltip` | 700 | Tooltips (seat info on hover, icon label tooltips) |
| `z-skip-nav` | 900 | Skip navigation link — always the top layer when visible |

### Rules

**No z-index outside this scale.** If a component needs to be layered, it maps to one of these tokens. If none of the existing layers fit, the component's layering requirement should be re-examined — it is likely a composition problem, not a stacking problem.

**The Neural Ticker sits at `z-ticker` (201)** — one above `z-sticky` (200). This ensures it overlaps sticky headers when they share a viewport edge, but remains behind all overlay content (dropdowns, modals, toasts).

**Modals use both `z-modal-backdrop` and `z-modal`.** The backdrop scrim and the dialog content occupy separate layers so that the glassmorphism blur effect on the backdrop does not clip the modal's own shadow or content.

---

## 6. Motion & Animation: The Slow Dolly

Motion in this system is deliberate and weighted. Every transition should feel like a camera move — a slow dolly, a measured pan, a rack focus that pulls your attention from one plane to another. Nothing snaps. Nothing bounces. Nothing overshoots.

### Easing Curves

| Token | Value | Use |
|---|---|---|
| `ease-standard` | `cubic-bezier(0.2, 0.0, 0.0, 1.0)` | Default for most state transitions |
| `ease-enter` | `cubic-bezier(0.0, 0.0, 0.0, 1.0)` | Elements appearing: modals, toasts, dropdowns |
| `ease-exit` | `cubic-bezier(0.2, 0.0, 1.0, 1.0)` | Elements leaving: modals closing, toasts dismissing |
| `ease-emphasis` | `cubic-bezier(0.05, 0.7, 0.1, 1.0)` | Significant state changes: seat selection, checkout transition |
| `ease-linear` | `linear` | Neural Ticker scroll only |

### Duration Tokens

| Token | Value | Use |
|---|---|---|
| `duration-micro` | 100ms | Button hover/active, icon state toggle, checkbox check |
| `duration-standard` | 250ms | Underline reveal, input focus glow, card hover, dropdown open/close |
| `duration-emphasis` | 400ms | Modal entrance/exit, accordion expand/collapse, page section reveal |
| `duration-cinematic` | 700ms | Page transitions, hero image reveal, vignette bloom fade-in |

### Component Motion Summary

Each component's motion spec combines a duration token with an easing curve. The key patterns:

- **Hover/active states** — `duration-micro` + `ease-standard` (button color shift, card lift `translateY(-0.125rem)`, active press `scale(0.98)`)
- **Reveal/dismiss** — `duration-standard` + `ease-enter`/`ease-exit` (dropdown slide, toast slide, underline `scaleX`)
- **Structural transitions** — `duration-emphasis` + `ease-enter`/`ease-exit` (modal `scale(0.95→1)`, accordion `grid-template-rows: 0fr→1fr`)
- **Cinematic moments** — `duration-cinematic` + `ease-enter` (hero `translateY(1rem→0)` + fade, page crossfade, vignette bloom)
- **Continuous** — `ease-linear` (Neural Ticker at 2.5rem/second via `--ticker-speed`, skeleton shimmer at 1500ms infinite)
- **Emphasis** — `duration-standard` + `ease-emphasis` (seat selection `scale(1→1.05→1)` + color change)

### Reduced Motion

All motion respects `prefers-reduced-motion: reduce`. No exceptions. The global reset sets all `animation-duration` and `transition-duration` to `0.01ms !important`.

**Per-component overrides** beyond the global reset:

| Component | Reduced-motion behavior |
|---|---|
| Neural Ticker | Stops scrolling. Displays full text content statically, wrapping if needed. |
| Hero reveal | No translate or fade. Content visible immediately at full opacity. |
| Page transitions | Instant cut. No crossfade. |
| Skeleton loading | Solid `surface_container_low` fill. No shimmer. |
| Seat selection | Color change applies instantly. No scale pulse. |
| Vignette bloom | Gradient visible immediately at full opacity. No fade-in. |
| Modal entrance/exit | Instant appear/disappear. No scale or opacity transition. |

---

## 7. Accessibility: Universal Admission

This theater does not have a separate entrance. Every seat — front row, balcony, accessible — is reached through the same doors. WCAG 2.1 AA is the minimum standard. Where AAA is achievable without compromising the design language, it is the target.

### Contrast Audit

The full contrast audit is in `DESIGN_SYSTEM.md` § Token Mapping. Summary: **all foreground tokens on dark surface tiers pass AA or better.** The existing dark palette is accessibility-safe for all standard text usage.

#### Remediation Summary

| Issue | Remediation |
|---|---|
| Text on `secondary_container` (#675900) | Use `on_surface` (#E5E2E1) for normal-size text. `primary`, `secondary`, `tertiary` are safe for `title-lg` and above (large text AA threshold: 3:1). |
| `outline` on `secondary_container` | Do not use. If a border is needed on `secondary_container`, use `on_surface` at 30% opacity (decorative) or full opacity (functional). |
| `outline_variant` at 15% opacity anywhere | Decorative use only (~1.06:1 contrast). Never rely on it for functional boundaries. Use `outline` (#A58B86) at full opacity for accessible borders (4.54:1+ on all surface tiers). |
| Neural Ticker text (`on_tertiary_fixed_variant`) | Token hex value undefined in `DESIGN_SYSTEM.md`. Before implementation, resolve its value and verify contrast against `surface` and `surface_container` at minimum 4.5:1. |

### Focus Indicators

Focus indicators must be visible on all surface tiers without violating the "No-Line" rule. The solution: a **double-ring glow** using `secondary` (Gold, #DAC769) via `box-shadow: 0 0 0 0.125rem var(--surface), 0 0 0 0.25rem var(--secondary)`. The inner ring uses the local surface color, creating visual separation between the element edge and the gold ring.

**Contrast:** `secondary` against `surface`: 10.93:1 (AAA). Against `surface_container_high`: 8.44:1 (AAA).

**Per-component focus adjustments:**

| Component | Focus style |
|---|---|
| Buttons | Double-ring glow (default) |
| Input fields | Underline transitions to `secondary` (Gold) + subtle gold outer glow. The glow serves as the focus indicator — no additional ring needed. |
| Cards (interactive) | Double-ring glow on the entire card |
| Seat grid cells | **Focus:** 0.125rem `secondary` outline (inset) — this is the keyboard cursor. **Selected:** `primary_container` fill with `primary` check icon. **Focus + Selected:** Both render simultaneously. Focus and selection are independent visual states. |
| Nav links | Gold underline (already defined as hover/active state). Focus adds the double-ring glow in addition. |
| Modal close button | Double-ring glow (default) |

### Skip Navigation

A hidden link at the top of every page, visible only on keyboard focus. Uses `transform: translateY(-100%)` to hide, revealed on `:focus-visible`. Background: `primary_container`, text: `secondary`, `z-index: var(--z-skip-nav)` (900). Links to `#main-content`.

### ARIA Landmark Structure

Every page follows this landmark structure: `<header role="banner">` with `<nav aria-label="Primary">`, optional `<aside aria-label="Now showing updates">` (Neural Ticker), `<main id="main-content" tabindex="-1">`, optional `<aside>` with `<nav aria-label="Account">` (sidebar), `<footer role="contentinfo">`.

### Keyboard Navigation Patterns

#### Seat Selection Grid (Auditorium)

The seat grid implements a **roving tabindex** pattern within a `role="grid"` with `aria-label="Theater seating chart, [Screen Name]"`. Each row has `role="row"` with `aria-label="Row [letter]"`. Each seat has `role="gridcell"`.

| Key | Action |
|---|---|
| `Arrow Right` | Move focus to next seat in row |
| `Arrow Left` | Move focus to previous seat in row |
| `Arrow Down` | Move focus to same position in next row |
| `Arrow Up` | Move focus to same position in previous row |
| `Home` | Move focus to first available seat in current row |
| `End` | Move focus to last available seat in current row |
| `Enter` / `Space` | Toggle seat selection (select/deselect) |
| `Escape` | Deselect all seats and return focus to the grid container |
| `Tab` | Exit the grid; move focus to next interactive element |

**Screen reader announcements:** On focus: "[Seat ID], [status]. Row [letter], seat [number]. [Section] section. [Price tier]." On selection: "Seat [ID] selected. [Total] seats selected, [price] total." Taken seats are `aria-disabled="true"` and announced as "unavailable."

#### Neural Ticker

Decorative ambient content — excluded from tab order. Scrolling content is `aria-hidden="true"`. Screen readers receive a static `sr-only` text alternative. Only interactive element is the pause/play button (`aria-label="Pause ticker"` / `"Play ticker"`, `aria-pressed`).

#### Modal Dialogs

Focus trap per WAI-ARIA dialog pattern. `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing to title. Tab cycles within modal (wraps). Escape closes. On open: focus to first focusable element. On close: focus returns to trigger. Background receives `inert` attribute.

#### "What's On" Calendar

Grid navigation pattern with `role="grid"`. Each cell has `aria-label="[Full date]. [N] events."` and `aria-selected`.

| Key | Action |
|---|---|
| `Arrow Right/Left` | Next/previous day |
| `Arrow Down/Up` | Same day, next/previous week |
| `Page Down/Up` | Next/previous month |
| `Home/End` | First/last day of current month |
| `Enter` / `Space` | Select date, reveal events |
| `Tab` | Exit calendar grid |

### Screen Reader Conventions

**Decorative images:** `aria-hidden="true"` and empty `alt=""`. This includes: vignette bloom backgrounds, section texture overlays, purely atmospheric hero images where the content is conveyed by adjacent text.

**Meaningful images:** Descriptive `alt` text. Movie posters: `alt="[Movie Title] poster"`. Event photos: `alt="[Event description]"`.

**Icon buttons:** Must have `aria-label` describing the action. **Icon + text buttons:** Icon is `aria-hidden="true"`, visible text serves as the accessible name.

**Loading states:** Skeleton placeholders use `aria-busy="true"` on the container.

**Price and time formatting:** Use `<time datetime="...">` for showtimes. Use machine-readable values in `aria-label` when visual formatting is ambiguous.

---

## 8. Print & Export: The Lobby Card

Print is not a primary concern for a theater booking site, but two scenarios justify a stylesheet: a user printing their ticket confirmation, and a user printing event or showtime information for someone who doesn't have a phone.

### Strategy: Suppress and Simplify

The print stylesheet suppresses the interface and preserves the content: all backgrounds become white, all text becomes black, shadows and text-shadows are removed. Interface chrome (header, nav, Neural Ticker, skip-nav, footer, buttons, sidebar, toasts, modal backdrops) is hidden via `display: none`. Images are grayscaled. External link URLs are appended as text via `::after`. Page breaks are avoided after headings and inside cards.

### Ticket Confirmation Print Layout

The ticket confirmation page receives a dedicated print treatment. When a user completes a booking, the confirmation view is print-optimized:

- Theater name and logo (as text, not image)
- Movie title, showtime, date
- Seat numbers and section
- Order number and QR code (if applicable — QR renders in print as a static image)
- Food & drink pre-orders, if any
- Total price

The Auditorium grid, seat selection interface, and all navigation are suppressed. Only the booking summary prints.

---

**Final Frame:**
This document is the construction drawing. `DESIGN_SYSTEM.md` tells you what the film looks like. This document tells you how to build the set. Every value is load-bearing. If you find yourself improvising a spacing value, an arbitrary z-index, or an undocumented breakpoint — stop. The answer is in this document, or it needs to be added to it. The system is the constraint, and the constraint is what makes it look intentional.
