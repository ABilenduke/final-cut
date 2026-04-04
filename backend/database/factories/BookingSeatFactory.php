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
        $showtime = Showtime::factory()->create();
        $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);
        $seat = Seat::factory()->create(['auditorium_id' => $showtime->auditorium_id]);

        return [
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'section' => fake()->randomElement(['Standard', 'Premium', 'Accessible']),
            'price' => 1200,
        ];
    }
}
