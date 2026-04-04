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

### Token Definitions

```css
:root {
  --space-2xs: 0.125rem;  /* 2px  */
  --space-xs:  0.25rem;   /* 4px  */
  --space-sm:  0.5rem;    /* 8px  */
  --space-md:  1rem;      /* 16px */
  --space-lg:  1.5rem;    /* 24px */
  --space-xl:  2rem;      /* 32px */
  --space-2xl: 3rem;      /* 48px */
  --space-3xl: 4rem;      /* 64px */
  --space-4xl: 6rem;      /* 96px */
  --space-5xl: 8rem;      /* 128px */
}
```

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

**Max-width:** 90rem (90rem). All contained content is centered horizontally within this boundary.

**Beyond max-width:** The `surface` background extends full-bleed to the viewport edge. Content remains centered at 90rem. This applies to lobby kiosk screens, ultrawide monitors, and any viewport exceeding the container.

**Always full-bleed (ignoring max-width):** Hero sections using the "Wide Frame" composition, the Neural Ticker, and the vignette bloom gradient backgrounds described in `DESIGN_SYSTEM.md` Section 2 ("Glass & Gradient" Rule).

```css
.container {
  width: 100%;
  max-width: 90rem; /* 1440px */
  margin-inline: auto;
  padding-inline: var(--space-2xl); /* 48px gutters on desktop */
}

@media (max-width: 59.999rem) { /* below screen-md */
  .container {
    padding-inline: var(--space-xl); /* 32px */
  }
}

@media (max-width: 39.999rem) { /* below screen-sm */
  .container {
    padding-inline: var(--space-md); /* 16px */
  }
}
```

### Named Compositions

#### 2.1 "Establishing Shot" — 65/35 Offset Split

The wide establishing shot. Primary content dominates the left two-thirds; secondary content occupies the right third. The asymmetry creates directional tension — the eye enters from the dominant panel and discovers the secondary.

**Column ratio:** 65% / 35%
**Gap:** `space-2xl` (3rem)
**Use:** Movie detail (poster + synopsis), feature editorial (image + text), event highlight (info + action panel).

```css
.layout-establishing-shot {
  display: grid;
  grid-template-columns: 65fr 35fr;
  gap: var(--space-2xl);
  align-items: start;
}
```

**Responsive collapse:** Below `screen-md` (60rem), stacks to single column. The primary panel renders first; secondary follows. Gap reduces to `space-lg` (1.5rem).

```css
@media (max-width: 59.999rem) {
  .layout-establishing-shot {
    grid-template-columns: 1fr;
    gap: var(--space-lg);
  }
}
```

#### 2.2 "Rack Focus" — 35/65 Reverse Split

The true mirror of the Establishing Shot. Secondary content on the left, primary on the right. Used to alternate visual rhythm when stacking multiple split sections down a page — the eye shifts focus from one side to the other, like a rack focus between foreground and background.

**Column ratio:** 35% / 65%
**Gap:** `space-2xl` (3rem)
**Use:** Alternating content sections, reversed movie detail layout, testimonial + image pairings.

```css
.layout-rack-focus {
  display: grid;
  grid-template-columns: 35fr 65fr;
  gap: var(--space-2xl);
  align-items: start;
}
```

**Responsive collapse:** Below `screen-md` (60rem), stacks to single column. The primary panel (right column, the 65% side) renders first via `order: -1`, maintaining content hierarchy regardless of visual layout.

```css
@media (max-width: 59.999rem) {
  .layout-rack-focus {
    grid-template-columns: 1fr;
    gap: var(--space-lg);
  }
  .layout-rack-focus > :last-child {
    order: -1;
  }
}
```

#### 2.3 "Wide Frame" — Full-Bleed Cinematic

The panoramic shot. Background fills the entire viewport. Content is optionally inset within a narrower readable column, centered within the full-bleed expanse. The negative space is the point.

**Background:** Full viewport width, ignores container max-width.
**Content max-width:** 90rem (1440px) for general content; 45rem (720px) for text-only blocks.
**Use:** Landing page hero, vignette bloom sections, full-width image galleries, event announcement banners.

```css
.layout-wide-frame {
  width: 100vw;
  margin-inline: calc(-50vw + 50%);
  padding-inline: var(--space-2xl);
}

.layout-wide-frame__content {
  max-width: 90rem;
  margin-inline: auto;
}

.layout-wide-frame__text {
  max-width: 45rem; /* 720px — optimal reading width */
}
```

**Responsive collapse:** Remains full-bleed at all breakpoints. Below `screen-sm` (40rem), text block expands to full container width with page gutters (`space-md`). Image galleries switch from multi-column to single-column with horizontal scroll.

#### 2.4 "Close-Up" — Centered Narrow Column

The intimate shot. A single centered column for focused reading. No distractions in the peripheral frame.

**Max-width:** 40rem (640px)
**Use:** Article body text, FAQ accordion, legal/terms pages, checkout confirmation, accessibility information page.

```css
.layout-close-up {
  max-width: 40rem; /* 640px */
  margin-inline: auto;
}
```

**Responsive collapse:** No structural change needed — already single-column. Below `screen-sm`, the max-width constraint becomes irrelevant as the viewport is narrower than 40rem.

#### 2.5 "Ensemble" — Fluid Card Grid

The ensemble cast. A fluid grid of equally-weighted cards that fills available space. The grid self-adjusts column count based on container width — no fixed column numbers.

**Minimum card width:** 17.5rem (280px)
**Gap:** `space-lg` (1.5rem)
**Use:** Now Showing/Coming Soon listings, merch store products, food & drink menu items, blog archive, special events listing.

```css
.layout-ensemble {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr));
  gap: var(--space-lg);
}
```

