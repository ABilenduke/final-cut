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
 * Finance notification fired when an admin voids a gift card.
 *
 * Implements `ShouldQueue` so `Mail::to(...)->send(...)` queues rather than
 * delivering inline — `GiftCardService::void` dispatches this inside a DB
 * transaction and must not block on SMTP. Tests assert via `Mail::assertQueued`.
 */
class GiftCardVoidedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly GiftCard $giftCard,
        public readonly string $reason,
        public readonly ?User $by,
        public readonly int $balanceVoided,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Gift card voided — {$this->giftCard->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.gift-card-voided',
            with: [
                'giftCard' => $this->giftCard,
                'reason' => $this->reason,
                'by' => $this->by,
                'balanceVoided' => $this->balanceVoided,
            ],
        );
    }
}
