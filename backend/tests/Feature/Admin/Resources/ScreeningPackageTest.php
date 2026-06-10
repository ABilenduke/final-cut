<?php

use App\Filament\Resources\ScreeningPackageResource;
use App\Models\ScreeningPackage;
use Database\Seeders\ScreeningPackageSeeder;

use function Pest\Laravel\getJson;

test('the api returns published packages in display order with cents pricing', function (): void {
    ScreeningPackage::factory()->create([
        'name' => 'Corporate', 'starting_price' => 75000, 'display_order' => 2,
        'features' => ['Full rental', 'AV included'],
        'published_at' => now()->subDay(),
    ]);
    ScreeningPackage::factory()->create([
        'name' => 'Birthday', 'starting_price' => 35000, 'display_order' => 1,
        'published_at' => now()->subDay(),
    ]);
    ScreeningPackage::factory()->create(['name' => 'Draft', 'published_at' => null]);

    getJson('/api/screening-packages')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Birthday')
        ->assertJsonPath('data.0.startingPrice', 35000)
        ->assertJsonPath('data.1.features.1', 'AV included');
});

test('the cache busts on save', function (): void {
    $package = ScreeningPackage::factory()->published()->create(['name' => 'Before']);

    getJson('/api/screening-packages')->assertJsonPath('data.0.name', 'Before');
    $package->update(['name' => 'After']);
    getJson('/api/screening-packages')->assertJsonPath('data.0.name', 'After');
});

test('manager can manage; ops cannot', function (): void {
    $this->actingAsManager();
    expect(ScreeningPackageResource::canViewAny())->toBeTrue();

    $this->actingAsOps();
    expect(ScreeningPackageResource::canViewAny())->toBeFalse();
});

test('the seeder imports the four legacy packages as live', function (): void {
    $this->seed(ScreeningPackageSeeder::class);

    expect(ScreeningPackage::count())->toBe(4);
    getJson('/api/screening-packages')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.name', 'Birthday Party')
        ->assertJsonPath('data.0.startingPrice', 35000);
});
