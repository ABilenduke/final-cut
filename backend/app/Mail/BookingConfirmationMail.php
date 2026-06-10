<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The booking confirmation ticket email. First introduced for the admin
 * "resend confirmation" action (admin-v2 Plan 02) — customers previously only
 * received Stripe's hosted receipt. The dispatching job eager-loads
 * showtime.movie, showtime.auditorium.location, seats.seat, and foodItems
 * before constructing this mailable.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        $movieTitle = $this->booking->showtime->movie->title;

        return new Envelope(
            subject: "You're in — {$movieTitle} ({$this->booking->confirmation_code})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking-confirmation',
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->user ? $this->booking->user->name : 'there',
            ],
        );
    }
}
