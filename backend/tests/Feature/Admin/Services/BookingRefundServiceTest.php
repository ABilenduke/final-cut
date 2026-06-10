<?php

use App\Enums\BookingStatus;
use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingNotRefundableException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\LoyaltyAdjustment;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\GiftCardService;
use App\Services\SeatAvailabilityService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Stripe\Exception\ApiErrorException;
use Tests\Helpers\BookingTestHelper;
use Tests\Helpers\FakeStripeService;
use Tests\TestCase;

uses(BookingTestHelper::class);

/**
 * Layer B service-level integration tests for `BookingRefundService` against
 * the real DB: status flips, the booking_seats occupancy trigger, gift-card
 * ledger restoration, loyalty clawback, and the Phase A/B/C claim protocol
 * around the Stripe refund call.
 */
beforeEach(function (): void {
    $this->stripe = $this->fakeStripe();
    $this->service = app(BookingRefundService::class);
});

/**
 * Confirmed booking with two reserved seats. Defaults: $25.00 charged to card
 * (PI present); pass overrides for guest/held/gift-only variants.
 *
 * @return array{booking: Booking, user: ?User, showtime: Showtime, seats: Seat[]}
 */
function refundFixture(array $bookingOverrides = [], bool $withUser = true): array
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats();

    $user = $withUser ? User::factory()->create(['loyalty_points' => 100]) : null;

    $booking = Booking::factory()->create(array_merge([
        'showtime_id' => $ctx['showtime']->id,
        'user_id' => $user?->id,
        'guest_email' => $withUser ? null : 'guest@example.com',
        'status' => BookingStatus::Confirmed,
        'subtotal' => 2500,
        'discount' => 0,
        'total' => 2500,
        'payment_method' => PaymentMethod::Card,
        'stripe_payment_intent_id' => 'pi_refund_test_001',
    ], $bookingOverrides));

    app(SeatAvailabilityService::class)->reserveSeats(
        $ctx['showtime'],
        [$ctx['seats'][0]->id, $ctx['seats'][1]->id],
        $booking,
    );

    return ['booking' => $booking, 'user' => $user, 'showtime' => $ctx['showtime'], 'seats' => $ctx['seats']];
}

/** Attach a gift-card redemption of `$amount` to the booking (as checkout's Phase C would). */
function redeemGiftAgainst(Booking $booking, int $amount, int $cardBalance = 1000): GiftCard
{
    $card = GiftCard::factory()->active()->create([
        'initial_balance' => $cardBalance,
        'current_balance' => $cardBalance,
    ]);

    app(GiftCardService::class)->redeemAgainstBooking($card, $amount, $booking);

    return $card;
}

// ── Full refund: card + gift card + loyalty + seats ─────────────────────────

test('full split refund issues stripe refund, restores gift card, claws back points, releases seats', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture([
        'discount' => 500,
        'total' => 2500,
        'payment_method' => PaymentMethod::Mixed,
    ]);
    $card = redeemGiftAgainst($booking, 500);
    $admin = $this->actingAsAdmin();

    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(2);

    $this->service->refund($booking, 'customer complaint', $admin);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Refunded)
        ->and($booking->refunded_at)->not->toBeNull()
        ->and($booking->stripe_refund_id)->not->toBeNull();

    // Stripe: full refund of the PI (card portion == total).
    expect($this->stripe->refundedPaymentIntents)->toHaveCount(1)
        ->and($this->stripe->refundedPaymentIntents[0]['paymentIntentId'])->toBe('pi_refund_test_001');

    // Gift card: balance restored with a Refund ledger entry tied to the booking.
    $card->refresh();
    expect($card->current_balance)->toBe(1000);
    $refundEntry = GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Refund)->first();
    expect($refundEntry)->not->toBeNull()
        ->and($refundEntry->amount_cents)->toBe(500)
        ->and($refundEntry->booking_id)->toBe($booking->id);

    // Loyalty: floor(2500/100) = 25 points clawed back, audited.
    $user->refresh();
    expect($user->loyalty_points)->toBe(75);
    expect(LoyaltyAdjustment::where('user_id', $user->id)->where('points_delta', -25)->count())->toBe(1);

    // Seats: released by the occupancy trigger — never by direct booking_seats writes.
    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(0);

    // Activity log row attributed to the admin.
    $activity = Activity::where('log_name', 'admin')->where('description', 'booking.refunded')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

