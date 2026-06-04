<?php

use App\Enums\BookingStatus;
use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\Location;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

beforeEach(function () {
    $this->fakeStripe();

    // Seed the DB-backed equivalents of the former config/promo_codes.php
    // fixtures — individual tests reference these by code.
    PromoCode::factory()->create([
        'code' => 'WELCOME5',
        'discount_type' => PromoCode::TYPE_FIXED_CENTS,
        'amount' => 500,
    ]);
    PromoCode::factory()->create([
        'code' => 'SAVE10',
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'amount' => 10,
    ]);
});

/*
|--------------------------------------------------------------------------
| POST /api/locations/{location}/bookings — Store
|--------------------------------------------------------------------------
*/

test('successful guest booking creates booking and returns 201', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $seatIds = [$fixture['seats'][0]->id, $fixture['seats'][1]->id];

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => $seatIds,
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => [
            'id', 'confirmationCode', 'showtimeId', 'movieTitle',
            'screenName', 'startTime', 'seats' => ['*' => ['id', 'label', 'section', 'price']],
            'subtotal', 'total', 'paymentMethod', 'status', 'createdAt',
        ]]);

    $data = $response->json('data');
    expect($data['confirmationCode'])->toStartWith('CVF-')
        ->and($data['guestEmail'])->toBe('guest@example.com')
        ->and($data['userId'])->toBeNull()
        ->and($data['status'])->toBe('confirmed')
        ->and($data['subtotal'])->toBe(2400) // 2 × $12
        ->and($data['paymentMethod'])->toBe('card');

    // Seat rows expose human label, never the raw UUID, for display purposes.
    expect($data['seats'])->toHaveCount(2);
    foreach ($data['seats'] as $seat) {
        expect($seat)->toHaveKeys(['id', 'label', 'section', 'price']);
        // Label is the human format e.g. "A1", "B12" — not a UUID.
        expect($seat['label'])->toMatch('/^[A-Z]+\d+$/');
    }

    expect(BookingSeat::where('booking_id', $data['id'])->count())->toBe(2);
    expect($fakeStripe->createCallCount)->toBe(1);
    expect($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(2400);
});

test('Stripe PaymentIntent receives identifiable description, receipt_email and metadata', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id, $fixture['seats'][1]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(201);

    expect($fakeStripe->createdPaymentIntents)->toHaveCount(1);
    $created = $fakeStripe->createdPaymentIntents[0];

    // Description: "Final Cut · {Movie Title} · {YYYY-MM-DD}"
    expect($created['description'])->toStartWith('Final Cut · ')
        ->and($created['description'])->toContain($fixture['showtime']->movie->title)
        ->and($created['description'])->toContain($fixture['showtime']->start_time->toDateString());

    // Receipt email: the guest email comes through; an authenticated booking
    // (see next test) prefers user->email.
    expect($created['receiptEmail'])->toBe('guest@example.com');

    // Metadata bag for dashboard filtering.
    expect($created['metadata'])->toHaveKeys([
        'booking_id', 'showtime_id', 'location_slug', 'auditorium', 'movie_title', 'seat_count',
    ]);
    expect($created['metadata']['showtime_id'])->toBe($fixture['showtime']->id);
    expect($created['metadata']['location_slug'])->toBe($fixture['location']->slug);
    expect($created['metadata']['movie_title'])->toBe($fixture['showtime']->movie->title);
    expect($created['metadata']['seat_count'])->toBe('2');

    // After finalization, confirmation_code is patched on. Both records the
    // final booking_id (matches the JSON response) and the human code.
    expect($fakeStripe->metadataUpdates)->toHaveCount(1);
    $patched = $fakeStripe->metadataUpdates[0];
    $data = $response->json('data');
    expect($patched['metadata']['confirmation_code'])->toBe($data['confirmationCode'])
        ->and($patched['metadata']['booking_id'])->toBe($data['id']);
});

test('Stripe PaymentIntent receipt_email prefers authenticated user email over guest input', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $user = User::factory()->create(['email' => 'member@finalcut.test', 'loyalty_points' => 0]);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        // intentionally include a different `email` in the body — the auth
        // user's email should win.
        'email' => 'spoofed@example.com',
    ])->assertStatus(201);

    expect($fakeStripe->createdPaymentIntents[0]['receiptEmail'])->toBe('member@finalcut.test');
});

