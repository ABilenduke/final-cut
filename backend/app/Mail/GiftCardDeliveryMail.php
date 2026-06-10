<?php

namespace App\Mail;

use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Recipient-facing delivery of a purchased gift card (admin-v3 Plan 05 —
 * completes Plan 03's purchase flow). Carries the redemption code, so it is
 * only ever sent for cards that are still Active at send time; scheduled
 * sends ride the outbox row's available_at.
 */
class GiftCardDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly GiftCard $giftCard,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->giftCard->sender_name} sent you a Final Cut gift card",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.gift-card-delivery',
            with: [
                'giftCard' => $this->giftCard,
            ],
        );
    }
}
