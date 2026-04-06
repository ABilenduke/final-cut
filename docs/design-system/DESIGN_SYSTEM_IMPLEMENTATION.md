# Design System Implementation Guide

Bridges `DESIGN_SYSTEM.md` (the creative vision) and `DESIGN_SYSTEM_STRUCTURE.md` (the engineering blueprint) to actual CSS and Vue implementation. This document tells you how to translate the design system documents into working code.

---

## 1. CSS File Structure

All global styles live in `app/assets/css/`. A single entry file aggregates everything.

```
app/assets/css/
├── main.css           # Import aggregator — this is what nuxt.config.ts references
├── tokens.css         # CSS custom properties (colors, spacing, z-index, easing, duration, icons)
├── reset.css          # Minimal reset + scrollbar-gutter + reduced-motion
├── typography.css     # Type scale, font stacks, fluid sizing
├── layouts.css        # Six named compositions + sidebar + container
├── utilities.css      # Aspect ratios, touch targets, glassmorphism, skeleton shimmer
└── print.css          # Print stylesheet
```

**`main.css` contents:**
```css
@import './reset.css';
@import './tokens.css';
@import './typography.css';
@import './layouts.css';
@import './utilities.css';
@import './print.css';
```

**`nuxt.config.ts` registration:**
```typescript
export default defineNuxtConfig({
  css: ['~/assets/css/main.css'],
  // ...
})
```

---

## 2. Token Translation: Design System → CSS Custom Properties

### Naming Convention

Design system token names use underscores (e.g., `primary_container`, `surface_container_high`). CSS custom properties use hyphens:

`primary_container` → `--primary-container`
`surface_container_high` → `--surface-container-high`
`space-2xl` → `--space-2xl` (already hyphenated)

### Color Tokens (`tokens.css`)

```css
:root {
  /* Fills / Backgrounds / Surfaces */
  --primary-container: #550000;       /* Primary buttons, active nav indicators, hero accents */
  --secondary-container: #675900;     /* Secondary accent fills (rare) */
  --tertiary-container: #29261b;      /* Subtle warm highlights */
  --surface: #131313;                 /* Page background */
  --surface-variant: #3B3636;         /* Translucent glass substrate for floating surfaces */
  --surface-container-lowest: #0e0e0e; /* Recessed/void areas */
  --surface-container-low: #1c1b1b;   /* Card backgrounds on surface */
  --surface-container: #201f1f;       /* Content sections */
  --surface-container-high: #2a2a2a;  /* Elevated cards, modals, active elements */

  /* Text / Icons / Foreground */
  --primary: #FFB4A8;                 /* Text ON primary_container fills ONLY — never as fill */
  --secondary: #DAC769;              /* Gold text for CTAs, tertiary buttons, hover underlines — never as fill */
  --tertiary: #CCC6B6;              /* Ivory body text, subdued labels */
  --on-surface: #E5E2E1;            /* Default body text on dark backgrounds */
  --on-tertiary-fixed-variant: #A89F91; /* Low-emphasis telemetry text (Neural Ticker) */

  /* Borders / Edges */
  --outline-variant: #57423E;        /* Ghost borders / decorative edge catches only */
  --outline: #A58B86;                /* Input underlines (unfocused), functional interactive boundaries */
}
```

### Spacing Tokens (`tokens.css`)

Defined exactly as specified in `DESIGN_SYSTEM_STRUCTURE.md` Section 1:

```css
:root {
  --space-2xs: 0.125rem;   /* 2px  — optical adjustment only */
  --space-xs:  0.25rem;    /* 4px  — optical adjustment only */
  --space-sm:  0.5rem;     /* 8px  */
  --space-md:  1rem;       /* 16px */
  --space-lg:  1.5rem;     /* 24px */
  --space-xl:  2rem;       /* 32px */
  --space-2xl: 3rem;       /* 48px */
  --space-3xl: 4rem;       /* 64px */
  --space-4xl: 6rem;       /* 96px */
  --space-5xl: 8rem;       /* 128px */
}
```

### Z-Index Scale (`tokens.css`)

```css
:root {
  --z-recessed:        -1;
  --z-base:             0;
  --z-card:           100;
  --z-sticky:         200;
  --z-ticker:         201;
  --z-dropdown:       300;
  --z-modal-backdrop: 400;
  --z-modal:          500;
  --z-toast:          600;
  --z-tooltip:        700;
  --z-skip-nav:       900;
}
```

### Easing Curves (`tokens.css`)

