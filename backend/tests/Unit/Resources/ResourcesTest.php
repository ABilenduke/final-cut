<?php

use App\Enums\BookingStatus;
use App\Enums\MovieStatus;
use App\Enums\SeatType;
use App\Http\Resources\AuditoriumResource;
use App\Http\Resources\MovieListResource;
use App\Http\Resources\MovieResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| MovieResource
|--------------------------------------------------------------------------
*/

test('MovieResource transforms model to camelCase output', function () {
    $movie = Movie::factory()->nowShowing()->create([
        'title' => 'Test Movie',
        'slug' => 'test-movie',
        'tagline' => 'A test tagline',
        'synopsis' => 'A test synopsis',
        'runtime' => 120,
        'rating' => 7.5,
        'release_date' => '2026-01-15',
        'genres' => [['id' => 28, 'name' => 'Action']],
        'cast' => [['id' => 1, 'name' => 'Actor 1', 'character' => 'Hero', 'profileUrl' => null]],
        'poster_url' => 'https://image.tmdb.org/t/p/w500/poster.jpg',
        'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/backdrop.jpg',
        'trailer_key' => 'abc123',
    ]);

    $resource = (new MovieResource($movie))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKey('id', $movie->id)
        ->toHaveKey('slug', 'test-movie')
        ->toHaveKey('title', 'Test Movie')
        ->toHaveKey('tagline', 'A test tagline')
        ->toHaveKey('synopsis', 'A test synopsis')
        ->toHaveKey('runtime', 120)
        ->toHaveKey('releaseDate', '2026-01-15')
        ->toHaveKey('genres')
        ->toHaveKey('cast')
        ->toHaveKey('posterUrl', 'https://image.tmdb.org/t/p/w500/poster.jpg')
        ->toHaveKey('backdropUrl', 'https://image.tmdb.org/t/p/w1280/backdrop.jpg')
        ->toHaveKey('trailerKey', 'abc123')
        ->toHaveKey('status', MovieStatus::NowShowing->value);

    expect($resource['cast'])->toHaveCount(1);
    expect($resource['cast'][0]['name'])->toBe('Actor 1');
});

test('MovieResource includes cast and trailerKey', function () {
    $movie = Movie::factory()->create();

    $resource = (new MovieResource($movie))->toArray(Request::create('/'));

    expect($resource)->toHaveKeys(['cast', 'trailerKey']);
});

/*
|--------------------------------------------------------------------------
| MovieListResource
|--------------------------------------------------------------------------
*/

test('MovieListResource omits cast', function () {
    $movie = Movie::factory()->create();

    $resource = (new MovieListResource($movie))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKeys(['id', 'slug', 'title', 'posterUrl', 'status'])
        ->not->toHaveKey('cast');
});

/*
|--------------------------------------------------------------------------
| ShowtimeResource
|--------------------------------------------------------------------------
*/

test('ShowtimeResource includes denormalized movie and screen names', function () {
    $movie = Movie::factory()->create(['title' => 'Blade Runner', 'slug' => 'blade-runner']);
    $auditorium = Auditorium::factory()->create(['name' => 'Screen 1']);
    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-04-10 19:00:00',
        'end_time' => '2026-04-10 21:15:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    $showtime->load('movie', 'auditorium');

    $resource = (new ShowtimeResource($showtime))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKey('id', $showtime->id)
        ->toHaveKey('movieId', $movie->id)
        ->toHaveKey('movieSlug', 'blade-runner')
        ->toHaveKey('movieTitle', 'Blade Runner')
        ->toHaveKey('screenId', $auditorium->id)
        ->toHaveKey('screenName', 'Screen 1')
        ->toHaveKey('priceStandard', 1200)
        ->toHaveKey('pricePremium', 1800)
        ->toHaveKey('priceAccessible', 1000);

    // Verify ISO datetime format
    expect($resource['startTime'])->toContain('2026-04-10');
    expect($resource['endTime'])->toContain('2026-04-10');
});

/*
|--------------------------------------------------------------------------
| SeatResource
|--------------------------------------------------------------------------
*/

test('SeatResource includes computed status and price', function () {
    $auditorium = Auditorium::factory()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'row' => 'A',
        'number' => 1,
        'label' => 'A1',
        'type' => SeatType::Standard,
    ]);

    // Attach computed properties as the controller would
    $seat->computed_status = 'available';
    $seat->computed_price = 1200;

    $resource = (new SeatResource($seat))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKey('id', $seat->id)
        ->toHaveKey('row', 'A')
        ->toHaveKey('number', 1)
        ->toHaveKey('label', 'A1')
        ->toHaveKey('type', 'standard')
        ->toHaveKey('status', 'available')
        ->toHaveKey('price', 1200);
});

test('SeatResource shows taken status for booked seats', function () {
    $auditorium = Auditorium::factory()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'type' => SeatType::Premium,
    ]);

    $seat->computed_status = 'taken';
    $seat->computed_price = 1800;

    $resource = (new SeatResource($seat))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKey('status', 'taken')
        ->toHaveKey('price', 1800)
        ->toHaveKey('type', 'premium');
});

/*
|--------------------------------------------------------------------------
| AuditoriumResource
|--------------------------------------------------------------------------
*/

test('AuditoriumResource groups seats by row', function () {
    $auditorium = Auditorium::factory()->create(['name' => 'IMAX', 'total_seats' => 4]);

    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1', 'type' => SeatType::Standard]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 2, 'label' => 'A2', 'type' => SeatType::Standard]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'B', 'number' => 1, 'label' => 'B1', 'type' => SeatType::Premium]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'B', 'number' => 2, 'label' => 'B2', 'type' => SeatType::Premium]);

    $auditorium->load('seats');

    $resource = (new AuditoriumResource($auditorium))->toArray(Request::create('/'));

    expect($resource)
        ->toHaveKey('id', $auditorium->id)
        ->toHaveKey('name', 'IMAX')
        ->toHaveKey('totalSeats', 4)
        ->toHaveKey('rows');

    expect($resource['rows'])->toHaveCount(2);
    expect($resource['rows'][0]['label'])->toBe('A');
    expect($resource['rows'][0]['seats'])->toHaveCount(2);
    expect($resource['rows'][1]['label'])->toBe('B');
});

test('AuditoriumResource includes sections from seat types', function () {
    $auditorium = Auditorium::factory()->create(['total_seats' => 3]);

    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1', 'type' => SeatType::Standard]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'B', 'number' => 1, 'label' => 'B1', 'type' => SeatType::Premium]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'C', 'number' => 1, 'label' => 'C1', 'type' => SeatType::Accessible]);

    $auditorium->load('seats');

    $resource = (new AuditoriumResource($auditorium))->toArray(Request::create('/'));

    expect($resource)->toHaveKey('sections');

    $sectionNames = collect($resource['sections'])->pluck('name')->toArray();
    expect($sectionNames)->toContain('standard', 'premium', 'accessible');
});