test('Idempotency-Key header is forwarded to Stripe (namespaced)', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();
    $key = (string) Str::uuid();

    postJson(
        $this->bookingUrl($fixture['location']),
        [
            'showtimeId' => $fixture['showtime']->id,
            'seatIds' => [$fixture['seats'][0]->id],
            'paymentMethodId' => 'pm_test_visa',
            'email' => 'guest@example.com',
        ],
        ['Idempotency-Key' => $key],
    )->assertStatus(201);

    expect($fakeStripe->createdPaymentIntents[0]['idempotencyKey'])->toBe("booking:{$key}");
});

test('successful authenticated booking awards loyalty points', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $user = User::factory()->create(['loyalty_points' => 100]);

    $response = actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
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
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $firstBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id' => $fixture['seats'][0]->id,
    ]);

    // Second booking tries same seat
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(409);
    $errors = $response->json('errors');
    expect($errors[0]['unavailableSeatIds'])->toContain($fixture['seats'][0]->id);
});

test('expired showtime returns 410', function () {
    $fixture = $this->createShowtimeWithSeats([
        'start_time' => now()->subHour(),
        'end_time' => now()->subMinutes(30),
    ]);
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(410);
});

test('cancelled showtime cannot be booked', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fixture['showtime']->update([
        'cancelled_at' => now(),
        'cancellation_reason' => 'Equipment failure',
    ]);
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(410);
});

test('payment declined returns 402', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldDecline();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_declined',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(402);
    expect($response->json('errors.0.field'))->toBe('payment');

    // Booking should not exist
    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('non-terminal payment status returns 402 and does not create booking', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldReturnNonTerminalStatus('processing');

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(402);
    expect($response->json('errors.0.field'))->toBe('payment');

    // Booking should not exist
    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('invalid payment method returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldFailWithInvalidRequest('No such payment method: pm_expired');

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_expired',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('payment')
        ->and($response->json('errors.0.message'))->toContain('No such payment method');

    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('stripe service unavailable returns 502', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldFailWithApiError();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(502);
    expect($response->json('errors.0.field'))->toBe('payment')
        ->and($response->json('errors.0.message'))->toBe('Payment service is temporarily unavailable. Please try again.');

    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('3DS confirm with stripe api error returns 502', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    // Trigger 3DS
    $fakeStripe->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Stripe goes down during 3DS confirmation
    $fakeStripe->shouldFailWithApiError();

    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(502);
    expect($confirmResponse->json('errors.0.message'))
        ->toBe('Payment service is temporarily unavailable. Please try again.');

    // Pending state preserved for retry
    $pendingData = Cache::get("pending_booking:{$paymentIntentId}");
    expect($pendingData)->not->toBeNull();
});

test('3DS required returns requiresAction with clientSecret', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.requiresAction', true)
        ->assertJsonStructure(['data' => ['clientSecret', 'paymentIntentId']]);
});

test('valid promo code applies discount', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'promoCode' => 'WELCOME5',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['subtotal'])->toBe(1200)
        ->and($data['discount'])->toBe(500) // $5 off
        ->and($data['total'])->toBe(700);   // $12 - $5

    expect(PromoCode::where('code', 'WELCOME5')->first()->uses_count)->toBe(1);
});

test('invalid promo code returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'promoCode' => 'INVALID',
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('promoCode');
});

test('gift card covers full payment without Stripe call', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 5000,
        'status' => GiftCardStatus::Active,
    ]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['paymentMethod'])->toBe('gift_card')
        ->and($data['discount'])->toBe(1200) // full subtotal covered
        ->and($data['total'])->toBe(0);       // fully covered by gift card

    expect($fakeStripe->createCallCount)->toBe(0);

    $giftCard->refresh();
    expect($giftCard->current_balance)->toBe(3800); // 5000 - 1200

    $ledger = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)
        ->where('type', GiftCardLedgerType::Redemption)
        ->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->amount_cents)->toBe(-1200)
        ->and($ledger->balance_after_cents)->toBe(3800);
});

test('gift card partial payment uses mixed payment method', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status' => GiftCardStatus::Active,
    ]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
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

test('partial gift card without paymentMethodId returns 422', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500, // Only $5 — won't cover the $12 seat
        'status' => GiftCardStatus::Active,
    ]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
        // No paymentMethodId — gift card doesn't fully cover
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.0.field'))->toBe('paymentMethodId');

    // No booking should have been created
    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);
});

test('gift card with insufficient balance requires paymentMethodId for remaining amount', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // Gift card has $2 balance — passes pre-check (balance > 0) but only
    // partially covers the $12 seat. Without a paymentMethodId for the
    // remaining $10, the request should fail with 422.
    $giftCard = GiftCard::factory()->create([
        'current_balance' => 200,
        'status' => GiftCardStatus::Active,
    ]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.0.field'))->toBe('paymentMethodId');
});

