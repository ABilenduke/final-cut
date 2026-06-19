<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingAmendmentException;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingAmendmentService;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

/** A confirmed guest booking (no user_id). */
function amendmentGuestBooking(array $overrides = []): Booking
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats();

    return Booking::factory()->guest()->create(array_merge([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'guest_email' => 'typo@exmaple.com',
    ], $overrides));
}

// ── updateNotes ───────────────────────────────────────────────────────────

test('updateNotes persists the note and logs an audited event with the actor', function (): void {
    $booking = amendmentGuestBooking(['notes' => null]);
    $actor = User::factory()->admin()->create();

    app(BookingAmendmentService::class)->updateNotes($booking, 'Customer disputed seat; comped a drink.', $actor);

    expect($booking->refresh()->notes)->toBe('Customer disputed seat; comped a drink.');

    $activity = Activity::where('log_name', 'admin')
        ->where('description', 'booking.notes_updated')
        ->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->subject_id)->toBe($booking->id);
});

test('updateNotes can clear a note to null', function (): void {
    $booking = amendmentGuestBooking(['notes' => 'old note']);
    $actor = User::factory()->admin()->create();

    app(BookingAmendmentService::class)->updateNotes($booking, null, $actor);

    expect($booking->refresh()->notes)->toBeNull();
});

test('updateNotes with a null actor performs the write but logs nothing', function (): void {
    $booking = amendmentGuestBooking(['notes' => null]);

    app(BookingAmendmentService::class)->updateNotes($booking, 'system note', null);

    expect($booking->refresh()->notes)->toBe('system note');
    expect(Activity::where('description', 'booking.notes_updated')->count())->toBe(0);
});

// ── correctGuestEmail ───────────────────────────────────────────────────────

test('correctGuestEmail updates the guest email and logs old + new', function (): void {
    $booking = amendmentGuestBooking(['guest_email' => 'typo@exmaple.com']);
    $actor = User::factory()->admin()->create();

    app(BookingAmendmentService::class)->correctGuestEmail($booking, 'correct@example.com', $actor);

    expect($booking->refresh()->guest_email)->toBe('correct@example.com');

    $activity = Activity::where('log_name', 'admin')
        ->where('description', 'booking.guest_email_corrected')
        ->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->properties['previous_email'] ?? null)->toBe('typo@exmaple.com')
        ->and($activity->properties['new_email'] ?? null)->toBe('correct@example.com');
});

test('correctGuestEmail refuses a registered-user booking', function (): void {
    $booking = amendmentGuestBooking([
        'user_id' => User::factory()->create()->id,
        'guest_email' => null,
    ]);
    $actor = User::factory()->admin()->create();

    expect(fn () => app(BookingAmendmentService::class)->correctGuestEmail($booking, 'new@example.com', $actor))
        ->toThrow(BookingAmendmentException::class);
});

test('correctGuestEmail normalizes the email (trim + lowercase)', function (): void {
    $booking = amendmentGuestBooking(['guest_email' => 'a@b.com']);
    $actor = User::factory()->admin()->create();

    app(BookingAmendmentService::class)->correctGuestEmail($booking, '  Correct@Example.COM  ', $actor);

    expect($booking->refresh()->guest_email)->toBe('correct@example.com');
});
