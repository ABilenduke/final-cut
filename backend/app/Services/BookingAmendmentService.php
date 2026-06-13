<?php

namespace App\Services;

use App\Exceptions\BookingAmendmentException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Low-risk admin record corrections on a booking that don't touch its
 * state machine, money, or seats: editing the internal `notes` and fixing a
 * mistyped guest `guest_email`. Both run under a row lock and write an audited
 * `admin` activity event with the acting admin. Refunds, flags, and seat
 * reservations remain the responsibility of their dedicated services.
 */
class BookingAmendmentService
{
    use LogsAdminActivity;

    public const EVENT_NOTES_UPDATED = 'booking.notes_updated';

    public const EVENT_GUEST_EMAIL_CORRECTED = 'booking.guest_email_corrected';

    public function updateNotes(Booking $booking, ?string $notes, ?User $actor = null): void
    {
        $clean = $notes === null ? null : trim($notes);
        if ($clean === '') {
            $clean = null;
        }

        DB::transaction(function () use ($booking, $clean, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $locked->notes = $clean;
            $locked->save();

            $this->logIfAdmin(self::EVENT_NOTES_UPDATED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
            ]);
        });

        $booking->notes = $clean;
    }

    /**
     * @throws BookingAmendmentException When the booking belongs to a registered user.
     */
    public function correctGuestEmail(Booking $booking, string $email, ?User $actor = null): void
    {
        $normalized = mb_strtolower(trim($email));

        DB::transaction(function () use ($booking, $normalized, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null) {
                throw new BookingAmendmentException(BookingAmendmentException::REASON_NOT_GUEST_BOOKING);
            }

            $previous = $locked->guest_email;

            $locked->guest_email = $normalized;
            $locked->save();

            $this->logIfAdmin(self::EVENT_GUEST_EMAIL_CORRECTED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
                'previous_email' => $previous,
                'new_email' => $normalized,
            ]);
        });

        $booking->guest_email = $normalized;
    }
}
