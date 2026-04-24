<?php

namespace App\Jobs;

use App\Mail\ShowtimeCancelledMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by Plan 09's outbox worker when it drains a
 * `dispatch_outbox.event_type = 'showtime.cancelled'` row.
 *
 * Plan 06 ships the job class so the worker has a target. The job is NOT
 * dispatched directly from Plan 06 code — `ShowtimeService::cancel` writes an
 * outbox row inside the cancellation transaction, and the worker drains it.
 */
class NotifyCustomerOfShowtimeCancellation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $bookingId) {}

    public function handle(): void
    {
        $booking = Booking::with('showtime.movie', 'showtime.auditorium.location', 'user')
            ->find($this->bookingId);

        if ($booking === null) {
            // Booking was hard-deleted between outbox insert and job execution.
            // Nothing to do — the worker marks the row processed.
            return;
        }

        $recipient = $booking->user ? $booking->user->email : $booking->guest_email;

        if ($recipient === null) {
            // No destination — don't hard-fail the worker; log and move on.
            report(new \RuntimeException("Booking {$booking->id} has no email for cancellation notice."));

            return;
        }

        Mail::to($recipient)->send(new ShowtimeCancelledMail($booking));
    }
}