```css
:root {
  --ease-standard: cubic-bezier(0.2, 0.0, 0.0, 1.0);
  --ease-enter:    cubic-bezier(0.0, 0.0, 0.0, 1.0);
  --ease-exit:     cubic-bezier(0.2, 0.0, 1.0, 1.0);
  --ease-emphasis: cubic-bezier(0.05, 0.7, 0.1, 1.0);
  --ease-linear:   linear;
}
```

### Duration Tokens (`tokens.css`)

```css
:root {
  --duration-micro:     100ms;
  --duration-standard:  250ms;
  --duration-emphasis:  400ms;
  --duration-cinematic: 700ms;
}
```

### Breakpoint Tokens (`tokens.css`)

```css
:root {
  --screen-sm: 40rem;    /* 640px  */
  --screen-md: 60rem;    /* 960px  */
  --screen-lg: 80rem;    /* 1280px */
  --screen-xl: 100rem;   /* 1600px */
}
```

Note: CSS custom properties cannot be used in `@media` queries. Use the raw `rem` values in media queries directly. The variables exist for reference and for use in JavaScript if needed.

### Icon Sizes (`tokens.css`)

```css
:root {
  --icon-sm: 1rem;      /* 16px — inline with text */
  --icon-md: 1.5rem;    /* 24px — UI controls */
  --icon-lg: 3rem;      /* 48px — feature callouts */
  --icon-xl: 4rem;      /* 64px — hero decorative */
}
```

---

## 3. Typography Implementation (`typography.css`)

### Font Loading

Use the `@nuxt/fonts` module, which automatically downloads and self-hosts Google Fonts at build time. This provides production-grade font delivery with no external runtime dependency.

In `nuxt.config.ts`:
```typescript
modules: ['@nuxt/fonts'],
fonts: {
  families: [
    { name: 'Noto Serif', weights: [400, 700] },
    { name: 'Newsreader', weights: [400, 700], styles: ['normal', 'italic'] },
  ],
},
```

**Behavior:**
- `font-display: swap` applied by default — prevents FOIT (flash of invisible text)
- FOUT (flash of unstyled text) is acceptable — the serif fallback stack (Georgia, Times New Roman) preserves the system's tone during loading
- Fonts are preloaded automatically
- If custom fonts fail entirely, the fallback stack must still feel intentional, not broken

### Font Stacks

```css
:root {
  --font-display: 'Noto Serif', Georgia, 'Times New Roman', serif;
  --font-body: 'Newsreader', Georgia, 'Times New Roman', serif;
}
```

### Type Scale

Fluid tokens for display/headline (scale between `screen-sm` and `screen-lg`). Fixed tokens for body/label.

```css
:root {
  /* Display — Noto Serif, letter-spacing: -0.02em */
  --type-display-lg:  clamp(2.25rem, 1.25rem + 2.5vw, 3.5rem);
  --type-display-md:  clamp(1.875rem, 1.125rem + 1.875vw, 2.8125rem);
  --type-display-sm:  clamp(1.5rem, 0.9rem + 1.5vw, 2.25rem);

  /* Headline — Noto Serif, letter-spacing: -0.02em */
  --type-headline-lg: clamp(1.375rem, 0.875rem + 1.25vw, 2rem);
  --type-headline-md: clamp(1.25rem, 0.85rem + 1vw, 1.75rem);
  --type-headline-sm: clamp(1.125rem, 0.825rem + 0.75vw, 1.5rem);

  /* Title — Noto Serif */
  --type-title-lg:    clamp(1rem, 0.7rem + 0.75vw, 1.375rem);
  --type-title-md:    1rem;

  /* Body — Newsreader */
  --type-body-lg:     1.125rem;
  --type-body-md:     1rem;
  --type-body-sm:     0.875rem;

  /* Label — Newsreader */
  --type-label-lg:    0.875rem;
  --type-label-md:    0.75rem;
  --type-label-sm:    0.6875rem;
}
```

### Usage Classes

```css
.display-lg {
  font-family: var(--font-display);
  font-size: var(--type-display-lg);
  letter-spacing: -0.02em;
  line-height: 1.1;
}

/* ... same pattern for all tokens */

.body-md {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  line-height: 1.6;
}
```

---

## 4. Layout Composition Implementation (`layouts.css`)

All six compositions from `DESIGN_SYSTEM_STRUCTURE.md` Section 2, plus the global container and sidebar.

### Global Container

```css
.container {
  width: 100%;
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-2xl);
}

@media (max-width: 59.999rem) {
  .container { padding-inline: var(--space-xl); }
}

@media (max-width: 39.999rem) {
  .container { padding-inline: var(--space-md); }
}
```

### Named Compositions

Implemented exactly as specified in `DESIGN_SYSTEM_STRUCTURE.md` Section 2. The CSS is already provided there — use it directly. Key points:

