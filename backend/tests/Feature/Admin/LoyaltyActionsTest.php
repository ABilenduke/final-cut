<?php

use App\Enums\LoyaltyAdjustmentType;
use App\Enums\LoyaltyTier;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use App\Services\LoyaltyService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

/*
|--------------------------------------------------------------------------
| Adjust Points — end-to-end (real service, real DB, real activity log)
|--------------------------------------------------------------------------
*/

test('adjust_points action mounts, writes a PointsCorrection row, and logs activity', function (): void {
    $user = User::factory()->create(['loyalty_points' => 200]);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertActionVisible('adjust_points')
        ->mountAction('adjust_points')
        ->set('mountedActions.0.data.points_delta', 150)
        ->set('mountedActions.0.data.reason', 'Comp for delayed seating')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($user->fresh()->loyalty_points)->toBe(350);

    $adj = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adj->change_type)->toBe(LoyaltyAdjustmentType::PointsCorrection);
    expect($adj->points_delta)->toBe(150);
    expect($adj->admin_user_id)->toBe($this->admin->id);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', LoyaltyService::EVENT_POINTS_ADJUSTED)
        ->sole();
    expect($activity->causer_id)->toBe((string) $this->admin->id);
});

test('adjust_points with negative delta is accepted and logged', function (): void {
    $user = User::factory()->create(['loyalty_points' => 50]);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->mountAction('adjust_points')
        ->set('mountedActions.0.data.points_delta', -120)
        ->set('mountedActions.0.data.reason', 'Fraud clawback case #9912')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($user->fresh()->loyalty_points)->toBe(-70);
    expect(LoyaltyAdjustment::where('user_id', $user->id)->sole()->points_delta)->toBe(-120);
});

test('service called with null actor stores null admin_user_id and writes no activity log', function (): void {
    $user = User::factory()->create(['loyalty_points' => 100]);

    app(LoyaltyService::class)->adjustPoints($user, 75, 'system: seasonal promo', null);

    $adj = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adj->admin_user_id)->toBeNull();
    expect(Activity::where('log_name', 'admin')->where('description', LoyaltyService::EVENT_POINTS_ADJUSTED)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Premier grant / revoke actions
|--------------------------------------------------------------------------
*/

test('upgrade_premier is visible only for Member users and promotes them', function (): void {
    $member = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);

    Livewire::test(ViewUser::class, ['record' => $member->id])
        ->assertActionVisible('upgrade_premier')
        ->mountAction('upgrade_premier')
        ->set('mountedActions.0.data.expiry', '2027-06-15')
        ->set('mountedActions.0.data.reason', 'Corporate partnership sign-up')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $member->refresh();
    expect($member->loyalty_tier)->toBe(LoyaltyTier::Premier);
    expect($member->premier_expiry->toDateString())->toBe('2027-06-15');

    $adjustment = LoyaltyAdjustment::where('user_id', $member->id)->sole();
    expect($adjustment->change_type)->toBe(LoyaltyAdjustmentType::TierUpgrade);
    expect($adjustment->reason)->toBe('Corporate partnership sign-up');
});

test('upgrade_premier is hidden for users who are already Premier', function (): void {
    $premier = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Premier,
        'premier_expiry' => now()->addYear(),
    ]);

    Livewire::test(ViewUser::class, ['record' => $premier->id])
        ->assertActionHidden('upgrade_premier');
});

test('revoke_premier is visible only for Premier users and demotes them', function (): void {
    $premier = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Premier,
        'premier_expiry' => now()->addYear(),
    ]);

    Livewire::test(ViewUser::class, ['record' => $premier->id])
        ->assertActionVisible('revoke_premier')
        ->mountAction('revoke_premier')
        ->set('mountedActions.0.data.reason', 'Requested by customer — downgrade')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $premier->refresh();
    expect($premier->loyalty_tier)->toBe(LoyaltyTier::Member);
    expect($premier->premier_expiry)->toBeNull();
    expect(LoyaltyAdjustment::where('user_id', $premier->id)->sole()->change_type)
        ->toBe(LoyaltyAdjustmentType::TierRevoke);
});

test('revoke_premier is hidden for users who are Member', function (): void {
    $member = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);

    Livewire::test(ViewUser::class, ['record' => $member->id])
        ->assertActionHidden('revoke_premier');
});

/*
|--------------------------------------------------------------------------
| Permission split: loyalty.adjust_points vs loyalty.adjust_tier
|--------------------------------------------------------------------------
*/

test('a user with only loyalty.adjust_points cannot see tier actions', function (): void {
    /** @var User $actor */
    $actor = User::factory()->admin()->create();
    $actor->givePermissionTo(Permission::findByName('loyalty.adjust_points', 'admin'));
    // Base access permission so the page renders at all.
    $actor->givePermissionTo(Permission::findByName('users.view', 'admin'));
    $this->actingAs($actor, 'admin');

    $member = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);

    Livewire::test(ViewUser::class, ['record' => $member->id])
        ->assertActionVisible('adjust_points')
        ->assertActionHidden('upgrade_premier')
        ->assertActionHidden('revoke_premier');
});

test('a user with only loyalty.adjust_tier cannot see adjust_points', function (): void {
    /** @var User $actor */
    $actor = User::factory()->admin()->create();
    $actor->givePermissionTo(Permission::findByName('loyalty.adjust_tier', 'admin'));
    $actor->givePermissionTo(Permission::findByName('users.view', 'admin'));
    $this->actingAs($actor, 'admin');

    $member = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);

    Livewire::test(ViewUser::class, ['record' => $member->id])
        ->assertActionHidden('adjust_points')
        ->assertActionVisible('upgrade_premier')
        ->assertActionHidden('revoke_premier');
});
