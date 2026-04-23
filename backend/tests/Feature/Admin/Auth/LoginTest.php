<?php

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->adminHost = config('filament.admin_domain');
    $this->primaryHost = config('app.primary_domain');
});

test('unauthenticated GET to admin host redirects to login', function (): void {
    $response = $this->get("http://{$this->adminHost}/");

    $response->assertRedirect("http://{$this->adminHost}/login");
});

test('authenticated admin reaches the dashboard', function (): void {
    $user = AdminUser::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertOk();
});

test('Auth::attempt with wrong password rejects the credentials', function (): void {
    AdminUser::factory()->create([
        'email' => 'real-admin@finalcut.test',
        'password' => 'correct-password',
    ]);

    $accepted = Auth::guard('admin')->attempt([
        'email' => 'real-admin@finalcut.test',
        'password' => 'wrong-password',
    ]);

    expect($accepted)->toBeFalse();
    expect(Auth::guard('admin')->check())->toBeFalse();
});

test('disabled admin cannot access the panel', function (): void {
    $user = AdminUser::factory()->disabled()->create();
    $user->assignRole('admin');

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();

    // canAccessPanel returning false makes Filament's Authenticate middleware
    // abort with 403 (not redirect). Either way the user is locked out.
    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertForbidden();
});

test('re-enabling a disabled admin restores panel access', function (): void {
    $user = AdminUser::factory()->disabled()->create();
    $user->assignRole('admin');

    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertForbidden();

    $user->forceFill(['disabled_at' => null])->save();

    expect($user->fresh()->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();

    $this->actingAs($user->fresh(), 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertOk();
});

test('admin guard does not authenticate against customer api routes', function (): void {
    $admin = AdminUser::factory()->create();
    $admin->assignRole('admin');

    // Acting as an admin (admin guard) — the customer /api routes use sanctum
    // for auth, so the admin session should not authenticate the request.
    $this->actingAs($admin, 'admin')
        ->getJson("http://{$this->primaryHost}/api/account/profile")
        ->assertUnauthorized();

    // Sanity check: a customer User on the web/sanctum guard does authenticate.
    $customer = User::factory()->create();
    $this->actingAs($customer, 'sanctum')
        ->getJson("http://{$this->primaryHost}/api/account/profile")
        ->assertOk();
});
