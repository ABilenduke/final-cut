<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create-user
        {--name= : Full name (ignored with --reset-password)}
        {--email= : Email address}
        {--password= : Password}
        {--role=admin : Role (admin, manager, ops) — ignored with --reset-password unless --reassign-role is also passed}
        {--reset-password : Reset the password of an existing admin user matched by --email. Creation is skipped; role is not changed unless --reassign-role is also set.}
        {--reassign-role : With --reset-password, also re-assign the role to the value of --role. Ignored without --reset-password.}';

    protected $description = 'Create or password-reset an admin user';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        if ($this->option('reset-password')) {
            return $this->resetPassword($email, $password);
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $role = $this->option('role') ?: $this->choice('Role', ['admin', 'manager', 'ops'], 'admin');

        if (AdminUser::where('email', $email)->exists()) {
            $this->error("Email {$email} already exists. Pass --reset-password to reset the password of the existing account instead.");

            return self::FAILURE;
        }

        if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
            $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");

            return self::FAILURE;
        }

        $user = AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->assignRole($role);

        $this->info("Created admin user {$email} with role {$role}.");

        return self::SUCCESS;
    }

    private function resetPassword(string $email, string $password): int
    {
        $user = AdminUser::where('email', $email)->first();

        if (! $user) {
            $this->error("No admin user found with email {$email}. Drop the --reset-password flag to create a new account.");

            return self::FAILURE;
        }

        $user->update(['password' => $password]);

        if ($this->option('reassign-role')) {
            $role = $this->option('role');

            if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
                $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");

                return self::FAILURE;
            }

            $user->syncRoles([$role]);
            $this->info("Reset password and reassigned role to {$role} for {$email}.");
        } else {
            $this->info("Reset password for {$email}.");
        }

        return self::SUCCESS;
    }
}
