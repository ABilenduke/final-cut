<?php

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

test('admin login + logout write rows to activity_log scoped to auth log', function (): void {
    $admin = AdminUser::factory()->create(['email' => 'audit-login@finalcut.test']);
    $admin->assignRole('admin');

    Auth::guard('admin')->login($admin);
    Auth::guard('admin')->logout();

    $authRows = Activity::where('log_name', 'auth')->orderBy('id')->get();

    expect($authRows->pluck('description')->all())->toBe(['login', 'logout']);
    expect((int) $authRows->first()->causer_id)->toBe($admin->id);
    expect($authRows->first()->causer_type)->toBe(AdminUser::class);
});

test('failed admin login writes a row with the attempted email', function (): void {
    AdminUser::factory()->create([
        'email' => 'real@finalcut.test',
        'password' => 'right',
    ]);

    Auth::guard('admin')->attempt(['email' => 'real@finalcut.test', 'password' => 'wrong']);

    $row = Activity::where('description', 'login_failed')->firstOrFail();
    expect($row->log_name)->toBe('auth');
    expect($row->properties['email'])->toBe('real@finalcut.test');
});

test('successful admin login updates last_login_at and last_login_ip', function (): void {
    $admin = AdminUser::factory()->create(['email' => 'last-login@finalcut.test']);
    expect($admin->last_login_at)->toBeNull();
    expect($admin->last_login_ip)->toBeNull();

    Auth::guard('admin')->login($admin);

    $fresh = $admin->fresh();
    expect($fresh->last_login_at)->not->toBeNull();
    expect($fresh->last_login_at->diffInSeconds(now()))->toBeLessThan(5);
    expect($fresh->last_login_ip)->not->toBeNull();
});

test('AdminUser CRUD writes one activity_log row per event via LogsActivity', function (): void {
    $admin = AdminUser::factory()->create(['name' => 'Audit Probe']);
    $admin->update(['name' => 'Updated Name']);
    $admin->delete();

    $rows = Activity::where('subject_type', AdminUser::class)
        ->where('subject_id', $admin->id)
        ->orderBy('id')
        ->pluck('description');

    expect($rows->all())->toBe(['created', 'updated', 'deleted']);
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
    $admin = AdminUser::factory()->create(['email' => 'log-name-check@finalcut.test']);

    Auth::guard('admin')->login($admin);
    Auth::guard('admin')->logout();

    expect(Activity::where('log_name', 'auth')->where('description', 'login')->count())->toBe(1);
    expect(Activity::where('log_name', 'auth')->where('description', 'logout')->count())->toBe(1);
    expect(Activity::where('log_name', 'admin')->where('description', 'login')->count())->toBe(0);
});
