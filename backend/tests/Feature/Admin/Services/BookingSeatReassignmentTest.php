<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingAmendmentException;
use App\Exceptions\SeatConflictException;
use App\Models\Booking;
use App\Services\BookingAmendmentService;
use App\Services\SeatAvailabilityService;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();
    $this->showtime = $ctx['showtime'];
    $this->auditorium = $ctx['auditorium'];
    [$this->a1, $this->a2, $this->a3, $this->b1, $this->c1] = $ctx['seats'];
    $this->service = app(BookingAmendmentService::class);
    $this->seats = app(SeatAvailabilityService::class);
});

/** Create a confirmed booking occupying the given seats, with matching money columns. */
function bookingOn(array $seats, array $attrs = []): Booking
{
    /** @var TestCase $self */
    $self = test();
    $booking = Booking::factory()->create(array_merge([
        'showtime_id' => $self->showtime->id,
        'status' => BookingStatus::Confirmed,
        'subtotal' => 0,
        'discount' => 0,
        'total' => 0,
    ], $attrs));

    $total = $self->seats->reserveSeats($self->showtime, collect($seats)->pluck('id')->all(), $booking);
    $booking->update(['subtotal' => $total, 'total' => $total]);

    return $booking->fresh();
}

test('reassigns a confirmed booking to an equal-price seat, freeing the old one', function (): void {
    $booking = bookingOn([$this->a1]);

    $this->service->reassignSeats($booking, [$this->a2->id], $this->admin);

    $seatIds = $booking->fresh()->seats()->pluck('seat_id')->all();
    expect($seatIds)->toBe([$this->a2->id]);

    // The old seat is selectable again; the new one is now taken.
    expect($this->seats->checkAvailability($this->showtime->id, [$this->a1->id]))->toBe([]);
    expect($this->seats->checkAvailability($this->showtime->id, [$this->a2->id]))->toBe([$this->a2->id]);

    // Money is untouched.
    expect((int) $booking->fresh()->total)->toBe(1200);
});

test('writes an admin activity row with from/to seat ids', function (): void {
    $booking = bookingOn([$this->a1]);

    $this->service->reassignSeats($booking, [$this->a2->id], $this->admin);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', BookingAmendmentService::EVENT_SEATS_REASSIGNED)
        ->where('subject_id', $booking->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('from_seat_ids'))->toBe([$this->a1->id]);
    expect($activity->properties->get('to_seat_ids'))->toBe([$this->a2->id]);
});

test('a null actor reassigns without writing an activity row', function (): void {
    $booking = bookingOn([$this->a1]);

    $this->service->reassignSeats($booking, [$this->a2->id], null);

    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a2->id]);
    expect(Activity::where('description', BookingAmendmentService::EVENT_SEATS_REASSIGNED)->count())->toBe(0);
});

test('rejects a reassignment whose total price differs from the original', function (): void {
    $booking = bookingOn([$this->a1]); // standard, 1200

    expect(fn () => $this->service->reassignSeats($booking, [$this->b1->id], $this->admin)) // premium, 1800
        ->toThrow(BookingAmendmentException::class);

    // Rolled back: still on the original seat.
    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a1->id]);
    expect($this->seats->checkAvailability($this->showtime->id, [$this->b1->id]))->toBe([]);
});

test('rejects a reassignment onto a seat held by another booking, leaving the original intact', function (): void {
    $booking = bookingOn([$this->a1]);
    bookingOn([$this->a2]); // a2 is taken by someone else

    expect(fn () => $this->service->reassignSeats($booking, [$this->a2->id], $this->admin))
        ->toThrow(SeatConflictException::class);

    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a1->id]);
});

test('refuses to reassign a cancelled booking', function (): void {
    $booking = bookingOn([$this->a1], ['status' => BookingStatus::Cancelled]);

    expect(fn () => $this->service->reassignSeats($booking, [$this->a2->id], $this->admin))
        ->toThrow(BookingAmendmentException::class);
});

test('rejects a seat that belongs to a different auditorium', function (): void {
    $booking = bookingOn([$this->a1]);
    $other = $this->createShowtimeWithSeats();
    $foreignSeat = $other['seats'][0];

    expect(fn () => $this->service->reassignSeats($booking, [$foreignSeat->id], $this->admin))
        ->toThrow(ValidationException::class);

    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a1->id]);
});

test('rejects an empty seat selection', function (): void {
    $booking = bookingOn([$this->a1]);

    expect(fn () => $this->service->reassignSeats($booking, [], $this->admin))
        ->toThrow(BookingAmendmentException::class);

    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a1->id]);
});
