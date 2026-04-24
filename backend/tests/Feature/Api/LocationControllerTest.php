<?php

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('returns all locations ordered by name', function () {
    Location::factory()->create([
        'name' => 'Eastside',
        'slug' => 'eastside',
        'street' => '456 East Ave',
        'city' => 'Eastside',
        'state' => 'NY',
        'postal_code' => '10002',
    ]);
    Location::factory()->create([
        'name' => 'Downtown',
        'slug' => 'downtown',
        'street' => '123 Main St',
        'city' => 'Downtown',
        'state' => 'NY',
        'postal_code' => '10001',
    ]);

    $response = getJson('/api/locations');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Downtown')
        ->assertJsonPath('data.0.slug', 'downtown')
        ->assertJsonPath('data.0.address', '123 Main St, Downtown, NY 10001')
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