- **Establishing Shot:** `grid-template-columns: 65fr 35fr`, collapses at `59.999rem`
- **Rack Focus:** `grid-template-columns: 35fr 65fr`, collapses with `order: -1` on primary content
- **Wide Frame:** `width: 100vw; margin-inline: calc(-50vw + 50%)` — requires `scrollbar-gutter: stable` on `<html>` (set in `reset.css`) to prevent horizontal overflow on platforms with visible scrollbars
- **Close-Up:** `max-width: 40rem; margin-inline: auto`
- **Ensemble:** `grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr))`
- **Auditorium:** Two-column wrapper with pinned labels and scrollable grid

Refer to `DESIGN_SYSTEM_STRUCTURE.md` for the complete CSS for each composition.

---

## 5. Responsive Breakpoint Patterns

### Media Query Convention

Use `max-width` for mobile-down and `min-width` for desktop-up. The primary pivot is `screen-md` (60rem).

```css
/* Mobile-first default, then override upward */
.component { /* mobile styles */ }

@media (min-width: 60rem) {
  .component { /* desktop styles */ }
}

/* Or target below a breakpoint */
@media (max-width: 59.999rem) {
  .component { /* mobile-only styles */ }
}
```

### Container Queries

For components that appear in variable-width contexts (cards in sidebars, Ensemble grids within panels):

```css
.card-container {
  container-type: inline-size;
  container-name: card;
}

@container card (max-width: 20rem) {
  .card { flex-direction: row; }
  .card__thumbnail { width: 5rem; aspect-ratio: 1; }
}
```

---

## 6. `prefers-reduced-motion` Implementation

### Global Reset

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

### Per-Component Overrides

In addition to the global reset, specific components need explicit reduced-motion behavior as specified in `DESIGN_SYSTEM_STRUCTURE.md` Section 6:

| Component | Reduced-Motion Behavior |
| --------- | ----------------------- |
| Neural Ticker | Stops scrolling. Displays full text statically, wrapping as needed. `animation: none` |
| Hero reveal | No translate or fade. Content visible immediately at full opacity |
| Page transitions | Instant cut. No crossfade. `transition: none` |
| Skeleton loader | Solid `surface-container-low` fill. No shimmer gradient |
| Seat selection | Color change applies instantly. No scale pulse |
| Vignette bloom | Gradient visible immediately at full opacity |
| Modal enter/exit | Instant appear/disappear. No scale or opacity animation |

These are implemented within each component's `<style scoped>` block:

```css
@media (prefers-reduced-motion: reduce) {
  .neural-ticker__content {
    animation: none;
    white-space: normal; /* Allow wrapping */
  }
}
```

---

## 7. Component Styling Conventions

### Scoped Styles

Every component uses `<style scoped>`. Global utilities (layout classes, type classes) come from the global CSS files. Component-specific styling is scoped.

```vue
<style scoped>
.cv-button {
  height: 3rem;
  padding-inline: var(--space-md);
  background: var(--primary-container);
  color: var(--secondary);
  border: none;
  border-radius: 0.125rem;
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  cursor: pointer;
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.cv-button:hover {
  background: var(--surface-container-high);
}
</style>
```

### BEM Naming

Components use BEM naming within their scoped styles:

- Block: `.cv-button`, `.movie-card`, `.auditorium-grid`
- Element: `.cv-button__icon`, `.movie-card__poster`, `.auditorium-grid__row`
- Modifier: `.cv-button--primary`, `.movie-card--compact`, `.auditorium-grid__seat--selected`

The `Cv` prefix is used for global primitives. Domain components use their full descriptive name.

### No Tailwind

The design system is too specific and opinionated for utility classes. All styling uses CSS custom properties from the token system. If you find yourself wanting a utility class, check if a design token already covers the value.

---

## 8. Focus Indicator Pattern

The standard focus indicator across the site is a double-ring gold glow:

```css
:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
```

