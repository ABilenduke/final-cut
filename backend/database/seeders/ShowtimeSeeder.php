<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::where('status', MovieStatus::NowShowing)->get();
        $locations = Location::with('auditoriums')->get();
        // Screen times spread morning → late evening so at least one
        // slot is always in the future regardless of wall-clock time.
        $screenTimes = ['10:00', '13:00', '16:00', '19:00', '21:30'];

        foreach (range(-3, 13) as $dayOffset) {
            $date = Carbon::today()->addDays($dayOffset);

            foreach ($locations as $location) {
                $locationAuditoriums = $location->auditoriums;
                if ($locationAuditoriums->isEmpty()) {
                    continue;
                }

                foreach ($movies as $movie) {
                    // Every (movie × location × day) targets 3 showtimes
                    // covering morning, afternoon, and evening.
                    $timesForDay = fake()->randomElements($screenTimes, 3);
                    sort($timesForDay);

                    foreach ($timesForDay as $time) {
                        $startTime = $date->copy()->setTimeFromTimeString($time);

                        // Pick the first auditorium without an overlap — the
                        // EXCLUDE USING gist constraint (showtimes_no_overlap)
                        // would reject a colliding insert otherwise. Each
                        // auditorium has its own cleanup_minutes, so end_time
                        // is computed via ShowtimeService::computeEndTime so
                        // seeded rows use the same formula as the write path.
                        $chosenAuditorium = null;
                        $chosenEndTime = null;
                        foreach ($locationAuditoriums->shuffle() as $auditorium) {
                            $endTime = ShowtimeService::computeEndTime($movie, $auditorium, $startTime);

                            $hasConflict = Showtime::where('auditorium_id', $auditorium->id)
                                ->whereNull('cancelled_at')
                                ->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime)
                                ->exists();

                            if (! $hasConflict) {
                                $chosenAuditorium = $auditorium;
                                $chosenEndTime = $endTime;
                                break;
                            }
                        }

                        if ($chosenAuditorium === null || $chosenEndTime === null) {
                            continue;
                        }

                        Showtime::create([
                            'movie_id' => $movie->id,
                            'auditorium_id' => $chosenAuditorium->id,
                            'start_time' => $startTime,
                            'end_time' => $chosenEndTime,
                            'price_standard' => 1200,
                            'price_premium' => 1800,
                            'price_accessible' => 1000,
                        ]);
                    }
                }
            }
        }
    }
}
