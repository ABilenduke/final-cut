# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file also accrues entries on the refund-stack branches (#66→#68).
     If this section conflicts on merge, keep all step sections — they are disjoint. -->

## Step 1.4: Per-showtime seat-occupancy map
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 7 tests first (`ShowtimeOccupancyTest`), then implementation. Full backend
  suite on this branch: **1131 passed** (main baseline 1124 + 7). PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-showtime-occupancy` is **off main** (independent of the
  refund stack #66→#68) so review of the stack doesn't block ops features.

### Decisions
- [2026-06-09] Dedicated custom resource page (`/{record}/occupancy`) following the
  `VisualEditor` precedent rather than embedding a grid in `ViewShowtime`'s schema — state in
  public Livewire properties (`$seatStates`, `$counts`) so tests `assertSet` data instead of
  scraping markup. Step 1.5's walk-up seat picker will grow from this grid.
- [2026-06-09] Seat state derives from `booking_seats.occupies_seat` (the occupancy guard) —
  the map can never disagree with checkout/refund logic. Sold/held/refund-pending are split
  visually: sold = `#550000` fill per the token-mapping rule, held = steel, pending = gold.
- [2026-06-09] List column `occupied / capacity` uses a filtered
  `withCount(['bookingSeats as occupied_seats_count'])` on `getEloquentQuery()` (new
  `Showtime::bookingSeats()` hasMany over the denormalized `booking_seats.showtime_id`).

### Files Changed
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ShowtimeOccupancy.php` — new page
- `backend/resources/views/filament/resources/showtime-resource/pages/occupancy.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource.php` — occupancy route + column + withCount
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ViewShowtime.php` — occupancy_map action
- `backend/app/Models/Showtime.php` — bookingSeats() relation
- `backend/tests/Feature/Admin/Pages/ShowtimeOccupancyTest.php` — new: 7 tests
- `docs/plans/admin/v2/04-showtime-occupancy.md` — new: Step 1.4 spec
