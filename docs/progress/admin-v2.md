# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on several parallel branches (#66→#68 refund stack,
     #69 occupancy, this one). If it conflicts on merge, keep all step sections — disjoint. -->

## Step 1.6: Copy-week scheduling tool
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 8 tests first (`CopyWeekShowtimesTest` — service plan/write + page), then
  implementation. Full backend suite: **1132 passed**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-copy-week` off main, independent of all open PRs.

### Decisions
- [2026-06-09] Wall-clock shift via tz-aware `addDays()` (Carbon preserves the local clock
  across DST — verified by a test straddling US spring-forward 2027: the 19:00 EST show lands
  at 19:00 EDT, a UTC delta of 7d−1h). Week window `[source 00:00, +7d)` is interpreted in
  the app timezone (documented on the method); venue-local week boundaries deferred as a
  non-issue for current US-only locations.
- [2026-06-09] Plan-doc deviation: conflicts are auto-skipped with a per-row report (the
  BulkCreateShowtimes policy) instead of literal per-row include checkboxes — a conflicting
  row can never be force-included anyway (the EXCLUDE constraint would reject the batch).
- [2026-06-09] `copyWeek()` reuses `EVENT_CREATED` with `via: copy_week` properties (matches
  bulk's `via: bulk`); end times recomputed at copy time so runtime/cleanup changes since the
  source week propagate.

### Files Changed
- `backend/app/Services/ShowtimeService.php` — `buildWeekCopyPlan()` + `copyWeek()`
- `backend/app/Filament/Resources/ShowtimeResource/Pages/CopyWeekShowtimes.php` — new page
- `backend/resources/views/filament/resources/showtime-resource/pages/copy-week.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource.php` — copy_week route
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ListShowtimes.php` — header action
- `backend/tests/Feature/Admin/Pages/CopyWeekShowtimesTest.php` — new: 8 tests
- `docs/plans/admin/v2/06-copy-week.md` — new: Step 1.6 spec
