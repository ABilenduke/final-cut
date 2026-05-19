<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

// ── (a) returns full cross-location item set ─────────────────────────────────

test('GET /api/food-menu returns all available items across all locations', function () {
    $locationA = Location::factory()->create();
    $locationB = Location::factory()->create();

    MenuItem::factory()->forLocation($locationA)->create(['name' => 'Popcorn A', 'category' => 'popcorn']);
    MenuItem::factory()->forLocation($locationB)->create(['name' => 'Drink B', 'category' => 'drinks']);
    // Item attached to both
    $both = MenuItem::factory()->create(['name' => 'Combo Both', 'category' => 'combos']);
    $locationA->menuItems()->attach($both->id);
    $locationB->menuItems()->attach($both->id);

    $response = getJson('/api/food-menu');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'price', 'category', 'imageUrl', 'allergens', 'dietary', 'available_at']]]);

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Popcorn A')
        ->and($names)->toContain('Drink B')
        ->and($names)->toContain('Combo Both');
});

// ── (b) available_at correctly reflects pivot rows ────────────────────────────

test('available_at contains the slugs of all locations the item is attached to', function () {
    $locationA = Location::factory()->create(['slug' => 'downtown']);
    $locationB = Location::factory()->create(['slug' => 'uptown']);

    $item = MenuItem::factory()->create(['name' => 'Both Venues', 'category' => 'popcorn']);
    $locationA->menuItems()->attach($item->id);
    $locationB->menuItems()->attach($item->id);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $found = collect($response->json('data'))->firstWhere('name', 'Both Venues');

    expect($found)->not->toBeNull()
        ->and($found['available_at'])->toContain('downtown')
        ->and($found['available_at'])->toContain('uptown')
        ->and($found['available_at'])->toHaveCount(2);
});

test('available_at contains only the location the item is attached to', function () {
    $locationA = Location::factory()->create(['slug' => 'downtown']);
    $locationB = Location::factory()->create(['slug' => 'uptown']);

    $item = MenuItem::factory()->forLocation($locationA)->create(['name' => 'Downtown Only', 'category' => 'popcorn']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $found = collect($response->json('data'))->firstWhere('name', 'Downtown Only');

    expect($found['available_at'])->toContain('downtown')
        ->and($found['available_at'])->not->toContain('uptown')
        ->and($found['available_at'])->toHaveCount(1);
});

// ── (c) item with empty pivot returns available_at: [] ────────────────────────

test('item with no location pivot rows returns available_at as empty array', function () {
    Location::factory()->create();

    MenuItem::factory()->create(['name' => 'Orphan Item', 'category' => 'snacks']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $found = collect($response->json('data'))->firstWhere('name', 'Orphan Item');

    expect($found)->not->toBeNull()
        ->and($found['available_at'])->toBeArray()
        ->and($found['available_at'])->toBeEmpty();
});

// ── (d) unavailable items excluded ───────────────────────────────────────────

test('items with unavailable_at set are excluded from the response', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['name' => 'Available Item', 'category' => 'popcorn']);
    MenuItem::factory()->unavailable()->forLocation($location)->create(['name' => 'Unavailable Item', 'category' => 'popcorn']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Available Item')
        ->and($names)->not->toContain('Unavailable Item');
});

test('items without any location attachment but globally unavailable are excluded', function () {
    MenuItem::factory()->unavailable()->create(['name' => 'Global Kill', 'category' => 'snacks']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->not->toContain('Global Kill');
});

// ── (e) cache invalidation ────────────────────────────────────────────────────

test('cache is invalidated when a MenuItem is saved', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location)->create(['name' => 'Original Name', 'category' => 'popcorn']);

    // Prime the cache
    getJson('/api/food-menu')->assertOk();

    // Mutate and save
    $item->name = 'Updated Name';
    $item->save();

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Updated Name')
        ->and($names)->not->toContain('Original Name');
});

