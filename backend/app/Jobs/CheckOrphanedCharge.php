<?php

namespace App\Jobs;

use App\Mail\OrphanedChargeMail;
use App\Models\Booking;
use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Deferred reconciliation for a `payment_intent.succeeded` webhook that
 * matched no booking or gift card (admin-v4 Plan 01). Runs ~30 minutes
 * after the event so the in-band finalize (or the customer's 3DS confirm)
 * has every chance to win first. Alerts finance only when the charge is
 * STILL unmatched and no pending 3DS state remains — that is money taken
 * with nothing sold, and someone needs to refund or reconcile it.
 */
class CheckOrphanedCharge implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $paymentIntentId,
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function handle(): void
    {
        $matched = Booking::query()->where('stripe_payment_intent_id', $this->paymentIntentId)->exists()
            || GiftCard::query()->where('stripe_payment_intent_id', $this->paymentIntentId)->exists();

        if ($matched) {
            return;
        }

        // A live pending cache means the customer is (or was very recently)
        // mid-3DS — confirm() may still land. The cache expires in 15
        // minutes; with the 30-minute defer this only triggers when the
        // webhook arrived during an unusually late retry window.
        if (Cache::has("pending_booking:{$this->paymentIntentId}")
            || Cache::has("pending_gift_card:{$this->paymentIntentId}")) {
            return;
        }

        Log::error('Orphaned Stripe charge detected — no booking or gift card matches.', [
            'payment_intent_id' => $this->paymentIntentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ]);

        Mail::to(config('finance.notification_email'))
            ->send(new OrphanedChargeMail($this->paymentIntentId, $this->amount, $this->currency));
    }
}
