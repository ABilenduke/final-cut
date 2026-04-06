<?php

use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(BookingTestHelper::class);

/*
|--------------------------------------------------------------------------
| Helper: create a booking with proper relationships for a given user
|--------------------------------------------------------------------------
*/

function createBookingForUser(User $user, array $showtimeOverrides = [], array $bookingOverrides = []): Booking
{
    /** @var \Tests\Helpers\BookingTestHelper $this */
    $setup = test()->createShowtimeWithSeats($showtimeOverrides);

    $booking = Booking::factory()->create(array_merge([
        'showtime_id' => $setup['showtime']->id,
        'user_id' => $user->id,
    ], $bookingOverrides));

    // Attach a seat so BookingResource has data to render
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $setup['showtime']->id,
        'seat_id' => $setup['seats'][0]->id,
        'section' => 'Standard',
        'price' => 1200,
    ]);

    return $booking;
}

/*
|--------------------------------------------------------------------------
| GET /api/account/orders
|--------------------------------------------------------------------------
*/

test('orders returns 401 without authentication', function () {
    getJson('/api/account/orders')->assertUnauthorized();
});

test('orders returns paginated bookings with correct envelope', function () {
    $user = User::factory()->create();
    createBookingForUser($user);

    $response = actingAs($user)->getJson('/api/account/orders');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'confirmationCode',
                    'showtimeId',
                    'movieTitle',
                    'moviePosterUrl',
                    'screenName',
                    'startTime',
                    'seats',
                    'foodItems',
                    'subtotal',
                    'discount',
                    'total',
                    'paymentMethod',
                    'userId',
                    'guestEmail',
                    'status',
                    'createdAt',
                ],
            ],
            'meta' => [
                'total',
                'page',
                'per_page',
            ],
        ]);
});

test('orders returns bookings for authenticated user only', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    createBookingForUser($userA);
    createBookingForUser($userA);
    createBookingForUser($userB);

    $response = actingAs($userA)->getJson('/api/account/orders');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect(collect($data)->pluck('userId')->unique()->values()->all())
        ->toBe([$userA->id]);
});

test('orders are ordered by created_at DESC (most recent first)', function () {
    $user = User::factory()->create();

    $older = createBookingForUser($user, bookingOverrides: [
        'created_at' => now()->subDays(2),
    ]);

    $newer = createBookingForUser($user, bookingOverrides: [
        'created_at' => now()->subDay(),
    ]);

    $response = actingAs($user)->getJson('/api/account/orders');

    $data = $response->json('data');
    expect($data[0]['id'])->toBe($newer->id);
    expect($data[1]['id'])->toBe($older->id);
});

test('orders defaults to page 1 with limit 10', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->getJson('/api/account/orders');

    $response->assertOk();
    $meta = $response->json('meta');
    expect($meta['page'])->toBe(1);
    expect($meta['per_page'])->toBe(10);
});

test('orders respects page and limit query params', function () {
    $user = User::factory()->create();

    // Create 3 bookings
    for ($i = 0; $i < 3; $i++) {
        createBookingForUser($user);
    }

    $response = actingAs($user)->getJson('/api/account/orders?page=2&limit=2');

    $response->assertOk();
    $meta = $response->json('meta');
    expect($meta['page'])->toBe(2);
    expect($meta['per_page'])->toBe(2);
    expect($meta['total'])->toBe(3);

    $data = $response->json('data');
    expect($data)->toHaveCount(1); // page 2 of 3 items with limit 2 = 1 item
});

test('orders returns empty data for user with no bookings', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->getJson('/api/account/orders');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    expect($response->json('meta.total'))->toBe(0);
});

test('orders includes both confirmed and cancelled bookings', function () {
    $user = User::factory()->create();

    createBookingForUser($user);
    createBookingForUser($user, bookingOverrides: ['status' => \App\Enums\BookingStatus::Cancelled]);

    $response = actingAs($user)->getJson('/api/account/orders');

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $statuses = collect($data)->pluck('status')->sort()->values()->all();
    expect($statuses)->toBe(['cancelled', 'confirmed']);
});

