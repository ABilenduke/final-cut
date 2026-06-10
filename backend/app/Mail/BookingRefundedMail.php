<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin refunds a booking. The card/gift split is passed in by
 * the dispatching job (from the outbox payload) rather than re-derived, so
 * the email states exactly what the refund moved.
 */
class BookingRefundedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly int $cardRefund,
        public readonly int $giftRestored,
    ) {}

    public function envelope(): Envelope
    {
        // Recipient is chosen by the dispatching job via Mail::to(); the
        // envelope only owns the subject (same convention as
        // ShowtimeCancelledMail).
        return new Envelope(
            subject: "Your booking {$this->booking->confirmation_code} has been refunded",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking-refunded',
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->user ? $this->booking->user->name : 'there',
                'cardRefund' => $this->cardRefund,
                'giftRestored' => $this->giftRestored,
            ],
        );
    }
}
