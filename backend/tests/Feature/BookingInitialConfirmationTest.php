<?php

use App\Enums\PaymentMethod;
use App\Jobs\SendBookingConfirmation;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Outbox\OutboxDispatcher;
use App\Services\BookingNotificationService;
use App\Services\WalkUpBookingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

test('a successful guest checkout queues an initial confirmation email row', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldSucceed();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ])->assertStatus(201);

    $row = DispatchOutbox::query()
        ->where('event_type', BookingNotificationService::EVENT_CONFIRMATION)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->payload)->toHaveKey('booking_id');
});

test('the 3DS confirm path also queues the confirmation', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldRequire3ds();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_3ds',
        'email' => 'guest3ds@example.com',
    ])->assertOk();

    expect(DispatchOutbox::query()
        ->where('event_type', BookingNotificationService::EVENT_CONFIRMATION)
        ->exists())->toBeFalse();

    postJson($this->bookingUrl($fixture['location'], 'confirm'), [
        'paymentIntentId' => $response->json('data.paymentIntentId'),
    ])->assertStatus(201);

    expect(DispatchOutbox::query()
        ->where('event_type', BookingNotificationService::EVENT_CONFIRMATION)
        ->exists())->toBeTrue();
});

test('walk-up sales queue a confirmation only when an email was captured', function (): void {
    // The service only needs a User for activity attribution — no roles.
    $admin = User::factory()->create();
    $fixture = $this->createShowtimeWithSeats();
    $service = app(WalkUpBookingService::class);

    $service->create(
        $fixture['showtime']->id,
        [$fixture['seats'][0]->id],
        PaymentMethod::Cash,
        'walkup@example.com',
        $admin,
    );
    expect(DispatchOutbox::query()
        ->where('event_type', BookingNotificationService::EVENT_CONFIRMATION)
        ->count())->toBe(1);

    $service->create(
        $fixture['showtime']->id,
        [$fixture['seats'][1]->id],
        PaymentMethod::Cash,
        null,
        $admin,
    );
    expect(DispatchOutbox::query()
        ->where('event_type', BookingNotificationService::EVENT_CONFIRMATION)
        ->count())->toBe(1);
});

test('the dispatcher maps the initial-confirmation event to the existing job', function (): void {
    Queue::fake();
    $fixture = $this->createShowtimeWithSeats();
    $booking = Booking::factory()->create([
        'showtime_id' => $fixture['showtime']->id,
        'guest_email' => 'map@example.com',
    ]);

    $row = DispatchOutbox::create([
        'event_type' => BookingNotificationService::EVENT_CONFIRMATION,
        'payload' => ['booking_id' => $booking->id],
        'available_at' => now(),
    ]);

    app(OutboxDispatcher::class)->dispatch($row);

    Queue::assertPushed(SendBookingConfirmation::class, fn ($job) => $job->bookingId === $booking->id);
});

test('outbox:dispatch round-trips a checkout to a queued confirmation email', function (): void {
    Mail::fake();
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldSucceed();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'roundtrip@example.com',
    ])->assertStatus(201);

    // Rewind: Postgres NOW() is pinned to the test transaction start.
    DispatchOutbox::query()->update(['available_at' => now()->subMinute()]);

    $this->artisan('outbox:dispatch')->assertSuccessful();

    // BookingConfirmationMail is sent inline from the already-queued job
    // (it does not implement ShouldQueue) — assertSent, not assertQueued.
    Mail::assertSent(BookingConfirmationMail::class, fn (BookingConfirmationMail $mail) => $mail->hasTo('roundtrip@example.com'));
});
