<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\User;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $this->fixture = $this->createShowtimeWithSeats();
});

/*
|--------------------------------------------------------------------------
| List
|--------------------------------------------------------------------------
*/

test('admins can see the bookings list', function (): void {
    $bookings = Booking::factory()->count(3)->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords($bookings);
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('no role can create, edit, or delete a booking', function (): void {
    $booking = Booking::factory()->create(['showtime_id' => $this->fixture['showtime']->id]);

    foreach ([$this->actingAsAdmin(...), $this->actingAsManager(...), $this->actingAsOps(...)] as $actAs) {
        $actAs();
        expect(BookingResource::canCreate())->toBeFalse();
        expect(BookingResource::canEdit($booking))->toBeFalse();
        expect(BookingResource::canDelete($booking))->toBeFalse();
    }
});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

test('searching by confirmation code is case-insensitive', function (): void {
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    $code = strtolower($booking->confirmation_code); // confirmation codes are stored uppercase

    Livewire::test(ListBookings::class)
        ->searchTable($code)
        ->assertCanSeeTableRecords([$booking]);
});

test('searching by guest email finds guest bookings', function (): void {
    $guestBooking = Booking::factory()->guest()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'guest_email' => 'walkin@example.com',
    ]);

    $otherGuest = Booking::factory()->guest()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    Livewire::test(ListBookings::class)
        ->searchTable('walkin@example.com')
        ->assertCanSeeTableRecords([$guestBooking])
        ->assertCanNotSeeTableRecords([$otherGuest]);
});

test('searching by authenticated user email finds their bookings', function (): void {
    $alice = User::factory()->create(['email' => 'alice@example.com', 'name' => 'Alice Cooper']);
    $bob = User::factory()->create(['email' => 'bob@example.com', 'name' => 'Bob Dylan']);

    $aliceBooking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'user_id' => $alice->id,
    ]);
    $bobBooking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'user_id' => $bob->id,
    ]);

    Livewire::test(ListBookings::class)
        ->searchTable('alice@example.com')
        ->assertCanSeeTableRecords([$aliceBooking])
        ->assertCanNotSeeTableRecords([$bobBooking]);
});

test('searching by authenticated user name finds their bookings', function (): void {
    $alice = User::factory()->create(['email' => 'alice@example.com', 'name' => 'Alice Cooper']);
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'user_id' => $alice->id,
    ]);

    Livewire::test(ListBookings::class)
        ->searchTable('Cooper')
        ->assertCanSeeTableRecords([$booking]);
});

/*
|--------------------------------------------------------------------------
| Status synthesis + filters
|--------------------------------------------------------------------------
*/

test('flagged bookings render as flagged regardless of underlying status', function (): void {
    $flagged = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'flagged_at' => now(),
        'flag_reason' => 'showtime_cancelled:projector failure',
    ]);

    // State for a table column rendered with `badge()` + `getStateUsing` is
    // asserted via assertTableColumnStateSet.
    Livewire::test(ListBookings::class)
        ->assertTableColumnStateSet('status', 'flagged', $flagged);
});

test('status filter limits rows to the selected enum value', function (): void {
    $confirmed = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $cancelled = Booking::factory()->cancelled()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    Livewire::test(ListBookings::class)
        ->filterTable('status', BookingStatus::Cancelled->value)
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$confirmed]);
});

test('list page exposes the expected filter set', function (): void {
    Livewire::test(ListBookings::class)
        ->assertTableFilterExists('status')
        ->assertTableFilterExists('date_range')
        ->assertTableFilterExists('location_id')
        ->assertTableFilterExists('showtime_id');
});

/*
|--------------------------------------------------------------------------
| View page
|--------------------------------------------------------------------------
*/

test('view page renders a booking with eager-loaded relations', function (): void {
    $booking = Booking::factory()->create(['showtime_id' => $this->fixture['showtime']->id]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertSuccessful()
        ->assertSee($booking->confirmation_code);
});

test('stripe payment intent is hidden from ops (who lack bookings.resolve_refund)', function (): void {
    $this->actingAsOps();
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'stripe_payment_intent_id' => 'pi_hiddenfromops',
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertSuccessful()
        ->assertDontSee('pi_hiddenfromops');
});

test('stripe payment intent is shown to admins who have bookings.resolve_refund', function (): void {
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'stripe_payment_intent_id' => 'pi_visibletoadmin',
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertSuccessful()
        ->assertSee('pi_visibletoadmin');
});
