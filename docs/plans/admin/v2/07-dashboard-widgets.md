# Plan 07 — Dashboard KPI widgets

## Goal

Replace the stock Filament dashboard (AccountWidget + FilamentInfoWidget) with operator
KPIs: how is today going, and what needs attention. First widgets in the panel —
conventions follow `.claude/skills/finalcut-admin-design/references/widget-patterns.md`
(sentence case, lowercase description fragments, outline heroicons, semantic colors,
cents formatted at the widget boundary via `FormatsCurrency`, sort bands 1–10 stats /
21+ tables).

## Widgets

- **`TodayKpisWidget`** (StatsOverview, sort 1, heading "Today", `bookings.view`):
  confirmed bookings today, revenue today (sum of confirmed `total`), showtimes scheduled
  today. "Today" = the venue day: boundaries computed in
  `config('app.default_location_timezone')` (falls back to app timezone), converted to UTC
  for querying. Metrics exposed as a public static `metrics()` so tests assert numbers,
  not markup.
- **`OpsHealthWidget`** (StatsOverview, sort 2, heading "Needs attention", `bookings.view`):
  flagged bookings pending (deep link to the cancellation follow-up queue), outbox backlog
  (pending rows), outbox parked (failed rows — danger color when non-zero). Covers the
  audit's "no admin visibility into outbox failures" gap at the glance level; the full ops
  surface is Step 1.8.
- **`TodayShowtimesOccupancyWidget`** (TableWidget, sort 21, full width, `showtimes.view`):
  today's non-cancelled showtimes with an `occupied / capacity` column. Occupancy comes
  from an `addSelect` correlated subquery on `booking_seats.occupies_seat` — no model
  relation needed, keeping this branch independent of #69 (which adds
  `Showtime::bookingSeats()`; the audit-plan note "reuse 1.4's query" is satisfied by
  reusing the same flag, not the same code path).

Deviation from the plan index's four names: `FlaggedBookingsWidget` + `OutboxHealthWidget`
are folded into one `OpsHealthWidget` StatsOverview — three related "attention" stats in
one card beats two near-empty cards (skill guidance: ≤5 widgets on the dashboard).

`FilamentInfoWidget` is dropped from `AdminPanelProvider`; `AccountWidget` stays.

## Tests (`tests/Feature/Admin/Widgets/DashboardWidgetsTest.php`)

Metric correctness with seeded fixtures (today vs yesterday bookings, confirmed-only
revenue, flagged-pending excludes terminal statuses, outbox backlog vs parked); per-role
`canView` (ops sees all three; roleless admin none); render smoke tests incl. the
occupancy table's `x / y` cell.
