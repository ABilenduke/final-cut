<?php

use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

afterEach(function () {
    // Reset any frozen time so the setTestNow() calls inside individual
    // tests don't leak into subsequent tests within the same process.
    Carbon::setTestNow();
});

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
                // `runtime` is needed by the customer MovieCard meta row — without
                // it the card formats `undefined` and renders "NaNh NaNm".
                '*' => ['id', 'slug', 'title', 'runtime', 'posterUrl', 'status'],
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

test('the movies API exposes homeTeaserTag — null by default, the admin override when set (G9)', function () {
    Movie::factory()->nowShowing()->create(['slug' => 'plain', 'home_teaser_tag' => null]);
    Movie::factory()->nowShowing()->create(['slug' => 'tagged', 'home_teaser_tag' => 'Final Week']);

    getJson('/api/movies/plain')
        ->assertOk()
        ->assertJsonPath('data.homeTeaserTag', null);

    getJson('/api/movies/tagged')
        ->assertOk()
        ->assertJsonPath('data.homeTeaserTag', 'Final Week');
});

test('GET /api/movies/{slug} returns 404 for unknown slug', function () {
    getJson('/api/movies/nonexistent-movie')
        ->assertNotFound()
        ->assertJsonStructure(['errors' => [['message']]])
        ->assertJsonPath('errors.0.message', 'Movie not found');
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
| GET /api/locations/{location}/movies/{slug}/showtimes
|--------------------------------------------------------------------------
*/

test('GET showtimes returns showtimes for movie at location', function () {
    // Freeze time in the morning so `now()->addHours(2)` stays on the
    // same calendar day regardless of when the suite runs.
    Carbon::setTestNow('2026-06-15 10:00:00');

    $location = Location::factory()->create();
    $movie = Movie::factory()->create(['slug' => 'showtime-movie']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    getJson("/api/locations/{$location->slug}/movies/showtime-movie/showtimes?date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'movieId', 'movieSlug', 'movieTitle', 'screenId', 'screenName',
                    'startTime', 'endTime', 'priceStandard', 'pricePremium', 'priceAccessible'],
            ],
        ]);
});

test('GET showtimes filters by date', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $location = Location::factory()->create();
    $movie = Movie::factory()->create(['slug' => 'date-filter']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // Today's showtime
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    // Tomorrow's showtime
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDay()->addHours(2),
        'end_time' => now()->addDay()->addHours(4),
    ]);

    getJson("/api/locations/{$location->slug}/movies/date-filter/showtimes?date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');

    getJson("/api/locations/{$location->slug}/movies/date-filter/showtimes?date=".now()->addDay()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('GET showtimes defaults to upcoming 14-day window', function () {
    $location = Location::factory()->create();
    $movie = Movie::factory()->create(['slug' => 'upcoming-default']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // Today — should appear
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(3),
        'end_time' => now()->addHours(5),
    ]);

    // Tomorrow — should also appear
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDay()->addHours(3),
        'end_time' => now()->addDay()->addHours(5),
    ]);

    // 20 days out — outside the window, should NOT appear
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDays(20)->addHours(3),
        'end_time' => now()->addDays(20)->addHours(5),
    ]);

    getJson("/api/locations/{$location->slug}/movies/upcoming-default/showtimes")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET showtimes rejects malformed date parameter with 422', function () {
    $location = Location::factory()->create();
    Movie::factory()->create(['slug' => 'invalid-date']);

    // Empty string is normalized to null by ConvertEmptyStringsToNull,
    // which matches the "no date supplied" path and succeeds — that is
    // the documented behavior. Anything non-empty but non-ISO-date
    // must be rejected rather than reaching whereDate() and raising a
    // Postgres cast error.
    getJson("/api/locations/{$location->slug}/movies/invalid-date/showtimes?date=not-a-date")
        ->assertUnprocessable();

    getJson("/api/locations/{$location->slug}/movies/invalid-date/showtimes?date=2026-13-40")
        ->assertUnprocessable();
});

