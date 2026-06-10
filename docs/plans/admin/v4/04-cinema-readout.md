# Plan 04 (v4) — Live cinema readout

**Step:** 4.4 · **Status:** ✅ Complete

## Goal

Feed the what's-on Bridge Console telemetry panel (`BridgeCinemaReadout`)
from real data instead of the v1 static stub ("Members tonight: 2 perks
live · Valet: Open").

## Design

- **`GET /api/cinema-readout`** (`CinemaReadoutController`, 5-minute plain
  cache — time-derived telemetry, no editorial version key):
  - `screeningsToday` — today's non-cancelled showtime count (day window
    in the venue-default timezone, the `TodayKpisWidget` convention).
  - `doorsOpen` — earliest opening hour among venues for today's weekday,
    from the admin-managed `locations.hours` JSON (structurally guarded).
  - `lateShowing` — today's last showtime: `{time, auditorium}`.
  - `seatsLeftTonight` — today's slate capacity minus
    `booking_seats.occupies_seat` rows (the occupancy guard's own flag).
- **Frontend**: the component fetches with key `cinema-readout` and maps
  to stat cells (null-valued stats are omitted, not rendered blank); an
  explicit `stats` prop still overrides; the old static values remain
  only as the unreachable-API fallback.

The honest stat set replaces the un-derivable stub lines ("Members
tonight", "Valet") — no data source exists for those; inventing numbers
is what this step removes.

## Tests

- `backend/tests/Feature/Api/CinemaReadoutTest.php` — derivation
  (cancelled/tomorrow excluded, late showing, seats-left math), 5-minute
  cache, empty-schedule nulls.
- `frontend/tests/components/calendar/BridgeCinemaReadout.test.ts` — live
  stats render (stub copy suppressed), null stats omitted, fallback on
  unreachable API.
