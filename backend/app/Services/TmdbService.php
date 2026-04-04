<?php

namespace App\Services;

use App\Models\Movie;
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

    /**
     * Fetch enrichment data from TMDB for a given movie ID.
     * Returns transformed movie data array, or null on failure.
     * Only called by the enrichment command — never in the request path.
     */
    public function fetchEnrichmentData(int $tmdbId): ?array
    {
        $failKey = "tmdb.fail.{$tmdbId}";
        $cacheKey = "tmdb.movie.{$tmdbId}";

        if (Cache::get($failKey)) {
            return null;
        }

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $detail = $this->get("/movie/{$tmdbId}");

        if ($detail === null) {
            Cache::put($failKey, true, 300);

            return null;
        }

        $credits = $this->get("/movie/{$tmdbId}/credits") ?? ['cast' => [], 'crew' => []];
        $videos = $this->get("/movie/{$tmdbId}/videos") ?? ['results' => []];

        $data = $this->tmdbToMovie($detail, $credits, $videos);

        Cache::put($cacheKey, $data, 86400);

        return $data;
    }

    /**
     * Enrich a local movie with TMDB data.
     * Only updates fields where TMDB returned non-empty values.
     * Returns true if enrichment succeeded, false otherwise.
     */
    public function enrichMovie(Movie $movie): bool
    {
        if (! $movie->tmdb_id) {
            return false;
        }

        $data = $this->fetchEnrichmentData($movie->tmdb_id);

        if ($data === null) {
            return false;
        }

        $movie->update(array_filter([
            'tagline' => $data['tagline'] ?: $movie->tagline,
            'synopsis' => $data['synopsis'] ?: $movie->synopsis,
            'runtime' => $data['runtime'] ?: $movie->runtime,
            'rating' => $data['rating'] ?: $movie->rating,
            'genres' => $data['genres'] ?: $movie->genres,
            'poster_url' => $data['posterUrl'] ?: $movie->poster_url,
            'backdrop_url' => $data['backdropUrl'] ?: $movie->backdrop_url,
            'trailer_key' => $data['trailerKey'] ?? $movie->trailer_key,
            'cast' => $data['cast'] ?? $movie->cast,
            'tmdb_enriched_at' => now(),
        ], fn ($value) => $value !== null));

        return true;
    }

    private function get(string $endpoint, array $query = []): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->connectTimeout(3)
                ->timeout(5)
                ->retry(2, 500, throw: false)
                ->get($this->baseUrl.$endpoint, $query);

            if ($response->failed()) {
                Log::warning('TMDB API request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::warning('TMDB API request exception', [
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

        return $this->imageBaseUrl.$size.$path;
    }
}
