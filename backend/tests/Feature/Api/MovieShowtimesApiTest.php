<?php

declare(strict_types=1);

use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;

afterEach(function () {
    Carbon::setTestNow();
});

// ── (a) Returns showtimes across BOTH venues ──────────────────────────────────

test('GET /api/movies/{slug}/showtimes returns showtimes across all locations', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'cross-loc-movie']);

    $locationA = Location::factory()->create(['slug' => 'downtown', 'timezone' => 'America/New_York']);
    $locationB = Location::factory()->create(['slug' => 'uptown', 'timezone' => 'America/Los_Angeles']);

    $audA = Auditorium::factory()->create(['location_id' => $locationA->id]);
    $audB = Auditorium::factory()->create(['location_id' => $locationB->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audA->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audB->id,
        'start_time' => now()->addHours(3),
        'end_time' => now()->addHours(5),
    ]);

    $response = getJson('/api/movies/cross-loc-movie/showtimes');

    $response->assertOk()->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('location.slug')->all();
    expect($slugs)->toContain('downtown')->and($slugs)->toContain('uptown');
});

// ── (b) Each entry carries location slug, name, lat, lng ─────────────────────

test('each showtime entry includes location slug, name, latitude, longitude', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'loc-fields-movie']);
    $location = Location::factory()->create([
        'slug' => 'central',
        'name' => 'Central Cinema',
        'latitude' => 40.712776,
        'longitude' => -74.005974,
        'timezone' => 'America/New_York',
    ]);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
    ]);

    $response = getJson('/api/movies/loc-fields-movie/showtimes');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'movie_id',
                    'movie_slug',
                    'screen_id',
                    'screen_name',
                    'start_time',
                    'end_time',
                    'price_standard',
                    'price_premium',
                    'price_accessible',
                    'location' => ['slug', 'name', 'latitude', 'longitude'],
                ],
            ],
        ]);

    $locationData = $response->json('data.0.location');
    expect($locationData['slug'])->toBe('central')
        ->and($locationData['name'])->toBe('Central Cinema')
        ->and((float) $locationData['latitude'])->toBeFloat()
        ->and((float) $locationData['longitude'])->toBeFloat();
});

// ── (c) Ordered chronologically by start_time ASC ────────────────────────────

test('showtimes are ordered by start_time ASC regardless of location', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'order-movie']);
    $locationA = Location::factory()->create(['timezone' => 'America/New_York']);
    $locationB = Location::factory()->create(['timezone' => 'America/New_York']);
    $audA = Auditorium::factory()->create(['location_id' => $locationA->id]);
    $audB = Auditorium::factory()->create(['location_id' => $locationB->id]);

    // Create in reverse order to ensure DB ordering is applied, not insertion order
    $laterTime = now()->addHours(5);
    $earlierTime = now()->addHours(2);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audB->id,
        'start_time' => $laterTime,
        'end_time' => $laterTime->copy()->addHours(2),
    ]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audA->id,
        'start_time' => $earlierTime,
        'end_time' => $earlierTime->copy()->addHours(2),
    ]);

    $response = getJson('/api/movies/order-movie/showtimes');
    $response->assertOk()->assertJsonCount(2, 'data');

    $times = collect($response->json('data'))->pluck('start_time')->all();
    expect($times[0])->toBeLessThan($times[1]);
});

// ── (d) ?date= narrows to a single calendar day in the venue's local timezone ─

