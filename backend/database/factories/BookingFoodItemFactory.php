<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingFoodItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BookingFoodItem> */
class BookingFoodItemFactory extends Factory
{
    protected $model = BookingFoodItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomElement([499, 599, 799, 999]);

        return [
            'booking_id' => Booking::factory(),
            'menu_item_id' => Str::uuid()->toString(),
            'name' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ];
    }
}
