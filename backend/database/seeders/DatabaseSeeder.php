<?php

namespace Database\Seeders;

use App\Enums\LoyaltyTier;
use App\Models\AdminProfile;
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
            FeaturedSlideSeeder::class,
        ]);

        // Test-account-dependent seeders — only in local/testing
        // These seeders reference test@finalcut.test and member@finalcut.test
        if (app()->environment('local', 'testing')) {
            $this->provisionPersonalAdmin();

            $this->call([
                BookingSeeder::class,
                GiftCardSeeder::class,
            ]);
        }
    }

    /**
     * Bake the project owner in as a working admin so `make fresh` always
     * leaves a known-good login. Runs after AdminRolesAndPermissionsSeeder
     * so the `admin` role exists. Idempotent — re-running resets the password
     * back to the documented value.
     */
    private function provisionPersonalAdmin(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'andrewbilenduke@gmail.com'],
            [
                'name' => 'Andrew Bilenduke',
                'password' => 'Test@1234!!!',
                'email_verified_at' => now(),
            ],
        );

        AdminProfile::firstOrCreate(['user_id' => $user->id]);

        $user->syncRoles(['admin']);
    }
}
