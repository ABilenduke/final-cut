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
        $targetPerDay = 3;

        foreach (range(-3, 13) as $dayOffset) {
            $date = Carbon::today()->addDays($dayOffset);

            foreach ($locations as $location) {
                $locationAuditoriums = $location->auditoriums;
                if ($locationAuditoriums->isEmpty()) {
                    continue;
                }

                // Shuffle movies per (location × day) so no single movie is
                // consistently the last to compete for slots — earlier
                // iteration order won the conflict race, leaving later
                // movies (e.g. the home-page "featured" pick) with zero
                // showtimes and a broken purchase funnel in dev/e2e.
                foreach ($movies->shuffle() as $movie) {
                    // For each (movie × location × day) walk every screen
                    // time in shuffled order and place up to $targetPerDay
                    // showtimes, breaking only after we hit the target.
                    // The previous implementation picked 3 random times up
                    // front; if all 3 collided with already-seeded showtimes
                    // the movie got nothing that day. Trying every time
                    // slot guarantees each movie gets a fair shot at the
                    // remaining auditorium capacity.
                    $placed = 0;
                    foreach (collect($screenTimes)->shuffle() as $time) {
                        if ($placed >= $targetPerDay) {
                            break;
                        }

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
                        $placed++;
                    }
                }
            }
        }
    }
}
