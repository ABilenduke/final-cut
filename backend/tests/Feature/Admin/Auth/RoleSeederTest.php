<?php

use Database\Seeders\AdminRolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('seeder is idempotent', function (): void {
    $rolesBefore = Role::count();
    $permsBefore = Permission::count();

    $this->seed(AdminRolesAndPermissionsSeeder::class);
    $this->seed(AdminRolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe($rolesBefore);
    expect(Permission::count())->toBe($permsBefore);
});

test('admin role has every seeded admin-guard permission', function (): void {
    $admin = Role::where('name', 'admin')->where('guard_name', 'admin')->firstOrFail();

    $allPermissions = Permission::where('guard_name', 'admin')->pluck('name')->sort()->values();
    $rolePermissions = $admin->permissions->pluck('name')->sort()->values();

    expect($rolePermissions->all())->toBe($allPermissions->all());
});

test('manager role has the seeder-defined permission set', function (): void {
    $manager = Role::where('name', 'manager')->where('guard_name', 'admin')->firstOrFail();

    $expected = collect(AdminRolesAndPermissionsSeeder::MANAGER_PERMISSIONS)->sort()->values()->all();

    expect($manager->permissions->pluck('name')->sort()->values()->all())->toBe($expected);
});

test('ops role has the seeder-defined permission set', function (): void {
    $ops = Role::where('name', 'ops')->where('guard_name', 'admin')->firstOrFail();

    $expected = collect(AdminRolesAndPermissionsSeeder::OPS_PERMISSIONS)->sort()->values()->all();

    expect($ops->permissions->pluck('name')->sort()->values()->all())->toBe($expected);
});

test('no role carries users.update permission', function (): void {
    // Codifies the v1 decision: customer users (the `users` table) are
    // read-only from admin. Loyalty writes are exposed via the narrower
    // loyalty.adjust_points / loyalty.adjust_tier permissions only.
    expect(Permission::where('name', 'users.update')->where('guard_name', 'admin')->exists())->toBeFalse();

    foreach (Role::where('guard_name', 'admin')->get() as $role) {
        expect($role->hasPermissionTo(
            Permission::firstOrCreate(['name' => 'users.update', 'guard_name' => 'admin'])
        ))->toBeFalse("role {$role->name} should not carry users.update");
    }
});

test('every admin role is guard-scoped to admin', function (): void {
    $totalAdminRoles = Role::count();
    $adminGuardRoles = Role::where('guard_name', 'admin')->count();

    expect($adminGuardRoles)->toBe($totalAdminRoles);
    expect($adminGuardRoles)->toBeGreaterThanOrEqual(3);
});
