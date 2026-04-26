<?php

use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

test('admin role grants every permission', function (): void {
    $admin = $this->actingAsAdmin();

    foreach (Permission::where('guard_name', 'admin')->pluck('name') as $permission) {
        expect($admin->can($permission))->toBeTrue("admin should have permission {$permission}");
    }
});

test('manager can create movies but not admin users', function (): void {
    $manager = $this->actingAsManager();

    expect($manager->can('movies.create'))->toBeTrue();
    expect($manager->can('admin_users.create'))->toBeFalse();
});

test('ops can view bookings but not update movies', function (): void {
    $ops = $this->actingAsOps();

    expect($ops->can('bookings.view'))->toBeTrue();
    expect($ops->can('movies.update'))->toBeFalse();
});

test('a roleless admin user has no permissions', function (): void {
    $nobody = $this->actingAsNobody();

    foreach (Permission::where('guard_name', 'admin')->pluck('name') as $permission) {
        expect($nobody->can($permission))->toBeFalse("nobody should not have permission {$permission}");
    }
});

test('a customer User without an AdminProfile cannot access the admin panel', function (): void {
    $customer = User::factory()->create();

    expect($customer->isAdmin())->toBeFalse();
    expect($customer->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    // And without an admin role assignment, no admin permission resolves.
    foreach (Permission::where('guard_name', 'admin')->pluck('name') as $permission) {
        expect($customer->can($permission))->toBeFalse("customer should not have permission {$permission}");
    }
});

test('a User with an AdminProfile that is disabled cannot access the admin panel', function (): void {
    $user = User::factory()->admin()->create();
    $user->assignRole('admin');
    $user->adminProfile()->update(['disabled_at' => now()]);

    expect($user->fresh()->isAdmin())->toBeFalse();
    expect($user->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});
