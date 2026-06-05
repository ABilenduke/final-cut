<?php

namespace App\Http\Controllers\Api;

use App\Enums\GiftCardStatus;
use App\Http\Requests\PurchaseGiftCardRequest;
use App\Http\Resources\GiftCardResource;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use App\Services\StripeService;
use App\Support\PayloadFingerprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class GiftCardController extends Controller
{
    private const PENDING_TTL_MINUTES = 15;

    public function __construct(
        private readonly StripeService $stripeService,
        private readonly GiftCardService $giftCardService,
    ) {}

    public function purchase(PurchaseGiftCardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotencyKey'];

        $payloadHash = PayloadFingerprint::giftCard(
            $validated['amount'],
            $validated['recipientEmail'],
            $validated['recipientName'],
            $validated['senderName'],
            $validated['message'] ?? null,
            $validated['edition'],
            $validated['deliveryMethod'],
            $validated['scheduledSendAt'] ?? null,
        );

        // 1. Check DB for completed purchase with this idempotency key
        $existing = GiftCard::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->payload_hash !== $payloadHash) {
                return $this->payloadMismatchResponse();
            }

            return $this->successResponse(new GiftCardResource($existing), status: 201);
        }

        // 2. Check cache for pending or failed state
        $cached = Cache::get("gift_card_idempotency:{$idempotencyKey}");
        if ($cached) {
            if ($cached['payload_hash'] !== $payloadHash) {
                return $this->payloadMismatchResponse();
            }

            if ($cached['status'] === 'requires_action') {
                return $this->successResponse([
                    'requiresAction' => true,
                    'clientSecret' => $cached['client_secret'],
                    'paymentIntentId' => $cached['payment_intent_id'],
                ]);
            }

            if ($cached['status'] === 'failed') {
                return $this->errorResponse([
                    ['field' => $cached['error_field'], 'message' => $cached['error_message']],
                ], $cached['error_status']);
            }
        }

        // 3. No prior state — proceed with Stripe
        try {
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $validated['amount'],
                $validated['paymentMethodId'],
                ['type' => 'gift_card'],
                $idempotencyKey,
            );

            if ($paymentIntent->status === 'requires_action') {
                Cache::put("gift_card_idempotency:{$idempotencyKey}", [
                    'status' => 'requires_action',
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'payload_hash' => $payloadHash,
                ], now()->addMinutes(self::PENDING_TTL_MINUTES));

                Cache::put("pending_gift_card:{$paymentIntent->id}", [
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $validated['amount'],
                    'recipientEmail' => $validated['recipientEmail'],
                    'recipientName' => $validated['recipientName'],
                    'senderName' => $validated['senderName'],
                    'message' => $validated['message'] ?? null,
                    'edition' => $validated['edition'],
                    'deliveryMethod' => $validated['deliveryMethod'],
                    'scheduledSendAt' => $validated['scheduledSendAt'] ?? null,
                    'payload_hash' => $payloadHash,
                ], now()->addMinutes(self::PENDING_TTL_MINUTES));

                return $this->successResponse([
                    'requiresAction' => true,
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentIntentId' => $paymentIntent->id,
                ]);
            }

            if ($paymentIntent->status !== 'succeeded') {
                // Unexpected intermediate status (e.g. 'processing') — NOT cached
                return $this->errorResponse([
                    ['field' => 'payment', 'message' => 'Payment is in an unexpected state. Please try again or contact support.'],
                ], 502);
            }
        } catch (CardException $e) {
            $this->cacheHardFailure($idempotencyKey, 'payment', $e->getMessage(), 402, $payloadHash);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 402);
        } catch (InvalidRequestException $e) {
            report($e);
            // Cache the SANITISED message so an idempotent replay returns the same
            // generic error, never the raw integration-facing Stripe text.
            $genericMessage = 'We could not process your payment. Please try again or contact support.';
            $this->cacheHardFailure($idempotencyKey, 'payment', $genericMessage, 400, $payloadHash);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => $genericMessage],
            ], 400);
        } catch (ApiErrorException $e) {
            report($e);

            // Transient — NOT cached, retry goes through full flow
            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.'],
            ], 502);
        }

        // 4. Payment succeeded — create gift card
        return $this->createGiftCard(
            $validated,
            $paymentIntent->id,
            $idempotencyKey,
            $payloadHash,
        );
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => ['required', 'string'],
        ]);

        $paymentIntentId = $request->input('paymentIntentId');

        // 1. Check if gift card already exists for this PI (replay)
        $existing = GiftCard::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($existing) {
            $pendingData = Cache::get("pending_gift_card:{$paymentIntentId}");
            if ($pendingData && $existing->payload_hash !== $pendingData['payload_hash']) {
                return $this->payloadMismatchResponse();
            }

            return $this->successResponse(new GiftCardResource($existing), status: 201);
        }

        // 2. Check cache for pending state (authoritative app context)
        $pendingData = Cache::get("pending_gift_card:{$paymentIntentId}");
        if (! $pendingData) {
            return $this->errorResponse([
                ['message' => 'Session expired. Please start over.'],
            ], 410);
        }

        // 3. Confirm payment with Stripe
        try {
            $paymentIntent = $this->stripeService->confirmPaymentIntent($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                // Cache preserved for retry
                return $this->errorResponse([
                    ['field' => 'payment', 'message' => 'Payment confirmation failed.'],
                ], 402);
            }
        } catch (CardException $e) {
            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 402);
        } catch (InvalidRequestException $e) {
            report($e);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'We could not process your payment. Please try again or contact support.'],
            ], 400);
        } catch (ApiErrorException $e) {
            report($e);

            // Cache preserved for retry
            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.'],
            ], 502);
        }

        // 4. Payment confirmed — create gift card from cached pending data.
        // edition/deliveryMethod are always present in $pendingData because
        // PurchaseGiftCardRequest::prepareForValidation() defaults them before
        // the cache write upstream.
        $result = $this->createGiftCard(
            [
                'amount' => $pendingData['amount'],
                'recipientEmail' => $pendingData['recipientEmail'],
                'recipientName' => $pendingData['recipientName'],
                'senderName' => $pendingData['senderName'],
                'message' => $pendingData['message'],
                'edition' => $pendingData['edition'],
                'deliveryMethod' => $pendingData['deliveryMethod'],
                'scheduledSendAt' => $pendingData['scheduledSendAt'] ?? null,
            ],
            $paymentIntentId,
            $pendingData['idempotency_key'],
            $pendingData['payload_hash'],
        );

        // Only clear cache on success
        if ($result->getStatusCode() === 201) {
            Cache::forget("pending_gift_card:{$paymentIntentId}");
            Cache::forget("gift_card_idempotency:{$pendingData['idempotency_key']}");
        }

        return $result;
    }

    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $giftCard = GiftCard::where('code', $request->query('code'))->first();

        if (! $giftCard) {
            return $this->errorResponse([
                ['field' => 'code', 'message' => 'Gift card not found.'],
            ], 404);
        }

        return $this->successResponse([
            'balance' => $giftCard->current_balance,
            'status' => $giftCard->status->value,
        ]);
    }

    /**
     * Create a gift card after successful payment, with race-condition handling.
     *
     * If a unique constraint violation occurs on idempotency_key or
     * stripe_payment_intent_id (concurrent request raced us), fetches and
     * returns the existing record instead of surfacing a 500.
     */
    private function createGiftCard(array $data, string $paymentIntentId, string $idempotencyKey, string $payloadHash): JsonResponse
    {
        $maxAttempts = 3;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $code = $this->generateUniqueCode();

                    $giftCard = $this->giftCardService->purchase([
                        'code' => $code,
                        'initial_balance' => $data['amount'],
                        'current_balance' => $data['amount'],
                        'recipient_email' => $data['recipientEmail'],
                        'recipient_name' => $data['recipientName'],
                        'sender_name' => $data['senderName'],
                        'message' => $data['message'] ?? null,
                        'edition' => $data['edition'],
                        'delivery_method' => $data['deliveryMethod'],
                        'scheduled_send_at' => $data['scheduledSendAt'] ?? null,
                        'status' => GiftCardStatus::Active,
                        'stripe_payment_intent_id' => $paymentIntentId,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash' => $payloadHash,
                        'purchased_at' => now(),
                    ]);

                    return $this->successResponse(new GiftCardResource($giftCard), status: 201);
                } catch (UniqueConstraintViolationException) {
                    // Race condition: another request completed first, or code collision
                    $existing = GiftCard::where('idempotency_key', $idempotencyKey)->first()
                        ?? GiftCard::where('stripe_payment_intent_id', $paymentIntentId)->first();

                    if ($existing) {
                        if ($existing->payload_hash !== $payloadHash) {
                            return $this->payloadMismatchResponse();
                        }

                        return $this->successResponse(new GiftCardResource($existing), status: 201);
                    }

                    // Code collision — retry with a new code
                    if ($attempt < $maxAttempts) {
                        continue;
                    }

                    // Exhausted retries with no existing record found — refund
                    $this->refundOrReport($paymentIntentId);

                    return $this->errorResponse([
                        ['field' => 'payment', 'message' => 'An unexpected error occurred. Please try again.'],
                    ], 500);
                }
            }
        } catch (\Throwable $e) {
            $this->refundOrReport($paymentIntentId);

            throw $e;
        }

        // Unreachable, but satisfies return type
        $this->refundOrReport($paymentIntentId);

        return $this->errorResponse([
            ['field' => 'payment', 'message' => 'An unexpected error occurred. Please try again.'],
        ], 500);
    }

    private function cacheHardFailure(
        string $idempotencyKey,
        string $field,
        string $message,
        int $status,
        string $payloadHash,
    ): void {
        Cache::put("gift_card_idempotency:{$idempotencyKey}", [
            'status' => 'failed',
            'error_field' => $field,
            'error_message' => $message,
            'error_status' => $status,
            'payload_hash' => $payloadHash,
        ], now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * Attempt to refund a captured PaymentIntent as a compensating action.
     * If the refund itself fails, report it so it can be resolved manually.
     */
    private function refundOrReport(string $paymentIntentId): void
    {
        try {
            $this->stripeService->refundPaymentIntent($paymentIntentId);
        } catch (\Throwable $refundException) {
            report($refundException);
        }
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'GC-'.strtoupper(Str::random(8));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }

    private function payloadMismatchResponse(): JsonResponse
    {
        return $this->errorResponse([
            ['field' => 'idempotencyKey', 'message' => 'This key was already used with different parameters.'],
        ], 409);
    }
}
