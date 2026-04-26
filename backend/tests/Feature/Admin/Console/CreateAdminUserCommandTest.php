<?php

use App\Models\User;
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

    $user = User::where('email', 'prompted-role@finalcut.test')->firstOrFail();
    expect($user->adminProfile)->not->toBeNull();
    expect($user->hasRole('manager'))->toBeTrue();
});

test('non-interactive creation with flags creates an admin with the correct role', function (): void {
    $this->artisan('admin:create-user', [
        '--name' => 'Manager Create',
        '--email' => 'manager-create@finalcut.test',
        '--password' => 'secret',
        '--role' => 'manager',
    ])->assertSuccessful();

    $user = User::where('email', 'manager-create@finalcut.test')->firstOrFail();
    expect($user->name)->toBe('Manager Create');
    expect($user->adminProfile)->not->toBeNull();
    expect(Hash::check('secret', $user->password))->toBeTrue();
    expect($user->hasRole('manager'))->toBeTrue();
});

test('running create against an existing admin promotes idempotently without duplicating the User', function (): void {
    // Post-refactor, admin:create-user is a find-or-create. An existing admin
    // can be re-promoted (e.g. role re-assignment) without colliding on email.
    $existing = User::factory()->admin()->create(['email' => 'taken@finalcut.test']);
    $existing->assignRole('manager');

    $this->artisan('admin:create-user', [
        '--email' => 'taken@finalcut.test',
        '--role' => 'ops',
    ])->assertSuccessful();

    expect(User::where('email', 'taken@finalcut.test')->count())->toBe(1);
    $fresh = $existing->fresh();
    expect($fresh->hasRole('ops'))->toBeTrue();
    expect($fresh->hasRole('manager'))->toBeFalse();
});

test('promoting an existing customer attaches an AdminProfile and assigns the requested role', function (): void {
    // The User exists from the customer surface but has no admin profile yet.
    $customer = User::factory()->create(['email' => 'customer-promo@finalcut.test']);
    expect($customer->adminProfile()->exists())->toBeFalse();

    $this->artisan('admin:create-user', [
        '--email' => 'customer-promo@finalcut.test',
        '--role' => 'manager',
    ])->assertSuccessful();

    $fresh = $customer->fresh();
    expect($fresh->adminProfile()->exists())->toBeTrue();
    expect($fresh->hasRole('manager'))->toBeTrue();
});

test('--reset-password rehashes the password without touching role membership', function (): void {
    $user = User::factory()->admin()->create([
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
    $user = User::factory()->admin()->create([
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
    $user = User::factory()->admin()->create([
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

test('a case-variant email of an existing admin re-uses the existing User identity', function (): void {
    // Email is normalized to lowercase by the command, so a Case-Variant input
    // resolves to the same User row — the find-or-create branch fires.
    User::factory()->admin()->create(['email' => 'case-admin@finalcut.test']);

    $this->artisan('admin:create-user', [
        '--email' => 'Case-Admin@finalcut.test',
        '--role' => 'admin',
    ])->assertSuccessful();

    expect(User::where('email', 'case-admin@finalcut.test')->count())->toBe(1);
});
