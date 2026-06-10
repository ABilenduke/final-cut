<?php

namespace App\Services;

use App\Exceptions\AdminUserException;
use App\Models\AdminProfile;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * In-panel staff management (admin-v2 Plan 09) — the UI counterpart to the
 * `admin:create-user` command, sharing its create-or-promote semantics:
 * an existing customer email is promoted in place (their password is never
 * overwritten unless explicitly supplied); a new email requires name +
 * password. Disable/enable drive the existing `admin_profiles.disabled_at`
 * flag, which `AdminUserProvider` re-checks on every request — a disabled
 * admin's live session dies immediately.
 *
 * Self-guards: an actor can never change their own role or disable
 * themselves (lockout safety + privilege changes need a second admin).
 */
class AdminUserService
{
    use LogsAdminActivity;

    public const EVENT_PROVISIONED = 'admin_user.provisioned';

    public const EVENT_ROLE_ASSIGNED = 'admin_user.role_assigned';

    public const EVENT_DISABLED = 'admin_user.disabled';

    public const EVENT_ENABLED = 'admin_user.enabled';

    /**
     * Create a new admin or promote an existing customer (matched by email).
     *
     * @throws AdminUserException
     */
    public function provision(?string $name, string $email, ?string $password, string $role, User $actor): User
    {
        $this->assertRoleExists($role);

        $email = strtolower(trim($email));

        return DB::transaction(function () use ($name, $email, $password, $role, $actor): User {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                if ($password === null || $password === '' || $name === null || $name === '') {
                    throw new AdminUserException(AdminUserException::REASON_PASSWORD_REQUIRED);
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);
            } else {
                // Promotion path — only touch identity fields when explicitly given.
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
            }

            AdminProfile::firstOrCreate(['user_id' => $user->id]);

            // syncRoles: replace any previous role rather than layering.
            $user->syncRoles([$role]);

            $this->logIfAdmin(self::EVENT_PROVISIONED, $user, $actor, [
                'email' => $email,
                'role' => $role,
            ]);

            return $user->refresh();
        });
    }

    /**
     * @throws AdminUserException
     */
    public function assignRole(User $target, string $role, User $actor): void
    {
        if ($target->id === $actor->id) {
            throw new AdminUserException(AdminUserException::REASON_SELF_ROLE);
        }

        $this->assertRoleExists($role);
        $this->assertHasProfile($target);

        DB::transaction(function () use ($target, $role, $actor): void {
            $previous = $target->getRoleNames()->all();
            $target->syncRoles([$role]);

            $this->logIfAdmin(self::EVENT_ROLE_ASSIGNED, $target, $actor, [
                'previous_roles' => $previous,
                'role' => $role,
            ]);
        });
    }

    /**
     * @throws AdminUserException
     */
    public function disable(User $target, User $actor): void
    {
        if ($target->id === $actor->id) {
            throw new AdminUserException(AdminUserException::REASON_SELF_DISABLE);
        }

        $this->assertHasProfile($target);

        DB::transaction(function () use ($target, $actor): void {
            /** @var AdminProfile $profile */
            $profile = AdminProfile::whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($profile->disabled_at !== null) {
                throw new AdminUserException(AdminUserException::REASON_ALREADY_DISABLED);
            }

            $profile->update(['disabled_at' => now()]);

            $this->logIfAdmin(self::EVENT_DISABLED, $target, $actor, [
                'email' => $target->email,
            ]);
        });
    }

    /**
     * @throws AdminUserException
     */
    public function enable(User $target, User $actor): void
    {
        $this->assertHasProfile($target);

        DB::transaction(function () use ($target, $actor): void {
            /** @var AdminProfile $profile */
            $profile = AdminProfile::whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($profile->disabled_at === null) {
                throw new AdminUserException(AdminUserException::REASON_NOT_DISABLED);
            }

            $profile->update(['disabled_at' => null]);

            $this->logIfAdmin(self::EVENT_ENABLED, $target, $actor, [
                'email' => $target->email,
            ]);
        });
    }

    private function assertRoleExists(string $role): void
    {
        $exists = Role::query()
            ->where('name', $role)
            ->where('guard_name', 'admin')
            ->exists();

        if (! $exists) {
            throw new AdminUserException(AdminUserException::REASON_UNKNOWN_ROLE);
        }
    }

    private function assertHasProfile(User $target): void
    {
        if (! $target->adminProfile()->exists()) {
            throw new AdminUserException(AdminUserException::REASON_NOT_ADMIN);
        }
    }
}
