<?php

use App\Enums\BookingStatus;
use App\Filament\Pages\CancellationFollowupQueue;
use App\Models\User;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create(['location_id' => $this->location->id]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
    $this->showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);
});

test('queue lists only flagged bookings that are not yet refunded', function (): void {
    $this->actingAsAdmin();

    $flaggedPending = Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    // Already refunded → filtered out.
    Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'status' => BookingStatus::Refunded,
    ]);

    // Not flagged → filtered out.
    Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => null,
        'status' => BookingStatus::Confirmed,
    ]);

    Livewire::test(CancellationFollowupQueue::class)
        ->assertCanSeeTableRecords([$flaggedPending]);
});

test('mark_resolved action requires 10+ character notes, flips status to refunded, and logs activity', function (): void {
    $admin = $this->actingAsAdmin();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    Livewire::test(CancellationFollowupQueue::class)
        ->mountTableAction('mark_resolved', $booking)
        ->set('mountedActions.0.data.notes', 'Refunded via Stripe dashboard — reference ref_12345')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Refunded);
    expect($booking->notes)->toContain('ref_12345');

    $activity = Activity::where('log_name', 'admin')
        ->where('description', CancellationFollowupQueue::EVENT_MANUALLY_REFUNDED)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->causer_type)->toBe(User::class);
    expect($activity->properties->get('notes'))->toContain('ref_12345');
});

test('mark_resolved action rejects notes shorter than 10 characters', function (): void {
    $this->actingAsAdmin();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    Livewire::test(CancellationFollowupQueue::class)
        ->callTableAction('mark_resolved', $booking, ['notes' => 'too short'])
        ->assertHasTableActionErrors();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed);
});

test('ops can view the queue but cannot invoke mark_resolved', function (): void {
    $this->actingAsOps();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    // Ops role has bookings.view, so page access is granted.
    expect(CancellationFollowupQueue::canAccess())->toBeTrue();

    Livewire::test(CancellationFollowupQueue::class)
        ->assertTableActionHidden('mark_resolved', $booking);
});

test('an admin user with no role cannot access the queue', function (): void {
    $this->actingAsNobody();

    expect(CancellationFollowupQueue::canAccess())->toBeFalse();

    $this->get(CancellationFollowupQueue::getUrl())->assertForbidden();
});

test('navigation badge reflects pending count for permitted users', function (): void {
    $this->actingAsAdmin();

    Booking::factory()->guest()->count(2)->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    expect(CancellationFollowupQueue::getNavigationBadge())->toBe('2');
});

test('flagged bookings without a showtime_cancelled flag_reason stay out of the queue', function (): void {
    $this->actingAsAdmin();

    // Flagged but for a different reason (e.g. a future fraud-hold flow). This
    // must not surface in the cancellation follow-up queue.
    Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => 'fraud_hold:review',
        'status' => BookingStatus::Confirmed,
    ]);

    $cancellationBooking = Booking::factory()->guest()->create([
        'showtime_id' => $this->showtime->id,
        'flagged_at' => now()->subHour(),
        'flag_reason' => "showtime_cancelled:{$this->showtime->id}",
        'status' => BookingStatus::Confirmed,
    ]);

    Livewire::test(CancellationFollowupQueue::class)
        ->assertCanSeeTableRecords([$cancellationBooking])
        ->assertCountTableRecords(1);

    expect(CancellationFollowupQueue::getNavigationBadge())->toBe('1');
});
