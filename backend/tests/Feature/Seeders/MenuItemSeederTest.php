<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\MenuItem;
use Database\Seeders\MenuItemSeeder;

it('seeds every menu item with a CDN-hosted concession webp image URL', function (): void {
    // MenuItemSeeder::attachToLocations() needs downtown + eastside present;
    // create them up front so the pivot half of run() can complete cleanly.
    Location::factory()->create(['slug' => 'downtown']);
    Location::factory()->create(['slug' => 'eastside']);

    (new MenuItemSeeder)->run();

    $items = MenuItem::all();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->image_url)->toBeString()
            ->and($item->image_url)->toStartWith('https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com/finalcut/concessions/')
            ->and($item->image_url)->toEndWith('.webp');
    }
});

it('exposes the seeded CDN image URL on the cross-location food-menu API', function (): void {
    Location::factory()->create(['slug' => 'downtown']);
    Location::factory()->create(['slug' => 'eastside']);

    (new MenuItemSeeder)->run();

    $response = test()->getJson('/api/food-menu');
    $response->assertOk();

    $first = collect($response->json('data'))->first(
        fn (array $row): bool => str_contains((string) $row['image_url'], 'concessions/'),
    );

    expect($first)->not->toBeNull()
        ->and($first['image_url'])->toStartWith('https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com/finalcut/concessions/');
});
