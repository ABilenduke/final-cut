# Final Cut Design System — The Cinematic Void Framework

Final Cut is a premium independent cinema chain (web: ticketing, showtimes, calendar, food & drink, gift cards, private screenings, loyalty). This design system implements its visual language: **classic cinema compositional discipline meets science-fiction quiet**. Deep vacuum black surfaces, a reactor-core maroon accent, hull-ivory text, and a signal-gold CTA — all set in Noto Serif / Newsreader.

> "Every element should feel like a choice made under constraint — the kind of restraint you see in a film where every prop, every light, every silence is load-bearing."

---

## Sources

- **Codebase (source of truth):** `ABilenduke/final-cut` (GitHub). Nuxt 4 + Vue 3 frontend, Laravel backend. Live tokens, components, and DS docs are all in this same repo under `frontend/app/` and `docs/design-system/`.
- **Design-system docs:** `docs/design-system/DESIGN_SYSTEM.md`, `docs/design-system/DESIGN_SYSTEM_STRUCTURE.md`, `docs/design-system/DESIGN_SYSTEM_IMPLEMENTATION.md`.
- **Tokens:** `frontend/app/assets/css/tokens.css`, `frontend/app/assets/css/typography.css`, `frontend/app/assets/css/utilities.css`.
- **Vue components:** `frontend/app/components/ui/*.vue`, `frontend/app/components/layout/*.vue`, `frontend/app/components/movie/*.vue`, `frontend/app/components/home/*.vue`.

---

## Index (this folder)

| File / Folder | Purpose |
|---|---|
| `SKILL.md` | Agent-skill entry point. Read first. |
| `README.md` | This file — context, content & visual fundamentals, iconography. |
| `references/components.md` | Catalog of the live Vue UI primitives, layout components, and feature components with file paths. |
| `colors_and_type.css` | Drop-in CSS foundation for the rare standalone HTML artifact (slide deck, one-off mock outside the Nuxt app). Production work uses `frontend/app/assets/css/` instead. |
| `assets/icons/` | Material-style SVG icon set: `icons.js` (window.FC_ICONS), `sprite.svg`, plus a usage README. Same source as `frontend/app/components/ui/icons.ts`. |

The actual UI lives in the repo at `frontend/app/components/` (Vue primitives + features) and `frontend/app/assets/css/` (tokens, typography, utilities, layouts). This skill points at those — it does not ship a parallel UI kit.

---

## Products

One product surface is represented:

1. **Final Cut Web** — a Nuxt 4 site combining marketing (home, movies, events, food & drink, gift cards, private screenings, careers, blog, FAQ, accessibility) with transactional flows (showtimes → seat picker → food pre-order → checkout → confirmation) and an account area (bookings, orders, loyalty, payment methods, profile).

The backend is a Laravel API (TMDB-enriched movie catalog, Stripe + gift cards, seat-conflict service, loyalty tiers). It is not part of this design system but informs copy — bookings, showtimes, loyalty, calendar events, rentals.

---

## Content Fundamentals

**Tone.** Declarative, spare, present-tense. The voice of a well-lit title card or a bridge-console readout — never hype, never apology. Copy is load-bearing: if a word is not earning its place, it goes.

**Person.** Second person ("you") for instructions and CTAs. First person is avoided entirely — the system speaks _to_ the user, not _as_ the brand.

**Casing.** Title Case for nav, buttons, and page titles ("Get Tickets", "What's On", "Gift Cards"). Sentence case for body copy, helper text, and form labels. All-caps is reserved for the Neural Ticker and small telemetry labels.

**Punctuation.** Em-dashes and middle-dots (` · `) to join short atoms ("Final Cut Theatre · 123 Cinema Boulevard · (555) 123-4567"). Avoid exclamation marks. Avoid trailing periods on isolated UI labels.

**Numbers & times.** 24-hour or 12-hour-with-meridiem based on locale; currency always with symbol. "Today" / "Tomorrow" are permitted in friendly contexts (showtime tabs) but never in error states.

**Vibe.** Cinematic gravitas with bridge-instrument precision. Titles read like a poster for a film that trusts itself. Body copy is editorial — warm, legible, never chatty.

**No emoji.** Ever. Unicode dingbats are avoided outside of the `·` middle-dot separator.

