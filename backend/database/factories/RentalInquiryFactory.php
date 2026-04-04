<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Enums\RentalEventType;
use App\Models\RentalInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalInquiry> */
class RentalInquiryFactory extends Factory
{
    protected $model = RentalInquiry::class;

    public function definition(): array
    {
        return [
            'event_type' => fake()->randomElement(RentalEventType::cases()),
            'preferred_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'guest_count' => fake()->numberBetween(10, 200),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'message' => fake()->optional()->paragraph(),
            'status' => InquiryStatus::Pending,
        ];
    }
}
