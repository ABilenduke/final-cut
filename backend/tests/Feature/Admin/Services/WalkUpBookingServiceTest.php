<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\SeatConflictException;
use App\Exceptions\WalkUpBookingException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Services\SeatAvailabilityService;
use App\Services\WalkUpBookingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->service = app(WalkUpBookingService::class);
});

test('a cash walk-up sale creates a confirmed booking occupying its seats', function (): void {
    $admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    $booking = $this->service->create(
        $ctx['showtime']->id,
        [$ctx['seats'][0]->id, $ctx['seats'][3]->id], // A1 standard 1200 + B1 premium 1800
        PaymentMethod::Cash,
        'walkup@example.com',
        $admin,
    );

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->subtotal)->toBe(3000)
        ->and($booking->discount)->toBe(0)
        ->and($booking->total)->toBe(3000)
        ->and($booking->payment_method)->toBe(PaymentMethod::Cash)
        ->and($booking->guest_email)->toBe('walkup@example.com')
        ->and($booking->stripe_payment_intent_id)->toBeNull()
        ->and($booking->confirmation_code)->toStartWith('CVF-');

    expect(BookingSeat::where('booking_id', $booking->id)->where('occupies_seat', true)->count())->toBe(2);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', WalkUpBookingService::EVENT_CREATED)
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

test('a comp sale records the value as discount and charges nothing', function (): void {
    $admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    $booking = $this->service->create(
        $ctx['showtime']->id,
        [$ctx['seats'][0]->id],
        PaymentMethod::Comp,
        null,
        $admin,
        'manager comp — projector glitch apology',
    );

    expect($booking->subtotal)->toBe(1200)
        ->and($booking->discount)->toBe(1200)
        ->and($booking->total)->toBe(0)
        ->and($booking->notes)->toContain('projector glitch');
});

test('a seat conflict aborts cleanly without an orphan booking', function (): void {
    $admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    $existing = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    app(SeatAvailabilityService::class)->reserveSeats($ctx['showtime'], [$ctx['seats'][0]->id], $existing);

    $before = Booking::count();

    expect(fn () => $this->service->create(
        $ctx['showtime']->id,
        [$ctx['seats'][0]->id],
        PaymentMethod::Cash,
        null,
        $admin,
    ))->toThrow(SeatConflictException::class);

    expect(Booking::count())->toBe($before);
});

test('past and cancelled showtimes are refused', function (): void {
    $admin = $this->actingAsAdmin();

    $past = $this->createShowtimeWithSeats([
        'start_time' => now()->subHours(3),
        'end_time' => now()->subHour(),
    ]);
    expect(fn () => $this->service->create($past['showtime']->id, [$past['seats'][0]->id], PaymentMethod::Cash, null, $admin))
        ->toThrow(WalkUpBookingException::class, 'already started');

    $cancelled = $this->createShowtimeWithSeats(['cancelled_at' => now()]);
    expect(fn () => $this->service->create($cancelled['showtime']->id, [$cancelled['seats'][0]->id], PaymentMethod::Cash, null, $admin))
        ->toThrow(ModelNotFoundException::class);
});

test('non-pos payment methods and empty seat lists are refused', function (): void {
    $admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    expect(fn () => $this->service->create($ctx['showtime']->id, [$ctx['seats'][0]->id], PaymentMethod::Card, null, $admin))
        ->toThrow(WalkUpBookingException::class, 'point-of-sale');

    expect(fn () => $this->service->create($ctx['showtime']->id, [], PaymentMethod::Cash, null, $admin))
        ->toThrow(WalkUpBookingException::class, 'seat');
});
