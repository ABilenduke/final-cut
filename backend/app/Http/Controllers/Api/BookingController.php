<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\GiftCardStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PromoCodeNotConsumableException;
use App\Exceptions\SeatConflictException;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\GiftCard;
use App\Models\Location;
use App\Models\PromoCode;
use App\Models\Showtime;
use App\Models\User;
use App\Services\GiftCardService;
use App\Services\LoyaltyService;
use App\Services\PromoCodeService;
use App\Services\SeatAvailabilityService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class BookingController extends Controller
{
    private const BOOKING_RELATIONS = ['showtime.movie', 'showtime.auditorium', 'seats.seat', 'foodItems'];

    public function __construct(
        private readonly SeatAvailabilityService $seatService,
        private readonly StripeService $stripeService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PromoCodeService $promoCodeService,
        private readonly GiftCardService $giftCardService,
    ) {}

    public function store(Location $location, CreateBookingRequest $request): JsonResponse
    {
        $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
            ->whereNull('cancelled_at')
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
                ->wherePivotNull('unavailable_at')
                ->find($item['itemId']);

            if (! $menuItem) {
                return $this->errorResponse([['field' => 'foodItems', 'message' => "Menu item {$item['itemId']} is unavailable at this location."]], 400);
            }

            $resolvedPrice = $menuItem->priceForLocation();
            $itemTotal = $resolvedPrice * $item['quantity'];
            $foodTotal += $itemTotal;

            $resolvedFoodItems[] = [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'quantity' => $item['quantity'],
                'unit_price' => $resolvedPrice,
                'total_price' => $itemTotal,
            ];
        }

        // Validate promo code — DB-backed via PromoCodeService (replaced config/promo_codes.php).
        $promo = null;
        if ($promoCode) {
            $promo = $this->promoCodeService->validateCode($promoCode, 0);

            if (! $promo) {
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

        try {
            return DB::transaction(function () use (
                $location, $showtime, $seatIds, $resolvedFoodItems, $foodTotal, $promo, $giftCardCode, $paymentMethodId, $request,
            ) {
                $showtime = Showtime::with('auditorium', 'movie')
                    ->whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                    ->whereNull('cancelled_at')
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
                $promoDiscount = $promo
                    ? $this->promoCodeService->calculateDiscount($promo, $subtotal)
                    : 0;

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
                            'field' => 'giftCardCode',
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
                            'field' => 'paymentMethodId',
                            'message' => 'A payment method is required when the gift card does not cover the full amount.',
                        ]], 422);
                    }

                    try {
                        $paymentIntent = $this->stripeService->createPaymentIntent(
                            $cardAmount,
                            $paymentMethodId,
                            $this->buildStripeMetadata($showtime, $location, $seatIds, $booking),
                            $request->header('Idempotency-Key'),
                            $this->buildStripeDescription($showtime),
                            $request->user()?->email ?? $request->input('email'),
                        );

                        if ($paymentIntent->status === 'requires_action') {
                            Cache::put("pending_booking:{$paymentIntent->id}", [
                                'location_id' => $location->id,
                                'showtime_id' => $showtime->id,
                                'user_id' => $request->user()?->id,
                                'guest_email' => $request->user() ? null : $request->input('email'),
                                'seat_ids' => $seatIds,
                                'food_items' => $resolvedFoodItems,
                                'subtotal' => $subtotal,
                                'discount' => $discount,
                                'total' => $total,
                                'card_amount' => $cardAmount,
                                'gift_card_id' => $giftCard?->id,
                                'gift_card_amount' => $giftCardAmount,
                                'promo_code_id' => $promo?->id,
                                'payment_method' => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                            ], now()->addMinutes(15));

                            $booking->delete();

                            return response()->json([
                                'data' => [
                                    'requiresAction' => true,
                                    'clientSecret' => $paymentIntent->client_secret,
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
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'total' => $total,
                        'payment_method' => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                        'stripe_payment_intent_id' => $stripePaymentIntentId,
                    ]);

                    $this->finalizeBooking($booking, $resolvedFoodItems, $giftCard, $giftCardAmount, $promo, $request->user()?->id, $total);
                } catch (\Throwable $e) {
                    // PromoCodeNotConsumableException (and any other throwable
                    // post-charge) propagates out of the transaction so the
                    // booking + ledger rows roll back cleanly. The outer catch
                    // owns refund + response shaping using the captured PI id.
                    if ($stripePaymentIntentId) {
                        $this->refundOrReport($stripePaymentIntentId);
                    }

                    throw $e;
                }

                $booking->load(self::BOOKING_RELATIONS);

                // Best-effort: attach the booking's confirmation_code to the
                // Stripe PaymentIntent so a row in the Stripe dashboard ties
                // back to the human-readable code on the customer's receipt.
                // Payment has already succeeded; a failure here must not
                // block the customer.
                if ($stripePaymentIntentId) {
                    $this->attachConfirmationCodeToStripe($stripePaymentIntentId, $booking);
                }

                return $this->successResponse(new BookingResource($booking), status: 201);
            });
        } catch (PromoCodeNotConsumableException $e) {
            // The transaction rolled back (booking row + ledger gone) and
            // the inner `\Throwable` catch already issued the compensating
            // refund before re-throwing. Outer catch only shapes the 409.
            return $this->errorResponse([[
                'field' => 'promoCode',
                'message' => 'This promo code is no longer redeemable. Please remove it and try again.',
            ]], 409);
        }
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
            'email' => 'required|email',
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
        try {
            $result = DB::transaction(function () use ($location, $pendingData, $paymentIntentId) {
                $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                    ->whereNull('cancelled_at')
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
                    throw new SeatConflictException($unavailable);
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
                            'field' => 'giftCardCode',
                            'message' => 'Gift card is no longer valid. Please start over.',
                        ]], 409);
                    }

                    if ($giftCardAmount > 0 && $giftCard->current_balance < $giftCardAmount) {
                        return $this->errorResponse([[
                            'field' => 'giftCardCode',
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

                    // Rehydrate the promo BEFORE writing the booking so a hard-
                    // delete during the 3DS window fails fast — otherwise a null
                    // promo would silently skip `consume()` and the cached
                    // discount would be honoured against a code that no longer
                    // exists. `consume()` itself catches the deactivated /
                    // expired / limit-reached cases via revalidation under lock.
                    $promo = null;
                    if (! empty($pendingData['promo_code_id'])) {
                        $promo = PromoCode::query()->find($pendingData['promo_code_id']);
                        if ($promo === null) {
                            throw new PromoCodeNotConsumableException(
                                PromoCodeNotConsumableException::REASON_NOT_FOUND,
                            );
                        }
                    }

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
                        $promo,
                        $pendingData['user_id'],
                        $total,
                    );
                } catch (\Throwable $e) {
                    // Promo / seat / DB throws after the PI capture; refund and
                    // re-throw so the transaction rolls back the booking +
                    // ledger writes. The outer catch below converts a
                    // `PromoCodeNotConsumableException` into a 409.
                    $this->refundOrReport($paymentIntentId);

                    throw $e;
                }

                $booking->load(self::BOOKING_RELATIONS);

                // Same best-effort metadata patch as the non-3DS path. The
                // PaymentIntent already carries the showtime/movie metadata
                // from store(); here we tack the booking's confirmation_code
                // on now that the row exists.
                $this->attachConfirmationCodeToStripe($paymentIntentId, $booking);

                return $this->successResponse(new BookingResource($booking), status: 201);
            });
        } catch (PromoCodeNotConsumableException $e) {
            // Booking row + ledger were rolled back when the exception
            // bubbled through DB::transaction; the inner catch already
            // refunded the captured PI. Pending cache is preserved so the
            // customer can restart with a different (or no) promo code.
            return $this->errorResponse([[
                'field' => 'promoCode',
                'message' => 'This promo code is no longer redeemable. Please remove it and try again.',
            ]], 409);
        }

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
        ?PromoCode $promo,
        ?string $userId,
        int $total,
    ): void {
        foreach ($foodItems as $foodItem) {
            BookingFoodItem::create(array_merge($foodItem, [
                'booking_id' => $booking->id,
            ]));
        }

        if ($giftCard && $giftCardAmount > 0) {
            $this->giftCardService->redeemAgainstBooking(
                $giftCard,
                $giftCardAmount,
                $booking,
                null,
            );
        }

        if ($promo) {
            // Atomic re-validate + increment under lock. Throws
            // `PromoCodeNotConsumableException` if the code was deactivated,
            // expired, or hit its usage limit between pre-check and now —
            // the booking transaction rolls back and the outer try/catch
            // refunds the captured PaymentIntent.
            $this->promoCodeService->consume($promo, null);
        }

        /** @var User|null $user */
        $user = $booking->user;
        if ($user) {
            $this->loyaltyService->awardPointsForPurchase($user, $total);
        }
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

    /**
     * Build the Stripe PaymentIntent description string. Shown in the Stripe
     * dashboard column and on the hosted receipt. Format chosen so a finance
     * scan of the dashboard tells you which film and date the charge covers
     * without needing to cross-reference internal IDs.
     */
    private function buildStripeDescription(Showtime $showtime): string
    {
        $movieTitle = $showtime->movie?->title ?? 'Final Cut';
        $date = $showtime->start_time->toDateString();

        return "Final Cut · {$movieTitle} · {$date}";
    }

    /**
     * Build the Stripe PaymentIntent metadata bag. All values must be
     * Stripe-compatible strings (no nulls, no arrays). Keys mirror the field
     * names you'd want when filtering charges in the dashboard.
     *
     * @param  string[]  $seatIds
     */
    private function buildStripeMetadata(Showtime $showtime, Location $location, array $seatIds, Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'location_slug' => $location->slug,
            'auditorium' => $showtime->auditorium?->name ?? '',
            'movie_title' => $showtime->movie?->title ?? '',
            'seat_count' => (string) count($seatIds),
        ];
    }

    /**
     * Patch a captured PaymentIntent's metadata with the finalized booking's
     * confirmation_code and ID. In the 3DS path the booking that exists at
     * confirm() time is a new row — different ID from the provisional one
     * stamped into metadata at createPaymentIntent time — so we re-write
     * booking_id alongside confirmation_code to keep the dashboard pointing
     * at a live row. Best-effort: payment has already succeeded, log + move
     * on if Stripe is unreachable.
     */
    private function attachConfirmationCodeToStripe(string $paymentIntentId, Booking $booking): void
    {
        if (! $booking->confirmation_code) {
            return;
        }

        try {
            $this->stripeService->updatePaymentIntentMetadata(
                $paymentIntentId,
                [
                    'confirmation_code' => $booking->confirmation_code,
                    'booking_id' => $booking->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to attach confirmation_code to Stripe PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
                'booking_id' => $booking->id,
                'confirmation_code' => $booking->confirmation_code,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
