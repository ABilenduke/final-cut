<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Support\Str;

it('creates a booking seat with UUID primary key', function () {
    $bs = BookingSeat::factory()->create();
    expect($bs->id)->toBeString();
    expect(Str::isUuid($bs->id))->toBeTrue();
});

it('belongs to a booking', function () {
    $bs = BookingSeat::factory()->create();
    expect($bs->booking)->toBeInstanceOf(Booking::class);
});

it('belongs to a seat', function () {
    $bs = BookingSeat::factory()->create();
    expect($bs->seat)->toBeInstanceOf(Seat::class);
});

it('allows multiple booking_seats for the same showtime and seat across booking history', function () {
    // booking_seats is a price/section snapshot: a seat accrues one row per
    // booking that ever held it. The partial unique index
    // (booking_seats_one_occupant_per_seat) forbids only more than one
    // *occupying* row at a time (see SeatOccupancyTriggerTest) — a terminal
    // cancelled snapshot coexists with a new occupying booking on the same seat.
    $showtime = Showtime::factory()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $showtime->auditorium_id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);

    $cancelled = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Cancelled,
    ]);
    $bs1 = BookingSeat::factory()->create([
        'booking_id' => $cancelled->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $confirmed = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $bs2 = BookingSeat::factory()->create([
        'booking_id' => $confirmed->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    expect($bs1->id)->not->toBe($bs2->id);
});

it('stores price as integer (cents)', function () {
    $bs = BookingSeat::factory()->create(['price' => 1800]);
    expect($bs->price)->toBe(1800);
});
