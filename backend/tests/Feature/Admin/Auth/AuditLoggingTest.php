<?php

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

test('admin login + logout write rows to activity_log scoped to auth log', function (): void {
    $admin = User::factory()->admin()->create(['email' => 'audit-login@finalcut.test']);
    $admin->assignRole('admin');

    Auth::guard('admin')->login($admin);
    Auth::guard('admin')->logout();

    $authRows = Activity::where('log_name', 'auth')->orderBy('id')->get();

    expect($authRows->pluck('description')->all())->toBe(['login', 'logout']);
    expect($authRows->first()->causer_id)->toBe($admin->id);
    expect($authRows->first()->causer_type)->toBe(User::class);
});

test('failed admin login writes a row with the attempted email', function (): void {
    User::factory()->admin()->create([
        'email' => 'real@finalcut.test',
        'password' => 'right',
    ]);

    Auth::guard('admin')->attempt(['email' => 'real@finalcut.test', 'password' => 'wrong']);

    $row = Activity::where('description', 'login_failed')->firstOrFail();
    expect($row->log_name)->toBe('auth');
    expect($row->properties['email'])->toBe('real@finalcut.test');
});

test('successful admin login updates AdminProfile last_login_at and last_login_ip', function (): void {
    $admin = User::factory()->admin()->create(['email' => 'last-login@finalcut.test']);
    expect($admin->adminProfile->last_login_at)->toBeNull();
    expect($admin->adminProfile->last_login_ip)->toBeNull();

    Auth::guard('admin')->login($admin);

    $profile = $admin->adminProfile()->first();
    expect($profile->last_login_at)->not->toBeNull();
    expect($profile->last_login_at->diffInSeconds(now()))->toBeLessThan(5);
    expect($profile->last_login_ip)->not->toBeNull();
});

test('AdminProfile disabled_at toggling writes one activity_log row per change via LogsActivity', function (): void {
    // LogsActivity on AdminProfile is configured to log only `disabled_at`. User
    // name/password mutations are deliberately routed through services that emit
    // explicit rows rather than auto-logging on every customer profile edit.
    $admin = User::factory()->admin()->create();
    /** @var AdminProfile $profile */
    $profile = $admin->adminProfile;

    $profile->update(['disabled_at' => now()]);
    $profile->update(['disabled_at' => null]);
    $profile->delete();

    $rows = Activity::where('subject_type', AdminProfile::class)
        ->where('subject_id', $profile->user_id)
        ->orderBy('id')
        ->pluck('description');

    expect($rows->all())->toBe(['created', 'updated', 'updated', 'deleted']);
});

test('customer web-guard login does not write to activity_log', function (): void {
    Activity::query()->delete();

    $customer = User::factory()->create();
    Auth::guard('web')->login($customer);

    expect(Activity::where('description', 'login')->count())->toBe(0);
});

test('LogsActivity is opt-in: writes to unrelated models like Role are not logged', function (): void {
    Activity::query()->delete();

    Role::create(['name' => 'experiment', 'guard_name' => 'admin']);

    expect(Activity::count())->toBe(0);
});

test('admin auth events log to the auth log, not the default admin log', function (): void {
    $admin = User::factory()->admin()->create(['email' => 'log-name-check@finalcut.test']);

    Auth::guard('admin')->login($admin);
    Auth::guard('admin')->logout();

    expect(Activity::where('log_name', 'auth')->where('description', 'login')->count())->toBe(1);
    expect(Activity::where('log_name', 'auth')->where('description', 'logout')->count())->toBe(1);
    expect(Activity::where('log_name', 'admin')->where('description', 'login')->count())->toBe(0);
});
