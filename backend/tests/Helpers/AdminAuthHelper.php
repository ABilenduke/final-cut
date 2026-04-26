<?php

namespace Tests\Helpers;

use App\Models\User;

trait AdminAuthHelper
{
    protected function actingAsAdmin(): User
    {
        return $this->actingAsAdminWithRole('admin');
    }

    protected function actingAsManager(): User
    {
        return $this->actingAsAdminWithRole('manager');
    }

    protected function actingAsOps(): User
    {
        return $this->actingAsAdminWithRole('ops');
    }

    /** A User with an AdminProfile but no role — exercises the roleless permission-deny path. */
    protected function actingAsNobody(): User
    {
        return $this->actingAsAdminWithRole(null);
    }

    private function actingAsAdminWithRole(?string $role): User
    {
        $user = User::factory()->admin()->create();
        if ($role !== null) {
            $user->assignRole($role);
        }
        $this->actingAs($user, 'admin');

        return $user;
    }
}
