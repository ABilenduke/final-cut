<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->optional()->phoneNumber(),
            'date_of_birth' => fake()->optional()->date(),
            'avatar_url' => null,
            'loyalty_points' => 0,
            'loyalty_tier' => 'member',
            'premier_expiry' => null,
            'stripe_customer_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Attach an AdminProfile so this User can authenticate against the admin
     * panel. Role assignment is the caller's responsibility.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            AdminProfile::updateOrCreate(['user_id' => $user->id]);
        });
    }

    /** Composes with `admin()`; updateOrCreate so chaining order doesn't matter. */
    public function disabled(): static
    {
        return $this->afterCreating(function (User $user): void {
            AdminProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['disabled_at' => now()],
            );
        });
    }
}
