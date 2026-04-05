<?php

namespace Database\Factories;

use App\Models\Auditorium;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditorium> */
class AuditoriumFactory extends Factory
{
    protected $model = Auditorium::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Screen ' . fake()->unique()->numberBetween(1, 10000),
            'total_seats' => fake()->numberBetween(50, 300),
        ];
    }
}
