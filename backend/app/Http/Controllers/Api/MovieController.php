<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MovieListResource;
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
        $perPage = (int) $request->input('per_page', 20);

        $paginator = Movie::where('status', $status)
            ->orderBy('title')
            ->paginate($perPage);

        return $this->successResponse(
            MovieListResource::collection($paginator->items()),
            [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
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
