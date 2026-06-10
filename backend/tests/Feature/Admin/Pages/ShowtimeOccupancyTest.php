<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\ShowtimeResource\Pages\ListShowtimes;
use App\Filament\Resources\ShowtimeResource\Pages\ShowtimeOccupancy;
use App\Filament\Resources\ShowtimeResource\Pages\ViewShowtime;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

/**
 * Fixture: 5-seat showtime (A1 A2 A3 standard, B1 premium, C1 accessible)
 * with one Confirmed booking on A1+A2, one Held on B1, and one Refunded on
 * A3 (whose seat must read as available again).
 *
 * @return array{showtime: Showtime, seats: Seat[]}
 */
function occupancyFixture(): array
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats();
    $seatService = app(SeatAvailabilityService::class);

    $confirmed = Booking::factory()->create([
        'showtime_id' => $ctx['showtime']->id,
        'user_id' => User::factory()->create()->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $seatService->reserveSeats($ctx['showtime'], [$ctx['seats'][0]->id, $ctx['seats'][1]->id], $confirmed);

    $held = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Held,
    ]);
    $seatService->reserveSeats($ctx['showtime'], [$ctx['seats'][3]->id], $held); // B1

    // Reserve under Confirmed, then refund — the trigger releases the seat.
    $refunded = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $seatService->reserveSeats($ctx['showtime'], [$ctx['seats'][2]->id], $refunded); // A3
    $refunded->update(['status' => BookingStatus::Refunded]);

    return ['showtime' => $ctx['showtime'], 'seats' => $ctx['seats']];
}

test('the occupancy page derives per-seat states and counts from the occupancy guard', function (): void {
    $this->actingAsAdmin();
    ['showtime' => $showtime, 'seats' => $seats] = occupancyFixture();

    Livewire::test(ShowtimeOccupancy::class, ['record' => $showtime->id])
        ->assertSet('counts.sold', 2)
        ->assertSet('counts.held', 1)
        ->assertSet('counts.refund_pending', 0)
        ->assertSet('counts.available', 2)
        ->assertSet('counts.unavailable', 0)
        ->assertSet('counts.capacity', 5)
        ->assertSet("seatStates.{$seats[0]->id}.state", 'sold')
        ->assertSet("seatStates.{$seats[1]->id}.state", 'sold')
        ->assertSet("seatStates.{$seats[2]->id}.state", 'available') // refunded → released
        ->assertSet("seatStates.{$seats[3]->id}.state", 'held')
        ->assertSet("seatStates.{$seats[4]->id}.state", 'available');
});

test('refund-pending bookings still occupy their seats on the map', function (): void {
    $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::RefundPending,
    ]);
    app(SeatAvailabilityService::class)->reserveSeats($ctx['showtime'], [$ctx['seats'][0]->id], $booking);

    Livewire::test(ShowtimeOccupancy::class, ['record' => $ctx['showtime']->id])
        ->assertSet('counts.refund_pending', 1)
        ->assertSet("seatStates.{$ctx['seats'][0]->id}.state", 'refund_pending');
});

test('admin-blocked seats render as unavailable', function (): void {
    $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();
    Seat::whereKey($ctx['seats'][4]->id)->update(['unavailable_at' => now()]);

    Livewire::test(ShowtimeOccupancy::class, ['record' => $ctx['showtime']->id])
        ->assertSet('counts.unavailable', 1)
        ->assertSet("seatStates.{$ctx['seats'][4]->id}.state", 'unavailable');
});

test('occupied seats carry their booking confirmation code', function (): void {
    $this->actingAsAdmin();
    ['showtime' => $showtime, 'seats' => $seats] = occupancyFixture();

    $component = Livewire::test(ShowtimeOccupancy::class, ['record' => $showtime->id]);
    $soldSeat = $component->get('seatStates')[$seats[0]->id];

    expect($soldSeat['confirmation_code'])->toStartWith('CVF-');
});

test('ops can view the occupancy page; a roleless admin cannot', function (): void {
    $ctx = $this->createShowtimeWithSeats();

    $this->actingAsOps();
    Livewire::test(ShowtimeOccupancy::class, ['record' => $ctx['showtime']->id])
        ->assertOk();

    $this->actingAsNobody();
    Livewire::test(ShowtimeOccupancy::class, ['record' => $ctx['showtime']->id])
        ->assertForbidden();
});

test('the showtimes list shows an occupied-over-capacity column', function (): void {
    $this->actingAsAdmin();
    ['showtime' => $showtime] = occupancyFixture();

    Livewire::test(ListShowtimes::class)
        ->assertCanSeeTableRecords([$showtime])
        ->assertSee('3 / 5'); // 2 sold + 1 held occupy; refunded released
});

test('the view page links to the occupancy map', function (): void {
    $this->actingAsAdmin();
    $ctx = $this->createShowtimeWithSeats();

    Livewire::test(ViewShowtime::class, ['record' => $ctx['showtime']->id])
        ->assertActionVisible('occupancy_map');
});
