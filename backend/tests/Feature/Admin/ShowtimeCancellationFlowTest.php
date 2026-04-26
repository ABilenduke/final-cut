<?php

use App\Exceptions\ShowtimeAlreadyCancelledException;
use App\Jobs\NotifyCustomerOfShowtimeCancellation;
use App\Mail\ShowtimeCancelledMail;
use App\Models\User;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
    $this->service = app(ShowtimeService::class);
});

test('cancelling a showtime flags all its bookings and writes an outbox row per booking inside the same transaction', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    Booking::factory()->count(3)->create(['showtime_id' => $showtime->id]);

    $this->service->cancel($showtime, 'Projector failure', $this->admin);

    // Cancellation persisted.
    $fresh = $showtime->fresh();
    expect($fresh->cancelled_at)->not->toBeNull();
    expect($fresh->cancellation_reason)->toBe('Projector failure');

    // Every booking flagged with the pointer reason.
    $bookings = Booking::where('showtime_id', $showtime->id)->get();
    expect($bookings)->toHaveCount(3);
    expect($bookings->every(fn (Booking $b) => $b->flagged_at !== null))->toBeTrue();
    expect($bookings->every(fn (Booking $b) => $b->flag_reason === "showtime_cancelled:{$showtime->id}"))->toBeTrue();

    // One outbox row per booking.
    $outboxRows = DispatchOutbox::where('event_type', ShowtimeService::EVENT_CANCELLED)->get();
    expect($outboxRows)->toHaveCount(3);
    expect($outboxRows->every(fn (DispatchOutbox $r) => $r->payload['showtime_id'] === $showtime->id))->toBeTrue();

    // Activity log recorded with the admin as causer + flagged count in properties.
    // Spatie stores the ->log() argument as `description`, not `event`.
    $activity = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_CANCELLED)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->admin->id);
    expect($activity->causer_type)->toBe(User::class);
    expect($activity->properties->get('flagged_bookings'))->toBe(3);
    expect($activity->properties->get('reason'))->toBe('Projector failure');
});

test('cancellation without an actor writes no admin activity log row but still flags bookings', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    Booking::factory()->count(2)->create(['showtime_id' => $showtime->id]);

    $initialCount = Activity::where('log_name', 'admin')->count();

    $this->service->cancel($showtime, 'Non-admin path', null);

    // Flags + outbox still happen; only admin-attribution skipped.
    expect(Booking::where('showtime_id', $showtime->id)->whereNotNull('flagged_at')->count())->toBe(2);
    expect(DispatchOutbox::where('event_type', ShowtimeService::EVENT_CANCELLED)->count())->toBe(2);

    // No new admin activity row.
    expect(Activity::where('log_name', 'admin')->count())->toBe($initialCount);
});

test('re-cancelling an already-cancelled showtime does not duplicate outbox rows or re-flag bookings', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    Booking::factory()->count(2)->create(['showtime_id' => $showtime->id]);

    $this->service->cancel($showtime, 'First pass', $this->admin);

    $firstFlaggedAt = Booking::where('showtime_id', $showtime->id)
        ->orderBy('id')
        ->value('flagged_at');

    try {
        $this->service->cancel($showtime->fresh(), 'Second pass', $this->admin);
    } catch (ShowtimeAlreadyCancelledException) {
        // expected
    }

    // Still exactly 2 outbox rows.
    expect(DispatchOutbox::where('event_type', ShowtimeService::EVENT_CANCELLED)->count())->toBe(2);

    // Bookings still flagged, `flagged_at` unchanged.
    $secondFlaggedAt = Booking::where('showtime_id', $showtime->id)
        ->orderBy('id')
        ->value('flagged_at');
    expect($secondFlaggedAt)->toEqual($firstFlaggedAt);
});

test('cancelled showtimes do not appear on the customer-facing showtime endpoint', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    $this->service->cancel($showtime, 'pulled', $this->admin);

    getJson("/api/locations/{$this->location->slug}/showtimes/{$showtime->id}")
        ->assertNotFound();
});

test('the mail template renders for the NotifyCustomerOfShowtimeCancellation job', function (): void {
    Mail::fake();

    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $showtime->id,
        'guest_email' => 'ticket-holder@example.com',
    ]);

    $this->service->cancel($showtime, 'Power outage', $this->admin);

    // Simulate the Plan 09 worker picking up the outbox row and dispatching
    // the job. The job itself is what Mailpit would capture in production.
    (new NotifyCustomerOfShowtimeCancellation((string) $booking->id))->handle();

    Mail::assertSent(ShowtimeCancelledMail::class, function (ShowtimeCancelledMail $mail) use ($booking) {
        return $mail->hasTo('ticket-holder@example.com')
            && $mail->booking->id === $booking->id;
    });
});

test('NotifyCustomerOfShowtimeCancellation is a no-op when the booking has been hard-deleted', function (): void {
    Mail::fake();

    // Reference a booking id that no longer exists — the job should silently succeed.
    (new NotifyCustomerOfShowtimeCancellation('00000000-0000-0000-0000-000000000000'))->handle();

    Mail::assertNothingSent();
});
