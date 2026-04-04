<?php

use App\Models\Movie;
use App\Services\TmdbService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// --- TMDB Response Fixtures ---

function tmdbMovieDetail(): array
{
    return [
        'id' => 550,
        'title' => 'Fight Club',
        'tagline' => 'Mischief. Mayhem. Soap.',
        'overview' => 'A ticking-Loss. Fight Club — insomniac narrator meets soap salesman Tyler Durden.',
        'runtime' => 139,
        'vote_average' => 8.438,
        'release_date' => '1999-10-15',
        'genres' => [
            ['id' => 18, 'name' => 'Drama'],
            ['id' => 53, 'name' => 'Thriller'],
        ],
        'poster_path' => '/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg',
        'backdrop_path' => '/hZkgoQYus5dXo3H8T7Uef6DNknx.jpg',
    ];
}

function tmdbCredits(): array
{
    $cast = [];
    for ($i = 1; $i <= 15; $i++) {
        $cast[] = [
            'id' => $i,
            'name' => "Actor {$i}",
            'character' => "Character {$i}",
            'profile_path' => "/actor{$i}.jpg",
        ];
    }

    return ['cast' => $cast, 'crew' => []];
}

function tmdbVideos(): array
{
    return [
        'results' => [
            ['key' => 'featurette123', 'type' => 'Featurette', 'site' => 'YouTube'],
            ['key' => 'trailer456', 'type' => 'Trailer', 'site' => 'YouTube'],
            ['key' => 'trailer789', 'type' => 'Trailer', 'site' => 'Vimeo'],
        ],
    ];
}

// --- tmdbToMovie transformation tests ---

test('tmdbToMovie transforms TMDB data correctly', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);

    $method = new ReflectionMethod($service, 'tmdbToMovie');

    $result = $method->invoke($service, tmdbMovieDetail(), tmdbCredits(), tmdbVideos());

    expect($result)
        ->toHaveKeys(['id', 'slug', 'title', 'tagline', 'synopsis', 'runtime', 'rating', 'releaseDate', 'genres', 'cast', 'posterUrl', 'backdropUrl', 'trailerKey'])
        ->and($result['id'])->toBe(550)
        ->and($result['slug'])->toBe('fight-club')
        ->and($result['title'])->toBe('Fight Club')
        ->and($result['tagline'])->toBe('Mischief. Mayhem. Soap.')
        ->and($result['runtime'])->toBe(139)
        ->and($result['rating'])->toBe(8.4)
        ->and($result['releaseDate'])->toBe('1999-10-15')
        ->and($result['genres'])->toHaveCount(2)
        ->and($result['posterUrl'])->toBe('https://image.tmdb.org/t/p/w500/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg')
        ->and($result['backdropUrl'])->toBe('https://image.tmdb.org/t/p/w1280/hZkgoQYus5dXo3H8T7Uef6DNknx.jpg');
});

test('tmdbToMovie limits cast to 12 members', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);
    $method = new ReflectionMethod($service, 'tmdbToMovie');

    $result = $method->invoke($service, tmdbMovieDetail(), tmdbCredits(), tmdbVideos());

    expect($result['cast'])->toHaveCount(12);
});

test('tmdbToMovie builds profile URLs with w185 size', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);
    $method = new ReflectionMethod($service, 'tmdbToMovie');

    $result = $method->invoke($service, tmdbMovieDetail(), tmdbCredits(), tmdbVideos());

    expect($result['cast'][0]['profileUrl'])->toBe('https://image.tmdb.org/t/p/w185/actor1.jpg');
});

test('tmdbToMovie extracts YouTube trailer key', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);
    $method = new ReflectionMethod($service, 'tmdbToMovie');

    $result = $method->invoke($service, tmdbMovieDetail(), tmdbCredits(), tmdbVideos());

    // Should pick the first YouTube Trailer, not the Featurette or Vimeo
    expect($result['trailerKey'])->toBe('trailer456');
});

test('tmdbToMovie handles missing data gracefully', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);
    $method = new ReflectionMethod($service, 'tmdbToMovie');

    $detail = [
        'id' => 999,
        'title' => 'No Data Movie',
        'tagline' => '',
        'overview' => '',
        'runtime' => 0,
        'vote_average' => 0,
        'release_date' => '',
        'genres' => [],
        'poster_path' => null,
        'backdrop_path' => null,
    ];

    $result = $method->invoke($service, $detail, ['cast' => [], 'crew' => []], ['results' => []]);

    expect($result['posterUrl'])->toBeNull()
        ->and($result['backdropUrl'])->toBeNull()
        ->and($result['trailerKey'])->toBeNull()
        ->and($result['cast'])->toBeEmpty()
        ->and($result['genres'])->toBeEmpty();
});

// --- fetchEnrichmentData tests ---

test('fetchEnrichmentData fetches detail, credits, and videos from TMDB', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response(tmdbCredits(), 200),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->fetchEnrichmentData(550);

    expect($result)
        ->not->toBeNull()
        ->and($result['id'])->toBe(550)
        ->and($result['title'])->toBe('Fight Club')
        ->and($result['cast'])->toHaveCount(12)
        ->and($result['trailerKey'])->toBe('trailer456');
});