**Responsive behavior:** Self-adapting. At `screen-lg` (80rem) within a 90rem container: ~4 columns. At `screen-md` (60rem): 2–3 columns. Below `screen-sm` (40rem): 1 column. No explicit breakpoint overrides needed — `auto-fill` and `minmax` handle the collapse.

**Container query enhancement:** When an Ensemble grid appears inside a sidebar or reduced-width panel, use container queries to ensure cards don't compress below 17.5rem:

```css
.layout-ensemble-container {
  container-type: inline-size;
}

@container (max-width: 37.5rem) { /* 600px — room for 2 cards + gap */
  .layout-ensemble {
    grid-template-columns: 1fr;
  }
}
```

#### 2.6 "Auditorium" — Seat Selection Grid

The most complex layout on the site. A spatial representation of a physical theater auditorium, rendered as an interactive grid. This composition has unique requirements that no other layout shares.

**Structure:**
- **Screen indicator:** A curved or straight bar at the top of the grid representing the movie screen. Fixed within the scroll container. Height: 0.25rem, width: 60% of grid width, centered, using `primary_container` (#550000).
- **Row labels:** Pinned left column that does NOT scroll with the seat matrix. Always visible regardless of horizontal scroll position. Width: `space-xl` (2rem). Font: `label-md`, color: `tertiary`.
- **Section labels:** Optional row grouping headers (e.g., "Premium," "Standard," "Accessible") spanning full grid width.
- **Seat cells:** Square interactive cells in the scrollable grid body.

**Cell sizing:**

| Breakpoint | Cell size | Gap | Notes |
|---|---|---|---|
| `screen-md` and above | 2.5rem (40px) | 0.25rem (`space-xs`) | Desktop pointer precision allows sub-3rem targets |
| Below `screen-md` | 3rem (48px) | 0.25rem (`space-xs`) | Meets 3rem touch target minimum natively — no zoom required |

Mobile cells are 3rem (48px), not smaller. The spec does not rely on pinch-to-zoom to satisfy touch target requirements. The 3rem cell size produces a wider grid on mobile, which is handled by horizontal scrolling (see overflow below). The 0.25rem gap provides reliable touch discrimination between adjacent cells.

**DOM structure:**

Row labels must be separated from the scrollable seat matrix so they remain visible during horizontal panning. The layout uses a two-column wrapper: a fixed label column and a scrollable grid.

```html
<div class="layout-auditorium-wrapper">
  <!-- Pinned row labels — never scrolls horizontally -->
  <div class="layout-auditorium-labels" aria-hidden="true">
    <div class="auditorium-label">A</div>
    <div class="auditorium-label">B</div>
    <!-- ... -->
  </div>
  <!-- Scrollable seat matrix -->
  <div class="layout-auditorium-scroll">
    <div class="layout-auditorium" role="grid">
      <div role="row" aria-label="Row A">
        <div role="gridcell">A1</div>
        <!-- ... -->
      </div>
    </div>
  </div>
</div>
```

**Grid definition:**

```css
.layout-auditorium-wrapper {
  display: grid;
  grid-template-columns: var(--space-xl) 1fr; /* label column + scrollable area */
  gap: 0;
}

.layout-auditorium-labels {
  display: flex;
  flex-direction: column;
  position: sticky;
  left: 0;
  z-index: var(--z-card);
  background: var(--surface); /* prevent bleed-through from scrolling content */
}

.auditorium-label {
  height: var(--seat-size);
  margin-bottom: var(--seat-gap);
  display: flex;
  align-items: center;
  justify-content: center;
}

.layout-auditorium-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.layout-auditorium {
  --seat-size: 2.5rem;    /* 40px */
  --seat-gap: 0.25rem;    /* 4px  */

  display: grid;
  grid-template-columns: repeat(var(--seats-per-row), var(--seat-size));
  grid-auto-rows: var(--seat-size);
  gap: var(--seat-gap);
  width: fit-content;
}

@media (max-width: 59.999rem) {
  .layout-auditorium {
    --seat-size: 3rem; /* 48px — meets touch target minimum */
  }
  .layout-auditorium-scroll {
    scroll-snap-type: x proximity;
  }
  .layout-auditorium {
    touch-action: pan-x pan-y pinch-zoom;
  }
}
```

**Seat states** (visual, managed via data attributes):
- `available` — `surface_container_high` (#2a2a2a)
- `selected` — `primary_container` (#550000) with `primary` (#FFB4A8) check icon
- `taken` — `surface_container_low` (#1c1b1b), non-interactive, reduced opacity (0.4)
- `accessible` — `available` state + wheelchair icon in `secondary` (#DAC769)
- `premium` — `available` state + subtle `secondary_container` (#675900) bottom edge accent

**Overflow and interaction on mobile:**
Below `screen-md`, the 3rem cells produce a grid wider than most viewports. The seat matrix scrolls horizontally while row labels remain pinned to the left edge. Pinch-to-zoom is available as a supplementary navigation aid (for surveying the full auditorium), not as a workaround for undersized targets.

Keyboard navigation and accessibility for the Auditorium are specified in Section 7.

### Sidebar Layout

For pages with persistent left-rail navigation (account dashboard, settings, loyalty program management):

**Desktop — above `screen-lg` (80rem / 1280px):**
```css
.layout-sidebar {
  display: grid;
  grid-template-columns: 15rem 1fr; /* 240px rail */
  gap: var(--space-2xl);
}
```

**Tablet — `screen-md` to `screen-lg` (60rem–80rem):**
```css
@media (min-width: 60rem) and (max-width: 79.999rem) {
  .layout-sidebar {
    grid-template-columns: 4rem 1fr; /* 64px icon rail */
    gap: var(--space-md);
  }
}
```

**Mobile — below `screen-md` (60rem):**
Sidebar collapses to a fixed bottom navigation bar. The grid becomes single-column. The bottom bar is a separate fixed-position element.

```css
@media (max-width: 59.999rem) {
  .layout-sidebar {
    grid-template-columns: 1fr;
  }

  .sidebar-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: calc(3.5rem + env(safe-area-inset-bottom, 0px)); /* 56px + device safe area */
    padding-bottom: env(safe-area-inset-bottom, 0px);
    z-index: var(--z-sticky);
    background: var(--surface-container);
    display: flex;
    justify-content: space-around;
    align-items: center;
    /* 5 items maximum in bottom bar */
  }

  /* Account for bottom bar height + safe area */
  .layout-sidebar__content {
    padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)); /* 56px bar + 16px space + safe area */
  }
}
```

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

```css
:root {
  --screen-sm: 40rem;   /* 640px  */
  --screen-md: 60rem;   /* 960px  */
  --screen-lg: 80rem;   /* 1280px */
  --screen-xl: 100rem;  /* 1600px */
}
```

**20rem (320px) of separation** between each breakpoint. This spacing is deliberate — it provides enough viewport distance to justify distinct layout rules at each tier. The seat selection grid ("Auditorium") and "What's On" calendar are the most layout-sensitive pages; they need this headroom.

**Design-first breakpoint, not device-first:** `screen-md` at 60rem is the primary layout pivot. Most split compositions (Establishing Shot, Rack Focus) collapse here. Most components shift to touch-optimized sizing here.

### Fluid Typography

Display and headline type tokens scale fluidly between `screen-sm` (40rem) and `screen-lg` (80rem) using `clamp()`. Body and label sizes remain fixed — they are already optimized for readability and do not benefit from scaling.

The existing type scale from `DESIGN_SYSTEM.md` Section 3 defines these tokens. The `clamp()` values below implement fluid scaling using the formula: `clamp(min, preferred, max)` where preferred uses the viewport-relative calculation `(min + (max - min) * ((100vw - 40rem) / (80rem - 40rem)))`.

| Token | Min (mobile) | Max (desktop) | Fluid rule |
|---|---|---|---|
| `display-lg` | 2.25rem (36px) | 3.5rem (56px) | `clamp(2.25rem, 1.25rem + 2.5vw, 3.5rem)` |
| `display-md` | 1.875rem (30px) | 2.8125rem (45px) | `clamp(1.875rem, 1.125rem + 1.875vw, 2.8125rem)` |
| `display-sm` | 1.5rem (24px) | 2.25rem (36px) | `clamp(1.5rem, 0.9rem + 1.5vw, 2.25rem)` |
| `headline-lg` | 1.375rem (22px) | 2rem (32px) | `clamp(1.375rem, 0.875rem + 1.25vw, 2rem)` |
| `headline-md` | 1.25rem (20px) | 1.75rem (28px) | `clamp(1.25rem, 0.85rem + 1vw, 1.75rem)` |
| `headline-sm` | 1.125rem (18px) | 1.5rem (24px) | `clamp(1.125rem, 0.825rem + 0.75vw, 1.5rem)` |
| `title-lg` | 1rem (16px) | 1.375rem (22px) | `clamp(1rem, 0.7rem + 0.75vw, 1.375rem)` |
| `title-md` | 0.9375rem (15px) | 1.125rem (18px) | Fixed at 1rem — scaling range too small to justify fluid behavior |
| `body-lg` | — | 1.125rem (18px) | Fixed |
| `body-md` | — | 1rem (16px) | Fixed |
| `body-sm` | — | 0.875rem (14px) | Fixed |
| `label-lg` | — | 0.875rem (14px) | Fixed |
| `label-md` | — | 0.75rem (12px) | Fixed |
| `label-sm` | — | 0.6875rem (11px) | Fixed |

```css
:root {
  --type-display-lg:  clamp(2.25rem, 1.25rem + 2.5vw, 3.5rem);
  --type-display-md:  clamp(1.875rem, 1.125rem + 1.875vw, 2.8125rem);
  --type-display-sm:  clamp(1.5rem, 0.9rem + 1.5vw, 2.25rem);
  --type-headline-lg: clamp(1.375rem, 0.875rem + 1.25vw, 2rem);
  --type-headline-md: clamp(1.25rem, 0.85rem + 1vw, 1.75rem);
  --type-headline-sm: clamp(1.125rem, 0.825rem + 0.75vw, 1.5rem);
  --type-title-lg:    clamp(1rem, 0.7rem + 0.75vw, 1.375rem);
  --type-title-md:    1rem;
  --type-body-lg:     1.125rem;
  --type-body-md:     1rem;
  --type-body-sm:     0.875rem;
  --type-label-lg:    0.875rem;
  --type-label-md:    0.75rem;
  --type-label-sm:    0.6875rem;
}
```

All display and headline tokens retain `letter-spacing: -0.02em` as specified in `DESIGN_SYSTEM.md` Section 3. This value does not scale — it is a fixed ratio applied at all sizes.

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

Below `screen-md` (60rem), all interactive elements must meet a **3rem (48px) minimum** for both height and width. This applies to:

- Buttons (already 3rem default — no change needed)
- Seat grid cells (3rem on mobile — meets minimum natively)
- Navigation links (increase tap area with padding, even if visual size is smaller)
- Icon buttons (1.5rem icon within 3rem touch container)
- Calendar day cells
- Dropdown items

```css
@media (max-width: 59.999rem) {
  .touch-target {
    min-height: 3rem;  /* 48px */
    min-width: 3rem;   /* 48px */
  }
}
```

The seat grid meets touch target requirements natively on mobile: cells are 3rem with 0.25rem gaps. No pinch-to-zoom workaround is needed for selection accuracy. Horizontal scrolling provides access to the full grid width.

### Container Queries

Container queries are used for components that appear in variable-width contexts — primarily cards within the Ensemble grid that also appear in sidebar widgets or modal panels.

```css
.card-container {
  container-type: inline-size;
  container-name: card;
}

/* Compact card layout when container is narrow */
@container card (max-width: 20rem) { /* 320px */
  .card {
    /* Switch from vertical to horizontal layout */
    flex-direction: row;
  }
  .card__thumbnail {
    width: 5rem; /* 80px */
    aspect-ratio: 1;
  }
}
```

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

**Sub-3rem interactive sizing rule:** Small variants for buttons (2.25rem) and input fields (2.25rem) exist exclusively for desktop contexts where a fine pointer is available. They must never render on touch devices. Gate them behind `@media (pointer: fine)`:

```css
@media (pointer: fine) {
  .btn-sm { height: 2.25rem; }
  .input-sm { height: 2.25rem; }
}
```

Below `screen-md` (60rem), all interactive elements are 3rem minimum regardless of variant class. No exceptions.

### Icon Sizes

| Context | Size | Token |
|---|---|---|
| Inline (within body text) | 1rem (16px) | `icon-sm` |
| UI controls (buttons, nav, inputs) | 1.5rem (24px) | `icon-md` |
| Feature callouts, empty states | 3rem (48px) | `icon-lg` |
| Hero decorative, landing page | 4rem (64px) | `icon-xl` |

```css
:root {
  --icon-sm: 1rem;     /* 16px */
  --icon-md: 1.5rem;   /* 24px */
  --icon-lg: 3rem;     /* 48px */
  --icon-xl: 4rem;     /* 64px */
}
```

Icons at `icon-sm` and `icon-md` are functional. Icons at `icon-lg` and `icon-xl` are decorative or illustrative — they carry visual weight but should not be the sole means of conveying information (see Section 7: Accessibility).

### Image Aspect Ratios

| Content Type | Ratio | Dimensions (reference) | Use |
|---|---|---|---|
| Movie poster | 2:3 | 400×600, 200×300 | Listings, detail page hero, ticket confirmation |
| Hero banner | 21:9 | 2100×900, 1260×540 | Landing page, event heroes, wide promotional |
| Thumbnail | 16:9 | 320×180, 480×270 | Blog cards, editorial features, video previews |
| Event card | 4:3 | 400×300, 280×210 | Calendar view, special events, private rentals |
| Avatar | 1:1 | 120×120, 80×80 | Account UI, user reviews, staff profiles |

```css
.aspect-poster    { aspect-ratio: 2 / 3; }
.aspect-hero      { aspect-ratio: 21 / 9; }
.aspect-thumbnail { aspect-ratio: 16 / 9; }
.aspect-event     { aspect-ratio: 4 / 3; }
.aspect-avatar    { aspect-ratio: 1 / 1; }
```

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

Depth in this system is managed through surface tiers (see `DESIGN_SYSTEM.md` Section 4), but stacking order requires explicit z-index assignment. Without a defined scale, z-index values drift into the thousands. This scale provides named layers with 100 units of headroom between each — enough room for sub-layers within a tier without colliding with adjacent tiers.

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

```css
:root {
  --z-recessed:       -1;
  --z-base:            0;
  --z-card:          100;
  --z-sticky:        200;
  --z-ticker:        201;
  --z-dropdown:      300;
  --z-modal-backdrop: 400;
  --z-modal:         500;
  --z-toast:         600;
  --z-tooltip:       700;
  --z-skip-nav:      900;
}
```

### Rules

**No z-index outside this scale.** If a component needs to be layered, it maps to one of these tokens. If none of the existing layers fit, the component's layering requirement should be re-examined — it is likely a composition problem, not a stacking problem.

**The Neural Ticker sits at `z-ticker` (201)** — one above `z-sticky` (200). This ensures it overlaps sticky headers when they share a viewport edge, but remains behind all overlay content (dropdowns, modals, toasts). It is ambient telemetry — present but never obstructive.

**Modals use both `z-modal-backdrop` and `z-modal`.** The backdrop scrim and the dialog content occupy separate layers so that the glassmorphism blur effect on the backdrop does not clip the modal's own shadow or content.

---

## 6. Motion & Animation: The Slow Dolly

Motion in this system is deliberate and weighted. Every transition should feel like a camera move — a slow dolly, a measured pan, a rack focus that pulls your attention from one plane to another. Nothing snaps. Nothing bounces. Nothing overshoots. The audience should feel the mass of the interface moving.

### Easing Curves

| Token | Value | Character | Use |
|---|---|---|---|
| `ease-standard` | `cubic-bezier(0.2, 0.0, 0.0, 1.0)` | Smooth deceleration — arrives with weight | Default for most state transitions |
| `ease-enter` | `cubic-bezier(0.0, 0.0, 0.0, 1.0)` | Pure deceleration — resolves out of nothing | Elements appearing: modals opening, toasts entering, dropdowns expanding |
| `ease-exit` | `cubic-bezier(0.2, 0.0, 1.0, 1.0)` | Quick start, accelerates away | Elements leaving: modals closing, toasts dismissing |
| `ease-emphasis` | `cubic-bezier(0.05, 0.7, 0.1, 1.0)` | Dramatic arc — the hero moment | Significant state changes: seat selection confirmation, checkout transition |
| `ease-linear` | `linear` | Constant velocity — mechanical | Neural Ticker scroll only |

```css
:root {
  --ease-standard: cubic-bezier(0.2, 0.0, 0.0, 1.0);
  --ease-enter:    cubic-bezier(0.0, 0.0, 0.0, 1.0);
  --ease-exit:     cubic-bezier(0.2, 0.0, 1.0, 1.0);
  --ease-emphasis: cubic-bezier(0.05, 0.7, 0.1, 1.0);
  --ease-linear:   linear;
}
```

### Duration Tokens

| Token | Value | Character | Use |
|---|---|---|---|
| `duration-micro` | 100ms | Instantaneous feedback — the click register | Button hover/active color shift, icon state toggle, checkbox check |
| `duration-standard` | 250ms | The functional transition — fast enough to not block, slow enough to track | Underline reveal, input focus glow, card hover elevation, dropdown open/close |
| `duration-emphasis` | 400ms | The deliberate move — you watch it happen | Modal entrance/exit, layout reflow, accordion expand/collapse, page section reveal |
| `duration-cinematic` | 700ms | The establishing shot — sets the scene | Page transitions, hero image reveal, vignette bloom fade-in, landing page load sequence |

```css
:root {
  --duration-micro:     100ms;
  --duration-standard:  250ms;
  --duration-emphasis:  400ms;
  --duration-cinematic: 700ms;
}
```

### Component Motion Specifications

| Element | Property | Duration | Easing | Description |
|---|---|---|---|---|
| Button hover | `background-color`, `box-shadow` | `duration-micro` | `ease-standard` | Color shift to lighter surface tier |
| Button active (press) | `transform: scale(0.98)` | `duration-micro` | `ease-standard` | Subtle compression — tactile feedback |
| Tertiary button underline | `transform: scaleX(0→1)` | `duration-standard` | `ease-standard` | Underline extends from center outward per `DESIGN_SYSTEM.md` Section 5 |
| Input focus underline | `border-color`, `box-shadow` | `duration-standard` | `ease-standard` | Underline transitions to `secondary` (Gold) with outer glow |
| Card hover | `transform: translateY(-0.125rem)` | `duration-standard` | `ease-standard` | Subtle lift — focal plane shift |
| Dropdown open | `opacity`, `transform: translateY(-0.5rem→0)` | `duration-standard` | `ease-enter` | Fade in + slide down from above |
| Dropdown close | `opacity`, `transform: translateY(0→-0.5rem)` | `duration-standard` | `ease-exit` | Fade out + slide up |
| Modal entrance | `opacity`, `transform: scale(0.95→1)` | `duration-emphasis` | `ease-enter` | Scale up from slightly smaller — resolving out of darkness |
| Modal exit | `opacity`, `transform: scale(1→0.95)` | `duration-emphasis` | `ease-exit` | Scale down and fade — receding into the void |
| Modal backdrop | `opacity` (0→1), `backdrop-filter` | `duration-emphasis` | `ease-enter` | Glassmorphism blur fades in |
| Toast enter | `transform: translateY(100%→0)`, `opacity` | `duration-standard` | `ease-enter` | Slide up from bottom |
| Toast exit | `transform: translateY(0→100%)`, `opacity` | `duration-standard` | `ease-exit` | Slide down and fade |
| Page transition | `opacity` | `duration-cinematic` | `ease-standard` | Crossfade between pages — the dissolve |
| Hero reveal | `opacity`, `transform: translateY(1rem→0)` | `duration-cinematic` | `ease-enter` | Fade in + subtle rise on initial load |
| Vignette bloom | `opacity` | `duration-cinematic` | `ease-enter` | Radial gradient fades in on section enter |
| Neural Ticker scroll | `transform: translateX` | continuous | `ease-linear` | Constant velocity horizontal scroll. Speed: 2.5rem/second (configurable via `--ticker-speed`) |
| Seat selection | `background-color`, `transform: scale(1→1.05→1)` | `duration-standard` | `ease-emphasis` | Color change + brief pulse on selection |
| Accordion expand | `grid-template-rows: 0fr→1fr` | `duration-emphasis` | `ease-standard` | CSS Grid row animation for smooth height |
| Skeleton loading | `background-position` | 1500ms, infinite | `ease-linear` | Shimmer sweep across skeleton placeholder |

### Reduced Motion

All motion respects `prefers-reduced-motion`. No exceptions. No "subtle enough to keep" rationalizations.

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

**Per-component overrides** beyond the global reset:

| Component | Reduced-motion behavior |
|---|---|
| Neural Ticker | Stops scrolling. Displays full text content statically, wrapping if needed. `animation: none`. |
| Hero reveal | No translate or fade. Image and text appear immediately at full opacity. |
| Page transitions | Instant cut. No crossfade. `transition: none`. |
| Skeleton loading | Solid `surface_container_low` fill. No shimmer. |
| Seat selection | Color change applies instantly. No scale pulse. |
| Vignette bloom | Gradient visible immediately at full opacity. No fade-in. |
| Modal entrance/exit | Instant appear/disappear. No scale or opacity transition. |

---

## 7. Accessibility: Universal Admission

This theater does not have a separate entrance. Every seat — front row, balcony, accessible — is reached through the same doors. WCAG 2.1 AA is the minimum standard. Where AAA is achievable without compromising the design language, it is the target.

### Contrast Audit

Every foreground token on every background token, computed to two decimal places. The design system's darkness works in our favor — high-luminance text on near-black surfaces produces strong ratios. The failures are specific and contained.

#### All Foreground Tokens on Dark Surface Tiers

| Foreground | Background | Ratio | Result |
|---|---|---|---|
| `primary` (#FFB4A8) | `surface_container_lowest` (#0e0e0e) | 11.35:1 | AAA |
| `primary` (#FFB4A8) | `surface` (#131313) | 10.92:1 | AAA |
| `primary` (#FFB4A8) | `surface_container_low` (#1c1b1b) | 10.10:1 | AAA |
| `primary` (#FFB4A8) | `surface_container` (#201f1f) | 9.67:1 | AAA |
| `primary` (#FFB4A8) | `surface_container_high` (#2a2a2a) | 8.44:1 | AAA |
| `secondary` (#DAC769) | `surface_container_lowest` (#0e0e0e) | 11.36:1 | AAA |
| `secondary` (#DAC769) | `surface` (#131313) | 10.93:1 | AAA |
| `secondary` (#DAC769) | `surface_container_low` (#1c1b1b) | 10.11:1 | AAA |
| `secondary` (#DAC769) | `surface_container` (#201f1f) | 9.67:1 | AAA |
| `secondary` (#DAC769) | `surface_container_high` (#2a2a2a) | 8.44:1 | AAA |
| `tertiary` (#CCC6B6) | `surface_container_lowest` (#0e0e0e) | 11.33:1 | AAA |
| `tertiary` (#CCC6B6) | `surface` (#131313) | 10.90:1 | AAA |
| `tertiary` (#CCC6B6) | `surface_container_low` (#1c1b1b) | 10.08:1 | AAA |
| `tertiary` (#CCC6B6) | `surface_container` (#201f1f) | 9.65:1 | AAA |
| `tertiary` (#CCC6B6) | `surface_container_high` (#2a2a2a) | 8.42:1 | AAA |
| `on_surface` (#E5E2E1) | `surface_container_lowest` (#0e0e0e) | 14.98:1 | AAA |
| `on_surface` (#E5E2E1) | `surface` (#131313) | 14.42:1 | AAA |
| `on_surface` (#E5E2E1) | `surface_container_low` (#1c1b1b) | 13.34:1 | AAA |
| `on_surface` (#E5E2E1) | `surface_container` (#201f1f) | 12.76:1 | AAA |
| `on_surface` (#E5E2E1) | `surface_container_high` (#2a2a2a) | 11.14:1 | AAA |
| `outline` (#A58B86) | `surface_container_lowest` (#0e0e0e) | 6.10:1 | AA |
| `outline` (#A58B86) | `surface` (#131313) | 5.87:1 | AA |
| `outline` (#A58B86) | `surface_container_low` (#1c1b1b) | 5.43:1 | AA |
| `outline` (#A58B86) | `surface_container` (#201f1f) | 5.20:1 | AA |
| `outline` (#A58B86) | `surface_container_high` (#2a2a2a) | 4.54:1 | AA |

**All dark surface pairings pass AA or better.** The existing dark palette is accessibility-safe for all standard text usage.

#### Foreground Tokens on Accent Container Backgrounds

| Foreground | Background | Ratio | Result | Action |
|---|---|---|---|---|
| `primary` (#FFB4A8) | `primary_container` (#550000) | 8.91:1 | AAA | Safe |
| `secondary` (#DAC769) | `primary_container` (#550000) | 8.91:1 | AAA | Safe |
| `tertiary` (#CCC6B6) | `primary_container` (#550000) | 8.89:1 | AAA | Safe |
| `on_surface` (#E5E2E1) | `primary_container` (#550000) | 11.76:1 | AAA | Safe |
| `outline` (#A58B86) | `primary_container` (#550000) | 4.79:1 | AA | Safe |
| `primary` (#FFB4A8) | `secondary_container` (#675900) | 4.11:1 | AA-large only | **Use `on_surface` instead for normal text** |
| `secondary` (#DAC769) | `secondary_container` (#675900) | 4.11:1 | AA-large only | **Use `on_surface` instead for normal text** |
| `tertiary` (#CCC6B6) | `secondary_container` (#675900) | 4.10:1 | AA-large only | **Use `on_surface` instead for normal text** |
| `on_surface` (#E5E2E1) | `secondary_container` (#675900) | 5.42:1 | AA | Safe for all text sizes |
| `outline` (#A58B86) | `secondary_container` (#675900) | 2.21:1 | **FAIL** | **Never use `outline` on `secondary_container`** |
| `primary` (#FFB4A8) | `tertiary_container` (#29261b) | 8.90:1 | AAA | Safe |
| `secondary` (#DAC769) | `tertiary_container` (#29261b) | 8.91:1 | AAA | Safe |
| `tertiary` (#CCC6B6) | `tertiary_container` (#29261b) | 8.88:1 | AAA | Safe |
| `on_surface` (#E5E2E1) | `tertiary_container` (#29261b) | 11.75:1 | AAA | Safe |
| `outline` (#A58B86) | `tertiary_container` (#29261b) | 4.78:1 | AA | Safe |

#### `outline_variant` at 15% Opacity

| Background | Effective color | Ratio | Result |
|---|---|---|---|
| `surface_container_lowest` (#0e0e0e) | #181515 | 1.06:1 | **FAIL** |
| `surface` (#131313) | #1d1a19 | 1.07:1 | **FAIL** |
| `surface_container_low` (#1c1b1b) | #242020 | 1.07:1 | **FAIL** |
| `surface_container` (#201f1f) | #282423 | 1.07:1 | **FAIL** |
| `surface_container_high` (#2a2a2a) | #302d2c | 1.05:1 | **FAIL** |

**`outline_variant` at 15% opacity is not accessible.** It produces effectively invisible contrast (~1.06:1) on all surfaces. Per `DESIGN_SYSTEM.md` Section 4, this is intentional for the "Edge Catch" decorative effect — "a faint luminous edge, light grazing a polished surface." This is acceptable **only when the edge catch is purely decorative** and the element it borders is already distinguishable via surface tier shift. It must never be the sole boundary indicator for interactive or content-bearing elements. Where accessibility requires a visible boundary (e.g., form field containers for users who need visible borders), use `outline` (#A58B86) at full opacity instead — it achieves 4.54:1+ on all surface tiers.

#### Remediation Summary

| Issue | Remediation |
|---|---|
| Text on `secondary_container` (#675900) | Use `on_surface` (#E5E2E1) for normal-size text. `primary`, `secondary`, `tertiary` are safe for `title-lg` and above (large text AA threshold: 3:1). |
| `outline` on `secondary_container` | Do not use. If a border is needed on `secondary_container`, use `on_surface` (#E5E2E1) at 30% opacity (effective ratio: ~2.8:1 for decorative, or full opacity for functional). |
| `outline_variant` at 15% opacity anywhere | Decorative use only. Never rely on it for functional boundaries. Use `outline` at full opacity for accessible borders. |
| Neural Ticker text (`on_tertiary_fixed_variant`) | `DESIGN_SYSTEM.md` Section 5 references this token but provides no hex value. It cannot be audited until defined. Before implementation, resolve its hex value and verify contrast against `surface` (#131313) and `surface_container` (#201f1f) at minimum 4.5:1. |

### Focus Indicators

Focus indicators must be visible on all surface tiers without violating the "No-Line" rule from `DESIGN_SYSTEM.md` Section 2. The solution: a **double-ring glow** using `secondary` (Gold, #DAC769).

```css
:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),       /* Inner ring: matches background, creates gap */
    0 0 0 0.25rem var(--secondary);     /* Outer ring: gold, high contrast */
}
```

**Contrast verification:**
- `secondary` (#DAC769) against `surface` (#131313): 10.93:1 — exceeds AAA
- `secondary` (#DAC769) against `surface_container_high` (#2a2a2a): 8.44:1 — exceeds AAA
- The inner ring uses the local surface color, creating visual separation between the element edge and the gold ring.

**Per-component focus adjustments:**

| Component | Focus style |
|---|---|
| Buttons | Double-ring glow (default) |
| Input fields | Underline transitions to `secondary` (Gold) + subtle gold outer glow (per `DESIGN_SYSTEM.md` Section 5). The glow serves as the focus indicator — no additional ring needed. |
| Cards (interactive) | Double-ring glow on the entire card |
| Seat grid cells | **Focus (`:focus-visible`):** 0.125rem `secondary` (#DAC769) outline (inset) around the focused cell, regardless of selection state — this is the keyboard cursor. **Selected (`aria-selected="true"`):** `primary_container` fill with `primary` check icon (per seat states above). **Focus + Selected:** Both the gold outline and the maroon fill render simultaneously. Focus and selection are independent visual states. |
| Nav links | Gold underline (already defined as hover/active state). Focus adds the double-ring glow in addition. |
| Modal close button | Double-ring glow (default) |

### Skip Navigation

A hidden link at the top of every page, visible only on keyboard focus. Jumps the user past the navigation to the main content region.

```html
<a href="#main-content" class="skip-nav">Skip to main content</a>

<!-- ...navigation... -->

<main id="main-content" tabindex="-1">
```

```css
.skip-nav {
  position: absolute;
  top: var(--space-sm);
  left: var(--space-sm);
  z-index: var(--z-skip-nav); /* 900 */
  padding: var(--space-sm) var(--space-md);
  background: var(--primary-container);
  color: var(--secondary);
  font-family: var(--font-body); /* Newsreader */
  font-size: var(--type-body-md);
  border-radius: 0.125rem;
  transform: translateY(-100%);
  transition: transform var(--duration-standard) var(--ease-standard);
}

.skip-nav:focus-visible {
  transform: translateY(0);
  box-shadow: none; /* Override default focus ring — the element itself is the indicator */
}
```

### ARIA Landmark Structure

Every page follows this landmark structure:

```html
<body>
  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <header role="banner">
    <nav role="navigation" aria-label="Primary">
      <!-- Main site navigation -->
    </nav>
  </header>

  <!-- Neural Ticker, if present -->
  <aside role="complementary" aria-label="Now showing updates" aria-live="off">
    <!-- Neural Ticker content -->
  </aside>

  <main id="main-content" role="main" tabindex="-1">
    <!-- Page content -->
  </main>

  <!-- Sidebar, if present (account pages) -->
  <aside role="complementary" aria-label="Account navigation">
    <nav role="navigation" aria-label="Account">
      <!-- Sidebar nav items -->
    </nav>
  </aside>

  <footer role="contentinfo">
    <!-- Footer content -->
  </footer>
</body>
```

### Keyboard Navigation Patterns

#### Seat Selection Grid (Auditorium)

The seat grid implements a **roving tabindex** pattern within a grid role.

```html
<div role="grid" aria-label="Theater seating chart, [Screen Name]">
  <div role="row" aria-label="Row A">
    <div role="gridcell" tabindex="0" aria-label="Seat A1, available" aria-selected="false">A1</div>
    <div role="gridcell" tabindex="-1" aria-label="Seat A2, taken" aria-disabled="true">A2</div>
    <!-- ... -->
  </div>
</div>
```

| Key | Action |
|---|---|
| `Arrow Right` | Move focus to next seat in row |
| `Arrow Left` | Move focus to previous seat in row |
| `Arrow Down` | Move focus to same position in next row |
| `Arrow Up` | Move focus to same position in previous row |
| `Home` | Move focus to first available seat in current row |
| `End` | Move focus to last available seat in current row |
| `Enter` / `Space` | Toggle seat selection (select/deselect) |
| `Escape` | Deselect all seats in current selection and return focus to the grid container |
| `Tab` | Exit the grid; move focus to next interactive element (e.g., "Confirm Selection" button) |

**Screen reader announcements:**
- On seat focus: "[Seat ID], [status]. Row [letter], seat [number]. [Section name] section. [Price tier]."
- On selection: "Seat [ID] selected. [Total] seats selected, [price] total."
- On deselection: "Seat [ID] deselected."
- Taken seats are `aria-disabled="true"` and announced as "unavailable."

#### Neural Ticker

The Neural Ticker is **decorative ambient content** — it is not a navigation element or critical information carrier. It is excluded from the tab order by default.

```html
<aside aria-label="Now showing updates" aria-live="off">
  <div class="neural-ticker" aria-roledescription="scrolling information ticker">
    <div class="neural-ticker__content" aria-hidden="true">
      <!-- Scrolling visual content -->
    </div>
    <div class="sr-only">
      <!-- Static, complete text content for screen readers -->
      Now showing: [Film 1] at [Time]. [Film 2] at [Time]. ...
    </div>
    <button class="neural-ticker__control" aria-label="Pause ticker" aria-pressed="false">
      <!-- Pause/Play icon -->
    </button>
  </div>
</aside>
```

| Key | Action |
|---|---|
| `Tab` | Reaches the pause/play control button (only interactive element) |
| `Enter` / `Space` | Toggle pause/resume. Updates `aria-label` to "Play ticker" / "Pause ticker" and `aria-pressed` state. |

The scrolling visual content is `aria-hidden="true"`. Screen readers receive the static text alternative instead.

#### Modal Dialogs

Modals implement a **focus trap** per WAI-ARIA dialog pattern.

```html
<div role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <h2 id="modal-title">Confirm Booking</h2>
  <!-- Modal content -->
  <button>Confirm</button>
  <button>Cancel</button>
</div>
```

| Key | Action |
|---|---|
| `Tab` | Cycle through focusable elements within the modal. Focus wraps from last to first. |
| `Shift+Tab` | Reverse cycle. Focus wraps from first to last. |
| `Escape` | Close modal. Return focus to the element that triggered the modal. |

On modal open: focus moves to the first focusable element within the modal (or the modal itself if no focusable children). Background content receives `aria-hidden="true"` and `inert` attribute.

#### "What's On" Calendar

The calendar implements a **grid navigation** pattern.

| Key | Action |
|---|---|
| `Arrow Right` | Next day |
| `Arrow Left` | Previous day |
| `Arrow Down` | Same day, next week |
| `Arrow Up` | Same day, previous week |
| `Page Down` | Next month |
| `Page Up` | Previous month |
| `Home` | First day of current month |
| `End` | Last day of current month |
| `Enter` / `Space` | Select date, reveal events for that day |
| `Tab` | Exit calendar grid; move to next UI element |

```html
<div role="grid" aria-labelledby="calendar-heading">
  <div role="row">
    <div role="columnheader">Mon</div>
    <!-- ... -->
  </div>
  <div role="row">
    <div role="gridcell" tabindex="0" aria-selected="true" aria-label="Friday, April 3, 2026. 3 events.">3</div>
    <div role="gridcell" tabindex="-1" aria-label="Saturday, April 4, 2026. No events.">4</div>
    <!-- ... -->
  </div>
</div>
```

### Screen Reader Conventions

**Decorative images:** `aria-hidden="true"` and empty `alt=""`. This includes: vignette bloom backgrounds, section texture overlays, purely atmospheric hero images where the content is conveyed by adjacent text.

**Meaningful images:** Descriptive `alt` text. Movie posters: `alt="[Movie Title] poster"`. Event photos: `alt="[Event description]"`. Hero images with unique content: `alt="[Scene description]"`.

**Icon buttons:** Every button that uses an icon without visible text must have `aria-label` describing the action: `aria-label="Close"`, `aria-label="Add to favorites"`, `aria-label="Open menu"`.

**Icon + text buttons:** The icon is decorative. Use `aria-hidden="true"` on the icon element. The visible text serves as the accessible name.

**Loading states:** Skeleton placeholders use `aria-busy="true"` on the container. Screen readers announce "Loading" when the region is busy and the content when it resolves.

**Price and time formatting:** Use `<time datetime="...">` for showtimes. Use machine-readable values in `aria-label` when visual formatting is ambiguous (e.g., `aria-label="$12.50"` on a price displayed as "$12.50" — ensures currency is announced).

---

## 8. Print & Export: The Lobby Card

Print is not a primary concern for a theater booking site, but two scenarios justify a stylesheet: a user printing their ticket confirmation, and a user printing event or showtime information for someone who doesn't have a phone.

### Strategy: Suppress and Simplify

The print stylesheet suppresses the interface and preserves the content.

```css
@media print {
  /* === Global reset === */
  *,
  *::before,
  *::after {
    background: #fff !important;
    color: #000 !important;
    box-shadow: none !important;
    text-shadow: none !important;
  }

  body {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 12pt;
    line-height: 1.5;
  }

  /* === Hide interface chrome === */
  header[role="banner"],
  nav,
  .neural-ticker,
  .skip-nav,
  footer[role="contentinfo"],
  button:not(.print-visible),
  .sidebar-nav,
  .toast,
  .modal-backdrop {
    display: none !important;
  }

  /* === Images === */
  img {
    filter: grayscale(100%);
    max-width: 100% !important;
  }

  /* === Links === */
  a[href]::after {
    content: " (" attr(href) ")";
    font-size: 0.8em;
    color: #555 !important;
  }
  a[href^="#"]::after,
  a[href^="javascript"]::after {
    content: none;
  }

  /* === Page breaks === */
  h2, h3 {
    page-break-after: avoid;
  }
  .card, .ticket-confirmation {
    page-break-inside: avoid;
  }
}
```

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
