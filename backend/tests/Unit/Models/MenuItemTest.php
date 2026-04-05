<?php

use App\Enums\MenuCategory;
use App\Models\Location;
use App\Models\MenuItem;
use Illuminate\Support\Str;

it('creates a menu item with UUID primary key', function () {
    $item = MenuItem::factory()->create();
    expect($item->id)->toBeString();
    expect(Str::isUuid($item->id))->toBeTrue();
});

it('casts category to MenuCategory enum', function () {
    $item = MenuItem::factory()->create(['category' => 'popcorn']);
    expect($item->category)->toBe(MenuCategory::Popcorn);
});

it('casts allergens to array', function () {
    $item = MenuItem::factory()->create(['allergens' => ['nuts', 'dairy']]);
    $item->refresh();
    expect($item->allergens)->toBeArray();
    expect($item->allergens)->toContain('nuts');
});

it('casts dietary to array', function () {
    $item = MenuItem::factory()->create(['dietary' => ['vegan']]);
    $item->refresh();
    expect($item->dietary)->toBeArray();
    expect($item->dietary)->toContain('vegan');
});

it('stores price as integer (cents)', function () {
    $item = MenuItem::factory()->create(['price' => 1299]);
    expect($item->price)->toBe(1299);
});

it('defaults available to true when unavailable_at is null', function () {
    $item = MenuItem::factory()->create();
    expect($item->available)->toBeTrue();
    expect($item->unavailable_at)->toBeNull();
});

it('returns available as false when unavailable_at is set', function () {
    $item = MenuItem::factory()->unavailable()->create();
    expect($item->available)->toBeFalse();
    expect($item->unavailable_at)->not->toBeNull();
});

it('scopes to available items', function () {
    MenuItem::factory()->create();
    MenuItem::factory()->unavailable()->create();

    expect(MenuItem::currentlyAvailable()->count())->toBe(1);
});

it('scopes to unavailable items', function () {
    MenuItem::factory()->create();
    MenuItem::factory()->unavailable()->create();

    expect(MenuItem::currentlyUnavailable()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Location Pivot Relationship
|--------------------------------------------------------------------------
*/

it('has a locations relationship', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location)->create();

    expect($item->locations)->toHaveCount(1)
        ->and($item->locations->first()->id)->toBe($location->id);
});

it('stores price_override on pivot', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location, ['price_override' => 999])->create(['price' => 599]);

    $pivotItem = $location->menuItems()->find($item->id);

    expect($pivotItem->pivot->price_override)->toBe(999);
});

it('resolves price from pivot override when set', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location, ['price_override' => 999])->create(['price' => 599]);

    $pivotItem = $location->menuItems()->find($item->id);

    expect($pivotItem->priceForLocation())->toBe(999);
});

it('resolves base price when pivot override is null', function () {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->forLocation($location)->create(['price' => 599]);

    $pivotItem = $location->menuItems()->find($item->id);

    expect($pivotItem->priceForLocation())->toBe(599);
});

it('scopes to available items at a specific location via relationship', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create();
    MenuItem::factory()->create(); // not attached — should be excluded

    expect($location->menuItems()->currentlyAvailable()->count())->toBe(1);
});

it('excludes globally unavailable items from location scope', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create();
    MenuItem::factory()->unavailable()->forLocation($location)->create();

    expect($location->menuItems()->currentlyAvailable()->count())->toBe(1);
});

it('excludes location-unavailable items from location scope', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location)->create();
    MenuItem::factory()->forLocation($location, ['unavailable_at' => now()])->create();

    expect($location->menuItems()->currentlyAvailable()->wherePivotNull('unavailable_at')->count())->toBe(1);
});

it('location-scoped query hydrates pivot attributes for priceForLocation', function () {
    $location = Location::factory()->create();

    MenuItem::factory()->forLocation($location, ['price_override' => 999])->create(['price' => 599]);

    $item = $location->menuItems()->currentlyAvailable()->wherePivotNull('unavailable_at')->first();

    expect($item->pivot)->not->toBeNull()
        ->and($item->pivot->price_override)->toBe(999)
        ->and($item->priceForLocation())->toBe(999);
});