**Example copy lifted from the product:**
- CTA: `Get Tickets` / `View Showtimes` / `Notify Me`
- Nav: `Movies` `What's On` `Food & Drink` `Events` `Gift Cards`
- Footer address: `Final Cut Theatre · 123 Cinema Boulevard · (555) 123-4567`
- Ticker: dense, facty; short status fragments joined by `·`.
- Empty states: `No showtimes available` — never `Oops!` or `We couldn't find…`.

---

## Visual Foundations

**Palette.** A foundation of vacuum-black surfaces (`#131313` `#0e0e0e` `#1c1b1b` `#201f1f` `#2a2a2a`) with one accent system: **reactor-core maroon** (`#550000`) fills, **salmon `#FFB4A8`** text _on_ those fills only, **signal gold `#DAC769`** for interactive highlights, **hull ivory `#CCC6B6`** for body, and **`#E5E2E1`** as the maximum white. **Never `#FFFFFF`.**

**Typography.** `Noto Serif` for display / headline / title (structural authority, tight -0.02em letter-spacing). `Newsreader` for body / label (editorial grace, organic curves). Dramatic jumps allowed — a `display-lg` can sit directly above a `label-md` on a poster-like composition. Fluid clamps on display/headline/title sizes; body/label sizes are fixed.

**Depth & elevation.** Light emanates from within. Depth is communicated by **surface-tier shifts**, never by drop shadows on static elements. A `surface-container-lowest` set inside `surface-container-low` reads as a milled channel, not a cast shadow. Floating elements (modals, toasts, raised CTAs) are the only place shadows appear: `0 20px 40px rgba(0,0,0,0.6)` — a darkened tint of the background, never gray.

**Borders.** Prohibited for layout sectioning (the No-Line Rule). Cards and sections are distinguished by surface tier alone. Borders are permitted only when a component's interactive nature cannot otherwise be identified — input underlines (`--outline` #A58B86 at full opacity), toggles, segmented controls. Decorative "edge catches" use `--outline-variant` (#57423E) at **15% opacity** only.

**Corners.** `--radius-sm` (0.125rem) or `--radius-none` (0) almost everywhere. Cards may use `--radius-card` (0.25rem) — just enough to imply fabrication tolerance. Never `full`, never `xl`. Pills and big pill buttons are forbidden.

