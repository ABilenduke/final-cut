# Admin Component Patterns

Pattern catalog for the Final Cut admin panel. Each entry maps to a live
Blade view or Filament component pattern in the repository.

---

## Dense operational table

Filament's `TableBuilder` is the default surface for all resource lists
(bookings, customers, showtimes, auditoriums, gift cards, etc.).

### Token overlays

Apply Final Cut tokens on top of Filament's generated table markup:

- Table header cells: `text-xs font-semibold uppercase tracking-wider` +
  `--outline-variant` at 15% opacity as bottom border
- Numeric columns: `admin-tabular` utility class (`font-variant-numeric: tabular-nums`)
- Row hover: `--surface-container-high` background (Filament's default dark
  theme already produces something close; verify and adjust in `theme.css`)
- Action buttons (edit, view, delete): `--radius-sm` (0.125rem), no
  rounded pill shapes

### Status column

Use `Filament\Tables\Columns\TextColumn` with a `badge()` modifier and a
`color()` closure that maps booking lifecycle state to the admin semantic
accents:

```php
TextColumn::make('display_status')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'confirmed'      => 'success',
        'held'           => 'info',
        'refund_pending' => 'warning',
        'cancelled'      => 'danger',
        'refunded'       => 'gray',
        default          => 'gray',
    })
```

The `success`, `info`, `warning`, `danger` color keys map to the semantic
accents registered in `AdminPanelProvider->colors()`.

---

## Bulk-action ribbon

Filament renders a bulk-action bar above the table when rows are selected.
The Final Cut admin theme styles this as a gold-tinted ribbon.

### Behavior

- Appears above the table body when one or more rows are selected
- Dismissible with the X button or the Esc key (Filament's default)
- Background: `--secondary-container` (#675900) at 20% opacity over
  `--surface-container-high`
- Text: `--secondary` (#DAC769)
- Action buttons: `--primary-container` (#550000) fill, `--primary`
  (#FFB4A8) text

### Implementation

Filament's bulk-action bar is rendered by the table component. Apply
custom styles in `theme.css` targeting Filament's bulk-action wrapper
selector. Do not modify Filament's PHP API for this -- it is a visual
override only.

---

## Status pill

Small inline indicator for booking lifecycle state, gift card status,
showtime published state, or any other discrete categorical value.

### Visual spec

- Border radius: `--radius-sm` (0.125rem) -- sharp, not rounded
- Font: Newsreader, `label-sm` equivalent (`0.6875rem`), ALL CAPS,
  tracking 0.04em, semibold
- Padding: `0.125rem 0.375rem`
- Background: semantic accent at 20% opacity
- Text: semantic accent at full opacity

### State mapping

| State | Accent | Background |
|-------|--------|-----------|
| Confirmed | `--success` (#5b8f6c) | `--success` at 20% opacity |
| Held | `--info` (#5a8aa0) | `--info` at 20% opacity |
| Refund pending | `--warning` (#c78438) | `--warning` at 20% opacity |
| Cancelled | `--destructive` (#b5443d) | `--destructive` at 20% opacity |
| Refunded | `--tertiary` / `--surface-container-high` | muted, low contrast |
| Flagged | `--warning` + exclamation icon | same as warning |
| Published | `--success` | same as confirmed |
| Draft | `--outline` | neutral |

Use the `admin-pill--*` utility classes from `tokens.css` for standalone
HTML mocks. In production Filament, use the `badge()` + `color()` pattern
on `TextColumn` (see Dense operational table above).

---

## Audit log row

Used in the activity log page (`backend/resources/views/filament/pages/activity-log.blade.php`)
and inline audit sections within resource view pages.

### Structure

Each row contains:
1. Monospaced timestamp (ISO-like, 24h, `admin-tabular` utility)
2. Actor name (abbreviated first initial + surname, or "System")
3. Past-tense action string (see `content-rules.md` for format)
4. Optional subject link (booking code, user email, showtime title)

### Visual spec

- Timestamp: `--on-tertiary-fixed-variant` (#A89F91), `label-sm`,
  `font-family: monospace` or a monospaced stack
- Actor: `--tertiary` (#CCC6B6), `label-md`
- Action text: `--on-surface` (#E5E2E1), `body-sm`
- Subject link: `--secondary` (#DAC769), underline on hover
- Row separator: none (spacing-only, `--space-sm` gap)

---

## Seat grid

Custom Blade partial rendering an interactive auditorium seat map in the
admin seat configuration UI.

**Live file:**
`backend/resources/views/filament/resources/auditorium-resource/pages/partials/seat-grid.blade.php`

**Parent page:**
`backend/resources/views/filament/resources/auditorium-resource/pages/configure-seats.blade.php`

### Notes

- Seat cell size matches the customer site spec: 2.5rem (desktop), 3rem
  (touch). Admin context is always desktop/pointer -- 2.5rem is acceptable.
- Available: `--surface-container-high`
- Selected / active: `--primary-container` (#550000)
- Unavailable: `--surface-container-low` at 40% opacity
- Section color accents: `--secondary-container` tint for premium,
  `--info` tint at 15% for accessible sections
- Row labels: `--tertiary`, Newsreader `label-md`, pinned left

---

## Schedule planner

Full-page calendar/timeline view for managing showtime slots across
auditoriums for a given date or week.

**Live file:**
`backend/resources/views/filament/pages/schedule-planner.blade.php`

### Notes

- Background: `--surface-container-lowest` for the timeline void
- Showtime blocks: `--primary-container` fill, `--primary` text (salmon
  on maroon is the one case where primary is a text color)
- Conflict highlighting: `--destructive` left border, `--destructive` at
  10% opacity background tint
- Current time indicator: `--secondary` (#DAC769), 1px, no label
- Noto Serif for the page title; Newsreader for all labels, times, counts

---

## Booking lookup

Single-field search page for finding a booking by confirmation code or
guest email, reachable from the Operations navigation group.

**Live file:**
`backend/resources/views/filament/pages/booking-lookup.blade.php`

### Notes

- Input style: underline-only, `--outline` unfocused, `--secondary` focused
- Result card: `--surface-container-high` background, `--radius-sm` corners,
  no shadow
- Confirmation code display: Newsreader, large (`headline-sm` equivalent),
  `--secondary` (#DAC769)
- Status pill inline with the booking summary
- Empty state: plain text, no illustration, centered, `--tertiary`

---

## Cancellation followup queue

Bulk-action page for notifying customers of a cancelled showtime and
tracking refund/notification status.

**Live file:**
`backend/resources/views/filament/pages/cancellation-followup-queue.blade.php`

### Notes

- Uses the dense operational table pattern for the affected bookings list
- Progress summary (notified / pending / failed) as stat cards:
  `--surface-container-high` background, Noto Serif count, Newsreader label
- Stat label: ALL CAPS eyebrow style, `--on-tertiary-fixed-variant`
- Action buttons at the top of the page: primary action ("Notify all")
  uses `--primary-container` fill, `--secondary` text; secondary action
  ("Export list") uses `--surface-container-high` fill, `--on-surface` text
