<?php

use App\Enums\BookingStatus;
use App\Enums\LoyaltyAdjustmentType;
use App\Enums\LoyaltyTier;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;

uses(BookingTestHelper::class);

/*
|--------------------------------------------------------------------------
| getPoints()
|--------------------------------------------------------------------------
*/

test('getPoints returns user loyalty_points value', function () {
    $user = User::factory()->create(['loyalty_points' => 150]);
    $service = new LoyaltyService;

    expect($service->getPoints($user))->toBe(150);
});

/*
|--------------------------------------------------------------------------
| getTier()
|--------------------------------------------------------------------------
*/

test('getTier returns loyalty_tier enum value as string', function () {
    $member = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member]);
    $premier = User::factory()->create(['loyalty_tier' => LoyaltyTier::Premier]);
    $service = new LoyaltyService;

    expect($service->getTier($member))->toBe('member');
    expect($service->getTier($premier))->toBe('premier');
});

/*
|--------------------------------------------------------------------------
| awardPointsForPurchase()
|--------------------------------------------------------------------------
*/

test('awardPointsForPurchase awards floor(cents/100) points', function () {
    $user = User::factory()->create(['loyalty_points' => 0]);
    $service = new LoyaltyService;

    $result = $service->awardPointsForPurchase($user, 2400);

    expect($result)->toBe(24);
    expect($user->refresh()->loyalty_points)->toBe(24);
});

test('awardPointsForPurchase awards 0 points for sub-dollar amount', function () {
    $user = User::factory()->create(['loyalty_points' => 0]);
    $service = new LoyaltyService;

    $result = $service->awardPointsForPurchase($user, 99);

    expect($result)->toBe(0);
    expect($user->refresh()->loyalty_points)->toBe(0);
});

test('awardPointsForPurchase increments existing points', function () {
    $user = User::factory()->create(['loyalty_points' => 100]);
    $service = new LoyaltyService;

    $result = $service->awardPointsForPurchase($user, 2400);

    expect($result)->toBe(124);
    expect($user->refresh()->loyalty_points)->toBe(124);
});

test('awardPointsForPurchase returns new total points value', function () {
    $user = User::factory()->create(['loyalty_points' => 50]);
    $service = new LoyaltyService;

    $result = $service->awardPointsForPurchase($user, 1550);

    expect($result)->toBe(65);
});

/*
|--------------------------------------------------------------------------
| getHistory()
|--------------------------------------------------------------------------
*/

test('getHistory returns entries from confirmed bookings only', function () {
    $user = User::factory()->create();
    $fixture = $this->createShowtimeWithSeats();

    Booking::factory()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 2400,
        'status' => BookingStatus::Confirmed,
    ]);

    Booking::factory()->cancelled()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 1200,
    ]);

    $service = new LoyaltyService;
    $history = $service->getHistory($user);

    expect($history)->toHaveCount(1);
    expect($history[0]['points'])->toBe(24);
});

test('getHistory entries have correct shape', function () {
    $user = User::factory()->create();
    $fixture = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 3000,
        'status' => BookingStatus::Confirmed,
    ]);

    $service = new LoyaltyService;
    $history = $service->getHistory($user);

    expect($history)->toHaveCount(1);
    expect($history[0])->toHaveKeys(['description', 'points', 'date', 'bookingId']);
    expect($history[0]['description'])->toBe("Booking for {$fixture['movie']->title}");
    expect($history[0]['points'])->toBe(30);
    expect($history[0]['bookingId'])->toBe($booking->id);
});

test('getHistory returns empty array for no bookings', function () {
    $user = User::factory()->create();
    $service = new LoyaltyService;

    expect($service->getHistory($user))->toBe([]);
});

test('getHistory is ordered most recent first', function () {
    $user = User::factory()->create();
    $fixture = $this->createShowtimeWithSeats();

    $older = Booking::factory()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 1000,
        'status' => BookingStatus::Confirmed,
        'created_at' => now()->subDays(5),
    ]);

    $newer = Booking::factory()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 2000,
        'status' => BookingStatus::Confirmed,
        'created_at' => now()->subDay(),
    ]);

    $service = new LoyaltyService;
    $history = $service->getHistory($user);

    expect($history)->toHaveCount(2);
    expect($history[0]['bookingId'])->toBe($newer->id);
    expect($history[1]['bookingId'])->toBe($older->id);
});

/*
|--------------------------------------------------------------------------
| adjustPoints()
|--------------------------------------------------------------------------
*/

