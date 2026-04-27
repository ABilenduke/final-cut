# Final Cut Admin — Design System (Filament panel)

The admin panel at `admin.finalcut.test` is the same Final Cut brand as the customer-facing Nuxt frontend, but tuned for **operators**: dense data, calm under pressure, after-hours legibility in low-light booths. This skill captures the rules that turn stock Filament v5 chrome into the Cinematic Void aesthetic without forking the framework.

For everything outside `backend/app/Filament/` — the customer site, marketing pages, the booking funnel — defer to the sibling skill at `.claude/skills/finalcut-design/`. This file is admin-only.

## Voice and tone (admin context)

The customer-facing voice is editorial and atmospheric. The admin voice is the **calm, literate house manager**: a hospitality veteran who respects the craft of running a room. Words are chosen, not stacked. No marketing voice, no startup breeziness, no exclamation marks.

- **You** when addressing staff: *"Ask a supervisor before continuing."*
- **We** sparingly, only when the product itself is acting: *"We've held the booking for 7 minutes."*
- **Past tense for audit events** (matches the activity-log voice already in use): *"Refund processed by A. Bilenduke · 19:42."*

Full content rules — casing, numbers, examples drawn from real Resources — live in `references/content-fundamentals.md`.

## Visual foundations (admin context)

### Color

- Same warm-near-black palette as the customer site. **Surfaces** climb: `surface_container_lowest` (`#0e0e0e`) → `surface_container_low` (`#1c1b1b`) → `surface_container` (`#201f1f`) → `surface_container_high` (`#2a2a2a`).
- **One accent, one job: signal gold (`#DAC769`).** Used for primary action, active nav, focus rings, the bulk-action ribbon. Never decorative.
- **Reactor maroon (`#550000`) is fill, not text.** Used for primary buttons, pressed states, the bulk-action ribbon background. The text color *on* maroon is `#FFB4A8` salmon — the only place that hex appears.
- **Filament's `'success' | 'warning' | 'danger' | 'info' | 'gray'` are not Final Cut tokens** — remap them in `AdminPanelProvider->colors()`. See `references/notification-and-badge-mapping.md`.

### Type

- **Noto Serif** for display, headings, dashboard titles, page titles. The "house manager's voice" — structural authority, tight letter-spacing.
- **Newsreader** for body, table cells, labels, button text. Editorial grace, organic curves.
- **Default body 0.875rem (14px)** in admin density — denser than the customer site by one step, because operators read tables, not stories.
- Negative letter-spacing on display/headings (−0.5% to −1%). Uppercase eyebrows on table column headers with +0.10em tracking.

### Spacing, radii, motion

Use Final Cut's existing scale verbatim — see `docs/design-system/DESIGN_SYSTEM_STRUCTURE.md` § 1. Admin-specific notes:

- Sidebar fixed. Topbar fixed. Content scrolls.
- Card radius: 0.25rem. Modal radius: 0.125rem (the existing `radius-sm`). Pills: full radius is permitted only for filter chips and badges — same exception the customer site grants for avatars.
- Hover: +1 surface tier (`surface_container` → `surface_container_high`). No `box-shadow` on static elements; depth is communicated by tier shifts.
- Floating elements (modals, command palette, dropdown menus) get the standard `box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.6)` — a darkened tint of the background, never gray.
- Motion: `--duration-micro: 100ms`, `--duration-standard: 250ms`, `--duration-emphasis: 400ms`. No bounces.

### The No-Line Rule (admin variant)

Layout sectioning uses surface-tier shifts, not borders. Filament's stock CSS draws lots of `border-*` separators between table rows, between sidebar items, around inputs. The bundled `theme.css` strips those and re-creates separation through tier contrast. Where a border *is* required (input underline, focused state, the active-nav left edge accent), use `outline` (`#A58B86`) at full opacity, never `outline_variant`.

### Iconography

Filament ships **Heroicons** natively. Prefer line-style:

- **Do:** `heroicon-o-ticket`, `heroicon-o-magnifying-glass`, `heroicon-o-funnel`
- **Avoid:** `heroicon-s-ticket` (solid icons fight the No-Line aesthetic)

Existing Resources already lean on `-o-` variants — keep that consistent.

### Backgrounds

- App canvas: flat `surface` (`#131313`). No gradients on the canvas.
- The **vignette bloom** gradient (radial maroon → black) is reserved for the dashboard hero region and the sign-in splash. Don't use it on Resource pages.
- No patterns, no repeating textures, no illustrations. The poster image of a movie or a seat-map visualization is the only legitimate "image" on an admin surface.

## Filament-specific rules

### Schema-first API only

Filament v5 with `Filament\Schemas\Schema` is the API in this codebase. Confirm imports look like:

```php
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
```

If you see `Filament\Forms\Form` or `Filament\Resources\Resource::form(Form $form)`, that's Filament v3 — generated patches that look like that are wrong for this codebase.

### Token discipline at three layers

The admin panel touches the brand at three distinct layers, in this order:

1. **`AdminPanelProvider::panel()`** — Filament's PHP color/font/brand API. Maps tokens to Filament's internal palette.
2. **`backend/public/css/admin/finalcut-overrides.css`** — the CSS overrides that retheme components Filament's PHP API doesn't reach (sidebar surface, badge backgrounds, card edges, the bulk-action ribbon).
3. **Inline class strings inside `HtmlString` and `Placeholder->content()` closures** — see `references/token-mapping.md` § "The HtmlString pitfall." This is where most token leakage happens; review it before merging any Resource that uses `HtmlString`.

`theme.css` in this skill is the source of layer 2. `references/panel-provider.md` describes layer 1.

### Surface roles in Filament terms

| Filament surface | Final Cut token | Notes |
| ---------------- | --------------- | ----- |
| `.fi-sidebar` | `surface_container_low` | Sidebar feels recessed against the canvas. |
| `.fi-topbar` | `surface_container` with 12px backdrop blur | Topbar dissolves into the canvas on scroll. |
| `.fi-main` (page canvas) | `surface` | Flat, no gradient. |
| Card / form section / table row | `surface_container` | Default elevation. |
| `.fi-modal`, `.fi-dropdown-panel`, command palette | `surface_container_high` | Floating elements only. |
| Input rest state | `surface_container_high` with `outline` underline | Underline only — no full border. |
| Active nav item | `surface_container_high` background + `secondary` (gold) leading edge | Two-token shift. |

### Dark by default, light supported

`AdminPanelProvider->darkMode(true, condition: …)` sets dark as the default and follows OS preference for opt-out. The bundled `theme.css` ships both modes:

- Dark tokens at `:root` (default).
- Light tokens at `:root.fi-light` — Filament adds the `.fi-light` class to `<html>` when the user picks the light theme via the topbar toggle.

The light mode exists for operators printing handover documents in a bright office; nobody is *expected* to use it as their working surface. If you build a new Resource view, sanity-check both modes — but optimize for dark.

## What this skill is NOT

- Not the canonical token sheet. Tokens live in `docs/design-system/DESIGN_SYSTEM.md`. The skill's `theme.css` *consumes* those tokens, it doesn't redefine them.
- Not a Filament tutorial. If you need help with the framework itself, read Filament's docs at https://filamentphp.com/docs/5.x. The skill's job is to teach you the brand, not the framework.
- Not for `frontend/` work. Route customer-facing requests to `.claude/skills/finalcut-design/`.
