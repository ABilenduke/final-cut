---
name: finalcut-admin-design
description: >
  Use this skill for ANY work touching the Filament 5 admin panel in this
  repository. Triggers on: files under app/Filament/ (Resources, Pages,
  Widgets, Actions, Forms, Tables), backend/resources/views/filament/,
  backend/resources/css/filament/, AdminPanelProvider.php, mentions of
  Filament resource / page / widget, admin panel table, bulk action, status
  pill, schedule planner, booking lookup, or configure-seats. Does NOT fire
  on frontend/ work -- use the finalcut-design skill for that instead.
user-invocable: true
---

# Final Cut Admin Design System

The Final Cut admin panel shares the same Cinematic Void Framework palette as
the customer site. It adapts that palette for dense operational work: forms,
tables, status pills, bulk-action ribbons, audit logs.

For frontend/ (Nuxt / Vue) work, use the finalcut-design skill instead.

---

## Quick start

Before writing any admin UI code, check these live files:

| File | Purpose |
|------|---------|
| `backend/app/Providers/Filament/AdminPanelProvider.php` | Panel registration: colors, font, vite theme |
| `backend/resources/css/filament/admin/theme.css` | Custom Tailwind v4 / Filament theme |
| `backend/package.json` | Vite + Tailwind v4 dev toolchain for the admin theme |
| `backend/vite.config.js` | Vite config wiring the admin theme |
| `backend/resources/views/filament/pages/booking-lookup.blade.php` | Example custom page |
| `backend/resources/views/filament/pages/schedule-planner.blade.php` | Example custom page |
| `backend/resources/views/filament/resources/auditorium-resource/pages/configure-seats.blade.php` | Example resource page |
| `backend/resources/views/filament/resources/auditorium-resource/pages/partials/seat-grid.blade.php` | Seat grid partial |

Build the admin theme with:

```bash
make admin-theme-build   # one-shot production build
make admin-theme-watch   # Vite dev server (watch mode)
make admin-filament-assets  # re-publish Filament's own CSS/JS/fonts
```

---

## Token discipline (the one rule that matters)

The salmon token (`#FFB4A8`, CSS `--primary`) is a text-on-dark color.
It lives on top of maroon fills. It is never used as a background, button
fill, badge fill, or border.

```
WRONG: background-color: var(--primary);      /* salmon on a surface */
RIGHT: background-color: var(--primary-container);  /* maroon fill */
RIGHT: color: var(--primary);                  /* salmon text ON maroon */
```

The full token mapping is in `README.md` and in `tokens.css`. The canonical
source of truth is `docs/design-system/DESIGN_SYSTEM.md` (customer-side doc
that admin inherits).

Admin-only semantic accents (not in the customer palette):

| Role | Hex | CSS var |
|------|-----|---------|
| Success / confirmed | `#5b8f6c` | `--success` |
| Destructive / danger | `#b5443d` | `--destructive` |
| Warning / refund-pending | `#c78438` | `--warning` |
| Info / held | `#5a8aa0` | `--info` |

---

## Creative direction

The admin panel is a projection booth, not a lobby. The people using it are
operations staff, managers, and finance leads who have tasks to finish, not
experiences to enjoy. The design earns their trust through clarity, not
through atmosphere.

That said, Final Cut's identity does not disappear at the staff entrance.
The same warm-dark surfaces, Noto Serif display type, Newsreader body copy,
and gold action accent carry through. The difference is restraint: less
vignette bloom, shorter durations, denser information hierarchy, no Neural
Ticker.

Think "projection booth at dusk": purposeful, dim-lit, calm, every control
exactly where it should be.

---

## What to do

**Production code is the default mode.** Write Filament PHP (Resources,
Pages, Widgets, Actions, Forms, Tables) and Blade views. Apply admin design
system tokens via Tailwind utility classes (from the compiled theme) and
inline CSS custom properties where Tailwind does not reach.

**One-off HTML mocks** (wireframes, Blade prototypes not yet wired to
Filament): use `tokens.css` from this skill directory as a local stylesheet.
Include it via `<link>` in a standalone HTML file and reference the CSS
custom properties directly.

---

## Disambiguation

- **This skill (finalcut-admin-design):** anything under `app/Filament/`,
  `backend/resources/views/filament/`, `backend/resources/css/filament/`,
  `AdminPanelProvider.php`, Filament table / form / action / widget work.
- **finalcut-design:** anything under `frontend/`, Nuxt components, Vue
  composables, customer-facing pages, `frontend/app/assets/css/`.
