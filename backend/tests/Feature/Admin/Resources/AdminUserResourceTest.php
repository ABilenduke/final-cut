<?php

use App\Auth\AdminUserProvider;
use App\Exceptions\AdminUserException;
use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\AdminUserResource\Pages\ListAdminUsers;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(AdminUserService::class);
});

test('provisioning a new email creates user, profile, and role with activity', function (): void {
    $actor = $this->actingAsAdmin();

    $user = $this->service->provision('Box Office Lead', 'lead@finalcut.test', 'secret-password-1', 'manager', $actor);

    expect($user->adminProfile)->not->toBeNull()
        ->and($user->hasRole('manager'))->toBeTrue()
        ->and(Hash::check('secret-password-1', $user->password))->toBeTrue();

    expect(Activity::where('log_name', 'admin')
        ->where('description', AdminUserService::EVENT_PROVISIONED)
        ->where('causer_id', $actor->id)
        ->exists())->toBeTrue();
});

test('provisioning an existing customer promotes without clobbering their password', function (): void {
    $actor = $this->actingAsAdmin();
    $customer = User::factory()->create(['password' => 'customer-pass-123']);

    $promoted = $this->service->provision(null, $customer->email, null, 'ops', $actor);

    expect($promoted->id)->toBe($customer->id)
        ->and($promoted->adminProfile)->not->toBeNull()
        ->and($promoted->hasRole('ops'))->toBeTrue()
        ->and(Hash::check('customer-pass-123', $promoted->fresh()->password))->toBeTrue();

    // A brand-new email without a password is refused.
    expect(fn () => $this->service->provision('No Pass', 'nopass@finalcut.test', null, 'ops', $actor))
        ->toThrow(AdminUserException::class, 'password');
});

test('admins cannot change their own role or disable themselves', function (): void {
    $actor = $this->actingAsAdmin();

    expect(fn () => $this->service->assignRole($actor, 'ops', $actor))
        ->toThrow(AdminUserException::class, 'own');
    expect(fn () => $this->service->disable($actor, $actor))
        ->toThrow(AdminUserException::class, 'yourself');

    Livewire::test(ListAdminUsers::class)
        ->assertTableActionHidden('assign_role', $actor)
        ->assertTableActionHidden('disable', $actor);
});

test('disabling another admin kills their access and is reversible', function (): void {
    $actor = $this->actingAsAdmin();
    $target = User::factory()->admin()->create();
    $target->assignRole('ops');

    $this->service->disable($target, $actor);

    expect($target->fresh()->isAdmin())->toBeFalse();
    // The admin guard's provider refuses disabled admins on every request —
    // this is the live-session rejection path.
    $provider = new AdminUserProvider(app('hash'), User::class);
    expect($provider->retrieveById($target->id))->toBeNull();

    // Double-disable is refused; enable restores access.
    expect(fn () => $this->service->disable($target->fresh(), $actor))
        ->toThrow(AdminUserException::class, 'already');

    $this->service->enable($target->fresh(), $actor);
    expect($target->fresh()->isAdmin())->toBeTrue();
});

test('the list shows only admin-profile users and is admin-role only', function (): void {
    $this->actingAsAdmin();
    User::factory()->count(2)->create(); // customers — must not appear

    $admin = User::factory()->admin()->create();

    Livewire::test(ListAdminUsers::class)
        ->assertCanSeeTableRecords([$admin]);

    expect(AdminUserResource::getEloquentQuery()->count())
        ->toBe(User::whereHas('adminProfile')->count());

    Auth::guard('admin')->logout();
    $this->actingAsManager();
    expect(AdminUserResource::canViewAny())->toBeFalse();

    $this->actingAsOps();
    expect(AdminUserResource::canViewAny())->toBeFalse();
});

test('provisioning from the page works end to end', function (): void {
    $this->actingAsAdmin();

    Livewire::test(ListAdminUsers::class)
        ->mountAction('provision')
        ->set('mountedActions.0.data.name', 'New Ops Person')
        ->set('mountedActions.0.data.email', 'newops@finalcut.test')
        ->set('mountedActions.0.data.password', 'a-strong-password-9')
        ->set('mountedActions.0.data.role', 'ops')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $user = User::where('email', 'newops@finalcut.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->hasRole('ops'))->toBeTrue();
});
