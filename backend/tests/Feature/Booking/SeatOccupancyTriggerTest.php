<?php

use App\Enums\BookingStatus;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Schema/trigger-level coverage for the booking_seats occupancy guard
 * (migration 2026_06_05_000000). No HTTP — these pin the database invariants
 * directly: the two triggers keep `occupies_seat` in lockstep with the parent
 * booking's status, and the partial unique index makes seat double-booking
 * impossible.
 */
beforeEach(function (): void {
    $this->auditorium = Auditorium::factory()->create();
    $this->showtime = Showtime::factory()->create(['auditorium_id' => $this->auditorium->id]);
    $this->seat = Seat::factory()->create(['auditorium_id' => $this->auditorium->id]);
});

/** Create an occupying-or-not booking_seats row and return the refreshed model. */
function makeBookingSeat(string $showtimeId, BookingStatus $status, ?string $seatId): BookingSeat
{
    $booking = Booking::factory()->create([
        'showtime_id' => $showtimeId,
        'status' => $status,
    ]);

    return BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtimeId,
        'seat_id' => $seatId,
    ])->refresh();
}

// ── Trigger 1: derive occupies_seat from parent status on insert ────────────
test('insert derives occupies_seat from the parent booking status', function (BookingStatus $status, bool $expected): void {
    $bs = makeBookingSeat($this->showtime->id, $status, $this->seat->id);

    expect($bs->occupies_seat)->toBe($expected);
})->with([
    'confirmed occupies' => [BookingStatus::Confirmed, true],
    'held occupies' => [BookingStatus::Held, true],
    'refund_pending occupies' => [BookingStatus::RefundPending, true],
    'cancelled does not occupy' => [BookingStatus::Cancelled, false],
    'refunded does not occupy' => [BookingStatus::Refunded, false],
]);

// ── Trigger 2: re-sync child rows when status changes via an event-bypassing write ──
test('a Builder status update from confirmed to refunded flips occupies_seat true to false', function (): void {
    $bs = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);
    expect($bs->occupies_seat)->toBeTrue();

    // Builder mass-update bypasses Eloquent model events — exactly the path the
    // AFTER UPDATE OF status trigger exists to cover.
    DB::table('bookings')->where('id', $bs->booking_id)->update(['status' => BookingStatus::Refunded->value]);

    expect($bs->refresh()->occupies_seat)->toBeFalse();
});

test('a Builder status update from cancelled to confirmed flips occupies_seat false to true', function (): void {
    $bs = makeBookingSeat($this->showtime->id, BookingStatus::Cancelled, $this->seat->id);
    expect($bs->occupies_seat)->toBeFalse();

    DB::table('bookings')->where('id', $bs->booking_id)->update(['status' => BookingStatus::Confirmed->value]);

    expect($bs->refresh()->occupies_seat)->toBeTrue();
});

test('confirmed to refund_pending is a no-op for occupies_seat (IS DISTINCT FROM guard)', function (): void {
    $bs = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);
    expect($bs->occupies_seat)->toBeTrue();

    DB::table('bookings')->where('id', $bs->booking_id)->update(['status' => BookingStatus::RefundPending->value]);

    // Both statuses occupy, so the guard skips the UPDATE — value stays true.
    expect($bs->refresh()->occupies_seat)->toBeTrue();
});

