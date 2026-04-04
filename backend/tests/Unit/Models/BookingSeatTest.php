<?php

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

it('allows multiple booking_seats for the same showtime and seat', function () {
    $showtime = Showtime::factory()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $showtime->auditorium_id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);

    $bs1 = BookingSeat::factory()->create([
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);
    $bs2 = BookingSeat::factory()->create([
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    expect($bs1->id)->not->toBe($bs2->id);
});

it('stores price as integer (cents)', function () {
    $bs = BookingSeat::factory()->create(['price' => 1800]);
    expect($bs->price)->toBe(1800);
});
