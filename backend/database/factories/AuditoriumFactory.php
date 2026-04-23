<?php

namespace Database\Factories;

use App\Models\Auditorium;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Auditorium> */
class AuditoriumFactory extends Factory
{
    protected $model = Auditorium::class;

    public function definition(): array
    {
        $name = 'Screen '.fake()->unique()->numberBetween(1, 10000);

        return [
            'location_id' => Location::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'cleanup_minutes' => 20,
            'notes' => null,
            'total_seats' => fake()->numberBetween(50, 300),
        ];
    }
}
