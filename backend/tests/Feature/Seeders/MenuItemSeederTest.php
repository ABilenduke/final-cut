<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\MenuItem;
use Database\Seeders\MenuItemSeeder;
use Illuminate\Support\Facades\Config;

function configureMenuItemSeederPublicCdnDisk(): void
{
    Config::set('filesystems.disks.public', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'nyc3',
        'bucket' => 'assets',
        'endpoint' => 'https://nyc3.digitaloceanspaces.com',
        'use_path_style_endpoint' => false,
        'url' => 'https://cdn.example.test',
        'root' => 'finalcut',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    app('filesystem')->forgetDisk('public');
}

it('seeds every menu item with a CDN-relative concession webp image path', function (): void {
    // MenuItemSeeder::attachToLocations() needs downtown + eastside present;
    // create them up front so the pivot half of run() can complete cleanly.
    Location::factory()->create(['slug' => 'downtown']);
    Location::factory()->create(['slug' => 'eastside']);

    (new MenuItemSeeder)->run();

    $items = MenuItem::all();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->image_url)->toBeString()
            ->and($item->image_url)->toStartWith('images/concessions/')
            ->and($item->image_url)->not->toStartWith('http')
            ->and($item->image_url)->toEndWith('.webp');
    }
});

it('resolves seeded concession image paths through the configured public CDN on the cross-location food-menu API', function (): void {
    configureMenuItemSeederPublicCdnDisk();

    Location::factory()->create(['slug' => 'downtown']);
    Location::factory()->create(['slug' => 'eastside']);

    (new MenuItemSeeder)->run();

    $response = test()->getJson('/api/food-menu');
    $response->assertOk();

    $first = collect($response->json('data'))->first(
        fn (array $row): bool => str_contains((string) $row['imageUrl'], 'concessions/'),
    );

    expect($first)->not->toBeNull()
        ->and($first)->toHaveKey('imageUrl')
        ->and($first)->not->toHaveKey('image_url')
        ->and($first['imageUrl'])->toStartWith('https://cdn.example.test/finalcut/images/concessions/');
});