test('refund calls stripe outside any open transaction', function (): void {
    ['booking' => $booking] = refundFixture();
    // RefreshDatabase wraps every test in a transaction, so "no transaction
    // open" inside a test means "at the baseline level", not literal 0.
    $baseline = DB::transactionLevel();

    $this->service->refund($booking, 'ops', null);

    expect($this->stripe->refundTransactionLevels)->toBe([$baseline]);
});

// ── No-card and no-user variants ────────────────────────────────────────────

test('gift-card-only booking refunds without touching stripe', function (): void {
    ['booking' => $booking] = refundFixture([
        'stripe_payment_intent_id' => null,
        'payment_method' => PaymentMethod::GiftCard,
        'discount' => 2500,
        'total' => 0,
    ]);
    $card = redeemGiftAgainst($booking, 2500, 3000);

    $this->service->refund($booking, 'event cancelled', null);

    expect($this->stripe->refundedPaymentIntents)->toBeEmpty();
    expect($booking->refresh()->status)->toBe(BookingStatus::Refunded);
    expect($card->refresh()->current_balance)->toBe(3000);
});

test('guest booking refunds without any loyalty mutation', function (): void {
    ['booking' => $booking] = refundFixture(withUser: false);

    $this->service->refund($booking, 'guest refund', null);

    expect($booking->refresh()->status)->toBe(BookingStatus::Refunded);
    expect(LoyaltyAdjustment::count())->toBe(0);
});

test('clawback below one point writes no loyalty adjustment row', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture(['subtotal' => 50, 'total' => 50]);

    $this->service->refund($booking, 'tiny order', null);

    expect(LoyaltyAdjustment::count())->toBe(0);
    expect($user->refresh()->loyalty_points)->toBe(100);
});

// ── Gift-card edge cases ────────────────────────────────────────────────────

test('restoring onto a depleted gift card reactivates it', function (): void {
    ['booking' => $booking] = refundFixture();
    $card = redeemGiftAgainst($booking, 800, 800); // fully drained → Depleted

    expect($card->refresh()->status)->toBe(GiftCardStatus::Depleted);

    $this->service->refund($booking, 'restore depleted', null);

    $card->refresh();
    expect($card->status)->toBe(GiftCardStatus::Active)
        ->and($card->current_balance)->toBe(800);
});

test('voided gift cards are skipped, not restored', function (): void {
    ['booking' => $booking] = refundFixture();
    $card = redeemGiftAgainst($booking, 400);
    app(GiftCardService::class)->void($card, 'fraud investigation', null);
    $admin = $this->actingAsAdmin();

    $this->service->refund($booking, 'refund with voided card', $admin);

    $card->refresh();
    expect($card->status)->toBe(GiftCardStatus::Voided)
        ->and($card->current_balance)->toBe(0);
    expect(GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Refund)->count())->toBe(0);

    // The skip is surfaced for manual follow-up in the activity properties.
    $activity = Activity::where('log_name', 'admin')->where('description', 'booking.refunded')->first();
    expect($activity->properties['gift_skipped'])->toContain($card->code);
});

// ── Status matrix ───────────────────────────────────────────────────────────

test('refund-pending booking from a showtime cancellation refunds cleanly', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture([
        'status' => BookingStatus::RefundPending,
        'flagged_at' => now(),
        'flag_reason' => 'showtime_cancelled:some-uuid',
    ]);

    $this->service->refund($booking, 'showtime cancelled payout', null);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Refunded);
    // Points were awarded when this booking was Confirmed — clawed back here.
    expect($user->refresh()->loyalty_points)->toBe(75);
});

test('held booking cancels with no money movement', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture(['status' => BookingStatus::Held]);

    $this->service->refund($booking, 'releasing stuck hold', null);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->refunded_at)->toBeNull();
    expect($this->stripe->refundedPaymentIntents)->toBeEmpty();
    expect($user->refresh()->loyalty_points)->toBe(100);
    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(0);
});

test('double refund is rejected', function (): void {
    ['booking' => $booking] = refundFixture();
    $this->service->refund($booking, 'first', null);

    $this->service->refund($booking->refresh(), 'second', null);
})->throws(BookingNotRefundableException::class, 'already been refunded');

