<?php

namespace Database\Factories;

use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobOpening> */
class JobOpeningFactory extends Factory
{
    protected $model = JobOpening::class;

    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Operations', 'Guest Services', 'Food & Beverage']),
            'employment_type' => 'Full-time',
            'description' => fake()->paragraph(),
            'display_order' => fake()->numberBetween(0, 20),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
