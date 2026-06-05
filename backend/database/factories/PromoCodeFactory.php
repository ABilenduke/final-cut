<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PromoCode> */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'amount' => 10,
            'usage_limit' => null,
            'per_user_limit' => null,
            'uses_count' => 0,
            'expires_at' => null,
            'deactivated_at' => null,
        ];
    }

    public function percentage(int $pct): static
    {
        return $this->state(fn () => [
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'amount' => $pct,
        ]);
    }

    public function fixed(int $cents): static
    {
        return $this->state(fn () => [
            'discount_type' => PromoCode::TYPE_FIXED_CENTS,
            'amount' => $cents,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['deactivated_at' => now()]);
    }

    public function withUsage(int $count, ?int $limit = null): static
    {
        return $this->state(fn () => [
            'uses_count' => $count,
            'usage_limit' => $limit,
        ]);
    }
}
