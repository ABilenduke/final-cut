<?php

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

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

it('enforces unique (showtime_id, seat_id) constraint', function () {
    $showtime = Showtime::factory()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $showtime->auditorium_id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);

    BookingSeat::factory()->create([
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);
    BookingSeat::factory()->create([
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('stores price as integer (cents)', function () {
    $bs = BookingSeat::factory()->create(['price' => 1800]);
    expect($bs->price)->toBe(1800);
});
