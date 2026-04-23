<?php

namespace Tests\Helpers;

use App\Models\AdminUser;

trait AdminAuthHelper
{
    protected function actingAsAdmin(): AdminUser
    {
        $user = AdminUser::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user, 'admin');

        return $user;
    }

    protected function actingAsManager(): AdminUser
    {
        $user = AdminUser::factory()->create();
        $user->assignRole('manager');
        $this->actingAs($user, 'admin');

        return $user;
    }

    protected function actingAsOps(): AdminUser
    {
        $user = AdminUser::factory()->create();
        $user->assignRole('ops');
        $this->actingAs($user, 'admin');

        return $user;
    }

    protected function actingAsNobody(): AdminUser
    {
        $user = AdminUser::factory()->create();
        $this->actingAs($user, 'admin');

        return $user;
    }
}