test('date param narrows to single day in each venues local timezone', function () {
    // Freeze time: UTC midnight → 2026-06-16 00:00 UTC
    // In America/New_York (UTC-4 in summer), this is 2026-06-15 20:00 local
    // In America/Los_Angeles (UTC-7 in summer), this is 2026-06-15 17:00 local
    Carbon::setTestNow('2026-06-16 00:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'tz-date-movie']);

    // Downtown is Eastern (UTC-4). At UTC midnight, it's 20:00 on June 15 locally.
    $locationEast = Location::factory()->create([
        'slug' => 'downtown-east',
        'timezone' => 'America/New_York',
    ]);
    // Uptown is Pacific (UTC-7). At UTC midnight, it's 17:00 on June 15 locally.
    $locationWest = Location::factory()->create([
        'slug' => 'uptown-west',
        'timezone' => 'America/Los_Angeles',
    ]);

    $audEast = Auditorium::factory()->create(['location_id' => $locationEast->id]);
    $audWest = Auditorium::factory()->create(['location_id' => $locationWest->id]);

    // East showtime: UTC 2026-06-16 01:00 = June 15 21:00 ET → local date is June 15
    $eastShowtime = Carbon::parse('2026-06-16 01:00:00', 'UTC');
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audEast->id,
        'start_time' => $eastShowtime,
        'end_time' => $eastShowtime->copy()->addHours(2),
    ]);

    // West showtime: UTC 2026-06-16 04:00 = June 15 21:00 PT → local date is June 15
    $westShowtime = Carbon::parse('2026-06-16 04:00:00', 'UTC');
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audWest->id,
        'start_time' => $westShowtime,
        'end_time' => $westShowtime->copy()->addHours(2),
    ]);

    // Showtime on June 16 in ET: UTC 2026-06-16 17:00 = June 16 13:00 ET
    $nextDayEast = Carbon::parse('2026-06-16 17:00:00', 'UTC');
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $audEast->id,
        'start_time' => $nextDayEast,
        'end_time' => $nextDayEast->copy()->addHours(2),
    ]);

    // Querying for June 15 should return the two local-June-15 showtimes only
    $response = getJson('/api/movies/tz-date-movie/showtimes?date=2026-06-15');
    $response->assertOk()->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('location.slug')->all();
    expect($slugs)->toContain('downtown-east')
        ->and($slugs)->toContain('uptown-west');
});

// ── (e) ?days=N window is respected ──────────────────────────────────────────

test('days param controls the query window', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'days-window-movie']);
    $location = Location::factory()->create(['timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // In window (day 3)
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDays(3),
        'end_time' => now()->addDays(3)->addHours(2),
    ]);

    // Outside window (day 10)
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDays(10),
        'end_time' => now()->addDays(10)->addHours(2),
    ]);

    // With days=5: only day 3 showtime should appear
    getJson('/api/movies/days-window-movie/showtimes?days=5')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // With days=14 (default): both should appear
    getJson('/api/movies/days-window-movie/showtimes?days=14')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ── (f) 404 for unknown movie slug ───────────────────────────────────────────

test('returns 404 for unknown movie slug', function () {
    getJson('/api/movies/nonexistent-slug/showtimes')
        ->assertNotFound();
});

// ── (g) Empty array for movie with no upcoming showtimes ─────────────────────

test('returns empty data array for movie with no upcoming showtimes', function () {
    Movie::factory()->create(['slug' => 'no-showtimes-movie']);

    $response = getJson('/api/movies/no-showtimes-movie/showtimes');

    $response->assertOk();
    expect($response->json('data'))->toBeArray()->toBeEmpty();
});

// ── Additional: cancelled showtimes excluded ──────────────────────────────────

test('cancelled showtimes are excluded from cross-location response', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'cancelled-cross-loc']);
    $location = Location::factory()->create(['timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
        'cancelled_at' => now(),
        'cancellation_reason' => 'test',
    ]);

    getJson('/api/movies/cancelled-cross-loc/showtimes')
        ->assertOk()
        ->assertJsonPath('data', []);
});

// ── Additional: endpoint is public, no auth required ─────────────────────────

test('endpoint is public and requires no authentication', function () {
    Movie::factory()->create(['slug' => 'public-auth-movie']);

    getJson('/api/movies/public-auth-movie/showtimes')
        ->assertOk();
});

// ── Additional: prices are integer cents ─────────────────────────────────────

test('prices are returned as integer cents', function () {
    Carbon::setTestNow('2026-06-15 12:00:00 UTC');

    $movie = Movie::factory()->create(['slug' => 'cents-movie']);
    $location = Location::factory()->create(['timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
        'price_standard' => 1899,
        'price_premium' => 2499,
        'price_accessible' => 1699,
    ]);

    $response = getJson('/api/movies/cents-movie/showtimes');
    $response->assertOk();

    $item = $response->json('data.0');
    expect($item['price_standard'])->toBe(1899)->toBeInt()
        ->and($item['price_premium'])->toBe(2499)->toBeInt()
        ->and($item['price_accessible'])->toBe(1699)->toBeInt();
});
