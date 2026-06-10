<?php

namespace Database\Seeders;

use App\Enums\LoyaltyTier;
use App\Models\AdminProfile;
use App\Models\User;
use App\Support\SeederUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeders set explicit deterministic `id` values via SeederUuid. Those
        // keys are NOT in the models' #[Fillable] allowlists, so mass-assignment
        // would silently strip them and HasUuids would generate random ids —
        // defeating the whole point of deterministic seeding. Unguarding for the
        // duration of the seed (Model::unguarded restores guarding via finally,
        // so test isolation is preserved) lets the ids land. The global flag
        // also covers every sub-seeder invoked via $this->call() below.
        Model::unguarded(fn () => $this->seedAll());
    }

    private function seedAll(): void
    {
        // Test users — only in local/testing environments
        if (app()->environment('local', 'testing')) {
            User::firstOrCreate(
                ['email' => 'test@finalcut.test'],
                [
                    'id' => SeederUuid::for('user:test@finalcut.test'),
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
                    'id' => SeederUuid::for('user:member@finalcut.test'),
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
            TickerItemSeeder::class,
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
     * Seed a working admin account so `make fresh` always leaves a known-good
     * login. Runs after AdminRolesAndPermissionsSeeder so the `admin` role
     * exists. Idempotent — re-running resets the password back to whatever the
     * env says.
     *
     * Override per-environment via SEEDER_ADMIN_EMAIL / SEEDER_ADMIN_NAME /
     * SEEDER_ADMIN_PASSWORD (see backend/.env.example). Defaults are the
     * project owner's preferred dev credentials; forks can override without
     * editing this file. Gated by `app()->environment('local', 'testing')`
     * at the call site — never runs in staging/production.
     */
    private function provisionPersonalAdmin(): void
    {
        $email = (string) env('SEEDER_ADMIN_EMAIL', 'andrewbilenduke@gmail.com');
        $name = (string) env('SEEDER_ADMIN_NAME', 'Andrew Bilenduke');
        $password = (string) env('SEEDER_ADMIN_PASSWORD', 'Test@1234!!!');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'id' => SeederUuid::for("user:{$email}"),
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        AdminProfile::firstOrCreate(['user_id' => $user->id]);

        $user->syncRoles(['admin']);
    }
}
