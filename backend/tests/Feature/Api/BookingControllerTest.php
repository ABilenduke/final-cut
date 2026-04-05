<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\GiftCard;
use App\Models\MenuItem;
use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

/*
|--------------------------------------------------------------------------
| POST /api/bookings — Store
|--------------------------------------------------------------------------
*/

test('successful guest booking creates booking and returns 201', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $seatIds = [$fixture['seats'][0]->id, $fixture['seats'][1]->id];

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => $seatIds,
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => [
            'id', 'confirmationCode', 'showtimeId', 'movieTitle',
            'screenName', 'startTime', 'seats', 'subtotal', 'total',
            'paymentMethod', 'status', 'createdAt',
        ]]);

    $data = $response->json('data');
    expect($data['confirmationCode'])->toStartWith('CVF-')
        ->and($data['guestEmail'])->toBe('guest@example.com')
        ->and($data['userId'])->toBeNull()
        ->and($data['status'])->toBe('confirmed')
        ->and($data['subtotal'])->toBe(2400) // 2 × $12
        ->and($data['paymentMethod'])->toBe('card');

    expect(BookingSeat::where('booking_id', $data['id'])->count())->toBe(2);
    expect($fakeStripe->createCallCount)->toBe(1);
    expect($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(2400);
});

test('successful authenticated booking awards loyalty points', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $user = User::factory()->create(['loyalty_points' => 100]);

    $response = actingAs($user)->postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['userId'])->toBe($user->id)
        ->and($data['guestEmail'])->toBeNull();

    // 1200 cents = $12 = 12 points
    expect($user->fresh()->loyalty_points)->toBe(112);
});

test('seat conflict returns 409 with unavailable seat IDs', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // First booking takes seat A1
    $firstBooking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status'      => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id'  => $firstBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id'     => $fixture['seats'][0]->id,
    ]);

    // Second booking tries same seat
    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(409);
    $errors = $response->json('errors');
    expect($errors[0]['unavailableSeatIds'])->toContain($fixture['seats'][0]->id);
});

test('expired showtime returns 410', function () {
    $fixture = $this->createShowtimeWithSeats([
        'start_time' => now()->subHour(),
        'end_time'   => now()->subMinutes(30),
    ]);
    $this->fakeStripe();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(410);
});

test('payment declined returns 402', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldDecline();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_declined',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(402);
    expect($response->json('errors.0.field'))->toBe('payment');

    // Booking should not exist
    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('3DS required returns requiresAction with clientSecret', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldRequire3ds();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email'           => 'guest@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.requiresAction', true)
        ->assertJsonStructure(['data' => ['clientSecret', 'paymentIntentId']]);
});

test('valid promo code applies discount', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
        'promoCode'       => 'WELCOME5',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['subtotal'])->toBe(1200)
        ->and($data['discount'])->toBe(500) // $5 off
        ->and($data['total'])->toBe(700);   // $12 - $5
});

test('invalid promo code returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
        'promoCode'       => 'INVALID',
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('promoCode');
});

test('gift card covers full payment without Stripe call', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 5000,
        'status'          => \App\Enums\GiftCardStatus::Active,
    ]);

    $response = postJson('/api/bookings', [
        'showtimeId'    => $fixture['showtime']->id,
        'seatIds'       => [$fixture['seats'][0]->id],
        'giftCardCode'  => $giftCard->code,
        'email'         => 'guest@example.com',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['paymentMethod'])->toBe('gift_card')
        ->and($data['discount'])->toBe(1200) // full subtotal covered
        ->and($data['total'])->toBe(0);       // fully covered by gift card

    expect($fakeStripe->createCallCount)->toBe(0);

    $giftCard->refresh();
    expect($giftCard->current_balance)->toBe(3800); // 5000 - 1200
});

