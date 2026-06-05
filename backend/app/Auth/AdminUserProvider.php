<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Eloquent provider for the `admin` guard. Identical to the default
 * EloquentUserProvider except that it never returns a User who lacks an active
 * AdminProfile. Customers (no profile) and disabled admins (profile exists but
 * `disabled_at` is set) fail the credential check before the Login event can
 * fire, which is what stops a customer from self-promoting into an admin row
 * via the Login listener.
 */
class AdminUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        return $this->scopeToActiveAdmin(parent::retrieveByCredentials($credentials));
    }

    public function retrieveById($identifier)
    {
        return $this->scopeToActiveAdmin(parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, $token)
    {
        return $this->scopeToActiveAdmin(parent::retrieveByToken($identifier, $token));
    }

    private function scopeToActiveAdmin(?Authenticatable $user): ?User
    {
        if (! $user instanceof User) {
            return null;
        }

        return $user->isAdmin() ? $user : null;
    }
}
