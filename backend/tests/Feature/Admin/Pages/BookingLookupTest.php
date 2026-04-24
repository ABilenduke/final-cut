<?php

use App\Filament\Pages\BookingLookup;
use App\Filament\Resources\BookingResource;
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
| Access control
|--------------------------------------------------------------------------
*/

test('ops can access the booking lookup page (has bookings.view)', function (): void {
    $this->actingAsOps();

    expect(BookingLookup::canAccess())->toBeTrue();
});

test('a nobody role cannot access the page', function (): void {
    $this->actingAsNobody();

    expect(BookingLookup::canAccess())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Query shape
|--------------------------------------------------------------------------
*/

test('uppercase confirmation code redirects to the booking view', function (): void {
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    Livewire::test(BookingLookup::class)
        ->set('query', $booking->confirmation_code)
        ->call('search')
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking]));
});

test('lowercase confirmation code is uppercased and still redirects', function (): void {
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    Livewire::test(BookingLookup::class)
        ->set('query', strtolower($booking->confirmation_code))
        ->call('search')
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking]));
});

test('prefixless confirmation code gets CVF- prepended and still redirects', function (): void {
    $booking = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
    ]);

    // Strip the "CVF-" prefix to simulate a user pasting just the suffix.
    $suffix = substr($booking->confirmation_code, 4);

    Livewire::test(BookingLookup::class)
        ->set('query', $suffix)
        ->call('search')
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking]));
});

test('email hit on an authenticated user redirects to the most recent matching booking', function (): void {
    $alice = User::factory()->create(['email' => 'alice@example.com']);

    $old = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'user_id' => $alice->id,
        'created_at' => now()->subDays(30),
    ]);
    $newer = Booking::factory()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'user_id' => $alice->id,
        'created_at' => now()->subDays(1),
    ]);

    Livewire::test(BookingLookup::class)
        ->set('query', 'alice@example.com')
        ->call('search')
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $newer]));
});

test('guest email hit redirects to that guest booking', function (): void {
    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $this->fixture['showtime']->id,
        'guest_email' => 'walkin@example.com',
    ]);

    Livewire::test(BookingLookup::class)
        ->set('query', 'walkin@example.com')
        ->call('search')
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking]));
});

/*
|--------------------------------------------------------------------------
| Validation + miss handling
|--------------------------------------------------------------------------
*/

test('a query shorter than 3 chars fails validation', function (): void {
    Livewire::test(BookingLookup::class)
        ->set('query', 'AB')
        ->call('search')
        ->assertHasErrors(['query']);
});

test('an empty query fails validation', function (): void {
    Livewire::test(BookingLookup::class)
        ->set('query', '')
        ->call('search')
        ->assertHasErrors(['query']);
});

test('an unknown query does not redirect and keeps the page mounted', function (): void {
    Livewire::test(BookingLookup::class)
        ->set('query', 'nosuch@example.com')
        ->call('search')
        ->assertNoRedirect();
});
