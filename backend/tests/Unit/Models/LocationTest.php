<?php

use App\Models\Auditorium;
use App\Models\Location;
use App\Models\MenuItem;
use App\Models\Showtime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a location with UUID primary key', function () {
    $location = Location::factory()->create();
    expect($location->id)->toBeString();
    expect(Str::isUuid($location->id))->toBeTrue();
});

it('has many auditoriums', function () {
    $location = Location::factory()->create();
    Auditorium::factory()->create(['location_id' => $location->id]);
    expect($location->auditoriums)->toHaveCount(1);
    expect($location->auditoriums->first())->toBeInstanceOf(Auditorium::class);
});

it('has many showtimes through auditoriums', function () {
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    Showtime::factory()->create(['auditorium_id' => $auditorium->id]);
    expect($location->showtimes)->toHaveCount(1);
    expect($location->showtimes->first())->toBeInstanceOf(Showtime::class);
});

it('uses slug as route key', function () {
    $location = Location::factory()->create(['slug' => 'downtown']);
    expect($location->getRouteKeyName())->toBe('slug');
});

it('has many menu items through pivot', function () {
    $location = Location::factory()->create();
    MenuItem::factory()->forLocation($location)->create();

    expect($location->menuItems)->toHaveCount(1)
        ->and($location->menuItems->first())->toBeInstanceOf(MenuItem::class);
});

it('returns only attached menu items, not all', function () {
    $location = Location::factory()->create();
    MenuItem::factory()->forLocation($location)->create();
    MenuItem::factory()->create(); // not attached

    expect($location->menuItems)->toHaveCount(1);
});

it('enforces unique slug constraint', function () {
    Location::factory()->create(['slug' => 'downtown']);
    Location::factory()->create(['slug' => 'downtown']);
})->throws(QueryException::class);