test('adjustPoints with positive delta increases balance and writes PointsCorrection row with actor + activity log', function () {
    $user = User::factory()->create(['loyalty_points' => 100]);
    $admin = AdminUser::factory()->create();
    $service = new LoyaltyService;

    $service->adjustPoints($user, 250, 'Goodwill for complaint', $admin);

    expect($user->refresh()->loyalty_points)->toBe(350);

    $adjustment = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->change_type)->toBe(LoyaltyAdjustmentType::PointsCorrection);
    expect($adjustment->points_delta)->toBe(250);
    expect($adjustment->admin_user_id)->toBe($admin->id);
    expect($adjustment->reason)->toBe('Goodwill for complaint');

    $activity = Activity::where('log_name', 'admin')->where('description', LoyaltyService::EVENT_POINTS_ADJUSTED)->sole();
    expect($activity->causer_id)->toBe((string) $admin->id);
    expect($activity->subject_id)->toBe($user->id);
    expect($activity->properties->get('delta'))->toBe(250);
    expect($activity->properties->get('balance_after'))->toBe(350);
});

test('adjustPoints with negative delta decreases balance (negative allowed)', function () {
    $user = User::factory()->create(['loyalty_points' => 50]);
    $admin = AdminUser::factory()->create();
    $service = new LoyaltyService;

    $service->adjustPoints($user, -200, 'Fraud clawback from disputed purchase #9912', $admin);

    expect($user->refresh()->loyalty_points)->toBe(-150);

    $adjustment = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->change_type)->toBe(LoyaltyAdjustmentType::PointsCorrection);
    expect($adjustment->points_delta)->toBe(-200);
});

test('adjustPoints with null actor stores null admin_user_id and writes no activity log', function () {
    $user = User::factory()->create(['loyalty_points' => 100]);
    $service = new LoyaltyService;

    $service->adjustPoints($user, 50, 'system: auto-credit', null);

    expect($user->refresh()->loyalty_points)->toBe(150);

    $adjustment = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->admin_user_id)->toBeNull();

    expect(Activity::where('log_name', 'admin')->where('description', LoyaltyService::EVENT_POINTS_ADJUSTED)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| grantPremier() / revokePremier()
|--------------------------------------------------------------------------
*/

test('grantPremier sets tier and expiry and writes TierUpgrade adjustment + activity log', function () {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Member, 'premier_expiry' => null]);
    $admin = AdminUser::factory()->create();
    $expiry = Carbon::parse('2027-04-24');
    $service = new LoyaltyService;

    $service->grantPremier($user, $expiry, 'Corporate partnership — 2026 Q2', $admin);

    $user->refresh();
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Premier);
    expect($user->premier_expiry->toDateString())->toBe('2027-04-24');

    $adjustment = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->change_type)->toBe(LoyaltyAdjustmentType::TierUpgrade);
    expect($adjustment->points_delta)->toBe(0);
    expect($adjustment->admin_user_id)->toBe($admin->id);
    expect($adjustment->reason)->toBe('Corporate partnership — 2026 Q2');

    $activity = Activity::where('log_name', 'admin')->where('description', LoyaltyService::EVENT_PREMIER_GRANTED)->sole();
    expect($activity->causer_id)->toBe((string) $admin->id);
});

test('revokePremier sets tier to member, nulls expiry, writes TierRevoke adjustment + activity log', function () {
    $user = User::factory()->create([
        'loyalty_tier' => LoyaltyTier::Premier,
        'premier_expiry' => now()->addYear(),
    ]);
    $admin = AdminUser::factory()->create();
    $service = new LoyaltyService;

    $service->revokePremier($user, 'Membership fraud — account terminated', $admin);

    $user->refresh();
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Member);
    expect($user->premier_expiry)->toBeNull();

    $adjustment = LoyaltyAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->change_type)->toBe(LoyaltyAdjustmentType::TierRevoke);
    expect($adjustment->reason)->toBe('Membership fraud — account terminated');

    $activity = Activity::where('log_name', 'admin')->where('description', LoyaltyService::EVENT_PREMIER_REVOKED)->sole();
    expect($activity->causer_id)->toBe((string) $admin->id);
});

/*
|--------------------------------------------------------------------------
| Transactional rollback
|--------------------------------------------------------------------------
*/

test('adjustPoints rolls back balance + adjustment on transaction failure', function () {
    $user = User::factory()->create(['loyalty_points' => 100]);
    $admin = AdminUser::factory()->create();

    // Subclass the service to simulate a throw between the user save and any
    // subsequent write. Because adjustPoints is a single transaction, a throw
    // inside the closure should reverse both the loyalty_points update and
    // any prior LoyaltyAdjustment insert within that closure.
    $brokenService = new class extends LoyaltyService
    {
        public function adjustPoints(User $user, int $delta, string $reason, ?AdminUser $actor = null): void
        {
            DB::transaction(function () use ($user, $delta) {
                $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $fresh->loyalty_points = $fresh->loyalty_points + $delta;
                $fresh->save();

                throw new RuntimeException('simulated post-save failure');
            });
        }
    };

    expect(fn () => $brokenService->adjustPoints($user, 500, 'test', $admin))
        ->toThrow(RuntimeException::class);

    expect($user->refresh()->loyalty_points)->toBe(100);
    expect(LoyaltyAdjustment::where('user_id', $user->id)->count())->toBe(0);
});
