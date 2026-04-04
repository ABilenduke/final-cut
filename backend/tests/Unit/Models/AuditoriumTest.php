<?php

use App\Models\Auditorium;
use App\Models\Seat;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates an auditorium with UUID primary key', function () {
    $auditorium = Auditorium::factory()->create();
    expect($auditorium->id)->toBeString();
    expect(Str::isUuid($auditorium->id))->toBeTrue();
});

it('has many seats', function () {
    $auditorium = Auditorium::factory()->create();
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
    ]);
    expect($auditorium->seats)->toHaveCount(1);
    expect($auditorium->seats()->first())->toBeInstanceOf(Seat::class);
});

it('has showtimes relationship', function () {
    $auditorium = Auditorium::factory()->create();
    expect($auditorium->showtimes())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('enforces unique name constraint', function () {
    Auditorium::factory()->create(['name' => 'IMAX']);
    Auditorium::factory()->create(['name' => 'IMAX']);
})->throws(\Illuminate\Database\QueryException::class);
