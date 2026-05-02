---
name: finalcut-admin-design
description: Use this skill when designing, theming, or extending the Final Cut Filament admin panel — Resources, Resource viewSchema/infolists, custom Pages, Widgets, Notifications, badges, and Blade view overrides under `backend/app/Filament/` and `backend/resources/views/filament/`. Triggers on requests like "style this Filament resource", "add a dashboard widget", "the admin panel still looks like default Filament", "the booking status badge is the wrong color", or any work touching `AdminPanelProvider`, `viteTheme`, or the admin theme CSS. NOT for customer-facing work under `frontend/` — use the sibling `finalcut-design` skill for that. NOT the bundled `frontend-design` skill either — that one builds generic interfaces and doesn't know the Final Cut brand.
user-invocable: true
---

This skill applies to work under `backend/app/Filament/` and the admin panel at `admin.finalcut.test`. The brand is **Final Cut** ("The Cinematic Void Framework"); the admin surface is Filament v5 using the **schema-first API** (`Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`). Patches you generate must not regress to v3-style `Forms\Form` idioms.

## Defer to the canonical sources

The admin skill does not redefine tokens — it wires existing tokens into Filament. Read these first:

- **Tokens / token mapping:** `docs/design-system/DESIGN_SYSTEM.md` § Token Mapping (the rule that `primary` is text-only, `primary_container` is fill, etc.).
- **Spacing, motion, focus, breakpoints:** `docs/design-system/DESIGN_SYSTEM_STRUCTURE.md`.
- **Currency / status / time conventions:** `docs/architecture/DATA_MODELS.md`, `app/Enums/BookingStatus.php`.
- **Sibling customer-facing skill:** `.claude/skills/finalcut-design/` — for any work under `frontend/`, route there instead.

## What's in this skill

- `README.md` — voice/tone for operators + visual foundations + Filament-specific rules.
- `theme.css` — a working, ready-to-deploy override sheet. Plain CSS (no Tailwind compile required) that re-skins Filament components using Final Cut tokens. Drop into `backend/public/css/admin/finalcut-overrides.css` and inject via `registerRenderHook` (see `references/panel-provider.md`).
- `references/panel-provider.md` — the two-file (or three, if Vite is added later) patch that brings the panel into the brand: `AdminPanelProvider.php` diff + the theme stylesheet path.
- `references/token-mapping.md` — load-bearing pitfalls. **Read this before changing colors.**
- `references/resource-and-infolist-patterns.md` — Form schema, `viewSchema` (`Placeholder` / `HtmlString`), table column patterns. Most Resources here are read-heavy, so view schemas dominate.
- `references/notification-and-badge-mapping.md` — Filament's `'success' | 'warning' | 'danger' | 'info' | 'gray'` → Final Cut tokens. Plus `Notification::make()` theming.
- `references/custom-page-patterns.md` — Page class + Blade view conventions. Precedents: `BookingLookup`, `SchedulePlanner`, `ActivityLog`, `CancellationFollowupQueue`.
- `references/widget-patterns.md` — stat / chart / table widgets in the brand. None exist yet; this establishes the convention.
- `references/content-fundamentals.md` — voice, tone, casing, numbers/units adapted for admin operators.

## Non-negotiables

1. **Token discipline.** `primary` (#FFB4A8) = text on maroon. `primary_container` (#550000) = fill. Confusing them is the #1 mistake — see `references/token-mapping.md`.
2. **Filament's stock semantic colors must be remapped.** `'success'/'warning'/'danger'/'info'/'gray'` resolve to Filament's default palette unless overridden in `AdminPanelProvider->colors()`. See `references/notification-and-badge-mapping.md`.
3. **Inline Tailwind classes inside `HtmlString` and `Placeholder->content()` bypass `theme.css`.** Look for `class="pl-5 space-y-1"` and similar baked into PHP strings — they need brand-correct utility classes or hand styling (see `references/token-mapping.md` § Inline-Tailwind pitfall).
4. **`rem` units only** for any custom CSS you write (borders, shadows, sub-pixel are exceptions). Filament's compiled CSS already mixes units; your additions must follow the project rule.
5. **No emoji in admin copy.** Sentence case for headings/buttons/menu items. ALL CAPS only for table-column eyebrows. 24-hour time. Tabular mono for digits operators compare. See `references/content-fundamentals.md`.
6. **Heroicons line-style (`heroicon-o-*`), not solid (`heroicon-s-*`)** — matches the No-Line aesthetic of the design system.
7. **Filament v5 schema-first API only.** `Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`. Don't reach for `Forms\Form`.

## Quick-start triggers

- "style this Filament resource" → read `resource-and-infolist-patterns.md`, then make the edit.
- "the admin panel still looks like default Filament" → produce the patch from `panel-provider.md` (start with `theme.css` deployment).
- "add a dashboard widget" → read `widget-patterns.md`, scaffold under `backend/app/Filament/Widgets/`.
- "the booking status badge is the wrong color" → read `notification-and-badge-mapping.md` for the semantic-color override.
- "what should this confirmation copy say" → read `content-fundamentals.md`.

If invoked without specific guidance, ask what surface (Resource / Page / Widget / global theme), then act as an expert designer working within the existing system.
