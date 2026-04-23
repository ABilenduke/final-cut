<?php

namespace Database\Factories;

use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeatHold> */
class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    public function definition(): array
    {
        return [
            'showtime_id' => Showtime::factory(),
            'seat_id' => Seat::factory(),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
