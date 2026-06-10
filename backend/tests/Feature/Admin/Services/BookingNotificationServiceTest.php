<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingNotResendableException;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Services\BookingNotificationService;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->service = app(BookingNotificationService::class);
});

function resendFixture(array $overrides = [], bool $withUser = true): Booking
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
    ], $overrides));
}

test('resendConfirmation writes an outbox row and admin activity in one transaction', function (): void {
    $booking = resendFixture();
    $admin = $this->actingAsAdmin();

    $this->service->resendConfirmation($booking, $admin);

    $row = DispatchOutbox::where('event_type', BookingNotificationService::EVENT_CONFIRMATION_RESEND)->first();
    expect($row)->not->toBeNull()
        ->and($row->payload['booking_id'])->toBe($booking->id);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', BookingNotificationService::EVENT_CONFIRMATION_RESEND)
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

test('resendConfirmation with null actor writes the outbox row but no activity', function (): void {
    $booking = resendFixture();

    $this->service->resendConfirmation($booking, null);

    expect(DispatchOutbox::where('event_type', BookingNotificationService::EVENT_CONFIRMATION_RESEND)->count())->toBe(1);
    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('resendConfirmation rejects non-confirmed bookings', function (BookingStatus $status): void {
    $booking = resendFixture(['status' => $status]);

    expect(fn () => $this->service->resendConfirmation($booking, null))
        ->toThrow(BookingNotResendableException::class, 'confirmed');

    expect(DispatchOutbox::count())->toBe(0);
})->with([
    'held' => BookingStatus::Held,
    'refund pending' => BookingStatus::RefundPending,
    'cancelled' => BookingStatus::Cancelled,
    'refunded' => BookingStatus::Refunded,
]);

test('resendConfirmation rejects a booking with no recipient email', function (): void {
    $booking = resendFixture(['guest_email' => null], withUser: false);

    expect(fn () => $this->service->resendConfirmation($booking, null))
        ->toThrow(BookingNotResendableException::class, 'email');

    expect(DispatchOutbox::count())->toBe(0);
});