test('gift card partial payment uses mixed payment method', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status'          => \App\Enums\GiftCardStatus::Active,
    ]);

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'giftCardCode'    => $giftCard->code,
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['paymentMethod'])->toBe('mixed')
        ->and($data['discount'])->toBe(500);

    // Stripe charged the remainder
    expect($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(700); // 1200 - 500

    $giftCard->refresh();
    expect($giftCard->current_balance)->toBe(0)
        ->and($giftCard->status->value)->toBe('depleted');
});

test('food items added to booking and included in total', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $menuItem = MenuItem::factory()->create(['price' => 599]);

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
        'foodItems'       => [
            ['itemId' => $menuItem->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['subtotal'])->toBe(2398) // 1200 + (599 × 2)
        ->and($data['foodItems'])->toHaveCount(1)
        ->and($data['foodItems'][0]['quantity'])->toBe(2)
        ->and($data['foodItems'][0]['totalPrice'])->toBe(1198);

    expect($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(2398);
});

test('unavailable food item returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $menuItem = MenuItem::factory()->unavailable()->create();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
        'foodItems'       => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('foodItems');
});

test('seats from wrong auditorium returns 422', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // Create a seat in a different auditorium
    $otherFixture = $this->createShowtimeWithSeats();
    $foreignSeatId = $otherFixture['seats'][0]->id;

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$foreignSeatId],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(422);
});

test('validation errors return 422', function () {
    $response = postJson('/api/bookings', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['showtimeId', 'seatIds']);
});

test('guest checkout requires email', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('cancelled booking frees seats for rebooking', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // Create and cancel a booking
    $cancelledBooking = Booking::factory()->cancelled()->create([
        'showtime_id' => $fixture['showtime']->id,
    ]);
    BookingSeat::factory()->create([
        'booking_id'  => $cancelledBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id'     => $fixture['seats'][0]->id,
    ]);

    // Rebook the same seat
    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(201);
});

test('loyalty points awarded on total after discount not subtotal', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $user = User::factory()->create(['loyalty_points' => 0]);

    $response = actingAs($user)->postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'promoCode'       => 'WELCOME5',
    ]);

    $response->assertStatus(201);

    // subtotal = 1200, discount = 500, total = 700 = $7 = 7 points (not 12)
    expect($user->fresh()->loyalty_points)->toBe(7);
});

test('premium and accessible seats use correct pricing', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // seats[3] = B1 Premium (1800), seats[4] = C1 Accessible (1000)
    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][3]->id, $fixture['seats'][4]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['subtotal'])->toBe(2800); // 1800 + 1000

    $seats = collect($data['seats']);
    $premium = $seats->firstWhere('section', 'premium');
    $accessible = $seats->firstWhere('section', 'accessible');

    expect($premium['price'])->toBe(1800)
        ->and($accessible['price'])->toBe(1000);
});

/*
|--------------------------------------------------------------------------
| GET /api/bookings/{id} — Show
|--------------------------------------------------------------------------
*/

test('authenticated owner retrieves their booking', function () {
    $fixture = $this->createShowtimeWithSeats();
    $user = User::factory()->create();

    $booking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'user_id'     => $user->id,
        'status'      => BookingStatus::Confirmed,
    ]);

    $response = actingAs($user)->getJson("/api/bookings/{$booking->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $booking->id)
        ->assertJsonPath('data.confirmationCode', $booking->confirmation_code);
});

test('authenticated non-owner gets 404', function () {
    $fixture = $this->createShowtimeWithSeats();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $booking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'user_id'     => $owner->id,
    ]);

    $response = actingAs($other)->getJson("/api/bookings/{$booking->id}");

    $response->assertStatus(404);
});

test('unauthenticated user gets 404 on show endpoint', function () {
    $fixture = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $fixture['showtime']->id,
    ]);

    $response = getJson("/api/bookings/{$booking->id}");

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| GET /api/bookings/lookup — Guest Lookup
|--------------------------------------------------------------------------
*/

