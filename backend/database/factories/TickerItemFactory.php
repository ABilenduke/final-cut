<?php

namespace Database\Factories;

use App\Models\TickerItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TickerItem> */
class TickerItemFactory extends Factory
{
    protected $model = TickerItem::class;

    public function definition(): array
    {
        return [
            'label' => fake()->randomElement(['Now Showing', 'Event', 'Members', 'Food']),
            'text' => fake()->sentence(4),
            'href' => null,
            'display_order' => fake()->numberBetween(0, 20),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