test('cache is invalidated when a MenuItem is deleted', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location)->create(['name' => 'To Be Deleted', 'category' => 'popcorn']);

    // Prime the cache
    getJson('/api/food-menu')->assertOk();

    // Verify item present
    $before = collect(getJson('/api/food-menu')->json('data'))->pluck('name')->all();
    expect($before)->toContain('To Be Deleted');

    // Delete
    $item->delete();

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->not->toContain('To Be Deleted');
});

test('cache is invalidated when a location pivot row is attached', function () {
    $location = Location::factory()->create(['slug' => 'uptown']);
    $item = MenuItem::factory()->create(['name' => 'Pivot Attach Item', 'category' => 'snacks']);

    // Prime cache — item exists but with empty available_at
    $before = getJson('/api/food-menu')->json('data');
    $beforeFound = collect($before)->firstWhere('name', 'Pivot Attach Item');
    expect($beforeFound['available_at'])->toBeEmpty();

    // Attach
    $location->menuItems()->attach($item->id);

    $after = getJson('/api/food-menu')->json('data');
    $afterFound = collect($after)->firstWhere('name', 'Pivot Attach Item');
    expect($afterFound['available_at'])->toContain('uptown');
});

test('cache is invalidated when a location pivot row is detached', function () {
    $location = Location::factory()->create(['slug' => 'downtown']);
    $item = MenuItem::factory()->forLocation($location)->create(['name' => 'Pivot Detach Item', 'category' => 'drinks']);

    // Prime cache
    $before = getJson('/api/food-menu')->json('data');
    $beforeFound = collect($before)->firstWhere('name', 'Pivot Detach Item');
    expect($beforeFound['available_at'])->toContain('downtown');

    // Detach
    $location->menuItems()->detach($item->id);

    $after = getJson('/api/food-menu')->json('data');
    $afterFound = collect($after)->firstWhere('name', 'Pivot Detach Item');
    expect($afterFound['available_at'])->toBeEmpty();
});

// ── (f) response envelope ─────────────────────────────────────────────────────

test('response envelope is { data: [...] } with flat array of items', function () {
    $location = Location::factory()->create();
    MenuItem::factory()->forLocation($location)->count(3)->create(['category' => 'popcorn']);

    $response = getJson('/api/food-menu');

    $response->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonIsArray('data');

    expect($response->json('data'))->toHaveCount(3);
});

test('endpoint is public and requires no authentication', function () {
    $response = getJson('/api/food-menu');
    $response->assertOk();
});

test('returns empty data array when no menu items exist', function () {
    $response = getJson('/api/food-menu');

    $response->assertOk();
    expect($response->json('data'))->toBeArray()->toBeEmpty();
});

// ── ordering ──────────────────────────────────────────────────────────────────

test('items are ordered by category then name', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create(['category' => 'popcorn', 'name' => 'Zesty']);
    MenuItem::factory()->forLocation($location)->create(['category' => 'drinks', 'name' => 'Apple Juice']);
    MenuItem::factory()->forLocation($location)->create(['category' => 'popcorn', 'name' => 'Aged Cheddar']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $items = $response->json('data');
    $names = array_column($items, 'name');

    // drinks comes before popcorn alphabetically; within popcorn: Aged Cheddar before Zesty
    $drinksIdx = array_search('Apple Juice', $names);
    $agedIdx = array_search('Aged Cheddar', $names);
    $zestyIdx = array_search('Zesty', $names);

    expect($drinksIdx)->toBeLessThan($agedIdx)
        ->and($agedIdx)->toBeLessThan($zestyIdx);
});

// ── price is integer cents ─────────────────────────────────────────────────────

test('price field is returned as integer cents', function () {
    $location = Location::factory()->create();
    MenuItem::factory()->forLocation($location)->create(['price' => 1499, 'category' => 'popcorn', 'name' => 'Cents Test']);

    $response = getJson('/api/food-menu');
    $response->assertOk();

    $found = collect($response->json('data'))->firstWhere('name', 'Cents Test');
    expect($found['price'])->toBe(1499)
        ->and($found['price'])->toBeInt();
});
