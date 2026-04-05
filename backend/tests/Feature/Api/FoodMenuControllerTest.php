<?php

use App\Models\Location;
use App\Models\MenuItem;

use function Pest\Laravel\getJson;

function foodMenuUrl(Location $location): string
{
    return "/api/locations/{$location->slug}/food-menu";
}

test('returns 200 with grouped menu items for a location', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['category' => 'popcorn', 'name' => 'Large Popcorn']);
    MenuItem::factory()->forLocation($location)->create(['category' => 'drinks', 'name' => 'Craft Beer']);

    $response = getJson(foodMenuUrl($location));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['popcorn', 'drinks']]);

    $data = $response->json('data');
    expect($data['popcorn'])->toHaveCount(1)
        ->and($data['drinks'])->toHaveCount(1);
});

test('returns only items attached to the requested location', function () {
    $locationA = Location::factory()->create();
    $locationB = Location::factory()->create();

    MenuItem::factory()->forLocation($locationA)->create(['name' => 'Popcorn A']);
    MenuItem::factory()->forLocation($locationB)->create(['name' => 'Popcorn B']);

    $response = getJson(foodMenuUrl($locationA));
    $response->assertOk();

    $names = collect($response->json('data'))
        ->flatten(1)
        ->pluck('name')
        ->all();

    expect($names)->toContain('Popcorn A')
        ->and($names)->not->toContain('Popcorn B');
});

test('returns pivot price_override when set, base price when null', function () {
    $location = Location::factory()->create();

    MenuItem::factory()
        ->forLocation($location, ['price_override' => 999])
        ->create(['price' => 599, 'category' => 'popcorn', 'name' => 'Override Item']);

    MenuItem::factory()
        ->forLocation($location)
        ->create(['price' => 799, 'category' => 'popcorn', 'name' => 'Base Price Item']);

    $response = getJson(foodMenuUrl($location));
    $response->assertOk();

    $items = collect($response->json('data.popcorn'));

    $overrideItem = $items->firstWhere('name', 'Override Item');
    $baseItem = $items->firstWhere('name', 'Base Price Item');

    expect($overrideItem['price'])->toBe(999)
        ->and($baseItem['price'])->toBe(799);
});

test('excludes items with global unavailable_at set', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['name' => 'Available']);
    MenuItem::factory()->unavailable()->forLocation($location)->create(['name' => 'Global Kill']);

    $response = getJson(foodMenuUrl($location));
    $response->assertOk();

    $names = collect($response->json('data'))
        ->flatten(1)
        ->pluck('name')
        ->all();

    expect($names)->toContain('Available')
        ->and($names)->not->toContain('Global Kill');
});

test('excludes items with location-level unavailable_at set', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['name' => 'Available']);
    MenuItem::factory()
        ->forLocation($location, ['unavailable_at' => now()])
        ->create(['name' => 'Location Unavailable']);

    $response = getJson(foodMenuUrl($location));
    $response->assertOk();

    $names = collect($response->json('data'))
        ->flatten(1)
        ->pluck('name')
        ->all();

    expect($names)->toContain('Available')
        ->and($names)->not->toContain('Location Unavailable');
});

test('filters by category query parameter', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['category' => 'popcorn', 'name' => 'Popcorn']);
    MenuItem::factory()->forLocation($location)->create(['category' => 'drinks', 'name' => 'Soda']);

    $response = getJson(foodMenuUrl($location) . '?category=popcorn');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveKey('popcorn')
        ->and($data)->not->toHaveKey('drinks');
});

test('returns 404 for nonexistent location slug', function () {
    getJson('/api/locations/nonexistent/food-menu')
        ->assertNotFound();
});

test('returns empty data when location has no menu items', function () {
    $location = Location::factory()->create();

    $response = getJson(foodMenuUrl($location));

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
