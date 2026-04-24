<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin cancels a showtime — one mail per affected booking.
 * Dispatched by `NotifyCustomerOfShowtimeCancellation`, which itself is
 * dispatched by Plan 09's outbox worker. Plan 06 ships the mailable so the
 * worker has a delivery target; no actual email dispatch happens in Plan 06.
 */
class ShowtimeCancelledMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        // Recipient is chosen by the dispatcher (NotifyCustomerOfShowtimeCancellation
        // via Mail::to()); the envelope only owns the subject so we don't end up
        // with two recipient sources that could drift.
        //
        // The NotifyCustomerOfShowtimeCancellation job eager-loads
        // showtime.movie before constructing this mailable, so accessing
        // `$this->booking->showtime->movie->title` here is safe even though
        // the chain isn't null-guarded.
        $movieTitle = $this->booking->showtime->movie->title;

        return new Envelope(
            subject: "Your {$movieTitle} showtime has been cancelled",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.showtime-cancelled',
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->user ? $this->booking->user->name : 'there',
            ],
        );
    }
}
