<?php

use App\Enums\BookingStatus;
use App\Enums\LoyaltyTier;
use App\Models\Booking;
use App\Models\User;
use App\Services\LoyaltyService;
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