The inner ring uses the local surface color to create a gap. The outer ring is gold (#DAC769).

**Exceptions documented in `DESIGN_SYSTEM_STRUCTURE.md` Section 7:**
- Input fields: gold underline + glow IS the focus indicator (no ring)
- Seat grid cells: inset gold outline (0.125rem)
- Skip nav: the element itself is the indicator (no ring when visible)

**Clipping rule:** Components inside any clipping or masked context — `overflow: auto`, `overflow: scroll`, `overflow: hidden`, `clip-path`, or size containment — must use `outline` for focus indication instead of `box-shadow`, because `box-shadow` is clipped by overflow boundaries.

Pattern for focus in clipped containers:
```css
:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}
```

This applies to: seat grid cells (already handled), horizontally scrolling tab bars, cast list items, and any future scrollable or clipped container with interactive children.

---

## 9. Design System Guardrails

These are the rules most likely to be violated during implementation. Flag violations in code review.

### 1. Primary (#FFB4A8) is NEVER a Background

`#FFB4A8` is text/icon color only. If you're writing `background-color: var(--primary)` or `background: #FFB4A8` — stop. You want `var(--primary-container)` (#550000).

**Correct:** `background: var(--primary-container); color: var(--secondary);` (maroon fill, gold text)
**Wrong:** `background: var(--primary);` (salmon pink fill)

### 2. No Borders for Layout Sectioning; Functional Borders for Interactive Identification

**Layout boundaries** are defined by surface tier shifts, not strokes. A card on `surface` (#131313) uses `surface-container-low` (#1c1b1b) background — the color difference IS the border.

**Interactive component boundaries** may use `outline` (#A58B86) at full opacity when a component's interactive nature cannot be identified by surface shift alone (e.g., inputs, toggles, segmented controls). See `DESIGN_SYSTEM.md` § No-Line Rule.

**Correct (layout):** Adjacent elements at different surface tiers
**Wrong (layout):** `border: 1px solid var(--outline);` on a content section or card-as-container
**Correct (interactive):** `border-bottom: 1px solid var(--outline);` on an input field
**Wrong (interactive):** `border-bottom: 1px solid var(--outline-variant);` as the sole boundary (decorative only, ~1.06:1 contrast)

### 3. Border Radius: 0.125rem or 0

Only two values exist. No `border-radius: 0.5rem`, no `rounded-lg`, no `rounded-full`.

**Exception:** Avatars use `border-radius: 50%` — documented exception for photographic content.

### 4. No #FFFFFF

Maximum white is `var(--on-surface)` (#E5E2E1). Search the codebase for `#fff`, `#FFF`, `#ffffff`, `#FFFFFF`, `white`, `rgb(255` — none should appear outside `print.css`. The print stylesheet is the **sole exception**: `body { background: white; color: black; }` is correct for print readability. This exception is confined to `print.css` and must not leak into screen styles.

### 5. Shadows Only on Floating Elements

Static cards, sections, and content areas do NOT have box-shadow. Shadows are reserved for:
- Floating modals (`box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6)`)
- Hover-lifted interactive cards (ephemeral, on hover only)
- Toast notifications

Shadow tint must match the background — never gray (`rgba(0,0,0,...)` is correct on dark backgrounds).

### 6. Neural Ticker is aria-hidden

The scrolling visual content is `aria-hidden="true"`. Screen readers get a static text alternative in a `.sr-only` element. The only interactive element is the pause/play button.

### 7. Touch Targets: 3rem Minimum Below screen-md

Every interactive element below 60rem viewport width must be at least 3rem (48px) in both dimensions. Small button variants (2.25rem) are gated behind `@media (pointer: fine)`.

### 8. All Motion Respects prefers-reduced-motion

No exceptions. No "this animation is subtle enough to keep." The global reset catches most cases, but components with unique animations need explicit overrides (see Section 6 above).

### 9. Token Completeness

Every token referenced in the design system must satisfy all four conditions:

1. It exists in the canonical token list (`DESIGN_SYSTEM.md` § Token Mapping)
2. It has an implementation value in `tokens.css` (this document, Section 2)
3. It has a role sentence explaining what it is for
4. If the token name is ambiguous (e.g., `primary` which is NOT a fill color), it has a usage note preventing misapplication

A `var(--` reference to an undeclared custom property is a build failure, not a TODO. Resolve it before shipping.

### 10. Forbidden Substitutions

These are the specific misapplications that the token naming makes possible. Each is a hard rule.

1. **Do not use `primary` (#FFB4A8) as a fill or background.** It is a text/icon color for use on `primary_container` fills. If you are writing `background: var(--primary)` — stop. You want `var(--primary-container)`.
2. **Do not use `secondary` (#DAC769) as a fill or background.** It is a text, icon, and accent-line color. Gold fills do not exist in this system.
3. **Do not use decorative edge catches as functional boundaries.** `outline_variant` at 15% opacity (~1.06:1 contrast) is decorative only. Interactive component boundaries require `outline` (#A58B86) at full opacity (4.54:1+).
4. **Do not use `backdrop-filter` blur as a substitute for surface contrast.** The glass effect is visual enhancement. The underlying surface must provide sufficient contrast for text readability without blur.
5. **Do not rely on surface tier shifts alone for interactive component identification.** Adjacent surface tiers differ by ~1.08:1 contrast. Where a component's interactive boundary must be perceivable (WCAG 1.4.11), use `outline` at full opacity.