test('GET showtimes returns empty for no showtimes on date', function () {
    $location = Location::factory()->create();
    Movie::factory()->create(['slug' => 'no-showtimes']);

    getJson("/api/locations/{$location->slug}/movies/no-showtimes/showtimes?date=2026-12-25")
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('GET showtimes hides cancelled showtimes from the movie listing', function () {
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    $movie = Movie::factory()->create(['slug' => 'cancelled-filter']);

    $startTime = now()->addDay()->setTime(19, 0);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => $startTime,
        'end_time' => $startTime->copy()->addMinutes(120),
        'cancelled_at' => now(),
        'cancellation_reason' => 'staffing',
    ]);

    getJson("/api/locations/{$location->slug}/movies/cancelled-filter/showtimes")
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('GET showtimes returns 404 for unknown movie slug', function () {
    $location = Location::factory()->create();

    getJson("/api/locations/{$location->slug}/movies/nonexistent/showtimes")
        ->assertNotFound()
        ->assertJsonStructure(['errors' => [['message']]])
        ->assertJsonPath('errors.0.message', 'Movie not found');
});

/*
|--------------------------------------------------------------------------
| GET /api/movies?location={slug} — location filter
|--------------------------------------------------------------------------
*/

test('GET /api/movies?location= returns only movies with upcoming showtimes at that location', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $downtown = Location::factory()->create(['slug' => 'downtown-filter-test']);
    $uptown = Location::factory()->create(['slug' => 'uptown-filter-test']);

    $audDown = Auditorium::factory()->create(['location_id' => $downtown->id]);
    $audUp = Auditorium::factory()->create(['location_id' => $uptown->id]);

    $movieDowntown = Movie::factory()->nowShowing()->create(['title' => 'Downtown Movie']);
    $movieUptown = Movie::factory()->nowShowing()->create(['title' => 'Uptown Movie']);
    $movieBoth = Movie::factory()->nowShowing()->create(['title' => 'Both Locations Movie']);

    // Downtown-only showtime (uses its own separate auditorium slot)
    $audDown2 = Auditorium::factory()->create(['location_id' => $downtown->id]);
    Showtime::factory()->create([
        'movie_id' => $movieDowntown->id,
        'auditorium_id' => $audDown2->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    // Uptown-only showtime
    $audUp2 = Auditorium::factory()->create(['location_id' => $uptown->id]);
    Showtime::factory()->create([
        'movie_id' => $movieUptown->id,
        'auditorium_id' => $audUp2->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    // Both-locations showtimes — each in its own auditorium, no overlap
    Showtime::factory()->create([
        'movie_id' => $movieBoth->id,
        'auditorium_id' => $audDown->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);
    Showtime::factory()->create([
        'movie_id' => $movieBoth->id,
        'auditorium_id' => $audUp->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    $response = getJson("/api/movies?status=now_showing&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();
    expect($titles)->toBe(['Both Locations Movie', 'Downtown Movie']);
});

test('GET /api/movies?location= excludes movies only showing at other locations', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $downtown = Location::factory()->create(['slug' => 'downtown-excl-test']);
    $uptown = Location::factory()->create(['slug' => 'uptown-excl-test']);

    $audUp = Auditorium::factory()->create(['location_id' => $uptown->id]);

    $movieUptown = Movie::factory()->nowShowing()->create(['title' => 'Uptown Only']);

    Showtime::factory()->create([
        'movie_id' => $movieUptown->id,
        'auditorium_id' => $audUp->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    // downtown has no showtimes, so the result should be empty
    getJson("/api/movies?status=now_showing&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('GET /api/movies?location= excludes movies with only past showtimes at the location', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $downtown = Location::factory()->create(['slug' => 'downtown-past-test']);
    $audDown = Auditorium::factory()->create(['location_id' => $downtown->id]);

    $movie = Movie::factory()->nowShowing()->create(['title' => 'Past Showtime Movie']);

    // Showtime already ended — should NOT appear in the location filter
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audDown->id,
        'start_time' => now()->subHours(3),
        'end_time' => now()->subHours(1),
    ]);

    getJson("/api/movies?status=now_showing&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('GET /api/movies?location= with invalid slug returns 422', function () {
    getJson('/api/movies?location=nonexistent-location-slug')
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'location');
});

test('GET /api/movies without location returns all movies (no regression)', function () {
    Movie::factory()->nowShowing()->count(3)->create();
    Movie::factory()->comingSoon()->count(2)->create();

    getJson('/api/movies?status=now_showing')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('GET showtimes excludes showtimes from other locations', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $location1 = Location::factory()->create();
    $location2 = Location::factory()->create();
    $movie = Movie::factory()->create(['slug' => 'cross-location']);

    $aud1 = Auditorium::factory()->create(['location_id' => $location1->id]);
    $aud2 = Auditorium::factory()->create(['location_id' => $location2->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud1->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud2->id,
        'start_time' => now()->addHours(3),
        'end_time' => now()->addHours(5),
    ]);

    // Location 1 sees only its showtime
    getJson("/api/locations/{$location1->slug}/movies/cross-location/showtimes?date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Location 2 sees only its showtime
    getJson("/api/locations/{$location2->slug}/movies/cross-location/showtimes?date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an invalid ?status is rejected with 422', function () {
    getJson('/api/movies?status=banana')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('a large ?per_page is clamped, not 422 (internal callers like the sitemap rely on this)', function () {
    // The sitemap source requests /api/movies?per_page=500; per_page is CLAMPED
    // to 100, never 422'd, so the sitemap still gets its dynamic URLs.
    getJson('/api/movies?per_page=500')->assertOk();
});

test('a valid ?status still returns 200', function () {
    getJson('/api/movies?status=coming_soon')->assertOk();
});

test('a bad ?status is rejected even when ?location is also invalid (validation runs first)', function () {
    // Pins the ordering: the filter validator precedes location resolution, so
    // the status 422 is not masked by a bad-location 422.
    getJson('/api/movies?status=banana&location=nonexistent')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});
