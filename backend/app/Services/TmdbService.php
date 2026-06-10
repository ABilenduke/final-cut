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

        $detail = $this->get("/movie/{$tmdbId}", [
            'append_to_response' => 'credits,videos',
        ]);

        if ($detail === null) {
            $this->cacheFailure($failKey);

            return null;
        }

        if (empty($detail['id']) || empty($detail['title'])) {
            Log::warning('TMDB returned invalid detail payload', [
                'tmdb_id' => $tmdbId,
                'detail_keys' => array_keys($detail),
            ]);
            $this->cacheFailure($failKey);

            return null;
        }

        $credits = $detail['credits'] ?? null;
        $videos = $detail['videos'] ?? null;
        $partial = $credits === null || $videos === null;

        if ($partial) {
            Log::info('TMDB partial enrichment for movie', ['tmdb_id' => $tmdbId, 'credits' => $credits !== null, 'videos' => $videos !== null]);
        }

        $data = $this->tmdbToMovie($detail, $credits ?? ['cast' => [], 'crew' => []], $videos ?? ['results' => []]);
        $data['_partial'] = $partial;

        if (! $partial) {
            Cache::put($cacheKey, $data, 86400);
        }

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

        $partial = $data['_partial'] ?? false;

        $fields = [
            'tagline' => $data['tagline'] ?: $movie->tagline,
            'synopsis' => $data['synopsis'] ?: $movie->synopsis,
            'runtime' => $data['runtime'] ?: $movie->runtime,
            'rating' => $data['rating'] ?: $movie->rating,
            'genres' => $data['genres'] ?: $movie->genres,
            'poster_url' => $data['posterUrl'] ?: $movie->poster_url,
            'backdrop_url' => $data['backdropUrl'] ?: $movie->backdrop_url,
        ];

        if (! $partial) {
            $fields['trailer_key'] = ! empty($data['trailerKey']) ? $data['trailerKey'] : $movie->trailer_key;
            $fields['cast'] = ! empty($data['cast']) ? $data['cast'] : $movie->cast;
            // Crew credits: TMDB fills the blanks, admin-authored values win
            // (admin-v5 Plan 02). Blank admin strings count as unfilled.
            $adminCredits = array_filter(
                $movie->credits ?? [],
                fn ($value) => is_string($value) && trim($value) !== '',
            );
            $merged = array_merge($data['crewCredits'] ?? [], $adminCredits);
            $fields['credits'] = $merged !== [] ? $merged : $movie->credits;
            $fields['tmdb_enriched_at'] = now();
        } else {
            // Partial: preserve existing cast/trailer, don't update timestamp so retry happens sooner
            $fields['trailer_key'] = $movie->trailer_key;
            $fields['cast'] = $movie->cast;
        }

        $movie->update(array_filter($fields, fn ($value) => $value !== null));

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
            ->first(fn (array $v) => ($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube');

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
            'crewCredits' => $this->mapCrewCredits($credits['crew'] ?? []),
        ];
    }

    /**
     * Map the TMDB crew list onto the movie's editorial credit fields
     * (admin-v5 Plan 02). Multiple holders of one job join with a comma;
     * jobs outside the mapping are ignored. aspect/advisory have no TMDB
     * source and stay admin-only.
     *
     * @param  array<int, array<string, mixed>>  $crew
     * @return array<string, string>
     */
    private function mapCrewCredits(array $crew): array
    {
        $jobMap = [
            'Director' => 'director',
            'Screenplay' => 'screenplay',
            'Writer' => 'screenplay',
            'Director of Photography' => 'cinematography',
            'Editor' => 'editor',
            'Original Music Composer' => 'composer',
        ];

        $credits = [];
        foreach ($crew as $member) {
            $field = $jobMap[$member['job'] ?? ''] ?? null;
            $name = $member['name'] ?? null;
            if ($field === null || ! is_string($name) || $name === '') {
                continue;
            }
            $credits[$field] = isset($credits[$field]) ? "{$credits[$field]}, {$name}" : $name;
        }

        return $credits;
    }

    private function cacheFailure(string $failKey): void
    {
        Cache::put($failKey, true, 300);
    }

    private function buildImageUrl(string $size, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return $this->imageBaseUrl.$size.$path;
    }
}
