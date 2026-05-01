# Final Cut icons

34 inline SVG icons, 24×24 viewBox, `fill="currentColor"`. Material Design Icons (Apache 2.0). Source of truth: `frontend/app/components/ui/icons.ts`.

## Two ways to use

**`icons.js` — JS path map.** Best when you're writing inline-rendered components (React, Vue, vanilla JS). Loads `window.FC_ICONS[name]` as a path-data string.

Vanilla HTML — assign `d` via JS, since attribute interpolation isn't valid HTML:

```html
<script src="assets/icons/icons.js"></script>
<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
  <path id="icon-check"></path>
</svg>
<script>
  document.getElementById('icon-check')
    .setAttribute('d', window.FC_ICONS['check']);
</script>
```

In a framework, bind directly:

```jsx
// React
<path d={FC_ICONS['check']} />
```

```html
<!-- Vue -->
<path :d="FC_ICONS['check']" />
```

**`sprite.svg` — single combined sprite.** Best for static HTML mocks where wiring up a script is overkill.

```html
<svg width="24" height="24" fill="currentColor" aria-hidden="true">
  <use href="assets/icons/sprite.svg#icon-check"></use>
</svg>
```

## Color rule

Icons inherit `currentColor` from their text color. Set the parent's `color` (or pass explicit `fill`):

- **Body / neutral context:** `var(--tertiary)` (ivory) or `var(--on-surface)`.
- **Active / interactive:** `var(--secondary)` (gold).
- **On a maroon fill only:** `var(--primary)` (salmon). Never use salmon on any other background.
- **Ambient / telemetry:** `var(--on-tertiary-fixed-variant)` (Neural Ticker context).

## Sizes (CSS variables)

| Token | Size | Use |
|---|---|---|
| `--icon-sm` | 1rem (16px) | Inline with body text |
| `--icon-md` | 1.5rem (24px) | UI controls (buttons, nav, inputs) |
| `--icon-lg` | 3rem (48px) | Feature callouts, empty states |
| `--icon-xl` | 4rem (64px) | Hero decorative |

## Available glyphs (34)

`close` `check` `chevron-down` `chevron-up` `chevron-right` `chevron-left` `alert` `info` `spinner` `menu` `home` `movie` `calendar` `account` `more-horiz` `gift-card` `food-drink` `location` `pause` `play` `loyalty` `orders` `bookings` `payment` `settings` `wheelchair` `accessible` `star` `print` `calendar-add` `minus` `plus` `receipt` `logout`

## Accessibility

- **Decorative icon next to a visible label:** `aria-hidden="true"` on the `<svg>`.
- **Icon-only button:** parent element gets an `aria-label` describing the action; the `<svg>` itself stays `aria-hidden="true"`.
- **Meaningful standalone icon:** wrap in an element with `role="img"` and `aria-label="..."`.
