<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MovieListResource;
use App\Http\Resources\MovieResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\Location;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'now_showing');
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

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

        return $this->successResponse(new MovieResource($movie));
    }

    public function showtimes(Request $request, Location $location, string $slug): JsonResponse
    {
        $movie = Movie::where('slug', $slug)->first();

        if (! $movie) {
            return $this->errorResponse(['message' => 'Movie not found'], 404);
        }

        // Validate the optional date query param — without this an
        // empty/malformed value (e.g. ?date= or ?date=not-a-date)
        // would reach Postgres via whereDate() and raise a 500.
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? null;

        $query = $movie->showtimes()
            ->whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
            ->with('movie', 'auditorium')
            ->where('start_time', '>', now())
            ->orderBy('start_time');

        if ($date !== null) {
            $query->whereDate('start_time', $date);
        } else {
            // Default: next 14 days of upcoming showtimes so the
            // frontend ShowtimeSelector can render date tabs without
            // needing a per-day fetch.
            $query->where('start_time', '<=', now()->addDays(14));
        }

        $showtimes = $query->get();

        return $this->successResponse(ShowtimeResource::collection($showtimes));
    }
}
