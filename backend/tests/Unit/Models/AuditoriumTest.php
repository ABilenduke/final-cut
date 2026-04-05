<?php

use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

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
    expect($auditorium->showtimes())->toBeInstanceOf(HasMany::class);
});

it('enforces unique name constraint within a location', function () {
    $location = Location::factory()->create();
    Auditorium::factory()->create(['location_id' => $location->id, 'name' => 'IMAX']);
    Auditorium::factory()->create(['location_id' => $location->id, 'name' => 'IMAX']);
})->throws(QueryException::class);

it('allows same name across different locations', function () {
    $location1 = Location::factory()->create();
    $location2 = Location::factory()->create();
    Auditorium::factory()->create(['location_id' => $location1->id, 'name' => 'Screen 1']);
    Auditorium::factory()->create(['location_id' => $location2->id, 'name' => 'Screen 1']);
    expect(Auditorium::where('name', 'Screen 1')->count())->toBe(2);
});

it('belongs to a location', function () {
    $auditorium = Auditorium::factory()->create();
    expect($auditorium->location)->toBeInstanceOf(Location::class);
});
