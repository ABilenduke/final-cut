---
name: finalcut-admin-design
description: Use when working on Final Cut Filament admin panel design, resource UI, widgets, custom admin pages, badges, notifications, table styling, or admin theme CSS.
---

# Final Cut Admin Design

This skill applies to Filament admin work under `backend/app/Filament/`, `backend/resources/views/filament/`, and `backend/resources/css/filament/`.

## Canonical Sources

- Admin theme CSS: `backend/resources/css/filament/admin/theme.css`.
- Token semantics: `docs/design-system/DESIGN_SYSTEM.md`.
- Admin architecture and deployment notes: `CLAUDE.md`.
- Existing Claude reference skill: `.claude/skills/finalcut-admin-design/`.

## Rules

- Use Filament 5 schema-first APIs (`Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`).
- Filament is a UI layer. Route invariant-bearing state changes through domain services/actions.
- Do not directly persist lifecycle or permission-sensitive fields from resources when a domain action should own the transition.
- Map Filament semantic colors to Final Cut tokens. Do not let stock success/warning/danger/info palettes drift from the brand.
- `primary` / `#FFB4A8` is text-on-dark only; `primary_container` / `#550000` is the fill/accent token.
- Use `rem` units for custom CSS except borders, shadows, and sub-pixel technical cases.
- Keep admin copy plain, operator-focused, sentence case, and free of emoji.
- Use line-style Heroicons where Filament icon choice is required.

## Verification

- Run `make admin-test` for admin behavior.
- Run `make admin-filament-assets` after editing admin theme CSS.
- Inspect admin UI for contrast, focus state, table density, and token-correct status colors.
