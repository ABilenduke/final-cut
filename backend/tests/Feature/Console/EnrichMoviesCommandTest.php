<?php

use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeTmdbCombinedResponse(int $id = 550, string $title = 'Fight Club'): array
{
    return [
        'id' => $id,
        'title' => $title,
        'tagline' => 'Mischief. Mayhem. Soap.',
        'overview' => 'An insomniac office worker meets a soap salesman.',
        'runtime' => 139,
        'vote_average' => 8.4,
        'release_date' => '1999-10-15',
        'genres' => [['id' => 18, 'name' => 'Drama']],
        'poster_path' => '/poster.jpg',
        'backdrop_path' => '/backdrop.jpg',
        'credits' => [
            'cast' => [
                ['id' => 1, 'name' => 'Brad Pitt', 'character' => 'Tyler Durden', 'profile_path' => '/brad.jpg'],
            ],
            'crew' => [],
        ],
        'videos' => [
            'results' => [
                ['key' => 'trailer123', 'type' => 'Trailer', 'site' => 'YouTube'],
            ],
        ],
    ];
}

test('enriches movies with stale tmdb_enriched_at', function () {
    Cache::flush();

    $staleMovie = Movie::factory()->create([
        'tmdb_id' => 550,
        'cast' => null,
        'tmdb_enriched_at' => now()->subHours(25),
    ]);

    $freshMovie = Movie::factory()->create([
        'tmdb_id' => 551,
        'tmdb_enriched_at' => now()->subHours(1),
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(fakeTmdbCombinedResponse(550), 200),
    ]);

    $this->artisan('movies:enrich')
        ->assertSuccessful();

    $staleMovie->refresh();
    expect($staleMovie->cast)->not->toBeNull();
    expect($staleMovie->tmdb_enriched_at->isToday())->toBeTrue();

    // Fresh movie should NOT have been re-enriched (no HTTP call for 551)
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'movie/551'));
});

test('skips movies without tmdb_id', function () {
    Cache::flush();

    Movie::factory()->create([
        'tmdb_id' => null,
        'tmdb_enriched_at' => null,
    ]);

    $this->artisan('movies:enrich')
        ->expectsOutput('No movies need enrichment.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('handles TMDB failures gracefully without crashing', function () {
    Cache::flush();

    Movie::factory()->create([
        'tmdb_id' => 999,
        'tmdb_enriched_at' => null,
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/999*' => Http::response([], 500),
    ]);

    $this->artisan('movies:enrich')
        ->assertFailed();
});

test('--movie flag enriches a single movie by slug', function () {
    Cache::flush();

    $movie = Movie::factory()->create([
        'tmdb_id' => 550,
        'slug' => 'fight-club',
        'cast' => null,
        'tmdb_enriched_at' => now(), // recently enriched, but --movie ignores staleness
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(fakeTmdbCombinedResponse(550), 200),
    ]);

    $this->artisan('movies:enrich --movie=fight-club')
        ->assertSuccessful();

    $movie->refresh();
    expect($movie->cast)->not->toBeNull();
});

test('--force flag re-enriches recently enriched movies', function () {
    Cache::flush();

    $movie = Movie::factory()->create([
        'tmdb_id' => 550,
        'cast' => [['id' => 1, 'name' => 'Old Actor']],
        'tmdb_enriched_at' => now()->subMinutes(5),
    ]);

    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(fakeTmdbCombinedResponse(550), 200),
    ]);

    $this->artisan('movies:enrich --force')
        ->assertSuccessful();

    $movie->refresh();
    expect($movie->cast[0]['name'])->toBe('Brad Pitt');
});

test('--movie flag reports error for unknown slug', function () {
    $this->artisan('movies:enrich --movie=nonexistent')
        ->assertFailed();

    Http::assertNothingSent();
});
