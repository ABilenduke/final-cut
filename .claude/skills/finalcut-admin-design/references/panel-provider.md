# Wiring Final Cut into `AdminPanelProvider`

The patch that takes the panel from stock Filament Amber to the Cinematic Void aesthetic. Two files today; three if/when Vite is added to `backend/`.

## Today's path: `registerRenderHook` (no new toolchain)

`backend/` does not currently run Vite. The simplest deployment path is a static CSS file injected into `<head>` via Filament's render hook system.

### File 1 — `backend/public/css/admin/finalcut-overrides.css`

Copy the bundled `theme.css` from this skill verbatim:

```bash
mkdir -p backend/public/css/admin
cp .claude/skills/finalcut-admin-design/theme.css \
   backend/public/css/admin/finalcut-overrides.css
```

Anything served from `backend/public/` is reachable at `/css/admin/finalcut-overrides.css` on the admin domain — nginx serves it directly (the existing dev compose binds `./backend/public:/var/www/html/public:ro`).

### File 2 — `backend/app/Providers/Filament/AdminPanelProvider.php`

Apply this diff. The `colors([])` block remaps Filament's stock semantic palette to Final Cut tokens. The `font()` call switches from Inter to Newsreader. `darkMode()` defaults the panel dark and follows OS preference. `registerRenderHook(PanelsRenderHook::HEAD_END, …)` injects the CSS file from File 1.

```php
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

// inside panel():
->colors([
    // The Filament `primary` SEMANTIC token = Final Cut's `primary_container`
    // BRAND token. The brand `primary` (#FFB4A8) is text-only — never wire it
    // here. See references/token-mapping.md.
    'primary' => Color::hex('#550000'),
    'gray'    => Color::hex('#2a2a2a'),
    'success' => Color::hex('#5b8f6c'),
    'warning' => Color::hex('#dac769'),
    'danger'  => Color::hex('#b5443d'),
    'info'    => Color::hex('#5a8aa0'),
])
->font('Newsreader', provider: FontProvider::BunnyFonts)
->brandName('Final Cut · Operations')
// ->brandLogo(asset('images/admin/logo-mark.svg')) // when the asset lands
->darkMode(true, condition: fn (): bool => true) // dark by default; user can toggle
->renderHook(
    PanelsRenderHook::HEAD_END,
    fn (): HtmlString => new HtmlString(
        '<link rel="stylesheet" href="/css/admin/finalcut-overrides.css">'
    ),
)
```

The `colors([])` array is the load-bearing part. PHP-side semantic colors flow into Filament's button, badge, and notification components automatically. The CSS file handles surfaces, sidebar, topbar, inputs, cards, and the inline-Tailwind leakage from `HtmlString` content.

### Verification (the round-trip dogfood)

After applying both files:

```bash
make admin-filament-assets    # republishes Filament's stock CSS to public/
# Hard-refresh https://admin.finalcut.test (Ctrl+Shift+R / Cmd+Shift+R)
```

Then walk through these four pages and confirm — chrome alone is not enough:

1. `/admin/bookings` — status badges render in maroon/gold/sage, **not** stock green/yellow/red.
2. `/admin/bookings/{id}` (View page) — the seat list `<ul>` (rendered from `HtmlString` in `BookingResource::viewSchema()`) doesn't show stock `list-disc` styling.
3. `/admin/booking-lookup` — search the wrong code, the warning Notification renders with a gold left-edge accent on a dark surface.
4. Heroicons across all pages render in `--fc-tertiary` ivory, not Filament's default mid-gray.

If any of these fail, something in `theme.css` didn't take effect — check the network tab to confirm `/css/admin/finalcut-overrides.css` returns 200.

## Future path: `viteTheme` (when Vite is added to backend/)

Filament's official theme path is:

```bash
php artisan make:filament-theme admin
```

This scaffolds:

- `backend/resources/css/filament/admin/theme.css` (where the bundled `theme.css` from this skill goes instead of `public/`)
- `backend/tailwind.config.js`
- entries in `backend/vite.config.js` and `backend/package.json`

Then `AdminPanelProvider->viteTheme('resources/css/filament/admin/theme.css')` replaces the `registerRenderHook` call. The CSS file's contents — including the `@theme` block already in the bundled `theme.css` — work unchanged in this path. The `@theme inline { ... }` block becomes meaningful (Tailwind v4 picks it up); under the render-hook path it's a benign no-op.

Don't migrate to this path solely for the brand wiring — `registerRenderHook` covers the brand. Migrate when something *else* requires Tailwind compilation in `backend/` (e.g., a custom Blade page that needs Tailwind utilities the published Filament CSS doesn't include).

## Light mode

`darkMode(true, condition: …)` accepts a closure; pass one that defaults dark and respects an opt-out. Filament's topbar theme switcher writes the user's preference to a cookie and toggles the `.fi-light` class on `<html>` — the bundled `theme.css` already has matching `:root.fi-light` token overrides.

Skip the toggle entirely (force dark) by passing `condition: null`:

```php
->darkMode(true, condition: null)
```

The light mode exists for handover documents printed from the panel. Don't optimize working surfaces for it; sanity-check both modes on any new view, but the design target is dark.

## What this patch deliberately does NOT do

- Does **not** install fonts as TTF assets in `backend/public/`. Bunny Fonts CDN delivers Newsreader on demand; bundling fonts ships unnecessary weight.
- Does **not** customize the topbar/sidebar layout via panel options. Filament's defaults (collapsible sidebar, sticky topbar) match the operational spec — nothing to change.
- Does **not** alter the `colors([])` for `Color::Amber` keys that aren't on the list above. If a future Resource references a non-standard color (e.g., `Color::Sky`), it falls through to Filament's default — which is correct: the panel's "one accent, one job" rule means non-standard colors should be eliminated, not re-skinned.
