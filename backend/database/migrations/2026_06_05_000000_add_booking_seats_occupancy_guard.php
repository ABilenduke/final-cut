<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level backstop against seat double-booking — the seat-map analogue
 * of the `showtimes_no_overlap` EXCLUDE constraint (migration
 * 2026_04_24_000000). `SeatAvailabilityService::reserveSeats` pre-checks
 * availability under a `lockForUpdate` on the showtime, but a partial unique
 * index is the authoritative TOCTOU guard: two bookings can never both occupy
 * the same (showtime, seat).
 *
 * This is the ONE documented ADDITIVE exception to the pre-launch
 * "edit migrations in place" rule (it conceptually co-locates with the showtime
 * EXCLUDE constraint but ships as a new file because that migration already ran).
 *
 * A plain Postgres partial-unique-index predicate cannot reference another
 * table (`bookings.status`), so occupancy is denormalised onto a
 * `booking_seats.occupies_seat` boolean kept in sync by two triggers:
 *   - BEFORE INSERT/UPDATE OF booking_id on booking_seats (derive from parent
 *     booking's status at write time), and
 *   - AFTER UPDATE OF status on bookings (re-sync the child rows when a status
 *     write bypasses Eloquent model events — e.g. ShowtimeService::cancel's
 *     Builder mass-update).
 *
 * The status→boolean mapping MUST stay in lockstep with
 * App\Enums\BookingStatus::occupyingStatuses(); a parity test pins this.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Denormalised occupancy flag. NOT NULL (boolean()->default(false)
        //    is is_nullable=NO) so the partial index predicate is never silently
        //    bypassed by a NULL. Empty at migration time (migrations run before
        //    seeders), so no backfill is required.
        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->boolean('occupies_seat')->default(false)->after('price');
        });

        // 2) IMMUTABLE status→occupancy helper. COALESCE(p,'') so a missing/NULL
        //    parent status yields FALSE (not NULL) — keeps the NOT NULL column
        //    well-defined for orphan/NULL-status lookups.
        //    KEEP IN SYNC with App\Enums\BookingStatus::occupyingStatuses().
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fc_status_occupies(p_status text)
            RETURNS boolean
            LANGUAGE sql IMMUTABLE
            AS $$
                SELECT COALESCE(p_status, '') IN ('confirmed', 'held', 'refund_pending')
            $$
        SQL);

        // 3) Trigger 1 function — derive occupies_seat from the parent booking's
        //    status whenever a booking_seats row is inserted (or its booking_id
        //    is repointed).
        //
        //    PRECONDITION (documented, currently guaranteed): booking_seats rows
        //    are always inserted in the SAME transaction as, and AFTER, their
        //    parent booking, while the showtime is held under lockForUpdate
        //    (SeatAvailabilityService::reserveSeats). The unlocked SELECT below is
        //    therefore transaction-stable. If a future caller ever inserts
        //    booking_seats for a pre-existing booking under READ COMMITTED, add
        //    `FOR SHARE` to this SELECT.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fc_booking_seat_set_occupies()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_status text;
            BEGIN
                SELECT status INTO v_status FROM bookings WHERE id = NEW.booking_id;
                NEW.occupies_seat := fc_status_occupies(v_status);
                RETURN NEW;
            END;
            $$
        SQL);

        // 4) Trigger 2 function — re-sync child booking_seats when a booking's
        //    status changes via an event-bypassing write path.
        //
        //    The `IS DISTINCT FROM` guard makes confirmed↔held and
        //    confirmed→refund_pending NO-OPs (all three occupy), so
        //    ShowtimeService::cancel (confirmed→refund_pending Builder update)
        //    does not actually exercise the UPDATE body — this trigger is
        //    belt-and-suspenders for that path, and the real guarantee that
        //    occupies_seat stays correct under ANY future event-bypassing status
        //    write. A non-occupying→occupying flip (e.g. un-cancelling a booking)
        //    onto a seat another booking already holds raises 23505 from this
        //    UPDATE and aborts the parent bookings UPDATE — an intentional loud
        //    failure (no current app path does this; pinned by a test).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fc_booking_resync_seat_occupies()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF fc_status_occupies(NEW.status) IS DISTINCT FROM fc_status_occupies(OLD.status) THEN
                    UPDATE booking_seats
                       SET occupies_seat = fc_status_occupies(NEW.status)
                     WHERE booking_id = NEW.id;
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);

        // 5) Wire the triggers. DROP IF EXISTS before each CREATE so the
        //    migration is re-runnable.
        //    `OR UPDATE OF booking_id` is currently DEAD (no app path repoints a
        //    booking_seats.booking_id) — kept defensively, documented as such.
        DB::statement('DROP TRIGGER IF EXISTS trg_booking_seat_set_occupies ON booking_seats');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_booking_seat_set_occupies
            BEFORE INSERT OR UPDATE OF booking_id ON booking_seats
            FOR EACH ROW EXECUTE FUNCTION fc_booking_seat_set_occupies()
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_booking_resync_seat_occupies ON bookings');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_booking_resync_seat_occupies
            AFTER UPDATE OF status ON bookings
            FOR EACH ROW EXECUTE FUNCTION fc_booking_resync_seat_occupies()
        SQL);

        // 6) The authoritative TOCTOU backstop. Plain btree partial-unique —
        //    no btree_gist needed (that extension is only for the showtimes GiST
        //    EXCLUDE). The guard predicate is `occupies_seat AND seat_id IS NOT
        //    NULL`: occupies_seat alone is not sufficient — a regeneration-orphaned
        //    snapshot row keeps occupies_seat=true but has a NULL seat_id and must
        //    not occupy an index slot.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX booking_seats_one_occupant_per_seat
            ON booking_seats (showtime_id, seat_id)
            WHERE occupies_seat AND seat_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS booking_seats_one_occupant_per_seat');
        DB::statement('DROP TRIGGER IF EXISTS trg_booking_resync_seat_occupies ON bookings');
        DB::statement('DROP TRIGGER IF EXISTS trg_booking_seat_set_occupies ON booking_seats');
        DB::statement('DROP FUNCTION IF EXISTS fc_booking_resync_seat_occupies()');
        DB::statement('DROP FUNCTION IF EXISTS fc_booking_seat_set_occupies()');
        DB::statement('DROP FUNCTION IF EXISTS fc_status_occupies(text)');

        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->dropColumn('occupies_seat');
        });
    }
};
