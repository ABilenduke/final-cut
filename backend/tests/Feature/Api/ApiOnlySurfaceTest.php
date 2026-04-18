<?php

use App\Models\Movie;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('GET / returns JSON 404 for the API-only backend', function () {
    get('/')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'Not Found',
        ]);
});

test('unmatched non-api routes return JSON 404', function () {
    get('/definitely-not-a-real-page')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'Not Found',
        ]);
});

test('unmatched non-api mutations also return JSON 404', function () {
    postJson('/definitely-not-a-real-page')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'Not Found',
        ]);
});

test('non-api catch-all POST routes exclude csrf middleware', function () {
    $router = app('router');
    $rootRoute = Route::getRoutes()->match(Request::create('/', 'POST'));
    $fallbackRoute = Route::getRoutes()->match(Request::create('/definitely-not-a-real-page', 'POST'));

    expect($rootRoute->uri())->toBe('/')
        ->and($router->gatherRouteMiddleware($rootRoute))
        ->not->toContain(PreventRequestForgery::class)
        ->and($fallbackRoute->uri())->toBe('{fallbackPlaceholder}')
        ->and($router->gatherRouteMiddleware($fallbackRoute))
        ->not->toContain(PreventRequestForgery::class);
});

test('non-api framework and dev-tool routes are not registered', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($uris)
        ->not->toContain('_boost/browser-logs')
        ->not->toContain('storage/{path}');
});

test('GET /api/sanctum/csrf-cookie remains available', function () {
    get('/api/sanctum/csrf-cookie')->assertNoContent();
});

test('GET /api/movies still returns the public movie collection', function () {
    Movie::factory()->nowShowing()->create([
        'title' => 'API Surface Feature',
        'slug' => 'api-surface-feature',
    ]);

    getJson('/api/movies')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'api-surface-feature');
});
