# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on several parallel branches (#66→#68 refund stack,
     #69 occupancy, #70 copy-week, this one). On merge conflicts keep ALL step sections. -->

## Step 1.7: Dashboard KPI widgets
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 6 tests first (`DashboardWidgetsTest`), then the three widgets + provider
  change. Full backend suite: **1130 passed**. PHPStan + Pint clean. First widgets in the
  panel — conventions per `.claude/skills/finalcut-admin-design/references/widget-patterns.md`.

### Decisions
- [2026-06-09] Plan-index deviation: `FlaggedBookingsWidget` + `OutboxHealthWidget` folded
  into one `OpsHealthWidget` StatsOverview (three related attention stats beat two
  near-empty cards; skill guidance caps the dashboard at five widgets).
- [2026-06-09] "Today" = the venue day — bounds computed in
  `config('app.default_location_timezone')` (fallback app tz), converted to UTC
  (`TodayKpisWidget::venueDayBounds()`, shared by the occupancy table).
- [2026-06-09] Occupancy via an `addSelect` correlated subquery on
  `booking_seats.occupies_seat` — keeps this branch independent of #69 (which adds the
  `Showtime::bookingSeats()` relation); same source-of-truth flag, different code path.
  Refunds-today stat deferred until #66 lands (`refunded_at` doesn't exist on main yet).
- [2026-06-09] Metric computations exposed as public static `metrics()` so tests assert
  numbers, not markup.

### Files Changed
- `backend/app/Filament/Widgets/TodayKpisWidget.php` — new
- `backend/app/Filament/Widgets/OpsHealthWidget.php` — new
- `backend/app/Filament/Widgets/TodayShowtimesOccupancyWidget.php` — new
- `backend/app/Providers/Filament/AdminPanelProvider.php` — drop FilamentInfoWidget
- `backend/tests/Feature/Admin/Widgets/DashboardWidgetsTest.php` — new: 6 tests
- `docs/plans/admin/v2/07-dashboard-widgets.md` — new: Step 1.7 spec
