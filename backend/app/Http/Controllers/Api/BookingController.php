<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\GiftCardStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\GiftCard;
use App\Models\Location;
use App\Models\Showtime;
use App\Models\User;
use App\Services\SeatAvailabilityService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class BookingController extends Controller
{
    private const BOOKING_RELATIONS = ['showtime.movie', 'showtime.auditorium', 'seats.seat', 'foodItems'];

    public function __construct(
        private readonly SeatAvailabilityService $seatService,
        private readonly StripeService $stripeService,
    ) {}

    public function store(Location $location, CreateBookingRequest $request): JsonResponse
    {
        $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
            ->find($request->input('showtimeId'));

        if (! $showtime || $showtime->start_time->isPast()) {
            return $this->errorResponse([['message' => 'This showtime has expired or does not exist.']], 410);
        }

        $seatIds = $request->input('seatIds');
        $foodItemsInput = $request->input('foodItems', []);
        $promoCode = $request->input('promoCode');
        $giftCardCode = $request->input('giftCardCode');
        $paymentMethodId = $request->input('paymentMethodId');

        if ($promoCode) {
            $promoCode = strtoupper($promoCode);
        }

        // Validate food items
        $foodTotal = 0;
        $resolvedFoodItems = [];

        foreach ($foodItemsInput as $item) {
            $menuItem = $location->menuItems()
                ->currentlyAvailable()
                ->locationAvailable()
                ->find($item['itemId']);

            if (! $menuItem) {
                return $this->errorResponse([['field' => 'foodItems', 'message' => "Menu item {$item['itemId']} is unavailable at this location."]], 400);
            }

            $resolvedPrice = $menuItem->priceForLocation();
            $itemTotal = $resolvedPrice * $item['quantity'];
            $foodTotal += $itemTotal;

            $resolvedFoodItems[] = [
                'menu_item_id' => $menuItem->id,
                'name'         => $menuItem->name,
                'quantity'     => $item['quantity'],
                'unit_price'   => $resolvedPrice,
                'total_price'  => $itemTotal,
            ];
        }

        // Validate promo code
        $promoConfig = null;
        if ($promoCode) {
            $promoConfig = config("promo_codes.{$promoCode}");

            if (! $promoConfig) {
                return $this->errorResponse([['field' => 'promoCode', 'message' => 'Invalid promo code.']], 400);
            }
        }

        // Validate gift card (pre-check before transaction)
        if ($giftCardCode) {
            $giftCardPreCheck = GiftCard::where('code', $giftCardCode)
                ->where('status', GiftCardStatus::Active)
                ->first();

            if (! $giftCardPreCheck || $giftCardPreCheck->current_balance <= 0) {
                return $this->errorResponse([['field' => 'giftCardCode', 'message' => 'Invalid or depleted gift card.']], 400);
            }
        }

        return DB::transaction(function () use (
            $location, $showtime, $seatIds, $resolvedFoodItems, $foodTotal,
            $promoConfig, $giftCardCode, $paymentMethodId, $request,
        ) {
            $showtime = Showtime::with('auditorium', 'movie')
                ->whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                ->lockForUpdate()
                ->find($showtime->id);

            if (! $showtime || $showtime->start_time->isPast()) {
                return $this->errorResponse([['message' => 'This showtime has expired or does not exist.']], 410);
            }

            // Create provisional booking (needed for seat reservation)
            $booking = new Booking;
            $booking->showtime_id = $showtime->id;
            $booking->user_id = $request->user()?->id;
            $booking->guest_email = $request->user() ? null : $request->input('email');
            $booking->status = BookingStatus::Confirmed;
            $booking->subtotal = 0;
            $booking->discount = 0;
            $booking->total = 0;
            $booking->save();

            $seatTotal = $this->seatService->reserveSeats($showtime, $seatIds, $booking);

            $subtotal = $seatTotal + $foodTotal;
            $promoDiscount = $this->calculatePromoDiscount($promoConfig, $subtotal);

            // Apply gift card (lock for concurrent use protection)
            $giftCard = null;
            $giftCardAmount = 0;
            if ($giftCardCode) {
                $giftCard = GiftCard::lockForUpdate()
                    ->where('code', $giftCardCode)
                    ->where('status', GiftCardStatus::Active)
                    ->first();

                if (! $giftCard || $giftCard->current_balance <= 0) {
                    $booking->delete();

                    return $this->errorResponse([[
                        'field'   => 'giftCardCode',
                        'message' => 'Gift card is no longer valid or has been depleted.',
                    ]], 409);
                }

                $giftCardAmount = min($giftCard->current_balance, $subtotal - $promoDiscount);
            }

            $cardAmount = $subtotal - $promoDiscount - $giftCardAmount;
            $discount = $promoDiscount + $giftCardAmount;
            $total = $subtotal - $discount;

            // Process payment
            $stripePaymentIntentId = null;

            if ($cardAmount > 0) {
                if (! $paymentMethodId) {
                    $booking->delete();

                    return $this->errorResponse([[
                        'field'   => 'paymentMethodId',
                        'message' => 'A payment method is required when the gift card does not cover the full amount.',
                    ]], 422);
                }

                try {
                    $paymentIntent = $this->stripeService->createPaymentIntent(
                        $cardAmount,
                        $paymentMethodId,
                        ['showtime_id' => $showtime->id],
                    );

                    if ($paymentIntent->status === 'requires_action') {
                        Cache::put("pending_booking:{$paymentIntent->id}", [
                            'location_id'      => $location->id,
                            'showtime_id'      => $showtime->id,
                            'user_id'          => $request->user()?->id,
                            'guest_email'      => $request->user() ? null : $request->input('email'),
                            'seat_ids'         => $seatIds,
                            'food_items'       => $resolvedFoodItems,
                            'subtotal'         => $subtotal,
                            'discount'         => $discount,
                            'total'            => $total,
                            'card_amount'      => $cardAmount,
                            'gift_card_id'     => $giftCard?->id,
                            'gift_card_amount' => $giftCardAmount,
                            'payment_method'   => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                        ], now()->addMinutes(15));

                        $booking->delete();

                        return response()->json([
                            'data' => [
                                'requiresAction'  => true,
                                'clientSecret'    => $paymentIntent->client_secret,
                                'paymentIntentId' => $paymentIntent->id,
                            ],
                        ]);
                    }

                    if ($paymentIntent->status !== 'succeeded') {
                        $booking->delete();

                        return $this->errorResponse([['field' => 'payment', 'message' => 'Payment could not be completed. Please try again.']], 402);
                    }

                    $stripePaymentIntentId = $paymentIntent->id;
                } catch (CardException $e) {
                    $booking->delete();

                    return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
                } catch (InvalidRequestException $e) {
                    $booking->delete();

                    return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 400);
                } catch (ApiErrorException $e) {
                    $booking->delete();

                    report($e);

                    return $this->errorResponse([['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
                }
            }

            // Payment has been captured. If any subsequent DB write fails,
            // issue a compensating refund so we don't orphan a charge.
            try {
                $booking->update([
                    'subtotal'                 => $subtotal,
                    'discount'                 => $discount,
                    'total'                    => $total,
                    'payment_method'           => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                    'stripe_payment_intent_id' => $stripePaymentIntentId,
                ]);

                $this->finalizeBooking($booking, $resolvedFoodItems, $giftCard, $giftCardAmount, $request->user()?->id, $total);
            } catch (\Throwable $e) {
                if ($stripePaymentIntentId) {
                    $this->refundOrReport($stripePaymentIntentId);
                }

                throw $e;
            }

            $booking->load(self::BOOKING_RELATIONS);

            return $this->successResponse(new BookingResource($booking), status: 201);
        });
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = Booking::with(self::BOOKING_RELATIONS)->find($id);

        if (! $booking || ! $request->user() || $booking->user_id !== $request->user()->id) {
            return $this->errorResponse([['message' => 'Booking not found']], 404);
        }

        return $this->successResponse(new BookingResource($booking));
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation_code' => 'required|string',
            'email'             => 'required|email',
        ]);

        $booking = Booking::with(self::BOOKING_RELATIONS)
            ->where('confirmation_code', $request->query('confirmation_code'))
            ->where('guest_email', $request->query('email'))
            ->first();

        if (! $booking) {
            return $this->errorResponse([['message' => 'Booking not found']], 404);
        }

        return $this->successResponse(new BookingResource($booking));
    }

    public function confirm(Location $location, Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => 'required|string',
        ]);

        $paymentIntentId = $request->input('paymentIntentId');
        $cacheKey = "pending_booking:{$paymentIntentId}";
        $pendingData = Cache::get($cacheKey);

        if (! $pendingData) {
            return $this->errorResponse([['message' => 'Session expired. Please start over.']], 410);
        }

        // Validate seats and confirm payment inside a single transaction so that
        // Stripe is never charged when seats are no longer available.
        $result = DB::transaction(function () use ($location, $pendingData, $paymentIntentId) {
            $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                ->lockForUpdate()
                ->find($pendingData['showtime_id']);

            if (! $showtime || $showtime->start_time->isPast()) {
                return $this->errorResponse([['message' => 'This showtime has expired or does not exist.']], 410);
            }

            // Validate seat availability BEFORE confirming payment.
            // checkAvailability returns unavailable seat IDs; if any, bail out
            // without touching Stripe — the uncaptured PI expires on its own.
            $unavailable = $this->seatService->checkAvailability(
                $showtime->id,
                $pendingData['seat_ids'],
            );

            if (! empty($unavailable)) {
                throw new \App\Exceptions\SeatConflictException($unavailable);
            }

            // Revalidate gift card balance BEFORE confirming payment.
            // If the balance dropped during the 3DS window, the original
            // PaymentIntent amount no longer covers the correct card portion.
            // Rather than silently under- or over-charging, fail early so the
            // customer can restart checkout with current pricing.
            $giftCard = null;
            $giftCardAmount = $pendingData['gift_card_amount'] ?? 0;
            $originalCardAmount = $pendingData['card_amount'] ?? $pendingData['total'];

            if ($pendingData['gift_card_id'] ?? null) {
                $giftCard = GiftCard::lockForUpdate()
                    ->where('status', GiftCardStatus::Active)
                    ->find($pendingData['gift_card_id']);

                if (! $giftCard) {
                    return $this->errorResponse([[
                        'field'   => 'giftCardCode',
                        'message' => 'Gift card is no longer valid. Please start over.',
                    ]], 409);
                }

                if ($giftCardAmount > 0 && $giftCard->current_balance < $giftCardAmount) {
                    return $this->errorResponse([[
                        'field'   => 'giftCardCode',
                        'message' => 'Gift card balance changed during payment. Please start over.',
                    ]], 409);
                }
            }

            // Gift card still valid — safe to confirm the PaymentIntent.
            try {
                $paymentIntent = $this->stripeService->confirmPaymentIntent($paymentIntentId);

                if ($paymentIntent->status !== 'succeeded') {
                    return $this->errorResponse([['field' => 'payment', 'message' => 'Payment confirmation failed.']], 402);
                }
            } catch (CardException $e) {
                return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
            } catch (InvalidRequestException $e) {
                return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 400);
            } catch (ApiErrorException $e) {
                report($e);

                return $this->errorResponse([['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
            }

            // Payment has been captured. If any subsequent DB write fails,
            // issue a compensating refund so we don't orphan a charge.
            try {
                $discount = $pendingData['discount'];
                $total = $pendingData['total'];
                $cardAmount = $originalCardAmount;

                $booking = new Booking;
                $booking->showtime_id = $pendingData['showtime_id'];
                $booking->user_id = $pendingData['user_id'];
                $booking->guest_email = $pendingData['guest_email'];
                $booking->status = BookingStatus::Confirmed;
                $booking->subtotal = $pendingData['subtotal'];
                $booking->discount = $discount;
                $booking->total = $total;
                $booking->payment_method = $this->determinePaymentMethod($cardAmount, $giftCardAmount);
                $booking->stripe_payment_intent_id = $paymentIntentId;
                $booking->save();

                $this->seatService->reserveSeats($showtime, $pendingData['seat_ids'], $booking);

                $this->finalizeBooking(
                    $booking,
                    $pendingData['food_items'],
                    $giftCard,
                    $giftCardAmount,
                    $pendingData['user_id'],
                    $total,
                );
            } catch (\Throwable $e) {
                $this->refundOrReport($paymentIntentId);

                throw $e;
            }

            $booking->load(self::BOOKING_RELATIONS);

            return $this->successResponse(new BookingResource($booking), status: 201);
        });

        // Only forget cache after a successful booking — error responses
        // (gift card conflict, payment failure) must preserve the pending state
        // so the customer can retry or restart.
        if ($result->getStatusCode() === 201) {
            Cache::forget($cacheKey);
        }

        return $result;
    }

    private function finalizeBooking(
        Booking $booking,
        array $foodItems,
        ?GiftCard $giftCard,
        int $giftCardAmount,
        ?string $userId,
        int $total,
    ): void {
        foreach ($foodItems as $foodItem) {
            BookingFoodItem::create(array_merge($foodItem, [
                'booking_id' => $booking->id,
            ]));
        }

        if ($giftCard && $giftCardAmount > 0) {
            $deduction = min($giftCardAmount, $giftCard->current_balance);
            $newBalance = $giftCard->current_balance - $deduction;
            $giftCard->update([
                'current_balance' => max(0, $newBalance),
                'status'          => $newBalance <= 0 ? GiftCardStatus::Depleted : GiftCardStatus::Active,
            ]);
        }

        if ($userId) {
            $points = (int) floor($total / 100);
            if ($points > 0) {
                User::where('id', $userId)->increment('loyalty_points', $points);
            }
        }
    }

    private function calculatePromoDiscount(?array $promoConfig, int $subtotal): int
    {
        if (! $promoConfig) {
            return 0;
        }

        if ($promoConfig['type'] === 'percentage') {
            $discount = (int) floor($subtotal * $promoConfig['value'] / 100);
            if (isset($promoConfig['max_discount'])) {
                $discount = min($discount, $promoConfig['max_discount']);
            }
        } else {
            $discount = $promoConfig['value'];
        }

        return min($discount, $subtotal);
    }

    /**
     * Attempt to refund a captured PaymentIntent as a compensating action.
     * If the refund itself fails, report it so it can be resolved manually
     * rather than swallowing the original exception.
     */
    private function refundOrReport(string $paymentIntentId): void
    {
        try {
            $this->stripeService->refundPaymentIntent($paymentIntentId);
        } catch (\Throwable $refundException) {
            report($refundException);
        }
    }

    private function determinePaymentMethod(int $cardAmount, int $giftCardAmount): PaymentMethod
    {
        if ($cardAmount > 0 && $giftCardAmount > 0) {
            return PaymentMethod::Mixed;
        }

        if ($giftCardAmount > 0) {
            return PaymentMethod::GiftCard;
        }

        return PaymentMethod::Card;
    }
}
