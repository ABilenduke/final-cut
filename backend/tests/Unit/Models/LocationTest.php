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

it('casts hours to array', function () {
    $hours = [
        'monday' => ['open' => '12:00', 'close' => '23:00'],
        'tuesday' => ['open' => '12:00', 'close' => '23:00'],
        'wednesday' => ['open' => '12:00', 'close' => '23:00'],
        'thursday' => ['open' => '12:00', 'close' => '23:00'],
        'friday' => ['open' => '12:00', 'close' => '00:30'],
        'saturday' => ['open' => '10:00', 'close' => '00:30'],
        'sunday' => ['open' => '10:00', 'close' => '23:00'],
    ];

    $location = Location::factory()->create(['hours' => $hours]);

    // Re-fetch from DB to confirm the cast round-trips through JSON storage
    $fresh = Location::find($location->id);
    expect($fresh->hours)->toBeArray()
        ->and($fresh->hours['monday'])->toBe(['open' => '12:00', 'close' => '23:00'])
        ->and($fresh->hours['saturday'])->toBe(['open' => '10:00', 'close' => '00:30']);
});

it('hours is null when not set', function () {
    $location = Location::factory()->create(['hours' => null]);
    $fresh = Location::find($location->id);
    expect($fresh->hours)->toBeNull();
});

it('factory produces valid hours by default', function () {
    $location = Location::factory()->create();
    expect($location->hours)->toBeArray()
        ->and($location->hours)->toHaveKey('monday')
        ->and($location->hours)->toHaveKey('sunday');
});
