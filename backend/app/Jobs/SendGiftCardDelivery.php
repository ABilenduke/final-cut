<?php

namespace App\Jobs;

use App\Enums\GiftCardStatus;
use App\Mail\GiftCardDeliveryMail;
use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by the outbox worker for `gift_card.delivery` rows —
 * `GiftCardService::purchase` writes the row inside the purchase
 * transaction (available_at honors scheduled_send_at), so a queue outage
 * can never lose a paid-for delivery.
 *
 * Only Active cards are delivered: a card voided between purchase and a
 * scheduled send must not email a dead code. At-least-once delivery is
 * acceptable for this email — a duplicate is a harmless re-send of the
 * same code.
 */
class SendGiftCardDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $giftCardId,
    ) {}

    public function handle(): void
    {
        $giftCard = GiftCard::find($this->giftCardId);

        if ($giftCard === null) {
            // Hard-deleted between outbox insert and execution — nothing to
            // deliver; the worker marks the row processed.
            return;
        }

        if ($giftCard->status !== GiftCardStatus::Active) {
            Log::info('Skipping gift card delivery for non-active card', [
                'gift_card_id' => $giftCard->id,
                'status' => $giftCard->status->value,
            ]);

            return;
        }

        Mail::to($giftCard->recipient_email)->send(new GiftCardDeliveryMail($giftCard));
    }
}