test('orders includes booking with food items', function () {
    $user = User::factory()->create();
    $booking = createBookingForUser($user);

    BookingFoodItem::factory()->create([
        'booking_id' => $booking->id,
        'name' => 'Large Popcorn',
        'quantity' => 2,
        'unit_price' => 799,
        'total_price' => 1598,
    ]);

    $response = actingAs($user)->getJson('/api/account/orders');

    $data = $response->json('data.0');
    expect($data['foodItems'])->toHaveCount(1);
    expect($data['foodItems'][0]['name'])->toBe('Large Popcorn');
});

/*
|--------------------------------------------------------------------------
| GET /api/account/bookings (upcoming only)
|--------------------------------------------------------------------------
*/

test('upcoming bookings returns 401 without authentication', function () {
    getJson('/api/account/bookings')->assertUnauthorized();
});

test('upcoming bookings returns only future showtimes', function () {
    $user = User::factory()->create();

    // Future showtime
    $future = createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(3),
        'end_time' => now()->addDays(3)->addHours(2),
    ]);

    // Past showtime
    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->subDays(7),
        'end_time' => now()->subDays(7)->addHours(2),
    ]);

    $response = actingAs($user)->getJson('/api/account/bookings');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($future->id);
});

test('upcoming bookings returns data envelope without pagination meta', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->getJson('/api/account/bookings');

    $response->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonMissing(['meta']);
});

test('upcoming bookings sorted by showtime start_time ASC (nearest first)', function () {
    $user = User::factory()->create();

    $later = createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(7),
        'end_time' => now()->addDays(7)->addHours(2),
    ]);

    $sooner = createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(1),
        'end_time' => now()->addDays(1)->addHours(2),
    ]);

    $response = actingAs($user)->getJson('/api/account/bookings');

    $data = $response->json('data');
    expect($data[0]['id'])->toBe($sooner->id);
    expect($data[1]['id'])->toBe($later->id);
});

test('upcoming bookings has correct BookingResource shape', function () {
    $user = User::factory()->create();

    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(2),
        'end_time' => now()->addDays(2)->addHours(2),
    ]);

    $response = actingAs($user)->getJson('/api/account/bookings');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'confirmationCode',
                    'showtimeId',
                    'movieTitle',
                    'moviePosterUrl',
                    'screenName',
                    'startTime',
                    'seats',
                    'foodItems',
                    'subtotal',
                    'discount',
                    'total',
                    'paymentMethod',
                    'userId',
                    'guestEmail',
                    'status',
                    'createdAt',
                ],
            ],
        ]);
});

test('upcoming bookings enforces user isolation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    createBookingForUser($userA, showtimeOverrides: [
        'start_time' => now()->addDays(2),
        'end_time' => now()->addDays(2)->addHours(2),
    ]);

    createBookingForUser($userB, showtimeOverrides: [
        'start_time' => now()->addDays(3),
        'end_time' => now()->addDays(3)->addHours(2),
    ]);

    $response = actingAs($userA)->getJson('/api/account/bookings');

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['userId'])->toBe($userA->id);
});

test('upcoming bookings returns empty data when no upcoming bookings', function () {
    $user = User::factory()->create();

    // Only a past booking
    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->subDays(3),
        'end_time' => now()->subDays(3)->addHours(2),
    ]);

    $response = actingAs($user)->getJson('/api/account/bookings');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('upcoming bookings excludes cancelled and refunded future bookings', function () {
    $user = User::factory()->create();

    // Confirmed future booking — should be included
    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(2),
        'end_time' => now()->addDays(2)->addHours(2),
    ]);

    // Cancelled future booking — should be excluded
    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(3),
        'end_time' => now()->addDays(3)->addHours(2),
    ], bookingOverrides: [
        'status' => \App\Enums\BookingStatus::Cancelled,
    ]);

    // Refunded future booking — should be excluded
    createBookingForUser($user, showtimeOverrides: [
        'start_time' => now()->addDays(4),
        'end_time' => now()->addDays(4)->addHours(2),
    ], bookingOverrides: [
        'status' => \App\Enums\BookingStatus::Refunded,
    ]);

    $response = actingAs($user)->getJson('/api/account/bookings');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['status'])->toBe('confirmed');
});
