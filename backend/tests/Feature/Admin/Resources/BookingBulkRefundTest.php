<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Models\Booking;
use App\Models\User;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->stripe = $this->fakeStripe();
});

/** A Confirmed, card-paid booking with one reserved seat. */
function bulkBookingFixture(array $overrides = []): Booking
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
        'stripe_payment_intent_id' => 'pi_bulk_'.bin2hex(random_bytes(6)),
    ], $overrides));

    app(SeatAvailabilityService::class)->reserveSeats(
        $ctx['showtime'],
        [$ctx['seats'][0]->id],
        $booking,
    );

    return $booking;
}

test('admin can bulk-refund several confirmed bookings at once', function (): void {
    $this->actingAsAdmin();
    $a = bulkBookingFixture();
    $b = bulkBookingFixture();

    Livewire::test(ListBookings::class)
        ->mountTableBulkAction('bulk_refund', [$a, $b])
        ->set('mountedActions.0.data.reason', 'Showtime cancelled — mass recovery')
        ->callMountedTableBulkAction()
        ->assertHasNoTableBulkActionErrors();

    expect($a->refresh()->status)->toBe(BookingStatus::Refunded)
        ->and($a->refunded_at)->not->toBeNull()
        ->and($b->refresh()->status)->toBe(BookingStatus::Refunded)
        ->and($b->refunded_at)->not->toBeNull();
});

test('bulk refund skips already-terminal bookings without touching them', function (): void {
    $this->actingAsAdmin();
    $confirmed = bulkBookingFixture();
    $alreadyRefunded = bulkBookingFixture(['status' => BookingStatus::Refunded, 'refunded_at' => now()->subDay()]);
    $refundedAtBefore = $alreadyRefunded->refunded_at;

    Livewire::test(ListBookings::class)
        ->mountTableBulkAction('bulk_refund', [$confirmed, $alreadyRefunded])
        ->set('mountedActions.0.data.reason', 'Bulk recovery run')
        ->callMountedTableBulkAction()
        ->assertHasNoTableBulkActionErrors();

    // The confirmed one is refunded; the already-refunded one is untouched (skipped, not errored or re-refunded).
    expect($confirmed->refresh()->status)->toBe(BookingStatus::Refunded);
    expect($alreadyRefunded->refresh()->status)->toBe(BookingStatus::Refunded)
        ->and($alreadyRefunded->refunded_at->equalTo($refundedAtBefore))->toBeTrue();
});

test('bulk refund requires a reason and refunds nothing without one', function (): void {
    $this->actingAsAdmin();
    $booking = bulkBookingFixture();

    Livewire::test(ListBookings::class)
        ->mountTableBulkAction('bulk_refund', [$booking])
        ->set('mountedActions.0.data.reason', '')
        ->callMountedTableBulkAction()
        ->assertHasTableBulkActionErrors(['reason']);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the bulk refund action is visible to admin but hidden for ops', function (): void {
    $this->actingAsAdmin();
    Livewire::test(ListBookings::class)->assertTableBulkActionVisible('bulk_refund');

    $this->actingAsOps();
    Livewire::test(ListBookings::class)->assertTableBulkActionHidden('bulk_refund');
});
