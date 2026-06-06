<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovieStatus;
use App\Http\Resources\MovieListResource;
use App\Http\Resources\MovieResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\Location;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Validate ?status BEFORE resolving the location, so a bad status
        // surfaces its own 422 instead of being masked by a bad-?location 422.
        // NOTE: per_page is intentionally NOT validated here — it is CLAMPED
        // below. Internal high-volume callers (the sitemap source requests
        // per_page=500) rely on the long-standing clamp; 422-ing a large value
        // would silently drop every dynamic URL from sitemap.xml.
        validator($request->query(), [
            'status' => ['nullable', Rule::enum(MovieStatus::class)],
        ])->validate();

        $locationSlug = $this->resolveOptionalLocationSlug($request);
        if ($locationSlug instanceof JsonResponse) {
            return $locationSlug;
        }

        // Read from query() (the validated bag). input() would merge a GET body
        // with precedence, letting an unvalidated body value slip past the
        // query-bag validator above.
        $status = $request->query('status', 'now_showing');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = Movie::where('status', $status)->orderBy('title');

        if (! empty($locationSlug)) {
            $query->whereHas('showtimes', fn ($q) => $q
                ->where('start_time', '>=', now())
                ->whereNull('cancelled_at')
                ->whereHas('auditorium.location', fn ($q) => $q->where('slug', $locationSlug))
            );
        }

        $paginator = $query->paginate($perPage);

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
            return $this->errorResponse([['message' => 'Movie not found']], 404);
        }

        return $this->successResponse(new MovieResource($movie));
    }

    public function showtimes(Request $request, Location $location, string $slug): JsonResponse
    {
        $movie = Movie::where('slug', $slug)->first();

        if (! $movie) {
            return $this->errorResponse([['message' => 'Movie not found']], 404);
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
            ->whereNull('cancelled_at')
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
