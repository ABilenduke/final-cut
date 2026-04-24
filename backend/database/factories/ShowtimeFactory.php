<?php

namespace Database\Factories;

use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Showtime> */
class ShowtimeFactory extends Factory
{
    protected $model = Showtime::class;

    public function definition(): array
    {
        $runtime = fake()->numberBetween(90, 180);

        return [
            'movie_id' => Movie::factory(),
            'auditorium_id' => Auditorium::factory(),
            'start_time' => fake()->dateTimeBetween('now', '+14 days'),
            // Compute end_time from whatever start_time actually lands on the row —
            // whether that's the factory default above or a test-supplied override —
            // so `end > start` stays true under the tsrange check constraint.
            'end_time' => fn (array $attrs) => Carbon::parse($attrs['start_time'])->addMinutes($runtime),
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
        ];
    }
}
