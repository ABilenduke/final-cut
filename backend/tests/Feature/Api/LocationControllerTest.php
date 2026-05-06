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

// ──────────────────────────────────────────────────────────────────────────────
// Task 6: Full field-shape coverage for /api/locations and /api/locations/{slug}
// The frontend /locations and /locations/:slug pages need every venue field.
// ──────────────────────────────────────────────────────────────────────────────

test('GET /api/locations includes all required venue fields', function () {
    Location::factory()->create([
        'name' => 'Downtown',
        'slug' => 'downtown',
        'phone' => '212-555-0101',
        'email' => 'downtown@finalcut.test',
        'street' => '123 Main Street',
        'city' => 'Downtown',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US',
        'timezone' => 'America/New_York',
        'latitude' => 40.712776,
        'longitude' => -74.005974,
        'hours' => [
            'monday' => ['open' => '12:00', 'close' => '23:00'],
            'tuesday' => ['open' => '12:00', 'close' => '23:00'],
            'wednesday' => ['open' => '12:00', 'close' => '23:00'],
            'thursday' => ['open' => '12:00', 'close' => '23:00'],
            'friday' => ['open' => '12:00', 'close' => '00:30'],
            'saturday' => ['open' => '10:00', 'close' => '00:30'],
            'sunday' => ['open' => '10:00', 'close' => '23:00'],
        ],
    ]);

    getJson('/api/locations')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'name',
                'slug',
                'address',
                'phone',
                'email',
                'street',
                'city',
                'state',
                'postal_code',
                'country',
                'timezone',
                'latitude',
                'longitude',
                'hours',
            ]],
        ])
        ->assertJsonPath('data.0.slug', 'downtown')
        ->assertJsonPath('data.0.phone', '212-555-0101')
        ->assertJsonPath('data.0.email', 'downtown@finalcut.test')
        ->assertJsonPath('data.0.street', '123 Main Street')
        ->assertJsonPath('data.0.city', 'Downtown')
        ->assertJsonPath('data.0.state', 'NY')
        ->assertJsonPath('data.0.postal_code', '10001')
        ->assertJsonPath('data.0.country', 'US')
        ->assertJsonPath('data.0.timezone', 'America/New_York')
        ->assertJsonPath('data.0.hours.monday.open', '12:00')
        ->assertJsonPath('data.0.hours.saturday.open', '10:00');
});

test('GET /api/locations/{slug} returns single venue with all required fields', function () {
    $location = Location::factory()->create([
        'name' => 'Downtown',
        'slug' => 'downtown',
        'phone' => '212-555-0101',
        'email' => 'downtown@finalcut.test',
        'street' => '123 Main Street',
        'city' => 'Downtown',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US',
        'timezone' => 'America/New_York',
        'latitude' => 40.712776,
        'longitude' => -74.005974,
        'hours' => [
            'monday' => ['open' => '12:00', 'close' => '23:00'],
            'friday' => ['open' => '12:00', 'close' => '00:30'],
            'saturday' => null,
        ],
    ]);

    getJson("/api/locations/{$location->slug}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'address',
                'phone',
                'email',
                'street',
                'city',
                'state',
                'postal_code',
                'country',
                'timezone',
                'latitude',
                'longitude',
                'hours',
            ],
        ])
        ->assertJsonPath('data.slug', 'downtown')
        ->assertJsonPath('data.phone', '212-555-0101')
        ->assertJsonPath('data.email', 'downtown@finalcut.test')
        ->assertJsonPath('data.street', '123 Main Street')
        ->assertJsonPath('data.city', 'Downtown')
        ->assertJsonPath('data.state', 'NY')
        ->assertJsonPath('data.postal_code', '10001')
        ->assertJsonPath('data.country', 'US')
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.hours.monday.open', '12:00')
        ->assertJsonPath('data.hours.saturday', null);
});

test('GET /api/locations/{slug} returns 404 for unknown slug', function () {
    getJson('/api/locations/nonexistent')->assertNotFound();
});

test('hours field is null when not set', function () {
    Location::factory()->create(['slug' => 'nohours', 'hours' => null]);

    getJson('/api/locations/nohours')
        ->assertOk()
        ->assertJsonPath('data.hours', null);
});
