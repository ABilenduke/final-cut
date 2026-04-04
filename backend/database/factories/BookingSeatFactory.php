<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingSeat> */
class BookingSeatFactory extends Factory
{
    protected $model = BookingSeat::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'showtime_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])->showtime_id,
            'seat_id' => Seat::factory(),
            'section' => fake()->randomElement(['Standard', 'Premium', 'Accessible']),
            'price' => 1200,
        ];
    }
}
