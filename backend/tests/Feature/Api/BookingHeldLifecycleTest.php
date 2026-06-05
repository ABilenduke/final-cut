<?php

use App\Enums\BookingStatus;
use App\Enums\GiftCardStatus;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\GiftCard;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

beforeEach(function () {
    $this->fakeStripe();
});

/*
|--------------------------------------------------------------------------
| Held-booking lifecycle hardening (payment-path review follow-ups)
|--------------------------------------------------------------------------
*/

test('a Phase A bail (partial gift card, no payment method) leaves NO Held booking behind', function () {
    // Regression: the bail used to `return` from inside DB::transaction, which
    // COMMITS — leaking the Held booking + booking_seats. It must throw + roll back.
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 100, // covers only part of the 1200 seat — card needed
        'status' => GiftCardStatus::Active,
    ]);

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
        // no paymentMethodId
    ])->assertStatus(422);

    expect(Booking::count())->toBe(0);
    expect(BookingSeat::count())->toBe(0);
});

test('store() reusing the key of an in-flight Held booking returns 409 (not a false 201, not a 500)', function () {
    // Exercises the UniqueConstraintViolationException catch (would 500 if the
    // import were missing) AND the replay-to-Confirmed filter (a Held booking
    // must never be reported as a completed purchase).
    $fixture = $this->createShowtimeWithSeats();
    $key = (string) Str::uuid();

    Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Held,
        'idempotency_key' => $key,
    ]);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ], ['Idempotency-Key' => $key]);

    $response->assertStatus(409);
    // No second booking created; the Held one is untouched.
    expect(Booking::where('idempotency_key', $key)->count())->toBe(1);
});

test('3DS confirm forgets pending state after a post-charge promo failure so retries are not poisoned', function () {
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

    // Promo deactivated during the 3DS window → Phase C consume() fails AFTER the
    // charge, so confirm() refunds and 409s.
    PromoCode::where('code', 'OFF5')->update(['deactivated_at' => now()]);
    $fakeStripe->shouldSucceed();

    postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ])->assertStatus(409);

    // The PI is now refunded, so the pending state must be gone — a retry gets a
    // clean 410, never a raw Stripe 400 against the dead intent.
    expect(Cache::get("pending_booking:{$paymentIntentId}"))->toBeNull();

    postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $paymentIntentId,
    ])->assertStatus(410);
});

test('bookings:expire-held deletes abandoned Held bookings and frees seats, keeping recent and confirmed ones', function () {
    $fixture = $this->createShowtimeWithSeats();

    $oldHeld = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Held,
        'created_at' => now()->subMinutes(30),
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $oldHeld->id,
        'showtime_id' => $fixture['showtime']->id,
        'seat_id' => $fixture['seats'][0]->id,
    ]);

    $recentHeld = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Held,
        'created_at' => now()->subMinutes(3),
    ]);
    $confirmed = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('bookings:expire-held')->assertExitCode(0);

    $this->assertDatabaseMissing('bookings', ['id' => $oldHeld->id]);
    $this->assertDatabaseMissing('booking_seats', ['booking_id' => $oldHeld->id]);
    $this->assertDatabaseHas('bookings', ['id' => $recentHeld->id]);
    $this->assertDatabaseHas('bookings', ['id' => $confirmed->id]);
});
