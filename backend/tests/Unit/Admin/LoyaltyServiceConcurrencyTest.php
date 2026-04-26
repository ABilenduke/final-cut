<?php

use App\Enums\LoyaltyTier;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proves the `lockForUpdate` contract holds inside LoyaltyService.
 *
 * Pest runs against a single PostgreSQL connection and RefreshDatabase wraps
 * each test in a transaction, so these tests do not stage a true
 * multi-connection race inside the suite itself.
 *
 * Instead, the tests verify the contract in two ways:
 * - query-log assertions confirm the service re-reads the user row with
 *   `SELECT ... FOR UPDATE` inside the transaction; and
 * - sequenced writes confirm the persisted balance/tier state and audit rows
 *   converge as expected when updates are serialized.
 *
 * That locking contract is what preserves correctness under the real
 * multi-connection contention that happens in production.
 */
test('two sequential adjustPoints calls converge on the sum and write two adjustment rows', function (): void {
    $user = User::factory()->create(['loyalty_points' => 0]);
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $service = new LoyaltyService;

    // Simulate two admins correcting the same account — serialized here, but
    // the lockForUpdate in adjustPoints is what guarantees the second read
    // sees the first write under real contention.
    $service->adjustPoints($user, 100, 'Admin A credit', $adminA);
    $service->adjustPoints($user, -50, 'Admin B correction', $adminB);

    expect($user->fresh()->loyalty_points)->toBe(50);

    $rows = LoyaltyAdjustment::where('user_id', $user->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->admin_user_id)->toBe($adminA->id);
    expect($rows[0]->points_delta)->toBe(100);
    expect($rows[1]->admin_user_id)->toBe($adminB->id);
    expect($rows[1]->points_delta)->toBe(-50);
});

test('each adjustPoints call wraps the user read in a lockForUpdate', function (): void {
    $user = User::factory()->create(['loyalty_points' => 0]);
    $admin = User::factory()->admin()->create();
    $service = new LoyaltyService;

    DB::enableQueryLog();

    try {
        $service->adjustPoints($user, 42, 'lock check', $admin);

        $queries = collect(DB::getQueryLog())->pluck('query');

        // The user re-read inside the transaction must carry `for update`. Without
        // this, two concurrent admin adjustments can race and one silently loses.
        expect($queries->contains(fn (string $q) => stripos($q, 'select') !== false
            && stripos($q, 'for update') !== false
            && stripos($q, 'users') !== false))
            ->toBeTrue('Expected a "SELECT ... FOR UPDATE" against users inside adjustPoints.');
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('each grantPremier call wraps the user read in a lockForUpdate', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);
    $admin = User::factory()->admin()->create();
    $service = new LoyaltyService;

    DB::enableQueryLog();

    try {
        $service->grantPremier($user, Carbon::parse('2027-01-01'), 'lock check', $admin);

        $queries = collect(DB::getQueryLog())->pluck('query');
        expect($queries->contains(fn (string $q) => stripos($q, 'select') !== false
            && stripos($q, 'for update') !== false
            && stripos($q, 'users') !== false))
            ->toBeTrue();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('sequential grantPremier calls settle on last-write expiry with two audit rows', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $service = new LoyaltyService;

    $service->grantPremier($user, Carbon::parse('2027-01-01'), 'first grant', $adminA);
    $service->grantPremier($user, Carbon::parse('2028-01-01'), 'renewal', $adminB);

    $user->refresh();
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Premier);
    expect($user->premier_expiry->toDateString())->toBe('2028-01-01');

    $rows = LoyaltyAdjustment::where('user_id', $user->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('admin_user_id')->all())->toBe([$adminA->id, $adminB->id]);
});