test('guest lookup with correct code and email returns booking', function () {
    $fixture = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $fixture['showtime']->id,
        'guest_email' => 'guest@example.com',
    ]);

    $response = getJson('/api/bookings/lookup?' . http_build_query([
        'confirmation_code' => $booking->confirmation_code,
        'email'             => 'guest@example.com',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.id', $booking->id);
});

test('guest lookup with wrong email returns 404', function () {
    $fixture = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $fixture['showtime']->id,
        'guest_email' => 'guest@example.com',
    ]);

    $response = getJson('/api/bookings/lookup?' . http_build_query([
        'confirmation_code' => $booking->confirmation_code,
        'email'             => 'wrong@example.com',
    ]));

    $response->assertStatus(404);
});

test('guest lookup with wrong code returns 404', function () {
    $fixture = $this->createShowtimeWithSeats();

    Booking::factory()->guest()->create([
        'showtime_id' => $fixture['showtime']->id,
        'guest_email' => 'guest@example.com',
    ]);

    $response = getJson('/api/bookings/lookup?' . http_build_query([
        'confirmation_code' => 'CVF-WRONG1',
        'email'             => 'guest@example.com',
    ]));

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| POST /api/bookings/confirm — 3DS Confirmation
|--------------------------------------------------------------------------
*/

test('3DS confirm completes booking after payment confirmation', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    // First request triggers 3DS
    $fakeStripe->shouldRequire3ds();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email'           => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Simulate 3DS completion
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson('/api/bookings/confirm', [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'confirmationCode']]);
});

test('confirm with expired session returns 410', function () {
    $this->fakeStripe();

    $response = postJson('/api/bookings/confirm', [
        'paymentIntentId' => 'pi_nonexistent',
    ]);

    $response->assertStatus(410);
});

/*
|--------------------------------------------------------------------------
| Regression: Duplicate Seat IDs
|--------------------------------------------------------------------------
*/

test('duplicate seat IDs are rejected by validation', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $seatId = $fixture['seats'][0]->id;

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$seatId, $seatId],
        'paymentMethodId' => 'pm_test_visa',
        'email'           => 'guest@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['seatIds.0', 'seatIds.1']);
});

/*
|--------------------------------------------------------------------------
| Regression: 3DS Gift Card Balance Revalidation
|--------------------------------------------------------------------------
*/

test('3DS confirm revalidates gift card balance and adjusts totals', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status'          => \App\Enums\GiftCardStatus::Active,
    ]);

    // First request triggers 3DS with gift card
    $fakeStripe->shouldRequire3ds();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'giftCardCode'    => $giftCard->code,
        'email'           => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Simulate the gift card being fully spent elsewhere during 3DS window
    $giftCard->update([
        'current_balance' => 0,
        'status'          => \App\Enums\GiftCardStatus::Depleted,
    ]);

    // Confirm 3DS — gift card should be revalidated
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson('/api/bookings/confirm', [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(201);

    $data = $confirmResponse->json('data');
    // Gift card had 500 at store() time but is now 0 — discount should reflect 0 gift card usage
    expect($data['discount'])->toBe(0)
        ->and($data['total'])->toBe(1200);

    // Gift card balance should still be 0, not negative
    $giftCard->refresh();
    expect($giftCard->current_balance)->toBeGreaterThanOrEqual(0);
});

/*
|--------------------------------------------------------------------------
| Regression: 3DS Pending State Preserved On Seat Conflict
|--------------------------------------------------------------------------
*/

test('3DS confirm preserves pending state on seat conflict for retry', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    // Trigger 3DS
    $fakeStripe->shouldRequire3ds();

    $response = postJson('/api/bookings', [
        'showtimeId'      => $fixture['showtime']->id,
        'seatIds'         => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email'           => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Seat gets taken by another booking during 3DS window
    $otherBooking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status'      => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id'  => $otherBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id'     => $fixture['seats'][0]->id,
    ]);

    // Confirm 3DS — seat conflict should occur
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson('/api/bookings/confirm', [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(409);

    // Pending state should still be in cache (not destroyed by Cache::pull)
    $pendingData = \Illuminate\Support\Facades\Cache::get("pending_booking:{$paymentIntentId}");
    expect($pendingData)->not->toBeNull();
});
