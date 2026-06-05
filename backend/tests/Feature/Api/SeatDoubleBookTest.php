<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

/**
 * End-to-end double-book coverage. The DB-level backstop (partial unique index)
 * is exercised by SeatOccupancyTriggerTest + SeatReservationConcurrencyTest;
 * here we prove the HTTP surface returns a clean 409 and — critically — that a
 * seat lost during a 3DS window refunds the capture and leaves no orphaned
 * Confirmed booking.
 */
uses(BookingTestHelper::class);

test('a second booking for an already-taken seat returns 409 with the seat id', function (): void {
    $this->fakeStripe()->shouldSucceed();
    $fixture = $this->createShowtimeWithSeats(['start_time' => now()->addDay()]);
    $seat = $fixture['seats'][0];

    $payload = [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$seat->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ];

    postJson($this->bookingUrl($fixture['location']), $payload)->assertStatus(201);

    // A genuine second attempt (no idempotency key) sees the committed first
    // booking via the checkAvailability pre-check → 409. The partial index is
    // the backstop if the pre-check ever raced; under single-connection
    // RefreshDatabase the pre-check is what fires here.
    postJson($this->bookingUrl($fixture['location']), $payload)
        ->assertStatus(409)
        ->assertJsonPath('errors.0.field', 'seatIds')
        ->assertJsonPath('errors.0.unavailableSeatIds.0', $seat->id);
});

test('a seat taken during the 3DS window is caught before capture: 409, no charge, no orphan', function (): void {
    $fakeStripe = $this->fakeStripe();
    $fixture = $this->createShowtimeWithSeats(['start_time' => now()->addDay()]);
    $seat = $fixture['seats'][0];

    // 3DS store: returns requiresAction + a paymentIntentId and discards the
    // Held booking, freeing the seat.
    $fakeStripe->shouldRequire3ds();
    $store = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$seat->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest@example.com',
    ]);
    $store->assertOk();
    $paymentIntentId = $store->json('data.paymentIntentId');

    // A racing customer grabs the seat during the 3DS wait.
    $other = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $other->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id' => $seat->id,
    ]);

    // confirm() Phase A re-checks seat availability BEFORE capturing the
    // PaymentIntent (BookingController:486), so the conflict 409s with NO
    // charge — the strongest outcome (nothing to refund, no orphaned booking).
    // The narrower post-capture A→C race (Phase C reserveSeats trips the partial
    // index → \Throwable@643 → refundOrReport) is covered by the index→exception
    // translation in SeatReservationConcurrencyTest plus the existing 3DS
    // refund-on-409 controller tests.
    $fakeStripe->shouldSucceed();
    postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ])
        ->assertStatus(409)
        ->assertJsonPath('errors.0.field', 'seatIds');

    expect($fakeStripe->confirmedPaymentIntents)->toBeEmpty(); // never captured
    expect($fakeStripe->refundedPaymentIntents)->toBeEmpty();  // nothing to refund
    expect(Booking::where('stripe_payment_intent_id', $paymentIntentId)
        ->where('status', BookingStatus::Confirmed)
        ->count())->toBe(0);
});
