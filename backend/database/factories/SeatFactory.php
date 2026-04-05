<?php

namespace Database\Factories;

use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Seat> */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

    public function definition(): array
    {
        $row = fake()->randomElement(range('A', 'J'));
        $number = fake()->numberBetween(1, 15);

        return [
            'auditorium_id' => Auditorium::factory(),
            'label' => $row.$number,
            'row' => $row,
            'number' => $number,
            'type' => SeatType::Standard,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn () => ['type' => SeatType::Premium]);
    }

    public function accessible(): static
    {
        return $this->state(fn () => ['type' => SeatType::Accessible]);
    }
}