test('fetchEnrichmentData caches results for 24 hours', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response(tmdbCredits(), 200),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);

    // First call — hits TMDB
    $service->fetchEnrichmentData(550);
    // Second call — should use cache
    $service->fetchEnrichmentData(550);

    // 3 calls for first request (detail + credits + videos), 0 for second (cached)
    Http::assertSentCount(3);
});

test('fetchEnrichmentData returns null when TMDB is unavailable', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response([], 500),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->fetchEnrichmentData(550);

    expect($result)->toBeNull();
});

test('fetchEnrichmentData returns null when no API key configured', function () {
    config(['services.tmdb.api_key' => null]);

    Cache::flush();

    $service = new TmdbService;
    $result = $service->fetchEnrichmentData(550);

    expect($result)->toBeNull();

    Http::assertNothingSent();
});

test('fetchEnrichmentData uses negative caching after failure', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response([], 500),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);

    // First call — hits TMDB (with retries), fails, caches sentinel
    $result1 = $service->fetchEnrichmentData(550);
    expect($result1)->toBeNull();

    $firstCallCount = count(Http::recorded());

    // Second call — should return null from sentinel, no additional API calls
    $result2 = $service->fetchEnrichmentData(550);
    expect($result2)->toBeNull();

    // No additional HTTP requests after the first call
    expect(count(Http::recorded()))->toBe($firstCallCount);
});

// --- enrichMovie tests ---

test('enrichMovie updates model fields from TMDB data', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response(tmdbCredits(), 200),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $movie = Movie::factory()->create([
        'tmdb_id' => 550,
        'tagline' => 'Old tagline',
        'cast' => null,
        'tmdb_enriched_at' => null,
    ]);

    $service = app(TmdbService::class);
    $result = $service->enrichMovie($movie);

    expect($result)->toBeTrue();

    $movie->refresh();
    expect($movie->tagline)->toBe('Mischief. Mayhem. Soap.')
        ->and($movie->cast)->toHaveCount(12)
        ->and($movie->trailer_key)->toBe('trailer456')
        ->and($movie->tmdb_enriched_at)->not->toBeNull();
});

test('enrichMovie returns false when tmdb_id is null', function () {
    $movie = Movie::factory()->create(['tmdb_id' => null]);

    $service = app(TmdbService::class);
    $result = $service->enrichMovie($movie);

    expect($result)->toBeFalse();

    Http::assertNothingSent();
});

test('fetchEnrichmentData marks result as partial when credits fail', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response([], 500),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->fetchEnrichmentData(550);

    expect($result)->not->toBeNull()
        ->and($result['_partial'])->toBeTrue()
        ->and($result['cast'])->toBeEmpty()
        ->and($result['trailerKey'])->toBe('trailer456');
});

test('fetchEnrichmentData does not cache partial results', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response([], 500),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);

    // First call — partial result, should NOT be cached
    $service->fetchEnrichmentData(550);
    $firstCallCount = count(Http::recorded());

    // Second call — should hit TMDB again since partial results aren't cached
    $service->fetchEnrichmentData(550);

    expect(count(Http::recorded()))->toBeGreaterThan($firstCallCount);
});

test('enrichMovie preserves cast and trailer on partial TMDB failure', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response([], 500),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $existingCast = [['id' => 1, 'name' => 'Existing Actor', 'character' => 'Hero', 'profileUrl' => null]];

    $movie = Movie::factory()->create([
        'tmdb_id' => 550,
        'tagline' => 'Old tagline',
        'cast' => $existingCast,
        'trailer_key' => 'existing-trailer',
        'tmdb_enriched_at' => now()->subDay(),
    ]);

    $service = app(TmdbService::class);
    $result = $service->enrichMovie($movie);

    expect($result)->toBeTrue();

    $movie->refresh();
    // Detail fields should update (tagline comes from detail endpoint which succeeded)
    expect($movie->tagline)->toBe('Mischief. Mayhem. Soap.')
        // Cast and trailer should be preserved from existing data
        ->and($movie->cast)->toHaveCount(1)
        ->and($movie->cast[0]['name'])->toBe('Existing Actor')
        ->and($movie->trailer_key)->toBe('existing-trailer')
        // tmdb_enriched_at should NOT be updated on partial enrichment
        ->and($movie->tmdb_enriched_at->toDateString())->toBe(now()->subDay()->toDateString());
});

test('enrichMovie preserves local data when TMDB is unavailable', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/999' => Http::response([], 500),
    ]);

    Cache::flush();

    $movie = Movie::factory()->create([
        'tmdb_id' => 999,
        'tagline' => 'Original tagline',
        'cast' => [['id' => 1, 'name' => 'Local Actor']],
        'poster_url' => 'https://original-poster.jpg',
    ]);

    $service = app(TmdbService::class);
    $result = $service->enrichMovie($movie);

    expect($result)->toBeFalse();

    $movie->refresh();
    expect($movie->tagline)->toBe('Original tagline')
        ->and($movie->cast)->toHaveCount(1)
        ->and($movie->poster_url)->toBe('https://original-poster.jpg');
});