test('food items added to booking and included in total', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $menuItem = MenuItem::factory()->forLocation($fixture['location'])->create(['price' => 599]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'foodItems' => [
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

    $menuItem = MenuItem::factory()->unavailable()->forLocation($fixture['location'])->create();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'foodItems' => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('foodItems');
});

test('food item from different location returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $otherLocation = Location::factory()->create();
    $menuItem = MenuItem::factory()->forLocation($otherLocation)->create(['price' => 599]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'foodItems' => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('foodItems');
});

test('food item not attached to any location returns 400', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $menuItem = MenuItem::factory()->create(['price' => 599]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'foodItems' => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('foodItems');
});

test('food item uses pivot price_override in booking total', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $menuItem = MenuItem::factory()
        ->forLocation($fixture['location'], ['price_override' => 999])
        ->create(['price' => 599, 'category' => 'popcorn']);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'foodItems' => [
            ['itemId' => $menuItem->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    // Seat: 1200 (standard) + food: 999 (pivot override, not base 599)
    expect($data['subtotal'])->toBe(2199)
        ->and($data['foodItems'][0]['unitPrice'])->toBe(999)
        ->and($data['foodItems'][0]['totalPrice'])->toBe(999);

    expect($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(2199);
});

test('seats from wrong auditorium returns 422', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // Create a seat in a different auditorium
    $otherFixture = $this->createShowtimeWithSeats();
    $foreignSeatId = $otherFixture['seats'][0]->id;

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$foreignSeatId],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(422);
});

test('booking showtime at wrong location returns 410', function () {
    $fixture = $this->createShowtimeWithSeats();
    $otherFixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // Try to book a showtime from $fixture using the OTHER location's URL
    $response = postJson($this->bookingUrl($otherFixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(410);
});

test('validation errors return 422', function () {
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['showtimeId', 'seatIds']);
});

test('guest checkout requires email', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
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
        'booking_id' => $cancelledBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id' => $fixture['seats'][0]->id,
    ]);

    // Rebook the same seat
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(201);
});

test('loyalty points awarded on total after discount not subtotal', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $user = User::factory()->create(['loyalty_points' => 0]);

    $response = actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'promoCode' => 'WELCOME5',
    ]);

    $response->assertStatus(201);

    // subtotal = 1200, discount = 500, total = 700 = $7 = 7 points (not 12)
    expect($user->fresh()->loyalty_points)->toBe(7);
});

test('premium and accessible seats use correct pricing', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    // seats[3] = B1 Premium (1800), seats[4] = C1 Accessible (1000)
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][3]->id, $fixture['seats'][4]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
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
        'user_id' => $user->id,
        'status' => BookingStatus::Confirmed,
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
        'user_id' => $owner->id,
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

    $response = getJson('/api/bookings/lookup?'.http_build_query([
        'confirmation_code' => $booking->confirmation_code,
        'email' => 'guest@example.com',
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

    $response = getJson('/api/bookings/lookup?'.http_build_query([
        'confirmation_code' => $booking->confirmation_code,
        'email' => 'wrong@example.com',
    ]));

    $response->assertStatus(404);
});

test('guest lookup with wrong code returns 404', function () {
    $fixture = $this->createShowtimeWithSeats();

    Booking::factory()->guest()->create([
        'showtime_id' => $fixture['showtime']->id,
        'guest_email' => 'guest@example.com',
    ]);

    $response = getJson('/api/bookings/lookup?'.http_build_query([
        'confirmation_code' => 'CVF-WRONG1',
        'email' => 'guest@example.com',
    ]));

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| POST /api/locations/{location}/bookings/confirm — 3DS Confirmation
|--------------------------------------------------------------------------
*/

test('3DS confirm completes booking after payment confirmation', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    // First request triggers 3DS
    $fakeStripe->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Simulate 3DS completion
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'confirmationCode']]);
});

test('confirm with expired session returns 410', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $response = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
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

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$seatId, $seatId],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['seatIds.0', 'seatIds.1']);
});

/*
|--------------------------------------------------------------------------
| Regression: 3DS Gift Card Balance Revalidation
|--------------------------------------------------------------------------
*/

