<?php

namespace Database\Factories;

use App\Models\Auditorium;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditorium> */
class AuditoriumFactory extends Factory
{
    protected $model = Auditorium::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Screen 1', 'Screen 2', 'Screen 3', 'IMAX', 'Screen 4', 'Dolby']),
            'total_seats' => fake()->numberBetween(50, 300),
        ];
    }
}
