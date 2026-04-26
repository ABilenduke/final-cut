<?php

namespace App\Console\Commands;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Provisions an admin: creates a `User` if one doesn't already exist for the
 * email, ensures an `AdminProfile` row exists, and assigns a role. Idempotent
 * for promotion — running against an existing customer's email reuses the
 * existing User without creating a second identity.
 *
 * `--reset-password` skips creation and only resets the password on the
 * existing User; it ALSO requires that the User have an admin profile, since
 * this command is for admin provisioning, not customer support.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'admin:create-user
        {--name= : Full name. Used only when creating a new User; ignored when promoting an existing User or with --reset-password (unless --reassign-role also supplied with no existing AdminProfile).}
        {--email= : Email address}
        {--password= : Password. Required when creating a new User; otherwise optional and applied to the existing User if supplied.}
        {--role= : Role (admin, manager, ops). Defaults to admin when omitted non-interactively; prompts otherwise. Ignored with --reset-password unless --reassign-role is also passed.}
        {--reset-password : Reset the password of an existing admin user matched by --email. Creation is skipped; role is not changed unless --reassign-role is also set.}
        {--reassign-role : With --reset-password, also re-assign the role to the value of --role. Ignored without --reset-password.}';

    protected $description = 'Create an admin user (User + AdminProfile + role) or password-reset an existing admin';

    public function handle(): int
    {
        $email = strtolower($this->option('email') ?: $this->ask('Email'));

        if ($this->option('reset-password')) {
            $password = $this->option('password') ?: $this->secret('Password');

            return $this->resetPassword($email, $password);
        }

        $role = $this->option('role')
            ?: ($this->input->isInteractive()
                ? $this->choice('Role', ['admin', 'manager', 'ops'], 'admin')
                : 'admin');

        if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
            $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        // Branch: promote an existing User vs create a new one.
        if ($existing !== null) {
            // --password is allowed but optional when promoting; we don't
            // overwrite a customer's existing password without an explicit
            // value, since this command is also a customer-promotion path.
            $password = $this->option('password');
            $name = $this->option('name');

            return $this->promote($existing, $role, $password, $name);
        }

        // Brand-new User — name + password are required.
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->option('password') ?: $this->secret('Password');

        if ($name === null || $name === '' || $password === null || $password === '') {
            $this->error('Both --name and --password are required when creating a new admin.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $password, $role): void {
            /** @var User $user */
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            AdminProfile::create(['user_id' => $user->id]);

            $user->assignRole($role);
        });

        $this->info("Created admin user {$email} with role {$role}.");

        return self::SUCCESS;
    }

    /**
     * Promote an existing User: ensure an AdminProfile exists and (re)assign
     * the role. Optionally update name/password if those flags were passed.
     */
    private function promote(User $user, string $role, ?string $password, ?string $name): int
    {
        DB::transaction(function () use ($user, $role, $password, $name): void {
            $updates = [];
            if ($name !== null && $name !== '') {
                $updates['name'] = $name;
            }
            if ($password !== null && $password !== '') {
                $updates['password'] = $password;
            }
            if ($updates !== []) {
                $user->update($updates);
            }

            AdminProfile::firstOrCreate(['user_id' => $user->id]);

            // Use syncRoles so a user's previous role (if previously promoted)
            // is replaced rather than additively layered.
            $user->syncRoles([$role]);
        });

        $this->info("Promoted existing user {$user->email} to admin with role {$role}.");

        return self::SUCCESS;
    }

    private function resetPassword(string $email, string $password): int
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->adminProfile()->exists()) {
            $this->error("No admin user found with email {$email}. Drop the --reset-password flag to create a new account.");

            return self::FAILURE;
        }

        if ($this->option('reassign-role')) {
            $role = $this->option('role');

            if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
                $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");

                return self::FAILURE;
            }
        }

        $user->update(['password' => $password]);

        if ($this->option('reassign-role')) {
            $user->syncRoles([$this->option('role')]);
            $this->info("Reset password and reassigned role to {$this->option('role')} for {$email}.");
        } else {
            $this->info("Reset password for {$email}.");
        }

        return self::SUCCESS;
    }
}