test('3DS confirm fails when gift card balance dropped during 3DS window', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status' => GiftCardStatus::Active,
    ]);

    // First request triggers 3DS with gift card
    $fakeStripe->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Simulate the gift card being fully spent elsewhere during 3DS window
    $giftCard->update([
        'current_balance' => 0,
        'status' => GiftCardStatus::Depleted,
    ]);

    // Confirm 3DS — gift card balance changed, must reject to prevent
    // charging the original (now-insufficient) PaymentIntent amount
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(409);
    expect($confirmResponse->json('errors.0.field'))->toBe('giftCardCode');

    // Stripe should NOT have been asked to confirm — no money captured
    expect($fakeStripe->confirmedPaymentIntents)->toBeEmpty();

    // No booking should have been created
    expect(Booking::where('showtime_id', $fixture['showtime']->id)
        ->where('status', BookingStatus::Confirmed)->count())->toBe(0);

    // Pending state preserved so the customer can retry
    $pendingData = Cache::get("pending_booking:{$paymentIntentId}");
    expect($pendingData)->not->toBeNull();
});

test('3DS confirm succeeds when gift card balance unchanged', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status' => GiftCardStatus::Active,
    ]);

    // First request triggers 3DS with gift card
    $fakeStripe->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Gift card balance has NOT changed during 3DS window
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(201);

    $data = $confirmResponse->json('data');
    // Amounts should match the original calculation: 1200 - 500 = 700 card charge
    expect($data['subtotal'])->toBe(1200)
        ->and($data['discount'])->toBe(500)
        ->and($data['total'])->toBe(700);

    // Gift card should be deducted
    $giftCard->refresh();
    expect($giftCard->current_balance)->toBe(0);
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

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Seat gets taken by another booking during 3DS window
    $otherBooking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $otherBooking->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id' => $fixture['seats'][0]->id,
    ]);

    // Confirm 3DS — seat conflict should occur BEFORE Stripe is charged
    $fakeStripe->shouldSucceed();

    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(409);

    // Stripe should NOT have been asked to confirm — payment must not be captured
    expect($fakeStripe->confirmedPaymentIntents)->toBeEmpty();

    // Pending state should still be in cache (not destroyed by Cache::pull)
    $pendingData = Cache::get("pending_booking:{$paymentIntentId}");
    expect($pendingData)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Regression: Promo race / 3DS revalidation
|--------------------------------------------------------------------------
*/

// The post-charge race where `validateCode` passes and `consume()` then
// finds the row exhausted requires interleaved execution that is not
// reproducible at HTTP scope — the `validateCode` pre-check on a single
// thread always catches a limit-reached row before Stripe is touched. The
// `consume()` revalidation contract is verified at the service layer in
// `tests/Unit/Admin/PromoCodeServiceTest.php` ("consume throws
// REASON_LIMIT_REACHED when uses_count caught up to usage_limit between
// validate and consume"), which exercises the locked re-read directly.

test('3DS confirm refunds and returns 409 when promo was deleted during the 3DS window', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    PromoCode::factory()->fixed(500)->create(['code' => 'GONE5']);

    $fakeStripe->shouldRequire3ds();
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
        'promoCode' => 'GONE5',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Admin hard-deletes the promo while 3DS is in flight.
    PromoCode::where('code', 'GONE5')->delete();

    $fakeStripe->shouldSucceed();
    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(409);
    expect($confirmResponse->json('errors.0.field'))->toBe('promoCode');

    // Compensating refund issued.
    expect($fakeStripe->refundedPaymentIntents)->toHaveCount(1);

    // No booking persisted.
    expect(Booking::where('stripe_payment_intent_id', $paymentIntentId)->exists())->toBeFalse();
});

test('3DS confirm refunds and returns 409 when promo was deactivated during the 3DS window', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();

    PromoCode::factory()->fixed(500)->create(['code' => 'OFF5']);

    $fakeStripe->shouldRequire3ds();
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
        'promoCode' => 'OFF5',
    ]);

    $response->assertOk();
    $paymentIntentId = $response->json('data.paymentIntentId');

    // Admin deactivates the promo while 3DS is in flight.
    PromoCode::where('code', 'OFF5')->update(['is_active' => false]);

    $fakeStripe->shouldSucceed();
    $confirmResponse = postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ]);

    $confirmResponse->assertStatus(409);
    expect($fakeStripe->refundedPaymentIntents)->toHaveCount(1);
    expect(Booking::where('stripe_payment_intent_id', $paymentIntentId)->exists())->toBeFalse();
    // The deactivated promo's uses_count must NOT have been incremented.
    expect(PromoCode::where('code', 'OFF5')->first()->uses_count)->toBe(0);
});
