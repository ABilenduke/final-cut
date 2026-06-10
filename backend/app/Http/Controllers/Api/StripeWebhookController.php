<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;

/**
 * POST /api/webhooks/stripe (admin-v4 Plan 01 — the post-MVP hardening the
 * routes file TODO documented). Closes the out-of-band payment window: a
 * charge whose API response was lost mid-finalize previously required
 * manual discovery in the Stripe dashboard.
 *
 * Deliberately conservative: the webhook never finalizes bookings or gift
 * cards itself (the synchronous confirm + idempotent replay own that). On
 * `payment_intent.succeeded` it only verifies a matching record exists —
 * and when none does, schedules a DEFERRED orphan check (+30 min via the
 * outbox's available_at) because Stripe fires the event while the in-band
 * Phase C transaction may still be running.
 */
class StripeWebhookController extends Controller
{
    private const HANDLED_EVENT = 'payment_intent.succeeded';

    private const ORPHAN_CHECK_DELAY_MINUTES = 30;

    public const EVENT_ORPHAN_CHECK = 'payment.orphan_check';

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');

            return response()->json(['error' => 'Webhook not configured.'], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        if ($event->type !== self::HANDLED_EVENT) {
            return response()->json(['received' => true]);
        }

        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        $matched = Booking::query()->where('stripe_payment_intent_id', $paymentIntent->id)->exists()
            || GiftCard::query()->where('stripe_payment_intent_id', $paymentIntent->id)->exists();

        if (! $matched) {
            $this->scheduleOrphanCheck($paymentIntent->id, (int) $paymentIntent->amount, (string) $paymentIntent->currency);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Idempotent per payment intent: Stripe retries webhooks, so a repeat
     * event must not stack a second pending check for the same charge.
     */
    private function scheduleOrphanCheck(string $paymentIntentId, int $amount, string $currency): void
    {
        $alreadyPending = DispatchOutbox::query()
            ->where('event_type', self::EVENT_ORPHAN_CHECK)
            ->whereNull('processed_at')
            ->where('payload->payment_intent_id', $paymentIntentId)
            ->exists();

        if ($alreadyPending) {
            return;
        }

        $now = now();
        DispatchOutbox::create([
            'event_type' => self::EVENT_ORPHAN_CHECK,
            'payload' => [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
                'currency' => $currency,
            ],
            'available_at' => $now->copy()->addMinutes(self::ORPHAN_CHECK_DELAY_MINUTES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
