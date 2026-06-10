# Plan 04 — Per-showtime seat-occupancy map

## Goal

Box-office visibility: a read-only seat map for any showtime showing exactly which seats are
sold / held / refund-pending / unavailable / available, plus an occupancy column on the
showtimes list. Step 1.5 (walk-up bookings) will reuse the grid as its seat picker.

## Design

- **`ShowtimeOccupancy` custom resource page** (`/{record}/occupancy`, registered in
  `ShowtimeResource::getPages()`), following the `VisualEditor` precedent: custom Page with its
  own Blade view, `mount()` gated by `showtimes.view`. State computed into public Livewire
  properties (`$seatStates`, `$counts`) so tests can `assertSet` instead of scraping DOM.
- **Seat state derivation** (authoritative source: the occupancy guard): seats of the
  auditorium LEFT-joined to `booking_seats WHERE occupies_seat AND showtime_id = …` with the
  parent booking's status — `sold` (Confirmed), `held` (Held), `refund_pending`
  (RefundPending); seats with `unavailable_at` set are `unavailable`; the rest `available`.
  Occupied cells carry the booking confirmation code (title attribute + visible on hover).
- **Blade grid** reuses the `seat-grid.blade.php` rendering shape (row letters, fixed cells)
  but static — no Alpine. Cell fills per the design system: sold = `#550000`
  (primary_container, THE fill color), held = steel `#5A8AA0`, refund-pending = gold
  `#DAC769` (dark text), unavailable = 50% gray (matches the visual editor), available =
  neutral outline. Legend carries the counts; a "View bookings" button deep-links to
  `BookingResource` pre-filtered by showtime.
- **Occupancy column** on the showtimes table: `occupied / capacity` via
  `withCount(['bookingSeats as occupied_seats_count' => occupies_seat])` on
  `getEloquentQuery()` (no N+1) against `auditorium.total_seats`. Requires a new
  `Showtime::bookingSeats()` hasMany (booking_seats already carries `showtime_id`).
- **Header action** `occupancy_map` on `ViewShowtime` linking to the page.

## Tests (`tests/Feature/Admin/Pages/ShowtimeOccupancyTest.php`)

Mixed statuses produce the correct per-seat states and counts (confirmed + held +
refund-pending occupy; refunded/cancelled don't); `unavailable_at` seats counted separately;
ops (has `showtimes.view`) can access, roleless admin 403s; list column renders
`occupied / capacity`; ViewShowtime shows the link action.
