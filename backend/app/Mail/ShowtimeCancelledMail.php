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
        $movieTitle = $this->booking->showtime?->movie?->title ?? 'your showing';

        return new Envelope(
            to: [$this->booking->user?->email ?? $this->booking->guest_email],
            subject: "Your {$movieTitle} showtime has been cancelled",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.showtime-cancelled',
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->user?->name ?? 'there',
            ],
        );
    }
}
