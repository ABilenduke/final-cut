<?php

use App\Enums\BookingStatus;
use App\Enums\LoyaltyTier;
use App\Models\Booking;
use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(BookingTestHelper::class);

/*
|--------------------------------------------------------------------------
| GET /api/account/loyalty
|--------------------------------------------------------------------------
*/

test('loyalty endpoint returns correct envelope structure', function () {
    $user = User::factory()->create([
        'loyalty_points' => 250,
        'loyalty_tier' => LoyaltyTier::Member,
    ]);

    $response = actingAs($user)->getJson('/api/account/loyalty');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'points',
                'tier',
                'premierExpiry',
                'history',
            ],
        ]);
});

test('loyalty endpoint returns correct points and tier for member', function () {
    $user = User::factory()->create([
        'loyalty_points' => 100,
        'loyalty_tier' => LoyaltyTier::Member,
        'premier_expiry' => null,
    ]);

    $response = actingAs($user)->getJson('/api/account/loyalty');

    $response->assertOk()
        ->assertJsonPath('data.points', 100)
        ->assertJsonPath('data.tier', 'member')
        ->assertJsonPath('data.premierExpiry', null);
});

test('loyalty endpoint returns ISO8601 premierExpiry for premier user', function () {
    $expiry = now()->addYear()->startOfDay();
    $user = User::factory()->create([
        'loyalty_points' => 500,
        'loyalty_tier' => LoyaltyTier::Premier,
        'premier_expiry' => $expiry,
    ]);

    $response = actingAs($user)->getJson('/api/account/loyalty');

    $response->assertOk()
        ->assertJsonPath('data.tier', 'premier')
        ->assertJsonPath('data.points', 500);

    $premierExpiry = $response->json('data.premierExpiry');
    expect($premierExpiry)->not->toBeNull();
    // Verify it is a valid ISO8601 string
    expect(fn () => new DateTimeImmutable($premierExpiry))->not->toThrow(Exception::class);
});

test('loyalty endpoint returns correctly shaped history entries', function () {
    $user = User::factory()->create(['loyalty_points' => 30]);
    $fixture = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'showtime_id' => $fixture['showtime']->id,
        'total' => 3000,
        'status' => BookingStatus::Confirmed,
    ]);

    $response = actingAs($user)->getJson('/api/account/loyalty');

    $response->assertOk();
    $history = $response->json('data.history');

    expect($history)->toHaveCount(1);
    expect($history[0])->toHaveKeys(['description', 'points', 'date', 'bookingId']);
    expect($history[0]['description'])->toBe("Booking for {$fixture['movie']->title}");
    expect($history[0]['points'])->toBe(30);
    expect($history[0]['bookingId'])->toBe($booking->id);
});

test('loyalty endpoint points value matches user model', function () {
    $user = User::factory()->create(['loyalty_points' => 42]);

    $response = actingAs($user)->getJson('/api/account/loyalty');

    $response->assertOk()
        ->assertJsonPath('data.points', 42);
});

test('loyalty endpoint requires authentication', function () {
    getJson('/api/account/loyalty')->assertUnauthorized();
});
