<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TmdbService
{
    private string $baseUrl;
    private string $imageBaseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.tmdb.base_url', 'https://api.themoviedb.org/3');
        $this->imageBaseUrl = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p/');
        $this->apiKey = config('services.tmdb.api_key');
    }

    public function nowPlaying(int $page = 1, string $region = 'US'): array
    {
        $cacheKey = "tmdb.now_playing.{$page}.{$region}";

        return Cache::remember($cacheKey, 1800, function () use ($page, $region) {
            $response = $this->get('/movie/now_playing', [
                'page' => $page,
                'region' => $region,
            ]);

            if ($response === null) {
                return ['movies' => [], 'total' => 0, 'page' => $page];
            }

            $movies = array_map(
                fn (array $movie) => $this->tmdbToMovie($movie),
                $response['results'] ?? []
            );

            return [
                'movies' => $movies,
                'total' => $response['total_results'] ?? 0,
                'page' => $response['page'] ?? $page,
            ];
        });
    }

    public function upcoming(int $page = 1, string $region = 'US'): array
    {
        $cacheKey = "tmdb.upcoming.{$page}.{$region}";

        return Cache::remember($cacheKey, 1800, function () use ($page, $region) {
            $response = $this->get('/movie/upcoming', [
                'page' => $page,
                'region' => $region,
            ]);

            if ($response === null) {
                return ['movies' => [], 'total' => 0, 'page' => $page];
            }

            $movies = array_map(
                fn (array $movie) => $this->tmdbToMovie($movie),
                $response['results'] ?? []
            );

            return [
                'movies' => $movies,
                'total' => $response['total_results'] ?? 0,
                'page' => $response['page'] ?? $page,
            ];
        });
    }

    public function movieDetail(int $id): ?array
    {
        $cacheKey = "tmdb.movie.{$id}";

        return Cache::remember($cacheKey, 3600, function () use ($id) {
            $detail = $this->get("/movie/{$id}");

            if ($detail === null) {
                return null;
            }

            $credits = $this->get("/movie/{$id}/credits") ?? ['cast' => [], 'crew' => []];
            $videos = $this->get("/movie/{$id}/videos") ?? ['results' => []];

            return $this->tmdbToMovie($detail, $credits, $videos);
        });
    }

    private function get(string $endpoint, array $query = []): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->get($this->baseUrl . $endpoint, $query);

            if ($response->failed()) {
                Log::warning("TMDB API request failed", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::warning("TMDB API request exception", [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function tmdbToMovie(array $detail, array $credits = [], array $videos = []): array
    {
        $cast = array_slice($credits['cast'] ?? [], 0, 12);

        $trailer = collect($videos['results'] ?? [])
            ->first(fn (array $v) => $v['type'] === 'Trailer' && $v['site'] === 'YouTube');

        return [
            'id' => $detail['id'],
            'slug' => Str::slug($detail['title']),
            'title' => $detail['title'],
            'tagline' => $detail['tagline'] ?? '',
            'synopsis' => $detail['overview'] ?? '',
            'runtime' => $detail['runtime'] ?? 0,
            'rating' => round($detail['vote_average'] ?? 0, 1),
            'releaseDate' => $detail['release_date'] ?? '',
            'genres' => $detail['genres'] ?? [],
            'cast' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'character' => $c['character'],
                'profileUrl' => $this->buildImageUrl('w185', $c['profile_path'] ?? null),
            ], $cast),
            'posterUrl' => $this->buildImageUrl('w500', $detail['poster_path'] ?? null),
            'backdropUrl' => $this->buildImageUrl('w1280', $detail['backdrop_path'] ?? null),
            'trailerKey' => $trailer['key'] ?? null,
        ];
    }

    private function buildImageUrl(string $size, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return $this->imageBaseUrl . $size . $path;
    }
}
