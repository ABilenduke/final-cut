<?php

namespace Database\Factories;

use App\Enums\GiftCardStatus;
use App\Models\GiftCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GiftCard> */
class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;

    public function definition(): array
    {
        $balance = fake()->randomElement([2500, 5000, 7500, 10000]);

        return [
            'code' => strtoupper(Str::random(12)),
            'initial_balance' => $balance,
            'current_balance' => $balance,
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'sender_name' => fake()->name(),
            'message' => fake()->optional()->sentence(),
            'status' => GiftCardStatus::Active,
            'purchased_at' => now(),
        ];
    }

    public function depleted(): static
    {
        return $this->state(fn () => [
            'current_balance' => 0,
            'status' => GiftCardStatus::Depleted,
        ]);
    }
}
