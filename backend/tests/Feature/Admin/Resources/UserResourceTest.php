<?php

use App\Enums\LoyaltyTier;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('no role can create or delete a customer user', function (): void {
    $user = User::factory()->create();

    foreach ([$this->actingAsAdmin(...), $this->actingAsManager(...), $this->actingAsOps(...)] as $actAs) {
        $actAs();
        expect(UserResource::canCreate())->toBeFalse();
        expect(UserResource::canDelete($user))->toBeFalse();
    }
});

test('canEdit requires at least one loyalty permission', function (): void {
    $user = User::factory()->create();

    // admin + manager hold both loyalty permissions.
    $this->actingAsAdmin();
    expect(UserResource::canEdit($user))->toBeTrue();

    $this->actingAsManager();
    expect(UserResource::canEdit($user))->toBeTrue();

    // ops holds only loyalty.view — not enough.
    $this->actingAsOps();
    expect(UserResource::canEdit($user))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| List page
|--------------------------------------------------------------------------
*/

test('admins can see customer users', function (): void {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

/*
|--------------------------------------------------------------------------
| Edit form narrowness
|--------------------------------------------------------------------------
*/

test('edit form only exposes loyalty fields — no email / password / name / phone / DOB', function (): void {
    $user = User::factory()->create();

    $livewire = Livewire::test(EditUser::class, ['record' => $user->id]);

    $livewire->assertFormFieldExists('loyalty_points');
    $livewire->assertFormFieldExists('loyalty_tier');

    foreach (['email', 'password', 'name', 'phone', 'date_of_birth'] as $field) {
        $livewire->assertFormFieldDoesNotExist($field);
    }
});

/*
|--------------------------------------------------------------------------
| handleRecordUpdate routes through LoyaltyService
|--------------------------------------------------------------------------
*/

test('editing loyalty_points routes through LoyaltyService::adjustPoints with the admin actor', function (): void {
    $user = User::factory()->create([
        'loyalty_points' => 100,
        'loyalty_tier' => LoyaltyTier::Member,
    ]);

    $captured = [];

    $service = $this->mock(LoyaltyService::class);
    $service->shouldReceive('adjustPoints')
        ->once()
        ->andReturnUsing(function (User $u, int $delta, string $reason, ?AdminUser $actor) use (&$captured) {
            $captured = compact('delta', 'reason', 'actor');
        });

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->set('data.loyalty_points', 350)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($captured['delta'])->toBe(250);
    expect($captured['reason'])->toBe('Edited via profile form');
    expect($captured['actor']?->id)->toBe($this->admin->id);
});

test('switching tier to premier routes through LoyaltyService::grantPremier with the expiry', function (): void {
    $user = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Member,
        'premier_expiry' => null,
    ]);

    $capturedExpiry = null;
    $capturedReason = null;
    $capturedActor = null;

    $service = $this->mock(LoyaltyService::class);
    $service->shouldReceive('grantPremier')
        ->once()
        ->andReturnUsing(function (User $u, Carbon $expiry, string $reason, ?AdminUser $actor) use (&$capturedExpiry, &$capturedReason, &$capturedActor) {
            $capturedExpiry = $expiry;
            $capturedReason = $reason;
            $capturedActor = $actor;
        });

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->set('data.loyalty_tier', LoyaltyTier::Premier->value)
        ->set('data.premier_expiry', '2027-12-31')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedExpiry?->toDateString())->toBe('2027-12-31');
    expect($capturedReason)->toBe('Edited via profile form');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('switching tier back to member routes through LoyaltyService::revokePremier', function (): void {
    $user = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Premier,
        'premier_expiry' => now()->addYear(),
    ]);

    $capturedReason = null;
    $capturedActor = null;

    $service = $this->mock(LoyaltyService::class);
    $service->shouldReceive('revokePremier')
        ->once()
        ->andReturnUsing(function (User $u, string $reason, ?AdminUser $actor) use (&$capturedReason, &$capturedActor) {
            $capturedReason = $reason;
            $capturedActor = $actor;
        });

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->set('data.loyalty_tier', LoyaltyTier::Member->value)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedReason)->toBe('Edited via profile form');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

/*
|--------------------------------------------------------------------------
| Permission-split enforcement on EditUser
|--------------------------------------------------------------------------
| Both tests protect the invariant from Codex's adversarial review: the
| edit form must not let a points-only admin change tier (or a tier-only
| admin change points) just because canEdit granted page access on
| either permission.
*/

test('a points-only admin cannot submit a tier change via the edit form', function (): void {
    $actor = AdminUser::factory()->create();
    $actor->givePermissionTo(Permission::findByName('loyalty.adjust_points', 'admin'));
    $actor->givePermissionTo(Permission::findByName('users.view', 'admin'));
    $this->actingAs($actor, 'admin');

    $user = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Member,
        'loyalty_points' => 100,
    ]);

    $service = $this->mock(LoyaltyService::class);
    $service->shouldNotReceive('grantPremier');
    $service->shouldNotReceive('revokePremier');

    // Field disabling + dehydrated(false) means Livewire won't submit the
    // tier key. Simulate a malicious payload that forces it anyway.
    Livewire::test(EditUser::class, ['record' => $user->id])
        ->set('data.loyalty_tier', LoyaltyTier::Premier->value)
        ->set('data.premier_expiry', '2027-06-01')
        ->call('save');

    expect($user->fresh()->loyalty_tier)->toBe(LoyaltyTier::Member);
});

test('a tier-only admin cannot submit a points change via the edit form', function (): void {
    $actor = AdminUser::factory()->create();
    $actor->givePermissionTo(Permission::findByName('loyalty.adjust_tier', 'admin'));
    $actor->givePermissionTo(Permission::findByName('users.view', 'admin'));
    $this->actingAs($actor, 'admin');

    $user = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Member,
        'loyalty_points' => 100,
    ]);

    $service = $this->mock(LoyaltyService::class);
    $service->shouldNotReceive('adjustPoints');

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->set('data.loyalty_points', 500)
        ->call('save');

    expect($user->fresh()->loyalty_points)->toBe(100);
});
