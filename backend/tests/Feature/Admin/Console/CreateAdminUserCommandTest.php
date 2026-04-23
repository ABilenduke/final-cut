<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

test('interactive creation without --role prompts for role selection', function (): void {
    $this->artisan('admin:create-user', [
        '--name' => 'Prompted Role',
        '--email' => 'prompted-role@finalcut.test',
        '--password' => 'secret',
    ])
        ->expectsChoice('Role', 'manager', ['admin', 'manager', 'ops'])
        ->assertSuccessful();

    $user = AdminUser::where('email', 'prompted-role@finalcut.test')->firstOrFail();
    expect($user->hasRole('manager'))->toBeTrue();
});

test('non-interactive creation with flags creates an admin with the correct role', function (): void {
    $this->artisan('admin:create-user', [
        '--name' => 'Manager Create',
        '--email' => 'manager-create@finalcut.test',
        '--password' => 'secret',
        '--role' => 'manager',
    ])->assertSuccessful();

    $user = AdminUser::where('email', 'manager-create@finalcut.test')->firstOrFail();
    expect($user->name)->toBe('Manager Create');
    expect(Hash::check('secret', $user->password))->toBeTrue();
    expect($user->hasRole('manager'))->toBeTrue();
});

test('duplicate email without --reset-password fails and names the flag', function (): void {
    AdminUser::factory()->create(['email' => 'taken@finalcut.test']);

    $this->artisan('admin:create-user', [
        '--name' => 'X',
        '--email' => 'taken@finalcut.test',
        '--password' => 'secret',
        '--role' => 'admin',
    ])
        ->expectsOutputToContain('--reset-password')
        ->assertFailed();
});

test('--reset-password rehashes the password without touching role membership', function (): void {
    $user = AdminUser::factory()->create([
        'email' => 'preserve-roles@finalcut.test',
        'password' => 'old-password',
    ]);
    $user->assignRole('manager');

    $this->artisan('admin:create-user', [
        '--reset-password' => true,
        '--email' => 'preserve-roles@finalcut.test',
        '--password' => 'new-password',
    ])->assertSuccessful();

    $fresh = $user->fresh();
    expect(Hash::check('new-password', $fresh->password))->toBeTrue();
    expect($fresh->hasRole('manager'))->toBeTrue();
    expect($fresh->hasRole('admin'))->toBeFalse();
});

test('--reset-password --reassign-role rehashes the password and replaces roles', function (): void {
    $user = AdminUser::factory()->create([
        'email' => 'reassign@finalcut.test',
        'password' => 'pw1',
    ]);
    $user->assignRole('manager');

    $this->artisan('admin:create-user', [
        '--reset-password' => true,
        '--reassign-role' => true,
        '--email' => 'reassign@finalcut.test',
        '--password' => 'pw2',
        '--role' => 'ops',
    ])->assertSuccessful();

    $fresh = $user->fresh();
    expect(Hash::check('pw2', $fresh->password))->toBeTrue();
    expect($fresh->hasRole('ops'))->toBeTrue();
    expect($fresh->hasRole('manager'))->toBeFalse();
});

test('--reset-password targeting an unknown email fails with operator guidance', function (): void {
    $this->artisan('admin:create-user', [
        '--reset-password' => true,
        '--email' => 'ghost@finalcut.test',
        '--password' => 'whatever',
    ])
        ->expectsOutputToContain('Drop the --reset-password flag')
        ->assertFailed();
});

test('admin_users table has no email_verified_at column', function (): void {
    expect(Schema::hasColumn('admin_users', 'email_verified_at'))->toBeFalse();
});

test('--reset-password --reassign-role with an invalid role leaves the password untouched', function (): void {
    $user = AdminUser::factory()->create([
        'email' => 'bogus-role@finalcut.test',
        'password' => 'original-password',
    ]);
    $user->assignRole('manager');
    $originalHash = $user->fresh()->password;

    $this->artisan('admin:create-user', [
        '--reset-password' => true,
        '--reassign-role' => true,
        '--email' => 'bogus-role@finalcut.test',
        '--password' => 'new-password',
        '--role' => 'bogus',
    ])
        ->expectsOutputToContain('Role bogus does not exist')
        ->assertFailed();

    $fresh = $user->fresh();
    expect($fresh->password)->toBe($originalHash);
    expect(Hash::check('original-password', $fresh->password))->toBeTrue();
    expect(Hash::check('new-password', $fresh->password))->toBeFalse();
    expect($fresh->hasRole('manager'))->toBeTrue();
});

test('creating an admin with a case-variant email of an existing admin fails', function (): void {
    AdminUser::factory()->create(['email' => 'case-admin@finalcut.test']);

    $this->artisan('admin:create-user', [
        '--name' => 'Case Variant',
        '--email' => 'Case-Admin@finalcut.test',
        '--password' => 'secret',
        '--role' => 'admin',
    ])
        ->expectsOutputToContain('--reset-password')
        ->assertFailed();

    expect(AdminUser::count())->toBe(1);
});
