<?php

namespace Database\Factories;

use App\Enums\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([499, 599, 799, 999, 1299, 1499]),
            'category' => fake()->randomElement(MenuCategory::cases()),
            'image_url' => null,
            'allergens' => [],
            'dietary' => [],
            'available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['available' => false]);
    }
}
