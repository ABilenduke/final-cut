<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = fake()->unique()->city() . ' Cinema';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
