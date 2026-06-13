<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Models\Booking;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();
    $this->showtime = $ctx['showtime'];
    [$this->a1, $this->a2, $this->a3, $this->b1, $this->c1] = $ctx['seats'];
});

function confirmedBookingOnSeat($seat): Booking
{
    /** @var TestCase $self */
    $self = test();
    $booking = Booking::factory()->create([
        'showtime_id' => $self->showtime->id,
        'status' => BookingStatus::Confirmed,
        'subtotal' => 0,
        'discount' => 0,
        'total' => 0,
    ]);
    $total = app(SeatAvailabilityService::class)->reserveSeats($self->showtime, [$seat->id], $booking);
    $booking->update(['subtotal' => $total, 'total' => $total]);

    return $booking->fresh();
}

test('the reassign-seats action is visible to an admin on a confirmed booking', function (): void {
    $booking = confirmedBookingOnSeat($this->a1);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionVisible('reassign_seats');
});

test('the reassign-seats action is hidden for ops', function (): void {
    $booking = confirmedBookingOnSeat($this->a1);

    $this->actingAsOps();

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionHidden('reassign_seats');
});

test('the reassign-seats action is hidden on a cancelled booking', function (): void {
    $booking = confirmedBookingOnSeat($this->a1);
    $booking->update(['status' => BookingStatus::Cancelled]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionHidden('reassign_seats');
});

test('admin reassigns a booking to an equal-price seat through the action', function (): void {
    $booking = confirmedBookingOnSeat($this->a1);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->mountAction('reassign_seats')
        ->set('mountedActions.0.data.seat_ids', [$this->a2->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a2->id]);
});

test('a price-mismatched reassignment is surfaced as a notification and leaves the booking unchanged', function (): void {
    $booking = confirmedBookingOnSeat($this->a1); // standard, 1200

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->mountAction('reassign_seats')
        ->set('mountedActions.0.data.seat_ids', [$this->b1->id]) // premium, 1800
        ->callMountedAction()
        ->assertHasNoActionErrors();

    // The service rejected it; the action caught the exception and notified.
    expect($booking->fresh()->seats()->pluck('seat_id')->all())->toBe([$this->a1->id]);
    expect((int) $booking->fresh()->total)->toBe(1200);
});

test('the seat picker excludes seats taken by other bookings but includes this booking’s own seats', function (): void {
    $booking = confirmedBookingOnSeat($this->a1);
    confirmedBookingOnSeat($this->a3); // a3 taken by someone else

    $options = BookingResourceSelectableSeatOptionsProbe($booking);

    expect(array_keys($options))->toContain($this->a1->id); // own seat
    expect(array_keys($options))->toContain($this->a2->id); // free
    expect(array_keys($options))->not->toContain($this->a3->id); // taken elsewhere
});

/** Reach the protected option builder for a focused assertion. */
function BookingResourceSelectableSeatOptionsProbe(Booking $booking): array
{
    $method = new ReflectionMethod(BookingResource::class, 'selectableSeatOptions');

    return $method->invoke(null, $booking);
}
