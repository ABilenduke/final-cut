<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
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

            foreach ($movies as $movie) {
                foreach ($locations as $location) {
                    $locationAuditoriums = $location->auditoriums;
                    if ($locationAuditoriums->isEmpty()) {
                        continue;
                    }

                    // Every (movie × location × day) gets 3 showtimes
                    // covering morning, afternoon, and evening.
                    $timesForDay = fake()->randomElements($screenTimes, 3);
                    sort($timesForDay);

                    foreach ($timesForDay as $time) {
                        $auditorium = $locationAuditoriums->random();
                        $startTime = $date->copy()->setTimeFromTimeString($time);
                        $endTime = $startTime->copy()->addMinutes($movie->runtime + 15);

                        Showtime::create([
                            'movie_id' => $movie->id,
                            'auditorium_id' => $auditorium->id,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
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
