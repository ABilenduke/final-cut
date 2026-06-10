<?php

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FaqItem> */
class FaqItemFactory extends Factory
{
    protected $model = FaqItem::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['Tickets & Booking', 'Policies', 'Accessibility']),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'display_order' => fake()->numberBetween(0, 20),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
