<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

beforeEach(function () {
    $this->fakeStripe();
});

/*
|--------------------------------------------------------------------------
| Stripe calls must run OUTSIDE the DB transaction / row lock (P0 #3)
|--------------------------------------------------------------------------
| The booking flow used to call Stripe inside DB::transaction while holding a
| lockForUpdate on the showtime row, serializing all bookings for a showtime
| behind Stripe latency. Seats are now reserved via a committed Held booking,
| the lock is released, and Stripe is called at the test's baseline transaction
| level (RefreshDatabase wraps each test in one transaction, so baseline is the
| floor — the flow must add no nesting of its own around the network call).
*/

test('store() calls Stripe createPaymentIntent outside any DB transaction', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();
    $baseline = DB::transactionLevel();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(201);

    expect($fakeStripe->createTransactionLevels)->not->toBeEmpty();
    foreach ($fakeStripe->createTransactionLevels as $level) {
        expect($level)->toBe($baseline);
    }
});

test('3DS confirm() calls Stripe confirmPaymentIntent outside any DB transaction', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();
    $baseline = DB::transactionLevel();

    $fakeStripe->shouldRequire3ds();
    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $response->assertOk();

    $fakeStripe->shouldSucceed();
    postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $response->json('data.paymentIntentId'),
    ])->assertStatus(201);

    expect($fakeStripe->confirmTransactionLevels)->not->toBeEmpty();
    foreach ($fakeStripe->confirmTransactionLevels as $level) {
        expect($level)->toBe($baseline);
    }
});
