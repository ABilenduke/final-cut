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
        // Test users — only in local/testing environments
        if (app()->environment('local', 'testing')) {
            User::firstOrCreate(
                ['email' => 'test@finalcut.test'],
                [
                    'name' => 'Test User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'loyalty_tier' => LoyaltyTier::Premier,
                    'loyalty_points' => 500,
                    'premier_expiry' => now()->addYear(),
                ],
            );

            User::firstOrCreate(
                ['email' => 'member@finalcut.test'],
                [
                    'name' => 'Member User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'loyalty_tier' => LoyaltyTier::Member,
                    'loyalty_points' => 50,
                ],
            );
        }

        // Additional users
        User::factory(10)->create();

        // Domain seeders in dependency order
        $this->call([
            AdminRolesAndPermissionsSeeder::class,
            MovieSeeder::class,
            AuditoriumSeeder::class,
            ShowtimeSeeder::class,
            CalendarEventSeeder::class,
            MenuItemSeeder::class,
        ]);

        // Test-account-dependent seeders — only in local/testing
        // These seeders reference test@finalcut.test and member@finalcut.test
        if (app()->environment('local', 'testing')) {
            $this->call([
                BookingSeeder::class,
                GiftCardSeeder::class,
            ]);
        }
    }
}
