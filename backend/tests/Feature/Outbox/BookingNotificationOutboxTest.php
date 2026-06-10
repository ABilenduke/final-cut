<?php

use App\Enums\BookingStatus;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingRefundConfirmation;
use App\Mail\BookingConfirmationMail;
use App\Mail\BookingRefundedMail;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Services\BookingNotificationService;
use App\Services\BookingRefundService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

/** A Confirmed booking with full relations for mail rendering. */
function notificationBookingFixture(array $overrides = [], bool $withUser = true): Booking
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats();

    $user = $withUser ? User::factory()->create() : null;

    return Booking::factory()->create(array_merge([
        'showtime_id' => $ctx['showtime']->id,
        'user_id' => $user?->id,
        'guest_email' => $withUser ? null : 'guest@example.com',
        'status' => BookingStatus::Confirmed,
        'subtotal' => 2500,
        'discount' => 0,
        'total' => 2500,
        'stripe_payment_intent_id' => 'pi_notify_test',
    ], $overrides));
}

// ── Dispatcher match arms (outbox:dispatch round trips) ─────────────────────

test('outbox:dispatch maps a booking.refunded row to SendBookingRefundConfirmation', function (): void {
    Bus::fake();

    $row = DispatchOutbox::create([
        'event_type' => BookingRefundService::EVENT_REFUNDED,
        'payload' => [
            'booking_id' => 'b-uuid-1',
            'card_refund' => 2500,
            'gift_restored' => 500,
            'refunded_by_admin_user_id' => null,
        ],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(
        SendBookingRefundConfirmation::class,
        fn ($job) => $job->bookingId === 'b-uuid-1'
            && $job->cardRefund === 2500
            && $job->giftRestored === 500,
    );

    expect($row->refresh()->processed_at)->not->toBeNull();
});

test('outbox:dispatch maps a booking.confirmation_resend row to SendBookingConfirmation', function (): void {
    Bus::fake();

    $row = DispatchOutbox::create([
        'event_type' => BookingNotificationService::EVENT_CONFIRMATION_RESEND,
        'payload' => ['booking_id' => 'b-uuid-2'],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(
        SendBookingConfirmation::class,
        fn ($job) => $job->bookingId === 'b-uuid-2',
    );

    expect($row->refresh()->processed_at)->not->toBeNull();
});

test('a booking.refunded row with a missing required key is parked as malformed', function (): void {
    Bus::fake();

    $row = DispatchOutbox::create([
        'event_type' => BookingRefundService::EVENT_REFUNDED,
        'payload' => ['card_refund' => 2500], // booking_id missing
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();

    $row->refresh();
    expect($row->failed_at)->not->toBeNull()
        ->and($row->last_error)->toContain('booking_id');
});

// ── SendBookingRefundConfirmation job ───────────────────────────────────────

test('refund confirmation job mails the account email for member bookings', function (): void {
    Mail::fake();
    $booking = notificationBookingFixture();

    (new SendBookingRefundConfirmation($booking->id, 2500, 0))->handle();

    Mail::assertSent(
        BookingRefundedMail::class,
        fn (BookingRefundedMail $mail) => $mail->hasTo($booking->user->email)
            && $mail->cardRefund === 2500,
    );
});

test('refund confirmation job mails the guest email for guest bookings', function (): void {
    Mail::fake();
    $booking = notificationBookingFixture(withUser: false);

    (new SendBookingRefundConfirmation($booking->id, 0, 1500))->handle();

    Mail::assertSent(
        BookingRefundedMail::class,
        fn (BookingRefundedMail $mail) => $mail->hasTo('guest@example.com')
            && $mail->giftRestored === 1500,
    );
});

test('refund confirmation job no-ops when the booking is gone', function (): void {
    Mail::fake();

    (new SendBookingRefundConfirmation('00000000-0000-0000-0000-000000000000', 100, 0))->handle();

    Mail::assertNothingSent();
});

test('booking confirmation job renders and mails the full ticket', function (): void {
    Mail::fake();
    $booking = notificationBookingFixture();

    (new SendBookingConfirmation($booking->id))->handle();

    Mail::assertSent(
        BookingConfirmationMail::class,
        fn (BookingConfirmationMail $mail) => $mail->hasTo($booking->user->email),
    );
});

test('mailables render their markdown views without errors', function (): void {
    $booking = notificationBookingFixture()
        ->load('showtime.movie', 'showtime.auditorium.location', 'seats.seat', 'foodItems', 'user');

    $confirmation = (new BookingConfirmationMail($booking))->render();
    $refunded = (new BookingRefundedMail($booking, 2500, 500))->render();

    expect($confirmation)->toContain($booking->confirmation_code)
        ->and($refunded)->toContain($booking->confirmation_code)
        ->and($refunded)->toContain('25.00');
});

// ── End-to-end: refund writes the row, worker drains it ─────────────────────

test('a refunded booking flows through the outbox to the refund confirmation job', function (): void {
    Bus::fake();
    $this->fakeStripe();
    $booking = notificationBookingFixture();

    app(BookingRefundService::class)->refund($booking, 'end to end', null);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(
        SendBookingRefundConfirmation::class,
        fn ($job) => $job->bookingId === $booking->id && $job->cardRefund === 2500,
    );
});
