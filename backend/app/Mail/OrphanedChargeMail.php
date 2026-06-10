<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Finance alert for a Stripe charge with no matching booking or gift card
 * (admin-v4 Plan 01) — the out-of-band failure the webhook exists to catch.
 */
class OrphanedChargeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $paymentIntentId,
        public readonly int $amount,
        public readonly string $currency,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Orphaned Stripe charge — {$this->paymentIntentId}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orphaned-charge',
            with: [
                'paymentIntentId' => $this->paymentIntentId,
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
        );
    }
}
