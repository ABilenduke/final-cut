# Plan 06 — Copy-week scheduling tool

## Goal

Replicate a whole week's schedule (every non-cancelled showtime across movies and
auditoriums, optionally filtered to one location) into a target week — the actual gap left
by BulkCreateShowtimes, which already covers recurring creation for a single movie+auditorium.

## Design

Two service methods on `ShowtimeService` (write boundary), one page mirroring the
`BulkCreateShowtimes` two-phase preview/commit pattern:

- **`buildWeekCopyPlan(sourceWeekStart, targetWeekStart, ?locationId)`** — pure read. Selects
  non-cancelled showtimes in `[sourceWeekStart 00:00, +7d)` (app-timezone window, documented),
  partitions out movies whose runtime went NULL, and shifts each remaining row by the week
  delta **in venue-local wall-clock time**: convert `start_time` to the location's IANA
  timezone, `addDays(delta)` (Carbon preserves local clock across DST), convert back. End
  times are recomputed via `computeEndTime` (runtime may have changed since the source week).
  Conflicts against the target week come from `detectConflictsForBatch`, grouped per
  auditorium. A uniform wall-clock shift preserves the source week's non-overlap except in
  pathological DST edge cases — the DB EXCLUDE constraint stays the final guard.
- **`copyWeek(rows, ?actor)`** — single transaction creating every included row; activity
  `showtime.created` with `via: copy_week`; Postgres exclusion violations translate to
  `ShowtimeConflictException` (whole batch rolls back, page surfaces the TOCTOU message).

**`CopyWeekShowtimes` page** (`/copy-week`, `showtimes.create`-gated, linked from the list
header next to Bulk create): pick a date in the source week + a date in the target week
(normalized to Monday) + optional location. Preview lists copyable rows and auto-skips
conflicting ones (same policy as BulkCreateShowtimes — conflicts cannot be force-included,
the DB would reject them; the plan's "per-row skip/include" is satisfied by the skip report).
Commit creates the copyable subset.

## Tests (`tests/Feature/Admin/Pages/CopyWeekShowtimesTest.php`)

DST week shift preserves 19:00 venue-local (source EST week → target EDT week, UTC instant
differs by 7d−1h); cancelled/out-of-window rows excluded; location filter; NULL-runtime rows
skipped + reported; target-week conflicts flagged in the plan; `copyWeek` writes
`via: copy_week` activity with actor; page permission gating; end-to-end clean copy; commit
skips conflicting rows.
