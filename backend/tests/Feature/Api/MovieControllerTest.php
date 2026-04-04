<?php

use App\Enums\MovieStatus;
use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;

use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| GET /api/movies — Local DB is source of truth
|--------------------------------------------------------------------------
*/

test('GET /api/movies returns local now_showing movies', function () {
    Movie::factory()->nowShowing()->count(3)->create();
    Movie::factory()->comingSoon()->count(2)->create();

    getJson('/api/movies?status=now_showing')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta.page', 1)
        ->assertJsonCount(3, 'data');
});

test('GET /api/movies returns local coming_soon movies', function () {
    Movie::factory()->nowShowing()->count(3)->create();
    Movie::factory()->comingSoon()->count(2)->create();

    getJson('/api/movies?status=coming_soon')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/movies defaults to now_showing when no status param', function () {
    Movie::factory()->nowShowing()->count(2)->create();
    Movie::factory()->comingSoon()->count(1)->create();

    getJson('/api/movies')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/movies returns correct JSON structure with meta', function () {
    Movie::factory()->nowShowing()->create();

    getJson('/api/movies')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'title', 'posterUrl', 'status'],
            ],
            'meta' => ['total', 'page'],
        ]);
});

test('GET /api/movies returns empty when no movies match status', function () {
    Movie::factory()->comingSoon()->create();

    getJson('/api/movies?status=now_showing')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('GET /api/movies paginates results', function () {
    Movie::factory()->nowShowing()->count(25)->create();

    $page1 = getJson('/api/movies?page=1&per_page=10')->assertOk();
    expect($page1->json('data'))->toHaveCount(10);
    expect($page1->json('meta.total'))->toBe(25);
    expect($page1->json('meta.page'))->toBe(1);

    $page3 = getJson('/api/movies?page=3&per_page=10')->assertOk();
    expect($page3->json('data'))->toHaveCount(5);
});

/*
|--------------------------------------------------------------------------
| GET /api/movies/{slug} — Pure local data, no TMDB
|--------------------------------------------------------------------------
*/

test('GET /api/movies/{slug} returns movie detail for valid slug', function () {
    $movie = Movie::factory()->nowShowing()->create([
        'slug' => 'test-movie',
        'title' => 'Test Movie',
        'cast' => [
            ['id' => 1, 'name' => 'Actor 1', 'character' => 'Hero', 'profileUrl' => 'https://image.tmdb.org/t/p/w185/a1.jpg'],
        ],
        'trailer_key' => 'yt123',
    ]);

    getJson('/api/movies/test-movie')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'slug', 'title', 'tagline', 'synopsis', 'runtime', 'rating',
                'releaseDate', 'genres', 'cast', 'posterUrl', 'backdropUrl', 'trailerKey', 'status'],
        ])
        ->assertJsonPath('data.id', $movie->id)
        ->assertJsonPath('data.title', 'Test Movie')
        ->assertJsonPath('data.trailerKey', 'yt123')
        ->assertJsonCount(1, 'data.cast');
});

test('GET /api/movies/{slug} returns 404 for unknown slug', function () {
    getJson('/api/movies/nonexistent-movie')
        ->assertNotFound();
});

test('GET /api/movies/{slug} returns empty cast when cast is null', function () {
    Movie::factory()->create([
        'slug' => 'no-cast-movie',
        'cast' => null,
    ]);

    getJson('/api/movies/no-cast-movie')
        ->assertOk()
        ->assertJsonPath('data.cast', []);
});

test('GET /api/movies/{slug} returns movie with persisted cast data', function () {
    $cast = [
        ['id' => 1, 'name' => 'Alice', 'character' => 'Commander', 'profileUrl' => null],
        ['id' => 2, 'name' => 'Bob', 'character' => 'Pilot', 'profileUrl' => null],
    ];

    Movie::factory()->create([
        'slug' => 'cast-movie',
        'cast' => $cast,
    ]);

    $response = getJson('/api/movies/cast-movie')->assertOk();

    expect($response->json('data.cast'))->toHaveCount(2);
    expect($response->json('data.cast.0.name'))->toBe('Alice');
    expect($response->json('data.cast.1.character'))->toBe('Pilot');
});

/*
|--------------------------------------------------------------------------
| GET /api/movies/{slug}/showtimes
|--------------------------------------------------------------------------
*/

test('GET /api/movies/{slug}/showtimes returns showtimes for movie', function () {
    $movie = Movie::factory()->create(['slug' => 'showtime-movie']);
    $auditorium = Auditorium::factory()->create();

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->setTime(19, 0),
        'end_time' => now()->setTime(21, 0),
    ]);

    getJson('/api/movies/showtime-movie/showtimes?date='.now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'movieId', 'movieSlug', 'movieTitle', 'screenId', 'screenName',
                    'startTime', 'endTime', 'priceStandard', 'pricePremium', 'priceAccessible'],
            ],
        ]);
});

test('GET /api/movies/{slug}/showtimes filters by date', function () {
    $movie = Movie::factory()->create(['slug' => 'date-filter']);
    $auditorium = Auditorium::factory()->create();

    // Today's showtime
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->setTime(19, 0),
        'end_time' => now()->setTime(21, 0),
    ]);

    // Tomorrow's showtime
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDay()->setTime(19, 0),
        'end_time' => now()->addDay()->setTime(21, 0),
    ]);

    getJson('/api/movies/date-filter/showtimes?date='.now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson('/api/movies/date-filter/showtimes?date='.now()->addDay()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('GET /api/movies/{slug}/showtimes defaults to today', function () {
    $movie = Movie::factory()->create(['slug' => 'today-default']);
    $auditorium = Auditorium::factory()->create();

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->setTime(20, 0),
        'end_time' => now()->setTime(22, 0),
    ]);

    // Tomorrow — should NOT appear
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDay()->setTime(20, 0),
        'end_time' => now()->addDay()->setTime(22, 0),
    ]);

    getJson('/api/movies/today-default/showtimes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('GET /api/movies/{slug}/showtimes returns empty for no showtimes on date', function () {
    $movie = Movie::factory()->create(['slug' => 'no-showtimes']);

    getJson('/api/movies/no-showtimes/showtimes?date=2026-12-25')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('GET /api/movies/{slug}/showtimes returns 404 for unknown slug', function () {
    getJson('/api/movies/nonexistent/showtimes')
        ->assertNotFound();
});
