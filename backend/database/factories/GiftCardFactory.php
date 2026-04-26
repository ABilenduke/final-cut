<?php

namespace Database\Factories;

use App\Enums\GiftCardStatus;
use App\Models\AdminUser;
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

    public function active(): static
    {
        return $this->state(fn () => ['status' => GiftCardStatus::Active]);
    }

    public function depleted(): static
    {
        return $this->state(fn () => [
            'current_balance' => 0,
            'status' => GiftCardStatus::Depleted,
        ]);
    }

    public function voided(?AdminUser $by = null, string $reason = 'voided via admin'): static
    {
        return $this->state(fn () => [
            'status' => GiftCardStatus::Voided,
            'voided_at' => now(),
            'voided_reason' => $reason,
            'voided_by_admin_user_id' => $by?->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => GiftCardStatus::Expired]);
    }
}
