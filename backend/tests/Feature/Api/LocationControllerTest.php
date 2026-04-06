<?php

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('returns all locations ordered by name', function () {
    Location::factory()->create(['name' => 'Eastside', 'slug' => 'eastside', 'address' => '456 East Ave']);
    Location::factory()->create(['name' => 'Downtown', 'slug' => 'downtown', 'address' => '123 Main St']);

    $response = getJson('/api/locations');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Downtown')
        ->assertJsonPath('data.0.slug', 'downtown')
        ->assertJsonPath('data.0.address', '123 Main St')
        ->assertJsonPath('data.1.name', 'Eastside');
});

test('returns empty array when no locations exist', function () {
    $response = getJson('/api/locations');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('response includes id, name, slug, and address for each location', function () {
    Location::factory()->create();

    $response = getJson('/api/locations');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'slug', 'address'],
            ],
        ]);
});
