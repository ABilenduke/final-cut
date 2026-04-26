<?php

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->adminHost = config('filament.admin_domain');
    $this->primaryHost = config('app.primary_domain');
});

test('unauthenticated GET to admin host redirects to login', function (): void {
    $response = $this->get("http://{$this->adminHost}/");

    $response->assertRedirect("http://{$this->adminHost}/login");
});

test('authenticated admin reaches the dashboard', function (): void {
    $user = User::factory()->admin()->create();
    $user->assignRole('admin');

    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertOk();
});

test('Auth::attempt with wrong password rejects the credentials', function (): void {
    User::factory()->admin()->create([
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
    $user = User::factory()->admin()->disabled()->create();
    $user->assignRole('admin');

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();

    // canAccessPanel returning false makes Filament's Authenticate middleware
    // abort with 403 (not redirect). Either way the user is locked out.
    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertForbidden();
});

test('re-enabling a disabled admin restores panel access', function (): void {
    $user = User::factory()->admin()->disabled()->create();
    $user->assignRole('admin');

    $this->actingAs($user, 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertForbidden();

    $user->adminProfile()->update(['disabled_at' => null]);

    expect($user->fresh()->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();

    $this->actingAs($user->fresh(), 'admin')
        ->get("http://{$this->adminHost}/")
        ->assertOk();
});

test('admin guard does not authenticate against customer api routes', function (): void {
    $admin = User::factory()->admin()->create();
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

// Regression for the Codex adversarial review finding: a customer with valid
// credentials must not be able to authenticate against the admin guard, and
// the Login listener must not be able to mint an AdminProfile during that
// attempt. The provider rejects at credential lookup; the listener never
// creates a row.
test('customer credentials cannot authenticate against the admin guard', function (): void {
    Event::fake([Login::class]);

    $customer = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'correct-password',
    ]);

    expect($customer->isAdmin())->toBeFalse();

    $accepted = Auth::guard('admin')->attempt([
        'email' => 'customer@example.com',
        'password' => 'correct-password',
    ]);

    expect($accepted)->toBeFalse();
    expect(Auth::guard('admin')->check())->toBeFalse();
    expect(AdminProfile::query()->where('user_id', $customer->id)->exists())->toBeFalse();
    Event::assertNotDispatched(Login::class);
});

test('disabled admin credentials cannot authenticate against the admin guard', function (): void {
    Event::fake([Login::class]);

    $user = User::factory()->admin()->disabled()->create([
        'email' => 'disabled-admin@finalcut.test',
        'password' => 'correct-password',
    ]);

    $accepted = Auth::guard('admin')->attempt([
        'email' => 'disabled-admin@finalcut.test',
        'password' => 'correct-password',
    ]);

    expect($accepted)->toBeFalse();
    Event::assertNotDispatched(Login::class);
});

test('login listener never creates an AdminProfile', function (): void {
    // Even if the credential filter were ever bypassed and the Login event
    // fired for a customer, the listener must not write entitlement.
    $customer = User::factory()->create();

    expect($customer->isAdmin())->toBeFalse();

    event(new Login('admin', $customer, false));

    expect(AdminProfile::query()->where('user_id', $customer->id)->exists())->toBeFalse();
    expect($customer->fresh()->isAdmin())->toBeFalse();
});

test('login listener stamps last_login on an active admin profile', function (): void {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('admin');

    $before = $admin->adminProfile->last_login_at;

    event(new Login('admin', $admin, false));

    $profile = $admin->fresh()->adminProfile;
    expect($profile)->not->toBeNull();
    expect($profile->last_login_at)->not->toBe($before);
    expect($profile->last_login_ip)->toBeString();
});

test('login listener does not stamp last_login on a disabled admin profile', function (): void {
    $user = User::factory()->admin()->disabled()->create();

    event(new Login('admin', $user, false));

    $profile = $user->fresh()->adminProfile;
    expect($profile->disabled_at)->not->toBeNull();
    expect($profile->last_login_at)->toBeNull();
});
