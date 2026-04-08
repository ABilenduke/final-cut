<?php

use App\Models\Movie;
use Database\Seeders\MovieSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('movie seeder remains deterministic when live tmdb seeding is disabled', function () {
    config([
        'seeders.movies.live_tmdb' => false,
        'services.tmdb.api_key' => null,
    ]);

    Http::fake();

    (new MovieSeeder)->run();

    expect(Movie::count())->toBe(20);

    $movie = Movie::where('tmdb_id', 550)->firstOrFail();

    expect($movie->poster_url)->toBe('https://image.tmdb.org/t/p/w500/placeholder.jpg')
        ->and($movie->backdrop_url)->toBe('https://image.tmdb.org/t/p/w1280/placeholder.jpg')
        ->and($movie->tmdb_enriched_at)->not->toBeNull();

    Http::assertNothingSent();
});

test('movie seeder fails fast when live tmdb seeding is enabled without api key', function () {
    config([
        'seeders.movies.live_tmdb' => true,
        'services.tmdb.api_key' => null,
    ]);

    expect(fn () => (new MovieSeeder)->run())
        ->toThrow(RuntimeException::class, 'TMDB_API_KEY is required');
});

test('movie seeder fails when live tmdb enrichment command fails', function () {
    config([
        'seeders.movies.live_tmdb' => true,
        'services.tmdb.api_key' => 'test-key',
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('movies:enrich', ['--force' => true])
        ->andReturn(1);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('TMDB API request failed');

    expect(fn () => (new MovieSeeder)->run())
        ->toThrow(RuntimeException::class, 'live TMDB enrichment did not complete successfully');
});

test('movie seeder runs live tmdb enrichment command when enabled', function () {
    config([
        'seeders.movies.live_tmdb' => true,
        'services.tmdb.api_key' => 'test-key',
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('movies:enrich', ['--force' => true])
        ->andReturn(0);

    (new MovieSeeder)->run();

    expect(Movie::count())->toBe(20)
        ->and(Movie::whereNull('tmdb_enriched_at')->count())->toBe(20);
});
