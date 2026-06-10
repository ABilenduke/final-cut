<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by the outbox worker for `booking.confirmation_resend` rows,
 * written by `BookingNotificationService::resendConfirmation()`.
 */
class SendBookingConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $bookingId) {}

    public function handle(): void
    {
        $booking = Booking::with(
            'showtime.movie',
            'showtime.auditorium.location',
            'seats.seat',
            'foodItems',
            'user',
        )->find($this->bookingId);

        if ($booking === null) {
            // Booking was hard-deleted between outbox insert and job execution.
            return;
        }

        $recipient = $booking->user ? $booking->user->email : $booking->guest_email;

        if ($recipient === null) {
            report(new \RuntimeException("Booking {$booking->id} has no email for confirmation."));

            return;
        }

        Mail::to($recipient)->send(new BookingConfirmationMail($booking));
    }
}
