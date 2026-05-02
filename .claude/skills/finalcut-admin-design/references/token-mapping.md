# Token mapping — the load-bearing rules

Three pitfalls account for the majority of brand drift in the admin panel. Read this file before changing colors anywhere.

## Pitfall 1 — `primary` is a text color, not a fill

This is the same #1 rule as the customer frontend. The full table lives in `docs/design-system/DESIGN_SYSTEM.md` § Token Mapping; here is the Filament-specific framing.

| Brand token | Hex | Role | Where it goes in Filament |
| ----------- | --- | ---- | ------------------------- |
| `primary_container` | `#550000` | **Fill** — buttons, active states, accents | `colors(['primary' => Color::hex('#550000'), …])` in `AdminPanelProvider`. CSS: `.fi-color-primary` background. |
| `primary` | `#FFB4A8` | **Text on maroon ONLY** | Foreground inside `.fi-btn-color-primary`, badge label inside `.fi-badge.fi-color-primary`, the salmon text on the bulk-action ribbon's primary CTA. Nowhere else. |
| `secondary` | `#DAC769` | Signal gold — focus rings, active nav accent | Filament focus outline, sidebar active-item leading edge, the bulk-action ribbon left edge. |

### The naming inversion

Filament's PHP `colors([...])` array uses semantic keys. The key `'primary'` in PHP **is the fill color**. So:

```php
'primary' => Color::hex('#550000'),  // brand primary_container — the FILL
```

That looks like it contradicts the rule. It doesn't — the *Filament-side* `primary` semantic name maps onto the *brand-side* `primary_container` token. The brand `primary` (#FFB4A8) doesn't appear in `colors([])` at all; it appears as text inside the maroon fill, which Filament applies automatically based on contrast.

If a generated patch ever passes `'primary' => Color::hex('#ffb4a8')`, it's wrong. Reject it.

## Pitfall 2 — Filament's stock semantic palette will defeat the brand

`Color::hex(...)` calls in `AdminPanelProvider->colors([...])` only retheme the keys you list. Anything left out falls back to Filament's stock palette. The bare-minimum set is:

```php
'primary' => Color::hex('#550000'),
'gray'    => Color::hex('#2a2a2a'),
'success' => Color::hex('#5b8f6c'),
'warning' => Color::hex('#dac769'),
'danger'  => Color::hex('#b5443d'),
'info'    => Color::hex('#5a8aa0'),
```

Existing Resources today emit string-keyed colors that resolve to *exactly* those keys. Examples in this codebase:

- `BookingResource::table()` — status badge mapping uses all six string keys (`'success'`/`'warning'`/`'danger'`/`'info'`/`'gray'`/synthesized `'flagged'`).
- `UserResource::table()` — loyalty-tier badge uses two (`'gray' => Member`, `'warning' => Premier`).

If `colors([...])` doesn't override these names, the badges render in Filament's stock green/yellow/red — chrome that looks correct around them, data that doesn't. Re-skinning happens at two layers in tandem (`AdminPanelProvider->colors()` and the bundled `theme.css`); see `references/notification-and-badge-mapping.md` for the full mapping including `Notification::make()->success()->warning()->danger()`.

## Pitfall 3 — inline Tailwind classes inside `HtmlString` and `Placeholder->content()` bypass `theme.css`

This is the **second-most-common drift** after pitfall 1. It's hard to spot in code review because the offending classes look innocuous.

### Real example — `BookingResource::viewSchema()`

The view page renders the seat list and a customer link via `HtmlString`:

```php
// backend/app/Filament/Resources/BookingResource.php (around line 220)
->content(fn (Booking $b): HtmlString => new HtmlString(
    '<a href="…" class="fi-link underline">'.e($b->user->name).'</a>'
))

// (around line 264)
->content(fn (Booking $b): HtmlString => new HtmlString(
    '<ul class="list-disc pl-5 space-y-1">'
    . collect($b->bookingSeats)->map(fn ($s) => '<li>…</li>')->implode('')
    . '</ul>'
))
```

Those `pl-5` / `space-y-1` / `list-disc` strings are Tailwind utility classes baked into PHP. When `theme.css` re-skins Filament's `.fi-fo-placeholder` containers, these inline classes still render under whatever Tailwind defaults Filament's compiled CSS provides — which is to say, generic spacing and a generic disc bullet.

The bundled `theme.css` includes a *backstop* (`.fi-fo-placeholder ul.list-disc { … }`) that lands the brand on these specific patterns. But the durable fix is to **stop emitting raw Tailwind from PHP**. Two routes:

1. **Use Filament components instead of `HtmlString`.** A list of seats can be a nested `Schema` of `TextEntry` rows, not an HTML string. A link can be a `TextEntry::make()->url()`.
2. **Where `HtmlString` is genuinely the right answer** (e.g., embedding the result of a Markdown render), wrap the markup in a class the brand owns: `class="fc-prose"` and add the rules to `theme.css`. Don't reach for stock Tailwind.

### Detection

Search the codebase before merging:

```bash
grep -rn "HtmlString" backend/app/Filament/
grep -rn 'class="' backend/app/Filament/  # any inline Tailwind in PHP is suspicious
```

Each match is a place to either replace with Filament components or document the brand-owned class.

## Pitfall 4 — Heroicon line vs. solid

Filament accepts `heroicon-o-*` (outline) and `heroicon-s-*` (solid) on `navigationIcon`, table column icons, action button icons, etc. The No-Line aesthetic of the design system favors **outline** — the solid set fights it visually. The codebase already leans this way; keep it consistent.

Quick fix for any divergence:

```bash
grep -rn "'heroicon-s-" backend/app/Filament/  # should be empty
```

## Where the canonical tokens live

Don't redefine these — point at the source:

- `docs/design-system/DESIGN_SYSTEM.md` (the canonical token map, including the do/don't table)
- `docs/design-system/DESIGN_SYSTEM_STRUCTURE.md` (spacing, motion, breakpoints)
- `frontend/app/assets/css/tokens.css` (the live customer-side CSS variables — confirms what hex maps to which name)
- `.claude/skills/finalcut-admin-design/theme.css` (the admin-side CSS variables and Filament-class overrides; same hex values, scoped to the panel)

If any two of those drift, the canonical doc wins; the others get fixed.
