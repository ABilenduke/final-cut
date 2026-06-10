<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingFlagException;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Services\BookingFlagService;
use App\Services\BookingNotificationService;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->stripe = $this->fakeStripe();
});

/** A Confirmed card-paid booking with two reserved seats. */
function actionBookingFixture(array $overrides = []): Booking
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats();

    $booking = Booking::factory()->create(array_merge([
        'showtime_id' => $ctx['showtime']->id,
        'user_id' => User::factory()->create()->id,
        'status' => BookingStatus::Confirmed,
        'subtotal' => 2500,
        'discount' => 0,
        'total' => 2500,
        'stripe_payment_intent_id' => 'pi_action_test',
    ], $overrides));

    app(SeatAvailabilityService::class)->reserveSeats(
        $ctx['showtime'],
        [$ctx['seats'][0]->id, $ctx['seats'][1]->id],
        $booking,
    );

    return $booking;
}

// ── Refund action ───────────────────────────────────────────────────────────

test('admin can refund a confirmed booking from the view page', function (): void {
    $this->actingAsAdmin();
    $booking = actionBookingFixture();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionVisible('refund')
        ->mountAction('refund')
        ->set('mountedActions.0.data.reason', 'Projector failure, full refund issued')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Refunded)
        ->and($booking->stripe_refund_id)->not->toBeNull();
    expect($this->stripe->refundedPaymentIntents)->toHaveCount(1);
    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(0);
});

test('refunding a held booking releases it as cancelled', function (): void {
    $this->actingAsAdmin();
    $booking = actionBookingFixture([
        'status' => BookingStatus::Held,
        'stripe_payment_intent_id' => null,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->mountAction('refund')
        ->set('mountedActions.0.data.reason', 'Releasing a stuck checkout hold')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
    expect($this->stripe->refundedPaymentIntents)->toBeEmpty();
});

test('refund action is hidden for terminal bookings and for ops', function (): void {
    $this->actingAsAdmin();
    $refunded = actionBookingFixture(['status' => BookingStatus::Refunded]);

    Livewire::test(ViewBooking::class, ['record' => $refunded->id])
        ->assertActionHidden('refund');

    $this->actingAsOps();
    $confirmed = actionBookingFixture(['stripe_payment_intent_id' => 'pi_ops_test']);

    Livewire::test(ViewBooking::class, ['record' => $confirmed->id])
        ->assertActionHidden('refund');
});

test('a stripe failure surfaces as an error notification and leaves the booking untouched', function (): void {
    $this->actingAsAdmin();
    $booking = actionBookingFixture();
    $this->stripe->shouldFailRefund();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->mountAction('refund')
        ->set('mountedActions.0.data.reason', 'Will fail at the Stripe call')
        ->callMountedAction()
        ->assertNotified();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->refund_initiated_at)->toBeNull();
});

// ── Resend confirmation action ──────────────────────────────────────────────

test('admin can resend the booking confirmation email', function (): void {
    $this->actingAsAdmin();
    $booking = actionBookingFixture();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionVisible('resend_confirmation')
        ->callAction('resend_confirmation')
        ->assertHasNoActionErrors();

    expect(DispatchOutbox::where('event_type', BookingNotificationService::EVENT_CONFIRMATION_RESEND)->count())->toBe(1);
});

test('resend confirmation is hidden for non-confirmed bookings and for ops', function (): void {
    $this->actingAsAdmin();
    $cancelled = actionBookingFixture(['status' => BookingStatus::Cancelled]);

    Livewire::test(ViewBooking::class, ['record' => $cancelled->id])
        ->assertActionHidden('resend_confirmation');

    $this->actingAsOps();
    $confirmed = actionBookingFixture(['stripe_payment_intent_id' => 'pi_ops_resend']);

    Livewire::test(ViewBooking::class, ['record' => $confirmed->id])
        ->assertActionHidden('resend_confirmation');
});

// ── Flag / unflag actions ───────────────────────────────────────────────────

test('admin can flag and unflag a booking with audited reasons', function (): void {
    $admin = $this->actingAsAdmin();
    $booking = actionBookingFixture();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionVisible('flag')
        ->assertActionHidden('unflag')
        ->mountAction('flag')
        ->set('mountedActions.0.data.reason', 'Possible duplicate purchase, reviewing')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $booking->refresh();
    expect($booking->flagged_at)->not->toBeNull()
        ->and($booking->flag_reason)->toBe('Possible duplicate purchase, reviewing');

    $flagActivity = Activity::where('log_name', 'admin')
        ->where('description', BookingFlagService::EVENT_FLAGGED)->first();
    expect($flagActivity)->not->toBeNull()
        ->and($flagActivity->causer_id)->toBe($admin->id);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionHidden('flag')
        ->assertActionVisible('unflag')
        ->callAction('unflag')
        ->assertHasNoActionErrors();

    $booking->refresh();
    expect($booking->flagged_at)->toBeNull()
        ->and($booking->flag_reason)->toBeNull();
    expect(Activity::where('description', BookingFlagService::EVENT_UNFLAGGED)->count())->toBe(1);
});

test('the reserved showtime_cancelled prefix is rejected at the form layer', function (): void {
    $this->actingAsAdmin();
    $booking = actionBookingFixture();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->mountAction('flag')
        ->set('mountedActions.0.data.reason', 'showtime_cancelled:sneaky-manual-flag')
        ->callMountedAction()
        ->assertHasActionErrors(['reason']);

    expect($booking->refresh()->flagged_at)->toBeNull();
});

test('flag actions are hidden without the bookings.flag permission', function (): void {
    $this->actingAsOps();
    $booking = actionBookingFixture();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionHidden('flag')
        ->assertActionHidden('unflag');
});

// ── BookingFlagService (service-layer guards) ───────────────────────────────

test('the flag service rejects the reserved prefix and double flags', function (): void {
    $booking = actionBookingFixture();
    $service = app(BookingFlagService::class);

    expect(fn () => $service->flag($booking, 'showtime_cancelled:bypass', null))
        ->toThrow(BookingFlagException::class, 'reserved');

    $service->flag($booking, 'fraud review', null);
    expect(fn () => $service->flag($booking->refresh(), 'again', null))
        ->toThrow(BookingFlagException::class, 'already flagged');

    $service->unflag($booking->refresh(), null);
    expect(fn () => $service->unflag($booking->refresh(), null))
        ->toThrow(BookingFlagException::class, 'not flagged');
});
