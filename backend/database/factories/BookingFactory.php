<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $subtotal = fake()->randomElement([1200, 2400, 3600]);

        return [
            'showtime_id' => Showtime::factory(),
            'user_id' => User::factory(),
            'guest_email' => null,
            'status' => BookingStatus::Confirmed,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'payment_method' => PaymentMethod::Card,
            'stripe_payment_intent_id' => 'pi_'.fake()->regexify('[a-zA-Z0-9]{24}'),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'guest_email' => fake()->safeEmail(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Cancelled]);
    }
}
