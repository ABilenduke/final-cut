<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingSeat> */
class BookingSeatFactory extends Factory
{
    protected $model = BookingSeat::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'showtime_id' => Showtime::factory(),
            'seat_id' => Seat::factory(),
            'section' => fake()->randomElement(['Standard', 'Premium', 'Accessible']),
            'price' => 1200,
        ];
    }
}
