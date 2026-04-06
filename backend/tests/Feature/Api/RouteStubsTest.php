<?php

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Public Read-Only Routes — 200 with data envelope
|--------------------------------------------------------------------------
*/

// Movies & Showtimes — covered by dedicated test files:
// - tests/Feature/Api/MovieControllerTest.php
// - tests/Feature/Api/ShowtimeControllerTest.php

// Calendar — covered by dedicated test file:
// - tests/Feature/Api/CalendarEventControllerTest.php

// Food Menu — covered by dedicated test file:
// - tests/Feature/Api/FoodMenuControllerTest.php

/*
|--------------------------------------------------------------------------
| Unimplemented Mutation/Auth Stubs — 501 Not Implemented
|--------------------------------------------------------------------------
|
| These routes are scaffolded but not yet functional. They must return
| 501 to prevent false-positive success semantics.
|
*/

// Auth — covered by dedicated test file:
// - tests/Feature/Api/AuthControllerTest.php

// Bookings — covered by dedicated test file:
// - tests/Feature/Api/BookingControllerTest.php

// Gift Cards
test('POST /api/gift-cards/purchase returns 501', function () {
    postJson('/api/gift-cards/purchase')
        ->assertStatus(501)
        ->assertJson(['message' => 'Not implemented']);
});

test('GET /api/gift-cards/balance returns 501', function () {
    getJson('/api/gift-cards/balance')
        ->assertStatus(501)
        ->assertJson(['message' => 'Not implemented']);
});

// Contact / Rentals
test('POST /api/rentals/inquiry returns 501', function () {
    postJson('/api/rentals/inquiry')
        ->assertStatus(501)
        ->assertJson(['message' => 'Not implemented']);
});

test('POST /api/contact returns 501', function () {
    postJson('/api/contact')
        ->assertStatus(501)
        ->assertJson(['message' => 'Not implemented']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes — 401 Unauthorized without auth
|--------------------------------------------------------------------------
*/

// Auth protected routes — covered by tests/Feature/Api/AuthControllerTest.php

test('GET /api/account/profile returns 401 without auth', function () {
    getJson('/api/account/profile')->assertUnauthorized();
});

test('PATCH /api/account/profile returns 401 without auth', function () {
    patchJson('/api/account/profile')->assertUnauthorized();
});

test('GET /api/account/orders returns 401 without auth', function () {
    getJson('/api/account/orders')->assertUnauthorized();
});

test('GET /api/account/bookings returns 401 without auth', function () {
    getJson('/api/account/bookings')->assertUnauthorized();
});

test('GET /api/account/loyalty returns 401 without auth', function () {
    getJson('/api/account/loyalty')->assertUnauthorized();
});

test('GET /api/account/payment-methods returns 401 without auth', function () {
    getJson('/api/account/payment-methods')->assertUnauthorized();
});

test('POST /api/account/payment-methods returns 401 without auth', function () {
    postJson('/api/account/payment-methods')->assertUnauthorized();
});

test('DELETE /api/account/payment-methods/{id} returns 401 without auth', function () {
    deleteJson('/api/account/payment-methods/1')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Service Configuration
|--------------------------------------------------------------------------
*/

test('TMDB config is accessible', function () {
    expect(config('services.tmdb'))->toBeArray()
        ->and(config('services.tmdb.base_url'))->toBe('https://api.themoviedb.org/3')
        ->and(config('services.tmdb.image_base_url'))->toBe('https://image.tmdb.org/t/p/');
});

test('Stripe config is accessible', function () {
    expect(config('services.stripe'))->toBeArray()
        ->and(config('services.stripe'))->toHaveKeys(['secret', 'publishable']);
});

/*
|--------------------------------------------------------------------------
| CORS Configuration
|--------------------------------------------------------------------------
*/

test('CORS preflight returns correct headers', function () {
    $response = $this->options('/api/movies', [], [
        'Origin' => 'https://finalcut.test',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'Content-Type',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', 'https://finalcut.test');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
});

test('CORS headers present on API responses', function () {
    $response = $this->getJson('/api/movies', [
        'Origin' => 'https://finalcut.test',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', 'https://finalcut.test');
});

test('CORS preflight allows X-XSRF-TOKEN header for Sanctum SPA auth', function () {
    $response = $this->options('/api/auth/login', [], [
        'Origin' => 'https://finalcut.test',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', 'https://finalcut.test');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    $allowedHeaders = $response->headers->get('Access-Control-Allow-Headers');
    expect(strtolower($allowedHeaders))->toContain('x-xsrf-token');
});