// ── Partial unique index: the authoritative double-book backstop ─────────────
test('the partial index rejects a second occupying row for the same showtime and seat', function (): void {
    makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);

    $secondBooking = Booking::factory()->create([
        'showtime_id' => $this->showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $sqlState = null;
    try {
        // Savepoint-wrap so the 23505 abort doesn't poison the RefreshDatabase
        // outer transaction (mirror ShowtimeConflictConcurrencyTest).
        DB::transaction(function () use ($secondBooking): void {
            BookingSeat::factory()->create([
                'booking_id' => $secondBooking->id,
                'showtime_id' => $this->showtime->id,
                'seat_id' => $this->seat->id,
            ]);
        });
    } catch (QueryException $e) {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();
    }

    expect($sqlState)->toBe('23505');
    expect(BookingSeat::where('showtime_id', $this->showtime->id)
        ->where('seat_id', $this->seat->id)
        ->where('occupies_seat', true)
        ->count())->toBe(1);
});

test('a seat freed by a cancelled booking is re-bookable', function (): void {
    makeBookingSeat($this->showtime->id, BookingStatus::Cancelled, $this->seat->id);

    // No throw — the cancelled row has occupies_seat=false and is not in the index.
    $second = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);

    expect($second->occupies_seat)->toBeTrue();
});

test('occupying rows with a null seat_id never collide on the index', function (): void {
    $a = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, null);
    $b = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, null);

    expect($a->occupies_seat)->toBeTrue();
    expect($b->occupies_seat)->toBeTrue();
    expect(BookingSeat::where('showtime_id', $this->showtime->id)
        ->whereNull('seat_id')
        ->count())->toBe(2);
});

test('un-cancelling a booking onto an already-occupied seat raises 23505 from trigger 2', function (): void {
    // Booking A holds the seat.
    makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);

    // Booking B is cancelled on the SAME seat — allowed (not in the index).
    $b = makeBookingSeat($this->showtime->id, BookingStatus::Cancelled, $this->seat->id);
    expect($b->occupies_seat)->toBeFalse();

    // Reactivating B (cancelled→confirmed via Builder) makes trigger 2 flip its
    // child row to occupies_seat=true, colliding with A on the partial index.
    // Documented intentional loud failure — no app path does this today.
    $sqlState = null;
    try {
        DB::transaction(function () use ($b): void {
            DB::table('bookings')->where('id', $b->booking_id)->update(['status' => BookingStatus::Confirmed->value]);
        });
    } catch (QueryException $e) {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();
    }

    expect($sqlState)->toBe('23505');
});

// ── COALESCE / NULL safety + PHP↔SQL parity ─────────────────────────────────
test('fc_status_occupies returns a well-defined boolean for null and unknown input', function (): void {
    // FK + NOT NULL status make a real orphan row unreachable, so we exercise
    // the COALESCE branch on the helper directly.
    expect((bool) DB::scalar('select fc_status_occupies(null)'))->toBeFalse();
    expect((bool) DB::scalar("select fc_status_occupies('not_a_status')"))->toBeFalse();
});

test('fc_status_occupies agrees with BookingStatus::occupyingStatuses for every case', function (): void {
    $occupying = BookingStatus::occupyingStatuses();

    foreach (BookingStatus::cases() as $case) {
        $sql = (bool) DB::scalar('select fc_status_occupies(?)', [$case->value]);
        $php = in_array($case, $occupying, true);

        expect($sql)->toBe($php, "fc_status_occupies('{$case->value}') drifted from BookingStatus::occupyingStatuses()");
    }
});

// ── Seat regeneration (nullOnDelete) survival ───────────────────────────────
test('deleting a seat nulls the snapshot row but leaves it surviving and out of the index', function (): void {
    $bs = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $this->seat->id);
    expect($bs->occupies_seat)->toBeTrue();

    // Regeneration deletes the seat; the booking_seats price/section snapshot
    // survives with seat_id nulled (nullOnDelete) and occupies_seat unchanged.
    $this->seat->delete();

    $bs->refresh();
    expect($bs->seat_id)->toBeNull();
    expect($bs->occupies_seat)->toBeTrue(); // exits the index via WHERE seat_id IS NOT NULL

    // A freshly regenerated seat in the same auditorium is bookable.
    $newSeat = Seat::factory()->create(['auditorium_id' => $this->auditorium->id]);
    $second = makeBookingSeat($this->showtime->id, BookingStatus::Confirmed, $newSeat->id);
    expect($second->occupies_seat)->toBeTrue();
});
