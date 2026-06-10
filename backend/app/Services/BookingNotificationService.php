<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingNotResendableException;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Admin-triggered booking notifications. Delivery is durable: the service
 * writes a `dispatch_outbox` row inside a transaction (alongside the activity
 * log) and `outbox:dispatch` drains it — a queue/Redis outage can never drop
 * the email silently.
 */
class BookingNotificationService
{
    use LogsAdminActivity;

    public const EVENT_CONFIRMATION_RESEND = 'booking.confirmation_resend';

    public const EVENT_CONFIRMATION = 'booking.confirmation';

    /**
     * Queue the INITIAL confirmation email for a just-finalized booking
     * (admin-v3 Plan 06 — until then only the admin resend existed).
     * Called inside the finalize transaction so the outbox row commits
     * atomically with the booking. Silently skips bookings with no
     * recipient (walk-up sales where no email was captured).
     */
    public function queueConfirmation(Booking $booking): void
    {
        $recipient = $booking->user ? $booking->user->email : $booking->guest_email;
        if ($recipient === null) {
            return;
        }

        $now = now();
        DispatchOutbox::create([
            'event_type' => self::EVENT_CONFIRMATION,
            'payload' => ['booking_id' => $booking->id],
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Queue a (re-)send of the booking confirmation email.
     *
     * @throws BookingNotResendableException When the booking is not Confirmed or has no recipient email.
     */
    public function resendConfirmation(Booking $booking, ?User $actor = null): void
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new BookingNotResendableException(BookingNotResendableException::REASON_NOT_CONFIRMED);
        }

        $recipient = $booking->user ? $booking->user->email : $booking->guest_email;
        if ($recipient === null) {
            throw new BookingNotResendableException(BookingNotResendableException::REASON_NO_RECIPIENT);
        }

        DB::transaction(function () use ($booking, $actor): void {
            $now = now();
            DispatchOutbox::create([
                'event_type' => self::EVENT_CONFIRMATION_RESEND,
                'payload' => [
                    'booking_id' => $booking->id,
                    'requested_by_admin_user_id' => $actor?->id,
                ],
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->logIfAdmin(self::EVENT_CONFIRMATION_RESEND, $booking, $actor, [
                'confirmation_code' => $booking->confirmation_code,
            ]);
        });
    }
}
