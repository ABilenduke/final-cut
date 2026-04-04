<?php

namespace Database\Seeders;

use App\Enums\LoyaltyTier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Test user
        User::create([
            'name' => 'Test User',
            'email' => 'test@finalcut.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'loyalty_tier' => LoyaltyTier::Premier,
            'loyalty_points' => 500,
            'premier_expiry' => now()->addYear(),
        ]);

        // Additional users
        User::factory(10)->create();

        // Domain seeders in dependency order
        $this->call([
            MovieSeeder::class,
            AuditoriumSeeder::class,
            ShowtimeSeeder::class,
            CalendarEventSeeder::class,
            MenuItemSeeder::class,
            BookingSeeder::class,
        ]);
    }
}
