# Notifications and badges — semantic color mapping

Filament's `'success' | 'warning' | 'danger' | 'info' | 'gray' | 'primary'` semantic strings appear all over the codebase already — in `Tables\Columns\TextColumn::badge()->colors([...])`, in `Notification::make()->success()`, in `Action::color('danger')`. The skill's job is to make those existing strings render in Final Cut's palette, not to ask developers to refactor every call site.

## The two-layer remap

The remap happens at two layers in tandem. Both are required — drop either one and badges or notifications fall back to Filament's defaults.

### Layer 1 — `AdminPanelProvider->colors([...])`

Wires PHP-side semantic colors into Filament's CSS variable system. From `references/panel-provider.md`:

```php
->colors([
    'primary' => Color::hex('#550000'),
    'gray'    => Color::hex('#2a2a2a'),
    'success' => Color::hex('#5b8f6c'),
    'warning' => Color::hex('#dac769'),
    'danger'  => Color::hex('#b5443d'),
    'info'    => Color::hex('#5a8aa0'),
])
```

`Color::hex(...)` generates a v3-style 50-950 palette around the hex you provide; Filament picks the right shade for fill vs. text vs. border based on context. The hex you pass should be the **fill** color — the darker, saturated form. Filament will derive the text shade automatically.

### Layer 2 — `theme.css`

Per-component overrides for surfaces and edges that Filament's PHP API doesn't reach. The bundled `theme.css` includes:

- `.fi-badge.fi-color-{success|warning|danger|info|gray}` — re-skinned with `color-mix()` against the relevant token at 18% opacity for the fill, full opacity for the text.
- `.fi-no-notification.fi-color-{success|warning|danger|info}` — gold/sage/maroon left-edge accent on the dark notification surface.

Both layers ship together. PR review hint: if a patch adds Layer 1 but skips Layer 2 (or vice versa), the notification borders or badge backgrounds will be inconsistent.

## The mapping

| Filament semantic | Hex (Layer 1) | Final Cut token role | Use cases in this codebase |
| ----------------- | ------------- | -------------------- | -------------------------- |
| `primary` | `#550000` | `primary_container` (reactor maroon) | Main CTAs, primary tab indicator. |
| `gray` | `#2a2a2a` | `surface_container_high` | Default fallback badge state. |
| `success` | `#5b8f6c` | sage green | `BookingStatus::Confirmed`. |
| `warning` | `#dac769` | signal gold | `flagged_at IS NOT NULL` synthetic state, `LoyaltyTier::Premier`, "needs review" flags. |
| `danger` | `#b5443d` | claret (admin-only — diverges from frontend) | `BookingStatus::Refunded`, `BookingStatus::Cancelled`, gift-card "voided". |
| `info` | `#5a8aa0` | steel | `BookingStatus::Held`, `BookingStatus::RefundPending` — transient holding states distinct from terminal success/failure. |

### Why claret (`#b5443d`) for `danger`

The customer frontend reuses `primary_container` (maroon) for destructive emphasis. The admin context has different needs: a maroon "Refunded" badge sitting next to a maroon primary button creates ambiguity about which element is the action. Claret is warm enough to feel like part of the same family but distinct enough to read as "data state, not button." This is a deliberate divergence and the only place the admin palette adds a hex that isn't in `frontend/app/assets/css/tokens.css`.

## Notification patterns

Filament notifications fire from controllers, page actions, and resource actions. Three real examples in this codebase:

```php
// backend/app/Filament/Pages/BookingLookup.php:73
Notification::make()
    ->title('No booking found')
    ->body('Double-check the code or email and try again.')
    ->warning()    // → gold left-edge accent, ivory text on surface_container_high
    ->send();

// backend/app/Filament/Resources/UserResource.php:304 (loyalty adjustment)
Notification::make()
    ->title('Points adjusted')
    ->body("$delta points · {$user->loyalty_points} balance.")
    ->success()    // → sage left-edge accent
    ->send();

// backend/app/Filament/Resources/GiftCardResource.php (void)
Notification::make()
    ->title('Gift card voided')
    ->body('Refund queued for the original card.')
    ->danger()     // → claret left-edge accent
    ->send();
```

The bundled `theme.css` re-skins the surface so all three render with a 0.1875rem left-edge accent in the appropriate color, on a `surface_container_high` floating panel. No code changes needed at the call sites.

### Title / body voice

Notification titles use **sentence case** and stay short (≤ 6 words). The body adds context, never re-states the title. Past tense for actions that completed (`Refund issued`, `Booking voided`); present-tense imperative for actions that need follow-up (`Try again`).

Examples drawn from existing flows:

- **Do:** Title: `Points adjusted`, Body: `+450 points · 1,820 balance.`
- **Do:** Title: `Gift card voided`, Body: `Refund queued for the original card.`
- **Do:** Title: `No booking found`, Body: `Double-check the code or email and try again.`
- **Avoid:** Title: `Booking Lookup Failed!` (Title Case + exclamation = consumer voice; admin doesn't shout)
- **Avoid:** Title: `Successfully adjusted points` (the "Successfully" prefix is noise — the success color carries that info)

## Badge patterns

Badges in tables use `TextColumn::badge()->color(fn ($state) => …)`:

```php
TextColumn::make('status')
    ->badge()
    ->getStateUsing(fn (Booking $r): string => $r->displayStatus())
    ->color(fn (string $state): string => match ($state) {
        BookingStatus::Confirmed->value => 'success',
        'flagged'                       => 'warning',
        BookingStatus::Held->value,
        BookingStatus::RefundPending->value => 'info',
        BookingStatus::Refunded->value,
        BookingStatus::Cancelled->value => 'danger',
        default                         => 'gray',
    });
```

This is the actual shape used in `BookingResource::table()`. Use `EnumCase->value` arms — never raw strings like `'confirmed'` — so the badge follows the enum if a status is renamed.

Once Layer 1 + Layer 2 are in place, that exact code emits a brand-correct badge. **Don't** rewrite call sites with `Color::hex(…)` per-row — the semantic-string indirection is what lets the brand evolve without churning every Resource.

## When to add a new semantic key

If a new badge state can't be expressed with the existing five (success/warning/danger/info/gray), the right move is **extend the data**, not the palette. Status enums in this codebase (e.g., `BookingStatus`) already model the cardinality the UI needs. If a state doesn't fit, it's because the enum should grow, not because the palette should.

The exception is purely cosmetic state (e.g., the bulk-action ribbon's "selected" indicator). Use `secondary` (gold) directly there — see `theme.css` `.fi-ta-selection-indicator`.

## Anti-patterns

- **Avoid:** `->color('amber')` or `->color('emerald')` — Filament's named colors. Falls outside the remap and renders in stock Tailwind.
- **Avoid:** `->color(Color::hex('#some-hex'))` per call site. Centralize at `AdminPanelProvider->colors()`.
- **Avoid:** Multiple badges in different colors on the same row (e.g., status + flagged + tier). Pick one — operators read tables in scan-mode and a row with three colored badges has no visual hierarchy.
