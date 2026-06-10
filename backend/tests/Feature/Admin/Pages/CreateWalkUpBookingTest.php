<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\BookingResource\Pages\CreateWalkUpBooking;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Models\Booking;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;

uses(BookingTestHelper::class);

test('the page requires bookings.create_walkup', function (): void {
    $this->actingAsOps();
    Livewire::test(CreateWalkUpBooking::class)->assertForbidden();

    $this->actingAsAdmin();
    Livewire::test(CreateWalkUpBooking::class)->assertOk();
});

test('the bookings list offers the walk-up action only to permitted roles', function (): void {
    $this->actingAsAdmin();
    Livewire::test(ListBookings::class)->assertActionVisible('walk_up');

    $this->actingAsOps();
    Livewire::test(ListBookings::class)->assertActionHidden('walk_up');
});

test('a walk-up sale flows end to end from the page', function (): void {
    $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats(['start_time' => now()->addHours(3), 'end_time' => now()->addHours(5)]);

    Livewire::test(CreateWalkUpBooking::class)
        ->set('data.showtime_id', $ctx['showtime']->id)
        ->call('loadSeats')
        ->call('toggleSeat', $ctx['seats'][0]->id)
        ->call('toggleSeat', $ctx['seats'][1]->id)
        ->set('data.payment_method', PaymentMethod::Cash->value)
        ->set('data.guest_email', 'counter@example.com')
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::where('guest_email', 'counter@example.com')->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->total)->toBe(2400)
        ->and($booking->seats()->count())->toBe(2);
});

test('taken seats cannot be selected', function (): void {
    $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats(['start_time' => now()->addHours(3), 'end_time' => now()->addHours(5)]);

    $existing = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    app(SeatAvailabilityService::class)->reserveSeats($ctx['showtime'], [$ctx['seats'][0]->id], $existing);

    Livewire::test(CreateWalkUpBooking::class)
        ->set('data.showtime_id', $ctx['showtime']->id)
        ->call('loadSeats')
        ->call('toggleSeat', $ctx['seats'][0]->id)
        ->assertSet('selectedSeatIds', []);
});
