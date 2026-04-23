<?php

namespace Database\Factories;

use App\Enums\LoyaltyAdjustmentType;
use App\Models\AdminUser;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAdjustment>
 */
class LoyaltyAdjustmentFactory extends Factory
{
    protected $model = LoyaltyAdjustment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'admin_user_id' => AdminUser::factory(),
            'points_delta' => $this->faker->numberBetween(-500, 500),
            'reason' => $this->faker->sentence(),
            'change_type' => $this->faker->randomElement(LoyaltyAdjustmentType::cases())->value,
        ];
    }
}
