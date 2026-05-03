<?php

use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Models\Location;
use App\Models\MenuItem;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admin can see the menu item list', function (): void {
    $items = MenuItem::factory()->count(3)->create();

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords($items);
});

test('admin can create a menu item via default Eloquent persistence', function (): void {
    $location = Location::factory()->create();

    Livewire::test(CreateMenuItem::class)
        ->set('data.name', 'Large Popcorn')
        ->set('data.category', 'popcorn')
        ->set('data.description', 'Buttered')
        ->set('data.price', 899)
        ->set('data.allergens', ['dairy'])
        ->set('data.dietary', [])
        ->set('data.unavailable_at', true) // toggle ON = available
        ->set('data.locations', [$location->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MenuItem::count())->toBe(1);
    $item = MenuItem::first();
    expect($item->name)->toBe('Large Popcorn')
        ->and($item->category->value)->toBe('popcorn')
        ->and($item->price)->toBe(899)
        ->and($item->allergens)->toBe(['dairy'])
        ->and($item->unavailable_at)->toBeNull();
});

test('availability toggle OFF stamps unavailable_at', function (): void {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->create(['unavailable_at' => null]);
    $item->locations()->attach($location);

    Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
        ->set('data.unavailable_at', false) // toggle OFF = unavailable
        ->set('data.locations', [$location->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh();
    expect($item->unavailable_at)->not->toBeNull();
});

test('availability toggle ON clears unavailable_at', function (): void {
    $location = Location::factory()->create();
    $item = MenuItem::factory()->unavailable()->create();
    $item->locations()->attach($location);

    Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
        ->set('data.unavailable_at', true) // toggle ON = available
        ->set('data.locations', [$location->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh();
    expect($item->unavailable_at)->toBeNull();
});

test('menu item can be attached to one or more locations', function (): void {
    $locationA = Location::factory()->create();
    $locationB = Location::factory()->create();
    $item = MenuItem::factory()->create();

    Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
        ->set('data.locations', [$locationA->id, $locationB->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh();
    expect($item->locations)->toHaveCount(2);
});

test('admin can delete a menu item', function (): void {
    $item = MenuItem::factory()->create();

    Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
        ->callAction('delete');

    expect(MenuItem::find($item->id))->toBeNull();
});
