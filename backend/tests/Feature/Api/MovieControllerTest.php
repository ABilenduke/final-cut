<?php

use App\Enums\MovieStatus;
use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;

// --- TMDB Response Helpers ---

function fakeTmdbCredits(): array
{
    return [
        'cast' => [
            ['id' => 1, 'name' => 'Actor 1', 'character' => 'Hero', 'profile_path' => '/a1.jpg'],
        ],
        'crew' => [],
    ];
}

function fakeTmdbVideos(): array
{
    return [
        'results' => [
            ['key' => 'yt123', 'type' => 'Trailer', 'site' => 'YouTube'],
        ],
    ];
}

function fakeTmdbMovie(int $id = 100001, string $title = 'Test Movie'): array
{
    return [
        'id' => $id,
        'title' => $title,
        'tagline' => 'A test tagline',
        'overview' => 'A test synopsis',
        'runtime' => 120,
        'vote_average' => 7.5,
        'release_date' => '2026-01-15',
        'genres' => [['id' => 28, 'name' => 'Action']],
        'poster_path' => '/poster.jpg',
        'backdrop_path' => '/backdrop.jpg',
    ];
}

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
| GET /api/movies/{slug}
|--------------------------------------------------------------------------
*/

test('GET /api/movies/{slug} returns movie detail for valid slug', function () {
    Cache::flush();

    $movie = Movie::factory()->nowShowing()->create([
        'id' => 100001,
        'slug' => 'test-movie',
        'title' => 'Test Movie',
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/100001' => Http::response(fakeTmdbMovie(100001, 'Test Movie'), 200),
        'api.themoviedb.org/3/movie/100001/credits*' => Http::response(fakeTmdbCredits(), 200),
        'api.themoviedb.org/3/movie/100001/videos*' => Http::response(fakeTmdbVideos(), 200),
    ]);

    getJson('/api/movies/test-movie')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'slug', 'title', 'tagline', 'synopsis', 'runtime', 'rating',
                'releaseDate', 'genres', 'cast', 'posterUrl', 'backdropUrl', 'trailerKey', 'status'],
        ])
        ->assertJsonPath('data.id', 100001)
        ->assertJsonPath('data.title', 'Test Movie')
        ->assertJsonPath('data.trailerKey', 'yt123');
});

test('GET /api/movies/{slug} returns 404 for unknown slug', function () {
    getJson('/api/movies/nonexistent-movie')
        ->assertNotFound();
});

test('GET /api/movies/{slug} returns cast limited to 12', function () {
    Cache::flush();

    $movie = Movie::factory()->create(['id' => 100002, 'slug' => 'cast-test']);

    $bigCast = [];
    for ($i = 1; $i <= 15; $i++) {
        $bigCast[] = ['id' => $i, 'name' => "Actor {$i}", 'character' => "Char {$i}", 'profile_path' => "/a{$i}.jpg"];
    }

    Http::fake([
        'api.themoviedb.org/3/movie/100002' => Http::response(fakeTmdbMovie(100002, 'Cast Test'), 200),
        'api.themoviedb.org/3/movie/100002/credits*' => Http::response(['cast' => $bigCast, 'crew' => []], 200),
        'api.themoviedb.org/3/movie/100002/videos*' => Http::response(['results' => []], 200),
    ]);

    $response = getJson('/api/movies/cast-test')->assertOk();

    expect($response->json('data.cast'))->toHaveCount(12);
});

test('GET /api/movies/{slug} falls back to local data when TMDB fails', function () {
    Cache::flush();

    Movie::factory()->create([
        'id' => 100003,
        'slug' => 'fallback-movie',
        'title' => 'Fallback Movie',
        'poster_url' => 'https://local-poster.jpg',
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/100003' => Http::response([], 500),
    ]);

    getJson('/api/movies/fallback-movie')
        ->assertOk()
        ->assertJsonPath('data.title', 'Fallback Movie')
        ->assertJsonPath('data.posterUrl', 'https://local-poster.jpg');
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

    getJson('/api/movies/showtime-movie/showtimes?date=' . now()->toDateString())
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

    getJson('/api/movies/date-filter/showtimes?date=' . now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson('/api/movies/date-filter/showtimes?date=' . now()->addDay()->toDateString())
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
