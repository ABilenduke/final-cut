<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShowtimeSeeder extends Seeder
{
    /**
     * Per-location slate of movie indices (into the now-showing list) and the
     * five screen times we'll attempt each day. The slates overlap on 6 films
     * and leave 3 venue-exclusive titles per side so the two locations don't
     * look like clones — Downtown leans matinee, Eastside leans late-evening.
     *
     * Indices are positional into the result of `Movie::where(status, NowShowing)
     * ->orderBy('id')->get()`. MovieSeeder inserts the now-showing list in a
     * stable, append-only order so positional addressing works in practice;
     * out-of-range indices are silently skipped, which keeps this seeder
     * resilient if the now-showing list shrinks below 12 films.
     */
    private const LOCATION_PROGRAMMING = [
        'downtown' => [
            'movie_indices' => [0, 1, 2, 3, 4, 5, 6, 7, 8],
            'times' => ['10:30', '13:30', '16:30', '19:30', '21:45'],
        ],
        'eastside' => [
            'movie_indices' => [3, 4, 5, 6, 7, 8, 9, 10, 11],
            'times' => ['11:00', '14:30', '17:30', '20:00', '22:30'],
        ],
    ];

    private const DAY_RANGE_START = -3;

    private const DAY_RANGE_END = 13;

    private const TARGET_SHOWTIMES_PER_MOVIE_PER_DAY = 3;

    public function run(): void
    {
        // Load now-showing movies in stable id order so the LOCATION_PROGRAMMING
        // positional indices resolve deterministically across runs.
        $movies = Movie::where('status', MovieStatus::NowShowing)
            ->orderBy('id')
            ->get();
        $moviesByIndex = $movies->values();

        $locations = Location::with('auditoriums')->get()->keyBy('slug');

        $now = now();
        $rows = [];

        foreach (self::LOCATION_PROGRAMMING as $slug => $programme) {
            $location = $locations->get($slug);
            if ($location === null || $location->auditoriums->isEmpty()) {
                continue;
            }

            $slate = collect($programme['movie_indices'])
                ->map(fn (int $index) => $moviesByIndex->get($index))
                ->filter()
                ->values();

            if ($slate->isEmpty()) {
                continue;
            }

            $screenTimes = $programme['times'];
            $auditoriums = $location->auditoriums;

            foreach (range(self::DAY_RANGE_START, self::DAY_RANGE_END) as $dayOffset) {
                $date = Carbon::today()->addDays($dayOffset);

                // Per-day, per-auditorium interval map. Each entry is a list of
                // [start, end) pairs already claimed for that auditorium on this
                // date — mirrors Postgres `tsrange(..., '[)')` semantics so a
                // bulk insert at the end of run() can't trip the
                // showtimes_no_overlap exclusion constraint.
                $occupiedByAuditorium = [];

                // Shuffle the slate so the same film isn't always first to
                // claim prime-time slots across days.
                foreach ($slate->shuffle() as $movie) {
                    $placed = 0;

                    foreach (collect($screenTimes)->shuffle() as $time) {
                        if ($placed >= self::TARGET_SHOWTIMES_PER_MOVIE_PER_DAY) {
                            break;
                        }

                        $startTime = $date->copy()->setTimeFromTimeString($time);

                        $chosen = $this->findOpenAuditorium(
                            $auditoriums,
                            $movie,
                            $startTime,
                            $occupiedByAuditorium,
                        );

                        if ($chosen === null) {
                            continue;
                        }

                        [$auditoriumId, $endTime] = $chosen;

                        $occupiedByAuditorium[$auditoriumId][] = [$startTime, $endTime];

                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'movie_id' => $movie->id,
                            'auditorium_id' => $auditoriumId,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'price_standard' => 1200,
                            'price_premium' => 1800,
                            'price_accessible' => 1000,
                            'cancelled_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $placed++;
                    }
                }
            }
        }

        // Chunked bulk insert mirrors the AuditoriumSeeder pattern — one round
        // trip per chunk instead of one per row. The in-memory conflict map
        // above guarantees we won't violate showtimes_no_overlap.
        foreach (array_chunk($rows, 500) as $chunk) {
            Showtime::insert($chunk);
        }
    }

    /**
     * Walk the location's auditoriums (in shuffled order) and return the first
     * one whose existing [start, end) intervals don't collide with the proposed
     * showtime. Returns [auditoriumId, endTime] or null if every auditorium is
     * busy at that minute.
     */
    private function findOpenAuditorium(
        $auditoriums,
        Movie $movie,
        CarbonInterface $startTime,
        array $occupiedByAuditorium,
    ): ?array {
        foreach ($auditoriums->shuffle() as $auditorium) {
            $endTime = ShowtimeService::computeEndTime($movie, $auditorium, $startTime);

            $intervals = $occupiedByAuditorium[$auditorium->id] ?? [];

            if ($this->hasConflict($intervals, $startTime, $endTime)) {
                continue;
            }

            return [$auditorium->id, $endTime];
        }

        return null;
    }

    /**
     * Half-open `[start, end)` overlap check — matches the Postgres exclusion
     * constraint added in 2026_04_24_000000_add_showtime_exclusion_constraint.
     * Two intervals conflict when new.start < existing.end AND new.end > existing.start.
     */
    private function hasConflict(array $intervals, CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ($intervals as [$existingStart, $existingEnd]) {
            if ($start->lt($existingEnd) && $end->gt($existingStart)) {
                return true;
            }
        }

        return false;
    }
}
