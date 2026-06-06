<?php

use App\Enums\BookingStatus;
use App\Exceptions\SeatConflictException;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\SeatAvailabilityService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Service-level coverage for the seat double-book backstop in
 * SeatAvailabilityService::reserveSeats: the checkAvailability pre-check, the
 * partial-index 23505 → SeatConflictException translation, and the critical
 * "catch does no DB read" property (a SELECT after the 23505 would throw 25P02).
 */
beforeEach(function (): void {
    $this->auditorium = Auditorium::factory()->create();
    $this->showtime = Showtime::factory()->create(['auditorium_id' => $this->auditorium->id]);
    $this->seat = Seat::factory()->create(['auditorium_id' => $this->auditorium->id]);
});

/** Seed an occupying winner booking + its booking_seats row on $this->seat. */
function seedOccupyingWinner(string $showtimeId, string $seatId): void
{
    $winner = Booking::factory()->create(['showtime_id' => $showtimeId, 'status' => BookingStatus::Confirmed]);
    BookingSeat::factory()->create([
        'booking_id' => $winner->id,
        'showtime_id' => $showtimeId,
        'seat_id' => $seatId,
    ]);
}

test('an existing occupying seat makes reserveSeats throw via the pre-check and write zero rows', function (): void {
    seedOccupyingWinner($this->showtime->id, $this->seat->id);

    $loser = Booking::factory()->create(['showtime_id' => $this->showtime->id, 'status' => BookingStatus::Confirmed]);
    $service = app(SeatAvailabilityService::class);

    expect(fn () => $service->reserveSeats($this->showtime, [$this->seat->id], $loser))
        ->toThrow(SeatConflictException::class);

    // The real checkAvailability pre-check caught it before any INSERT.
    expect(BookingSeat::where('booking_id', $loser->id)->count())->toBe(0);
});

test('the partial index 23505 is translated to SeatConflictException when the pre-check is bypassed', function (): void {
    seedOccupyingWinner($this->showtime->id, $this->seat->id);

    $loser = Booking::factory()->create(['showtime_id' => $this->showtime->id, 'status' => BookingStatus::Confirmed]);

    // Force the pre-check to pass so the INDEX (not checkAvailability) is what
    // trips — proving the catch path translates the DB violation.
    $service = Mockery::mock(SeatAvailabilityService::class)->makePartial();
    $service->shouldReceive('checkAvailability')->andReturn([]);

    expect(fn () => $service->reserveSeats($this->showtime, [$this->seat->id], $loser))
        ->toThrow(SeatConflictException::class);
});

test('reserveSeats inside an outer transaction yields SeatConflictException, not a 25P02 abort', function (): void {
    // Mirrors the production Phase A / Phase C wrap. If the catch did any DB
    // read after the 23505, the aborted transaction would surface a QueryException
    // (SQLSTATE 25P02) instead of the domain SeatConflictException.
    seedOccupyingWinner($this->showtime->id, $this->seat->id);

    $loser = Booking::factory()->create(['showtime_id' => $this->showtime->id, 'status' => BookingStatus::Confirmed]);

    $service = Mockery::mock(SeatAvailabilityService::class)->makePartial();
    $service->shouldReceive('checkAvailability')->andReturn([]);

    $thrown = null;
    try {
        DB::transaction(fn () => $service->reserveSeats($this->showtime, [$this->seat->id], $loser));
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(SeatConflictException::class);
    expect($thrown)->not->toBeInstanceOf(QueryException::class);
});

test('a non-occupancy unique violation is re-thrown, not mis-translated to SeatConflictException', function (): void {
    // A cancelled booking already holding a snapshot row for this seat: its row
    // is NOT in the occupancy index (occupies_seat=false), so re-inserting the
    // SAME (booking_id, seat_id) trips the booking_id+seat_id unique — a
    // DIFFERENT constraint that must keep its own meaning.
    $booking = Booking::factory()->create(['showtime_id' => $this->showtime->id, 'status' => BookingStatus::Cancelled]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $this->showtime->id,
        'seat_id' => $this->seat->id,
    ]);

    $service = Mockery::mock(SeatAvailabilityService::class)->makePartial();
    $service->shouldReceive('checkAvailability')->andReturn([]);

    $thrown = null;
    try {
        DB::transaction(fn () => $service->reserveSeats($this->showtime, [$this->seat->id], $booking));
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(UniqueConstraintViolationException::class);
    expect($thrown)->not->toBeInstanceOf(SeatConflictException::class);
    expect($thrown->index)->toBe('booking_seats_booking_id_seat_id_unique');
});
