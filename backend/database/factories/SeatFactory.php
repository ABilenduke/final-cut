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

    private static int $sequence = 0;

    public function definition(): array
    {
        $index = self::$sequence++;
        $row = chr(ord('A') + intdiv($index, 15) % 26);
        $number = ($index % 15) + 1;

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
