# Progress: booking_seats partial-unique-index + Postgres triggers

Plan: [`docs/superpowers/plans/2026-06-05-booking-seats-unique-index.md`](../superpowers/plans/2026-06-05-booking-seats-unique-index.md)
Branch: `feat/booking-seats-unique-index` (off `main`). One of three post-P1 hardening follow-ups (with `feat/promo-per-user-limit`, `chore/p2-hardening-batch`).

## Step 1: Migration + triggers + partial index
**Status:** ✅ Complete
**Completed:** 2026-06-05

### Work Done
- [2026-06-05] Additive migration `2026_06_05_000000_add_booking_seats_occupancy_guard.php` — the one documented exception to the pre-launch edit-in-place rule. Adds `occupies_seat` boolean NOT NULL default false; IMMUTABLE `fc_status_occupies(text)` helper (COALESCE → false for NULL/unknown); BEFORE INSERT/UPDATE OF booking_id trigger (derive from parent status); AFTER UPDATE OF status trigger (IS DISTINCT FROM guard, re-syncs child rows on event-bypassing writes); partial `UNIQUE INDEX booking_seats_one_occupant_per_seat (showtime_id, seat_id) WHERE occupies_seat AND seat_id IS NOT NULL`. DROP IF EXISTS before each CREATE for re-runnability; full teardown in `down()`.
- [2026-06-05] `BookingSeat` model: `occupies_seat => boolean` cast (read-only; NOT in `#[Fillable]` — triggers own it).
- [2026-06-05] `BookingStatus::occupyingStatuses()` docblock: DB-coupling cross-reference to `fc_status_occupies()`.
- [2026-06-05] `SeatOccupancyTriggerTest` (15 cases): all-5-status insert derivation, both-direction Builder re-sync, refund_pending no-op, index rejection (savepoint/23505), cancelled-seat re-bookable, NULL-seat coexistence, un-cancel-collision loud failure, COALESCE NULL safety, PHP↔SQL parity for all 5 cases, regeneration (nullOnDelete) survival.

### Decisions
- [2026-06-05] **Catch does ZERO DB reads.** The design workflow empirically confirmed (live `final_cut_test`) that a SELECT after a 23505 inside the still-open caller transaction throws SQLSTATE 25P02 ("transaction is aborted"). The original "recompute availability in the catch" would have masked the 409 with a 500. `reserveSeats` throws `new SeatConflictException($seatIds)` directly.
- [2026-06-05] **Constraint-specific catch.** Branch on `$e->index === 'booking_seats_one_occupant_per_seat'`; re-throw any other unique violation (booking_id+seat_id, stripe PI, idempotency) so they keep their own handling.

## Step 2: reserveSeats 23505 → SeatConflictException translation
**Status:** ✅ Complete
**Completed:** 2026-06-05

### Work Done
- [2026-06-05] `SeatAvailabilityService::reserveSeats` — import `UniqueConstraintViolationException` + wrap the `BookingSeat::create` loop in try/catch in ONE edit (Pint gotcha). Constraint-specific, no-DB-read catch.
- [2026-06-05] `SeatReservationConcurrencyTest` (4 cases): pre-check regression (zero rows written), index→SeatConflictException translation, **no-poison-in-outer-transaction proof** (SeatConflictException not 25P02), non-occupancy unique violation re-thrown (exact index `booking_seats_booking_id_seat_id_unique`).
- [2026-06-05] `SeatDoubleBookTest` (HTTP, 2 cases): sequential double-book → 409 with seat id; 3DS-window seat race caught pre-capture → 409, no charge, no refund, no orphan (the post-capture A→C refund path is covered by the unit translation test + existing 3DS refund-on-409 tests).

## Step 3: Regression gate
**Status:** ✅ Complete
**Completed:** 2026-06-05

### Work Done
- [2026-06-05] `make fresh` / `migrate:fresh --seed` survives (BookingSeeder never seeds two occupying bookings on one seat).
- [2026-06-05] Full backend suite green after two fixes: `BookingSeatTest` "allows multiple booking_seats…" reframed to terminal-snapshot-coexists-with-occupying (the old test asserted the now-forbidden two-occupying-rows behavior); `ShowtimeControllerTest` "different showtime" given explicit non-overlapping start_times (pre-existing `showtimes_no_overlap` flake surfaced by the full-suite run, unrelated to this change).
- [2026-06-05] Pint clean, PHPStan clean (no `env()` introduced).

### Files Changed
- `backend/database/migrations/2026_06_05_000000_add_booking_seats_occupancy_guard.php` — new additive migration
- `backend/app/Services/SeatAvailabilityService.php` — constraint-specific 23505 catch
- `backend/app/Models/BookingSeat.php` — `occupies_seat` boolean cast
- `backend/app/Enums/BookingStatus.php` — DB-coupling docblock
- `backend/tests/Feature/Booking/SeatOccupancyTriggerTest.php` — new (15)
- `backend/tests/Feature/Booking/SeatReservationConcurrencyTest.php` — new (4)
- `backend/tests/Feature/Api/SeatDoubleBookTest.php` — new (2)
- `backend/tests/Unit/Models/BookingSeatTest.php` — reframed history test
- `backend/tests/Feature/Api/ShowtimeControllerTest.php` — deterministic non-overlapping showtimes (flake fix)
