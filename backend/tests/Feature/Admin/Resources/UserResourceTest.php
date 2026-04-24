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
