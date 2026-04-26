# Filament Patterns Reference

How to apply the Final Cut admin design system inside Filament 5. Covers
theme wiring, color registration, font loading, render hooks, build workflow,
and custom Blade page conventions.

---

## Where the theme lives

```
backend/resources/css/filament/admin/theme.css
```

This is the entry point for the custom Tailwind v4 + Filament theme. It is
compiled by Vite (`backend/vite.config.js`) and output to
`backend/public/build/`. Filament's `->viteTheme()` panel method loads it.

The file:
1. Imports Tailwind v4 (`@import 'tailwindcss'`)
2. Imports Filament's base theme (`@import '../../../../vendor/filament/filament/resources/css/theme.css'`)
3. Declares `@source` directives so Tailwind scans Filament PHP classes and
   custom Blade views for utility usage
4. Overrides Filament's CSS custom properties in `@layer base`
5. Defines admin-specific utility classes in `@layer components`

---

## Wiring colors via ->colors([...])

In `AdminPanelProvider.php`, register the gold ramp as `primary` and the
semantic accents with `Color::hex()`:

```php
use Filament\Support\Colors\Color;

->colors([
    'primary' => [
        50  => '#1c1609',
        100 => '#2a2010',
        200 => '#4a371a',
        300 => '#7a5a2b',
        400 => '#a88040',
        500 => '#DAC769',
        600 => '#e6d489',
        700 => '#f0e1a9',
        800 => '#f8edc6',
        900 => '#fdf6e0',
        950 => '#fefae8',
    ],
    'danger'  => Color::hex('#b5443d'),
    'success' => Color::hex('#5b8f6c'),
    'warning' => Color::hex('#c78438'),
    'info'    => Color::hex('#5a8aa0'),
    'gray'    => Color::Stone,
])
```

Filament generates CSS custom properties (`--fi-color-primary-500`, etc.)
from these arrays. The `theme.css` `@layer base` overrides specific shades
where needed to enforce the maroon-fill / gold-text split.

`gray` is set to `Color::Stone` -- a warm neutral that aligns with the
project's ivory/terracotta ink ramp and avoids the cool-gray default that
would clash with the warm-dark surfaces.

---

## Wiring fonts via ->font()

```php
use Filament\FontProviders\GoogleFontProvider;

->font('Newsreader', provider: GoogleFontProvider::class)
```

This loads Newsreader from Google Fonts as Filament's panel font. Noto Serif
is applied in custom Blade views and CSS via direct class or the
`font-[Noto_Serif]` Tailwind utility.

---

## Wiring the custom theme via ->viteTheme()

```php
->viteTheme('resources/css/filament/admin/theme.css')
```

The path is relative to the backend application root (i.e., the `public_path`
parent). Filament reads the Vite manifest at `public/build/manifest.json` and
injects the correct hashed URL.

Run `make admin-theme-build` after any change to `theme.css` or after a fresh
`make build` to ensure the compiled asset exists before Filament tries to
load it.

---

## Render hooks for Blade injection

Use `FilamentRenderHook` constants to inject Blade fragments into the panel
layout without modifying Filament source:

```php
use Filament\View\PanelsRenderHook;

$panel->renderHook(
    PanelsRenderHook::BODY_START,
    fn () => view('filament.partials.noto-serif-link'),
);
```

Common hooks used in this project:

| Constant | Position |
|----------|----------|
| `PanelsRenderHook::HEAD_START` | Inside `<head>`, before Filament's own head |
| `PanelsRenderHook::HEAD_END` | End of `<head>` |
| `PanelsRenderHook::BODY_START` | Immediately after `<body>` opens |
| `PanelsRenderHook::SIDEBAR_NAV_START` | Top of the sidebar nav |
| `PanelsRenderHook::TOPBAR_START` | Start of the topbar |

---

## Build workflows

### make admin-filament-assets

Re-publishes Filament's own built CSS, JS, and fonts to `backend/public/`.
Run this after upgrading Filament or after a fresh container build where
`backend/public/` is empty.

```bash
make admin-filament-assets
# Equivalent: docker compose exec -u 1000 backend php artisan filament:assets
```

### make admin-theme-build

Compiles the custom `theme.css` entry point via Vite. Outputs to
`backend/public/build/`. The manifest at `backend/public/build/manifest.json`
is what Filament's `->viteTheme()` reads.

```bash
make admin-theme-build
# Equivalent: docker compose exec -u 1000 backend npm run build
```

### make admin-theme-watch

Starts Vite in watch mode for iterative theme development. Keep this running
in a separate terminal while editing `theme.css`.

```bash
make admin-theme-watch
# Equivalent: docker compose exec -u 1000 backend npm run dev
```

Run `make admin-filament-assets` and `make admin-theme-build` on first setup
after `make up`. The order matters: Filament assets first, then the custom
theme build.

---

## Custom page Blade convention

Custom pages extend `<x-filament-panels::page>`:

```blade
<x-filament-panels::page>
    {{-- Page content here --}}
</x-filament-panels::page>
```

Apply Final Cut admin tokens via:
1. Tailwind utility classes from the compiled theme (e.g., `bg-surface-container`, `text-secondary`)
2. Inline CSS custom properties for values Tailwind does not expose (e.g., `style="color: var(--success)"`)
3. The `admin-tabular` utility class for numeric columns

Noto Serif for display headings:
```blade
<h1 class="font-[Noto_Serif] text-2xl tracking-tight text-on-surface">
    Schedule Planner
</h1>
```

Do not override Filament's internal utility classes. Layer custom classes
on top; do not replace the Filament scaffold.

---

## Where build artifacts land

Vite outputs to `backend/public/build/`:

```
backend/public/build/
  manifest.json                 -- Filament reads this for hashed asset URLs
  assets/
    theme-[hash].css            -- Compiled admin theme
```

The `backend/public/` directory is bind-mounted into the nginx container
(`./backend/public:/var/www/html/public:ro`) so nginx can serve admin static
assets directly without proxying through PHP-FPM.

---

## node_modules in development

The backend container mounts `node_modules` as a named Docker volume
(`backend-node-modules`) so the bind-mounted source tree does not shadow it.
On first `make up`, run:

```bash
docker compose exec -u 1000 backend npm install
```

The `dev-entrypoint.sh` ensures the `node_modules` volume root is owned by
`devuser` (UID 1000) on container start.
