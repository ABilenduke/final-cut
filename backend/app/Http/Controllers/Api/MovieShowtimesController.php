<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CrossLocationShowtimeResource;
use App\Models\Location;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /api/movies/{slug}/showtimes
 *
 * Cross-location public endpoint. Returns upcoming showtimes for a movie
 * across every venue, each entry stamped with its location payload so the
 * frontend can group by venue and compute distance captions client-side.
 *
 * Query params:
 *   ?date=YYYY-MM-DD  — narrow to a single calendar day (in each venue's
 *                        local timezone)
 *   ?days=N           — window size (default 7). Ignored when ?date= is set.
 *
 * Kept in a separate file from MovieController to avoid merge conflicts with
 * Backend Task 3, which modifies MovieController::index in parallel.
 */
class MovieShowtimesController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $movie = Movie::where('slug', $slug)->first();

        if (! $movie) {
            return $this->errorResponse(['message' => 'Movie not found'], 404);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $date = $validated['date'] ?? null;
        $days = isset($validated['days']) ? (int) $validated['days'] : 7;

        $query = $movie->showtimes()
            ->join('auditoriums', 'showtimes.auditorium_id', '=', 'auditoriums.id')
            ->join('locations', 'auditoriums.location_id', '=', 'locations.id')
            ->select('showtimes.*')
            ->whereNull('showtimes.cancelled_at')
            ->with(['movie', 'auditorium.location'])
            ->orderBy('showtimes.start_time');

        if ($date !== null) {
            // Narrow to a single calendar date, respecting each venue's local
            // timezone. We iterate over all locations that have showtimes for
            // this movie and build a UTC time range union for those whose local
            // calendar date matches the requested date.
            //
            // For each location timezone, the local date [date 00:00, date+1 00:00)
            // maps to a UTC window. We use a raw WHERE to let the DB do the
            // comparison efficiently against the indexed start_time column.
            //
            // Simpler approach: use CONVERT_TZ / AT TIME ZONE — but PostgreSQL
            // does not have CONVERT_TZ. Instead, we load distinct timezones for
            // the movie's showtimes and compute the UTC range per-zone.
            $timezones = Location::query()
                ->join('auditoriums', 'auditoriums.location_id', '=', 'locations.id')
                ->join('showtimes as s', 's.auditorium_id', '=', 'auditoriums.id')
                ->where('s.movie_id', $movie->id)
                ->whereNull('s.cancelled_at')
                ->distinct()
                ->pluck('locations.timezone')
                ->all();

            if (empty($timezones)) {
                return $this->successResponse(CrossLocationShowtimeResource::collection(collect()));
            }

            // Build OR conditions: for each timezone, keep showtimes whose
            // UTC start_time falls within the local calendar day. The
            // locations table is already joined above (line 49), so we filter
            // against locations.timezone directly instead of issuing a
            // correlated whereHas subquery per timezone.
            //
            // Compute both bounds in the venue timezone FIRST, then convert
            // each to UTC. addDay() on a tz-aware Carbon respects DST so the
            // local day is correctly 23/25 hours on transition dates; doing
            // the math purely in UTC would add a fixed 24h and miss showtimes
            // near the spring-forward / fall-back boundary.
            $query->where(function ($q) use ($timezones, $date) {
                foreach ($timezones as $tz) {
                    $localDayStart = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
                    $localDayEnd = $localDayStart->copy()->addDay();
                    $dayStartUtc = $localDayStart->copy()->utc();
                    $dayEndUtc = $localDayEnd->copy()->utc();

                    $q->orWhere(function ($inner) use ($dayStartUtc, $dayEndUtc, $tz) {
                        $inner->where('locations.timezone', $tz)
                            ->where('showtimes.start_time', '>=', $dayStartUtc)
                            ->where('showtimes.start_time', '<', $dayEndUtc);
                    });
                }
            });
        } else {
            $query->where('showtimes.start_time', '>', now())
                ->where('showtimes.start_time', '<=', now()->addDays($days));
        }

        $showtimes = $query->get();

        return $this->successResponse(CrossLocationShowtimeResource::collection($showtimes));
    }
}
