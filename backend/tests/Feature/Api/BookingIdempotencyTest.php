<?php

use App\Models\Booking;
use Illuminate\Support\Str;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

beforeEach(function () {
    $this->fakeStripe();
});

/*
|--------------------------------------------------------------------------
| Booking idempotency (ultrareview P0 #7)
|--------------------------------------------------------------------------
| A retried booking POST (lost response, double-click) must not create a second
| booking or capture a second charge. When the client supplies an Idempotency-Key
| the server replays the original booking. (Same-seat retries were already 409'd
| by the seat-reservation-before-charge ordering; this turns that into a clean
| replay and covers the lost-response case.)
*/

test('a retried booking POST with the same Idempotency-Key replays the original booking and charges once', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();
    $key = (string) Str::uuid();

    $body = [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id, $fixture['seats'][1]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ];

    $first = postJson($this->bookingUrl($fixture['location']), $body, ['Idempotency-Key' => $key]);
    $first->assertStatus(201);

    $second = postJson($this->bookingUrl($fixture['location']), $body, ['Idempotency-Key' => $key]);
    $second->assertStatus(201);

    // Same booking returned, not a conflict or a new row.
    expect($second->json('data.id'))->toBe($first->json('data.id'));
    // Exactly one Stripe charge across both requests.
    expect($fakeStripe->createCallCount)->toBe(1);
    expect(Booking::count())->toBe(1);
});

test('the Idempotency-Key is namespaced before being sent to Stripe', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe();
    $key = (string) Str::uuid();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => $key])->assertStatus(201);

    expect($fakeStripe->createdPaymentIntents[0]['idempotencyKey'])->toBe("booking:{$key}");
});

test('a malformed (non-uuid) Idempotency-Key is rejected with 422', function () {
    $fixture = $this->createShowtimeWithSeats();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => 'not-a-uuid'])->assertStatus(422);
});
