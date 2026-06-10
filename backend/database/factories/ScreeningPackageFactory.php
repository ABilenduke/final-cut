<?php

namespace Database\Factories;

use App\Models\ScreeningPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScreeningPackage> */
class ScreeningPackageFactory extends Factory
{
    protected $model = ScreeningPackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(10),
            'starting_price' => fake()->numberBetween(20000, 100000),
            'features' => [fake()->sentence(4), fake()->sentence(4)],
            'display_order' => fake()->numberBetween(0, 10),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
