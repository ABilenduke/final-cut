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
use App\Models\MenuItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\SeatAvailabilityService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\CardException;

class BookingController extends Controller
{
    private const BOOKING_RELATIONS = ['showtime.movie', 'showtime.auditorium', 'seats.seat', 'foodItems'];

    public function __construct(
        private readonly SeatAvailabilityService $seatService,
        private readonly StripeService $stripeService,
    ) {}

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $showtime = Showtime::find($request->input('showtimeId'));

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
            $menuItem = MenuItem::currentlyAvailable()->find($item['itemId']);

            if (! $menuItem) {
                return $this->errorResponse([['field' => 'foodItems', 'message' => "Menu item {$item['itemId']} is unavailable."]], 400);
            }

            $itemTotal = $menuItem->price * $item['quantity'];
            $foodTotal += $itemTotal;

            $resolvedFoodItems[] = [
                'menu_item_id' => $menuItem->id,
                'name'         => $menuItem->name,
                'quantity'     => $item['quantity'],
                'unit_price'   => $menuItem->price,
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
            $showtime, $seatIds, $resolvedFoodItems, $foodTotal,
            $promoConfig, $giftCardCode, $paymentMethodId, $request,
        ) {
            $showtime = Showtime::with('auditorium', 'movie')
                ->lockForUpdate()
                ->find($showtime->id);

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

                if ($giftCard && $giftCard->current_balance > 0) {
                    $giftCardAmount = min($giftCard->current_balance, $subtotal - $promoDiscount);
                }
            }

            $cardAmount = $subtotal - $promoDiscount - $giftCardAmount;
            $discount = $promoDiscount + $giftCardAmount;
            $total = $subtotal - $discount;

            // Process payment
            $stripePaymentIntentId = null;

            if ($cardAmount > 0) {
                try {
                    $paymentIntent = $this->stripeService->createPaymentIntent(
                        $cardAmount,
                        $paymentMethodId,
                        ['showtime_id' => $showtime->id],
                    );

                    if ($paymentIntent->status === 'requires_action') {
                        Cache::put("pending_booking:{$paymentIntent->id}", [
                            'showtime_id'      => $showtime->id,
                            'user_id'          => $request->user()?->id,
                            'guest_email'      => $request->user() ? null : $request->input('email'),
                            'seat_ids'         => $seatIds,
                            'food_items'       => $resolvedFoodItems,
                            'subtotal'         => $subtotal,
                            'discount'         => $discount,
                            'total'            => $total,
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

                    $stripePaymentIntentId = $paymentIntent->id;
                } catch (CardException $e) {
                    $booking->delete();

                    return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
                }
            }

            $booking->update([
                'subtotal'                 => $subtotal,
                'discount'                 => $discount,
                'total'                    => $total,
                'payment_method'           => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ]);

            $this->finalizeBooking($booking, $resolvedFoodItems, $giftCard, $giftCardAmount, $request->user()?->id, $total);

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

    public function confirm(Request $request): JsonResponse
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

        try {
            $paymentIntent = $this->stripeService->confirmPaymentIntent($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse([['field' => 'payment', 'message' => 'Payment confirmation failed.']], 402);
            }
        } catch (CardException $e) {
            return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
        }

        $result = DB::transaction(function () use ($pendingData, $paymentIntentId) {
            $showtime = Showtime::lockForUpdate()->find($pendingData['showtime_id']);

            if (! $showtime) {
                return $this->errorResponse([['message' => 'Showtime not found']], 404);
            }

            // Revalidate gift card balance (may have changed during 3DS window)
            $giftCard = null;
            $giftCardAmount = 0;
            if ($pendingData['gift_card_id'] ?? null) {
                $giftCard = GiftCard::lockForUpdate()->find($pendingData['gift_card_id']);

                if ($giftCard && $giftCard->current_balance > 0) {
                    $giftCardAmount = min(
                        $giftCard->current_balance,
                        $pendingData['subtotal'] - ($pendingData['discount'] - ($pendingData['gift_card_amount'] ?? 0)),
                    );
                }
            }

            // Recompute totals with current gift card balance
            $promoDiscount = $pendingData['discount'] - ($pendingData['gift_card_amount'] ?? 0);
            $discount = $promoDiscount + $giftCardAmount;
            $total = $pendingData['subtotal'] - $discount;
            $cardAmount = $pendingData['subtotal'] - $discount;

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

            $booking->load(self::BOOKING_RELATIONS);

            return $this->successResponse(new BookingResource($booking), status: 201);
        });

        // Only forget cache after the transaction has committed successfully
        Cache::forget($cacheKey);

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
