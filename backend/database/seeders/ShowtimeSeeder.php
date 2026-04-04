<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::where('status', MovieStatus::NowShowing)->get();
        $auditoriums = Auditorium::all();
        $screenTimes = ['10:00', '13:00', '16:00', '19:00', '21:30'];

        foreach (range(0, 13) as $dayOffset) {
            $date = Carbon::today()->addDays($dayOffset);

            foreach ($movies as $movie) {
                // Each movie gets 2-3 showtimes per day
                $timesForDay = fake()->randomElements($screenTimes, fake()->numberBetween(2, 3));
                sort($timesForDay);

                foreach ($timesForDay as $time) {
                    $auditorium = $auditoriums->random();
                    $startTime = $date->copy()->setTimeFromTimeString($time);
                    $endTime = $startTime->copy()->addMinutes($movie->runtime + 15); // +15 for trailers/cleanup

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
