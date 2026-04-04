<?php

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

function tmdbNowPlayingResponse(): array
{
    return [
        'page' => 1,
        'total_results' => 1,
        'total_pages' => 1,
        'results' => [tmdbMovieDetail()],
    ];
}

// --- Tests ---

test('tmdbToMovie transforms TMDB data correctly', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $service = app(TmdbService::class);

    // Use reflection to call private method
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

test('nowPlaying fetches from TMDB and returns transformed movies', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/now_playing*' => Http::response(tmdbNowPlayingResponse(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->nowPlaying();

    expect($result)
        ->toHaveKey('movies')
        ->toHaveKey('total')
        ->toHaveKey('page')
        ->and($result['movies'])->toHaveCount(1)
        ->and($result['movies'][0]['title'])->toBe('Fight Club')
        ->and($result['page'])->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'movie/now_playing')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'region=US');
    });
});

test('upcoming fetches from TMDB and returns transformed movies', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/upcoming*' => Http::response(tmdbNowPlayingResponse(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->upcoming();

    expect($result)
        ->toHaveKey('movies')
        ->and($result['movies'])->toHaveCount(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'movie/upcoming');
    });
});

test('movieDetail fetches detail, credits, and videos from TMDB', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response(tmdbCredits(), 200),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->movieDetail(550);

    expect($result)
        ->not->toBeNull()
        ->and($result['id'])->toBe(550)
        ->and($result['title'])->toBe('Fight Club')
        ->and($result['cast'])->toHaveCount(12)
        ->and($result['trailerKey'])->toBe('trailer456');
});

test('nowPlaying caches results for 30 minutes', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/now_playing*' => Http::response(tmdbNowPlayingResponse(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);

    // First call — hits TMDB
    $service->nowPlaying();
    // Second call — should use cache
    $service->nowPlaying();

    Http::assertSentCount(1);
});

test('movieDetail caches results for 1 hour', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response(tmdbMovieDetail(), 200),
        'api.themoviedb.org/3/movie/550/credits*' => Http::response(tmdbCredits(), 200),
        'api.themoviedb.org/3/movie/550/videos*' => Http::response(tmdbVideos(), 200),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);

    $service->movieDetail(550);
    $service->movieDetail(550);

    // 3 calls for first request (detail + credits + videos), 0 for second (cached)
    Http::assertSentCount(3);
});

test('nowPlaying returns empty when TMDB is unavailable', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/now_playing*' => Http::response([], 500),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->nowPlaying();

    expect($result)
        ->toHaveKey('movies')
        ->and($result['movies'])->toBeEmpty();
});

test('movieDetail returns null when TMDB is unavailable', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550' => Http::response([], 500),
    ]);

    Cache::flush();

    $service = app(TmdbService::class);
    $result = $service->movieDetail(550);

    expect($result)->toBeNull();
});

test('nowPlaying returns empty when no API key configured', function () {
    config(['services.tmdb.api_key' => null]);

    Cache::flush();

    $service = new TmdbService();
    $result = $service->nowPlaying();

    expect($result['movies'])->toBeEmpty();

    Http::assertNothingSent();
});
