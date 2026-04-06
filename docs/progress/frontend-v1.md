# Frontend v1 Progress

> **Plans:** [Frontend v1 Index](../plans/frontend/v1/00-index.md)
> **Status:** In Progress

---

## Plan 01: Project Setup & Types
**Status:** ✅ Complete
**Completed:** 2026-04-06

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
- [2026-04-06] Created `utilities.css` — aspect ratios, touch targets, glassmorphism with @supports fallback, skeleton shimmer, sr-only, edge-catch, vignette-bloom, focus indicators (double-ring + clipped container variant), float shadow
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
