---
name: finalcut-design
description: Use when working on Final Cut customer-facing Nuxt/Vue UI, visual polish, component design, accessibility, layout, icons, tokens, or brand expression under frontend/.
---

# Final Cut Design

This skill applies to customer-facing work under `frontend/`.

## Canonical Sources

- Tokens and utilities: `frontend/app/assets/css/tokens.css`, `typography.css`, `utilities.css`, and `layouts.css`.
- UI primitives: `frontend/app/components/ui/*.vue`.
- Layout shell: `frontend/app/components/layout/*.vue`.
- Feature components: `frontend/app/components/{movie,booking,calendar,account,home,content}/*.vue`.
- Icons: `frontend/app/components/ui/icons.ts` with `CvIcon.vue`.
- Design docs: `docs/design-system/DESIGN_SYSTEM.md`, `DESIGN_SYSTEM_STRUCTURE.md`, and `DESIGN_SYSTEM_IMPLEMENTATION.md`.

## Rules

- Compose existing Vue primitives before creating new ones.
- Use project tokens; do not ship parallel CSS systems.
- `primary` / `#FFB4A8` is text-on-dark only.
- `primary_container` / `#550000` is the fill/accent token for buttons, active states, and hero accents.
- Use `rem` units for spacing and sizing except borders, shadows, and sub-pixel technical cases.
- Keep the brand cinematic, dark, sparse, and intentional.
- Avoid generic gradient SaaS styling, nested cards, decorative icon grids, fake metrics, and noisy chrome.
- Customer-facing UI must meet WCAG 2.1 AA.

## Verification

- Run `make test-frontend` for frontend changes.
- Run `make e2e` when changing user-facing flows.
- For visual work, inspect responsive behavior and confirm text does not overlap or overflow.
