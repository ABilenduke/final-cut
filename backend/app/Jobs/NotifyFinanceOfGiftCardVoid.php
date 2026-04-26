<?php

namespace App\Jobs;

use App\Mail\GiftCardVoidedMail;
use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by Plan 09's outbox worker when it drains a
 * `dispatch_outbox.event_type = 'gift_card.voided'` row.
 *
 * Plan 08 ships the job class so the worker has a target — the job is NOT
 * dispatched directly. `GiftCardService::void` writes an outbox row inside
 * the void transaction, and the worker drains it. This is the same pattern
 * Plan 06 uses for `NotifyCustomerOfShowtimeCancellation`, and closes the
 * gap where a queue/mail dispatch failure during `DB::afterCommit` would
 * silently lose the finance notification on an irreversible void.
 */
class NotifyFinanceOfGiftCardVoid implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $giftCardId,
        public readonly string $reason,
        public readonly int $balanceVoided,
        public readonly ?string $voidedByAdminUserId,
    ) {}

    public function handle(): void
    {
        $giftCard = GiftCard::find($this->giftCardId);

        if ($giftCard === null) {
            // Gift card was hard-deleted between outbox insert and job
            // execution. Nothing to do — the worker marks the row processed.
            return;
        }

        $admin = $this->voidedByAdminUserId !== null
            ? User::find($this->voidedByAdminUserId)
            : null;

        Mail::to(config('finance.notification_email'))
            ->send(new GiftCardVoidedMail($giftCard, $this->reason, $admin, $this->balanceVoided));
    }
}
