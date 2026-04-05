<?php

use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\Seat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a seat with UUID primary key', function () {
    $seat = Seat::factory()->create();
    expect($seat->id)->toBeString();
    expect(Str::isUuid($seat->id))->toBeTrue();
});

it('belongs to an auditorium', function () {
    $seat = Seat::factory()->create();
    expect($seat->auditorium)->toBeInstanceOf(Auditorium::class);
});

it('casts type to SeatType enum', function () {
    $seat = Seat::factory()->create(['type' => 'premium']);
    expect($seat->type)->toBe(SeatType::Premium);
});

it('enforces unique (auditorium_id, row, number) constraint', function () {
    $auditorium = Auditorium::factory()->create();
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1-dup',
    ]);
})->throws(QueryException::class);

it('enforces unique (auditorium_id, label) constraint', function () {
    $auditorium = Auditorium::factory()->create();
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'B',
        'number' => 2,
        'label' => 'A1',
    ]);
})->throws(QueryException::class);

it('cascades delete from auditorium to seats', function () {
    $auditorium = Auditorium::factory()->create();
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);
    $auditorium->delete();
    expect(Seat::count())->toBe(0);
});
