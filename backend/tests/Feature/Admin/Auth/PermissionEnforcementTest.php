<?php

use App\Models\User;
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

test('customer User has no role-assignment surface', function (): void {
    // Admin roles live on guard_name = 'admin'. The customer User model is
    // intentionally NOT given Spatie\Permission\Traits\HasRoles, so there is
    // no method by which a customer account can be assigned an admin role.
    // RoleSeederTest separately asserts that every admin role is guard-scoped
    // — the two checks together prevent admin role leakage to customer users.
    $customer = User::factory()->create();

    expect(method_exists($customer, 'assignRole'))->toBeFalse();
    expect(method_exists($customer, 'hasRole'))->toBeFalse();
});
