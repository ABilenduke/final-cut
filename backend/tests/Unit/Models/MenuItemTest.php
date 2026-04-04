<?php

use App\Enums\MenuCategory;
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

it('defaults available to true', function () {
    $item = MenuItem::factory()->create();
    expect($item->available)->toBeTrue();
});