**Glass & gradient.** Floating overlays use glassmorphism: `surface-variant` at 60% opacity + `backdrop-filter: blur(20px)`. Fallback: 85% opacity solid. Hero compositions use a **vignette bloom** — a radial gradient from `primary-container` (#550000) to `surface-container-lowest` (#0e0e0e), a reactor glimpsed through a corridor.

**Imagery.** Cinematic stills at `21:9` for heroes, `2:3` portrait posters for movie cards, `4:3` for events. Color vibe of imagery runs warm-desaturated — low-key, film-grain, never oversaturated marketing photography. Backdrops are always overlaid with the vignette bloom before text sits on them.

**Animation.** Cinematic easing — `--ease-enter: cubic-bezier(0, 0, 0, 1)` for reveals, `--ease-exit` for dismissals, `--ease-standard` for state changes. Hero content uses a `700ms` reveal (fade + 1rem upward translate). Micro-interactions (hover, state flip) are `100–250ms`. No bounces. No spring physics. All motion respects `prefers-reduced-motion`.

**Hover / press states.**
- **Hover:** color shift to `--secondary` (gold) for links and nav; subtle `translateY(-0.125rem)` lift on interactive cards; primary buttons hold fill.
- **Focus:** 0.125rem `--secondary` gold outline with 0.125rem offset. Never rely on color alone.
- **Press / active:** `transform: scale(0.98)` on buttons. No color flash.
- **Disabled:** `opacity: 0.5`, cursor `not-allowed`.

**Layout.** Reject rigid 12-col grid feel. Compose the viewport like a shot: intentional asymmetry, off-center images, headlines flush-left with body copy offset into a narrower column. Embrace the void — let large areas remain pure `surface-container-lowest`. The Neural Ticker sticks below the header as persistent bridge-console telemetry.

**Transparency / blur.** Only on floating surfaces (modals, toasts, the glass fallback). Static cards are always solid.

**Cards.** Solid surface tier (typically `--surface-container`), `--radius-card` (0.25rem), no border — except optionally the decorative edge catch at 15% opacity. On hover (if interactive): `translateY(-0.125rem)`. Internal padding: `--space-md` (1rem). No shadows.

**Signature component: Neural Ticker.** A horizontally scrolling `label-sm` feed in `--on-tertiary-fixed-variant` (#A89F91), anchored below the fixed header. It's bridge-console telemetry — ambient, ignorable, informative when you look. Pausable. Respects reduced motion (wraps to static text). The live implementation also includes a pulsing "On Air" reactor-light badge anchored to the leading edge.

---

## Iconography

**System:** Inline SVG, 24×24 viewBox, `fill="currentColor"`. Paths are lifted from **Material Symbols / Material Design Icons** (Apache 2.0). There is no icon font. Source set lives at `frontend/app/components/ui/icons.ts` (~34 glyphs) and is exported here as `assets/icons/icons.js` (path map) and `assets/icons/sprite.svg` (combined sprite).

**Sizing:** `--icon-sm` (16px, inline with text), `--icon-md` (24px, UI controls), `--icon-lg` (48px, feature callouts), `--icon-xl` (64px, hero decorative).

**Color:** Icons inherit from their text color — `--tertiary` in neutral contexts, `--secondary` (gold) for active / interactive states, `--primary` only when sitting on a `--primary-container` fill. No multi-color icons. No filled-duotone styles.

**Coverage:** Navigation (menu, close, chevrons, home, more-horiz), domain (movie, calendar, food-drink, gift-card, location, loyalty, orders, bookings, payment, settings), media (play, pause), status (check, alert, info, spinner, star), accessibility (accessible, wheelchair), and utilities (print, plus, minus, receipt, logout, calendar-add, account).

**No emoji. No unicode-char icons** — the only dingbat permitted is `·` (middle dot) as a copy separator.

---

## Fonts

Both **Noto Serif** and **Newsreader** are loaded from Google Fonts via the `@import` in `colors_and_type.css`. No local `.ttf`/`.woff2` files are required for HTML artifacts. If a fully offline bundle is needed, download the families from [fonts.google.com/noto/specimen/Noto+Serif](https://fonts.google.com/noto/specimen/Noto+Serif) and [fonts.google.com/specimen/Newsreader](https://fonts.google.com/specimen/Newsreader) into `fonts/` and swap the `@import` for `@font-face`.

---

## Using this system

**Production work (default).** Use the existing Vue primitives directly:

```vue
<script setup lang="ts">
const { activeLocation } = useLocations()
</script>

<template>
  <CvCard variant="default" interactive :href="`/movies/${slug}`">
    <h3 class="headline-sm">Dune: Part Three</h3>
    <p class="label-md">Tickets · 8:40 PM · {{ activeLocation?.name }}</p>
    <CvButton variant="primary">Get Tickets</CvButton>
  </CvCard>
</template>
```

`CvCard`, `CvButton`, `CvIcon`, etc. are auto-imported by Nuxt. Tokens come in via `main.css` (which imports `tokens.css`, `typography.css`, `utilities.css`). Apply typography classes (`.headline-sm`, `.body-md`, `.label-md`) directly to elements — there's no styling work needed beyond composition.

See `references/components.md` for the full catalog of available components.

**One-off HTML artifact (rare).** If you need a standalone file outside the Nuxt app — a slide, a throwaway mock for a stakeholder review, an investor deck — link the bundled CSS and use the utility classes it provides (`.btn`, `.card`, `.badge`, `.input`, plus the typography classes). **Never use inline styles.** If a class doesn't exist, add it to `colors_and_type.css` rather than reaching for a `style="…"` attribute.

```html
<link rel="stylesheet" href="colors_and_type.css">
<body>
  <h1>Final Cut</h1>
  <p class="body-md">Tickets · 8:40 PM · Dune: Part Three</p>
  <button class="btn btn--primary">Get Tickets</button>
</body>
```

In that mode, mirror the visual API of the live Vue primitives — read their `<style scoped>` blocks (e.g. `frontend/app/components/ui/CvButton.vue`) and replicate the values into named classes inside the stylesheet.
