<?php

use App\Enums\MovieStatus;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\MenuItem;
use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

/*
|--------------------------------------------------------------------------
| Full Purchase Flow Integration Test
|--------------------------------------------------------------------------
|
| End-to-end tests simulating the complete purchase journey from browsing
| through booking to post-purchase verification.
|
*/

test('full purchase journey from registration to order verification', function () {
    $fixture = $this->createShowtimeWithSeats();
    $location = $fixture['location'];
    $movie = $fixture['movie'];
    $showtime = $fixture['showtime'];
    $seats = $fixture['seats'];

    // Ensure movie is listed as now_showing for the browse step
    $movie->update(['status' => MovieStatus::NowShowing]);

    $menuItem = MenuItem::factory()->forLocation($location)->create(['price' => 599]);

    $fakeStripe = $this->fakeStripe()->shouldSucceed();

    // Step 1: Register a new user
    $registerResponse = postJson('/api/auth/register', [
        'name' => 'Integration Tester',
        'email' => 'integration@finalcut.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $registerResponse->assertStatus(201);
    $user = User::where('email', 'integration@finalcut.test')->firstOrFail();
    $pointsBefore = $user->loyalty_points;

    // Step 2: Browse movies
    $moviesResponse = actingAs($user)->getJson('/api/movies');

    $moviesResponse->assertOk();
    expect($moviesResponse->json('data'))->toBeArray()
        ->and(collect($moviesResponse->json('data'))->pluck('title'))->toContain($movie->title);

    // Step 3: Get showtimes for this movie at this location
    $showtimesResponse = actingAs($user)->getJson(
        "/api/locations/{$location->slug}/movies/{$movie->slug}/showtimes?date={$showtime->start_time->toDateString()}"
    );

    $showtimesResponse->assertOk();
    $showtimeIds = collect($showtimesResponse->json('data'))->pluck('id');
    expect($showtimeIds)->toContain($showtime->id);

    // Step 4: Get seat map — all seats should be available
    $seatMapResponse = actingAs($user)->getJson(
        "/api/locations/{$location->slug}/showtimes/{$showtime->id}"
    );

    $seatMapResponse->assertOk();
    $seatData = collect($seatMapResponse->json('data.seats'));
    expect($seatData)->toHaveCount(5);
    $seatData->each(fn ($seat) => expect($seat['status'])->toBe('available'));

    // Step 5: Book 2 seats + 1 food item
    $selectedSeatIds = [$seats[0]->id, $seats[1]->id]; // A1, A2 (Standard)

    $bookingResponse = actingAs($user)->postJson($this->bookingUrl($location), [
        'showtimeId' => $showtime->id,
        'seatIds' => $selectedSeatIds,
        'paymentMethodId' => 'pm_test_visa',
        'foodItems' => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $bookingResponse->assertStatus(201);
    $bookingData = $bookingResponse->json('data');

    // Step 6: Verify booking details
    expect($bookingData['confirmationCode'])->toStartWith('CVF-')
        ->and($bookingData['status'])->toBe('confirmed')
        ->and($bookingData['seats'])->toHaveCount(2)
        ->and($bookingData['foodItems'])->toHaveCount(1);

    $bookingId = $bookingData['id'];

    expect(BookingSeat::where('booking_id', $bookingId)->count())->toBe(2);
    expect(BookingFoodItem::where('booking_id', $bookingId)->count())->toBe(1);
    expect($fakeStripe->createCallCount)->toBe(1);

    // Verify loyalty points awarded (compare against pre-booking value)
    expect($user->fresh()->loyalty_points)->toBeGreaterThan($pointsBefore);

    // Step 7: Retrieve booking by ID
    $bookingShowResponse = actingAs($user)->getJson("/api/bookings/{$bookingId}");

    $bookingShowResponse->assertOk();
    expect($bookingShowResponse->json('data.confirmationCode'))->toBe($bookingData['confirmationCode']);

    // Step 8: Verify booking appears in order history
    $ordersResponse = actingAs($user)->getJson('/api/account/orders');

    $ordersResponse->assertOk();
    $orderIds = collect($ordersResponse->json('data'))->pluck('id');
    expect($orderIds)->toContain($bookingId);

    // Step 9: Verify booked seats now show as taken
    $seatMapAfterResponse = actingAs($user)->getJson(
        "/api/locations/{$location->slug}/showtimes/{$showtime->id}"
    );

    $seatMapAfterResponse->assertOk();
    $seatsAfter = collect($seatMapAfterResponse->json('data.seats'));

    $bookedSeatLabels = ['A1', 'A2'];
    $seatsAfter->each(function ($seat) use ($bookedSeatLabels) {
        if (in_array($seat['label'], $bookedSeatLabels)) {
            expect($seat['status'])->toBe('taken', "Seat {$seat['label']} should be taken");
        } else {
            expect($seat['status'])->toBe('available', "Seat {$seat['label']} should be available");
        }
    });
});

test('second booking for already-taken seats returns 409', function () {
    $fixture = $this->createShowtimeWithSeats();
    $location = $fixture['location'];
    $showtime = $fixture['showtime'];
    $seats = $fixture['seats'];

    $this->fakeStripe()->shouldSucceed();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $contestedSeatIds = [$seats[0]->id, $seats[1]->id]; // A1, A2

    // User A books successfully
    $responseA = actingAs($userA)->postJson($this->bookingUrl($location), [
        'showtimeId' => $showtime->id,
        'seatIds' => $contestedSeatIds,
        'paymentMethodId' => 'pm_test_visa',
    ]);

    $responseA->assertStatus(201);

    // User B attempts same seats — should get 409
    $responseB = actingAs($userB)->postJson($this->bookingUrl($location), [
        'showtimeId' => $showtime->id,
        'seatIds' => $contestedSeatIds,
        'paymentMethodId' => 'pm_test_visa',
    ]);

    $responseB->assertStatus(409);

    $unavailable = $responseB->json('errors.0.unavailableSeatIds');
    expect($unavailable)->toBeArray()
        ->and($unavailable)->toContain($seats[0]->id)
        ->and($unavailable)->toContain($seats[1]->id);

    // Only one booking should exist for this showtime
    expect(Booking::where('showtime_id', $showtime->id)->count())->toBe(1);
});
