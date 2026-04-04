<?php

namespace Database\Factories;

use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Showtime> */
class ShowtimeFactory extends Factory
{
    protected $model = Showtime::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+14 days');
        $runtime = fake()->numberBetween(90, 180);

        return [
            'movie_id' => Movie::factory(),
            'auditorium_id' => Auditorium::factory(),
            'start_time' => $start,
            'end_time' => (clone $start)->modify("+{$runtime} minutes"),
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
        ];
    }
}
