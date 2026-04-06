<?php

namespace App\Http\Controllers\Api;

use App\Enums\GiftCardStatus;
use App\Http\Requests\PurchaseGiftCardRequest;
use App\Http\Resources\GiftCardResource;
use App\Models\GiftCard;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class GiftCardController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    public function purchase(PurchaseGiftCardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $validated['amount'],
                $validated['paymentMethodId'],
                ['type' => 'gift_card'],
            );

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse([
                    ['field' => 'payment', 'message' => 'Payment could not be completed.'],
                ], 402);
            }
        } catch (CardException $e) {
            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 402);
        } catch (InvalidRequestException $e) {
            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 400);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.'],
            ], 502);
        }

        $code = $this->generateUniqueCode();

        $giftCard = GiftCard::create([
            'code' => $code,
            'initial_balance' => $validated['amount'],
            'current_balance' => $validated['amount'],
            'recipient_email' => $validated['recipientEmail'],
            'recipient_name' => $validated['recipientName'],
            'sender_name' => $validated['senderName'],
            'message' => $validated['message'] ?? null,
            'status' => GiftCardStatus::Active,
            'purchased_at' => now(),
        ]);

        return $this->successResponse(new GiftCardResource($giftCard), status: 201);
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

    private function generateUniqueCode(): string
    {
        do {
            $code = 'GC-'.strtoupper(Str::random(8));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }
}
