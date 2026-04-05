<?php

namespace Database\Factories;

use App\Enums\MenuCategory;
use App\Models\Location;
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
            'unavailable_at' => null,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['unavailable_at' => now()]);
    }

    public function forLocation(Location $location, array $pivotAttributes = []): static
    {
        return $this->afterCreating(function (MenuItem $item) use ($location, $pivotAttributes) {
            $location->menuItems()->attach($item->id, $pivotAttributes);
        });
    }
}
