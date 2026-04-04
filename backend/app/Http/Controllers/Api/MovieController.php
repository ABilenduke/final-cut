<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MovieResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\Movie;
use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(private TmdbService $tmdb) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'now_showing');
        $page = (int) $request->input('page', 1);

        $tmdbResult = match ($status) {
            'coming_soon' => $this->tmdb->upcoming($page),
            default => $this->tmdb->nowPlaying($page),
        };

        // TMDB data already arrives in the correct camelCase format from TmdbService
        $movies = collect($tmdbResult['movies'])->map(function (array $movie) use ($status) {
            $movie['status'] = $status;

            return $movie;
        })->values();

        return $this->successResponse(
            $movies,
            [
                'total' => $tmdbResult['total'] ?? 0,
                'page' => $tmdbResult['page'] ?? 1,
            ]
        );
    }

    public function show(string $slug): JsonResponse
    {
        $movie = Movie::where('slug', $slug)->first();

        if (! $movie) {
            return $this->errorResponse(['message' => 'Movie not found'], 404);
        }

        $tmdbData = $this->tmdb->movieDetail($movie->id);

        if ($tmdbData) {
            // Merge TMDB data onto local model: TMDB provides cast, trailer, enriched metadata
            // Local model provides slug, status, and is the source of truth for what's "ours"
            $movie->tagline = $tmdbData['tagline'] ?: $movie->tagline;
            $movie->synopsis = $tmdbData['synopsis'] ?: $movie->synopsis;
            $movie->runtime = $tmdbData['runtime'] ?: $movie->runtime;
            $movie->rating = $tmdbData['rating'] ?: $movie->rating;
            $movie->genres = $tmdbData['genres'] ?: $movie->genres;
            $movie->poster_url = $tmdbData['posterUrl'] ?: $movie->poster_url;
            $movie->backdrop_url = $tmdbData['backdropUrl'] ?: $movie->backdrop_url;
            $movie->trailer_key = $tmdbData['trailerKey'] ?? $movie->trailer_key;
            $movie->cast = $tmdbData['cast'] ?? [];
        }

        return $this->successResponse(new MovieResource($movie));
    }

    public function showtimes(Request $request, string $slug): JsonResponse
    {
        $movie = Movie::where('slug', $slug)->first();

        if (! $movie) {
            return $this->errorResponse(['message' => 'Movie not found'], 404);
        }

        $date = $request->input('date', now()->toDateString());

        $showtimes = $movie->showtimes()
            ->with('movie', 'auditorium')
            ->whereDate('start_time', $date)
            ->orderBy('start_time')
            ->get();

        return $this->successResponse(ShowtimeResource::collection($showtimes));
    }
}
