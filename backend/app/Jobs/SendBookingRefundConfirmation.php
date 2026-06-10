<?php

namespace App\Jobs;

use App\Mail\BookingRefundedMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by the outbox worker for `booking.refunded` rows, written by
 * `BookingRefundService::refund()` inside its finalize transaction. The
 * amounts ride in the payload (not re-derived here) so the email always
 * reflects what the refund actually moved, even if the booking row changes
 * later.
 */
class SendBookingRefundConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $bookingId,
        public readonly int $cardRefund,
        public readonly int $giftRestored,
    ) {}

    public function handle(): void
    {
        $booking = Booking::with('showtime.movie', 'showtime.auditorium.location', 'user')
            ->find($this->bookingId);

        if ($booking === null) {
            // Booking was hard-deleted between outbox insert and job execution.
            return;
        }

        $recipient = $booking->user ? $booking->user->email : $booking->guest_email;

        if ($recipient === null) {
            report(new \RuntimeException("Booking {$booking->id} has no email for refund confirmation."));

            return;
        }

        Mail::to($recipient)->send(new BookingRefundedMail($booking, $this->cardRefund, $this->giftRestored));
    }
}