test('cancelled booking is rejected', function (): void {
    ['booking' => $booking] = refundFixture(['status' => BookingStatus::Cancelled]);

    $this->service->refund($booking, 'nope', null);
})->throws(BookingNotRefundableException::class, 'already been cancelled');

// ── Claim protocol (concurrent + crashed runs) ──────────────────────────────

test('a fresh in-flight claim blocks a second refund attempt', function (): void {
    ['booking' => $booking] = refundFixture();
    $booking->update(['refund_initiated_at' => now()->subMinutes(2)]);

    $this->service->refund($booking, 'concurrent admin', null);
})->throws(BookingNotRefundableException::class, 'already in progress');

test('a stale claim from a crashed run can be retaken', function (): void {
    ['booking' => $booking] = refundFixture();
    $booking->update(['refund_initiated_at' => now()->subMinutes(20)]);

    $this->service->refund($booking, 'retaking crashed claim', null);

    expect($booking->refresh()->status)->toBe(BookingStatus::Refunded);
    expect($this->stripe->refundedPaymentIntents)->toHaveCount(1);
});

test('retry after a phase-c crash skips stripe and finalizes', function (): void {
    // Simulate: Stripe refund succeeded, then the finalize transaction died.
    ['booking' => $booking] = refundFixture();
    $booking->update([
        'refund_initiated_at' => now()->subMinutes(1),
        'stripe_refund_id' => 're_already_issued',
    ]);

    $this->service->refund($booking, 'resume after crash', null);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Refunded)
        ->and($booking->stripe_refund_id)->toBe('re_already_issued');
    expect($this->stripe->refundedPaymentIntents)->toBeEmpty();
});

// ── Stripe failure ──────────────────────────────────────────────────────────

test('stripe refund failure leaves the booking untouched and releases the claim', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture();
    $card = redeemGiftAgainst($booking, 500);
    $this->stripe->shouldFailRefund();

    expect(fn () => $this->service->refund($booking, 'stripe down', null))
        ->toThrow(ApiErrorException::class);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->refunded_at)->toBeNull()
        ->and($booking->stripe_refund_id)->toBeNull()
        ->and($booking->refund_initiated_at)->toBeNull(); // claim released for retry

    expect($card->refresh()->current_balance)->toBe(500);
    expect($user->refresh()->loyalty_points)->toBe(100);
    expect(GiftCardLedgerEntry::where('type', GiftCardLedgerType::Refund)->count())->toBe(0);
    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(2);
});

// ── Attribution + preview ───────────────────────────────────────────────────

test('null actor writes no service-level refund activity event', function (): void {
    ['booking' => $booking] = refundFixture();

    $this->service->refund($booking, 'customer-path refund', null);

    // The LoyaltyAdjustment model self-logs its own audit row regardless of
    // actor (existing behavior); the service-level booking.refunded event is
    // the admin-attribution row and must be absent for null actors.
    expect(Activity::where('log_name', 'admin')->where('description', 'booking.refunded')->count())->toBe(0);
});

test('previewSplit reports the split without mutating anything', function (): void {
    ['booking' => $booking, 'user' => $user] = refundFixture([
        'discount' => 500,
        'total' => 2500,
        'payment_method' => PaymentMethod::Mixed,
    ]);
    $card = redeemGiftAgainst($booking, 500);

    $split = $this->service->previewSplit($booking);

    expect($split['card_refund'])->toBe(2500)
        ->and($split['loyalty_clawback'])->toBe(25)
        ->and($split['target_status'])->toBe(BookingStatus::Refunded)
        ->and($split['gift_restores'])->toHaveCount(1)
        ->and($split['gift_restores'][0]['amount'])->toBe(500)
        ->and($split['gift_restores'][0]['code'])->toBe($card->code);

    // Read-only: nothing moved.
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->refund_initiated_at)->toBeNull();
    expect($this->stripe->refundedPaymentIntents)->toBeEmpty();
    expect($user->refresh()->loyalty_points)->toBe(100);
});

test('fake stripe service is bound for this suite', function (): void {
    expect(app(StripeService::class))->toBeInstanceOf(FakeStripeService::class);
});
