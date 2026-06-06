<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\GiftCardStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingNotAllowedException;
use App\Exceptions\GiftCardBalanceChangedException;
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
use App\Services\PromoRedemptionIdentity;
use App\Services\SeatAvailabilityService;
use App\Services\StripeService;
use Illuminate\Database\UniqueConstraintViolationException;
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
        // Idempotency replay: a retried POST (lost response, double-click) that
        // reuses the client's Idempotency-Key returns the original booking
        // instead of creating a second one / capturing a second charge.
        $idempotencyKey = $request->input('idempotencyKey');
        if ($idempotencyKey) {
            // Replay only a COMPLETED booking. A leaked/in-flight Held booking
            // with this key must NOT be returned as a successful purchase — the
            // request falls through and hits the unique index, which the catch
            // below resolves.
            $replay = Booking::where('idempotency_key', $idempotencyKey)
                ->where('status', BookingStatus::Confirmed)
                ->first();
            if ($replay) {
                $replay->load(self::BOOKING_RELATIONS);

                return $this->successResponse(new BookingResource($replay), status: 201);
            }
        }

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
            // Pre-check rejects a code this customer has already redeemed up to
            // its per_user_limit (email is canonicalized by CreateBookingRequest).
            $promo = $this->promoCodeService->validateCode(
                $promoCode,
                0,
                $this->promoRedemptionIdentity($request->user()?->id, $request->input('email')),
            );

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

        // ── PHASE A — reserve seats under a brief lock ──────────────────────
        // Commit a Held booking + its booking_seats so the seats stay reserved
        // (Held is an occupying status) WITHOUT holding the row lock across the
        // Stripe call. Returns the reservation context, or a JsonResponse on an
        // early failure (rolls back the Held booking).
        try {
            $reservation = DB::transaction(function () use (
                $location, $showtime, $seatIds, $foodTotal, $promo, $giftCardCode, $paymentMethodId, $request, $idempotencyKey,
            ) {
                $showtime = Showtime::with('auditorium', 'movie')
                    ->whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                    ->whereNull('cancelled_at')
                    ->lockForUpdate()
                    ->find($showtime->id);

                if (! $showtime || $showtime->start_time->isPast()) {
                    return $this->errorResponse([['message' => 'This showtime has expired or does not exist.']], 410);
                }

                $booking = new Booking;
                $booking->showtime_id = $showtime->id;
                $booking->user_id = $request->user()?->id;
                $booking->guest_email = $request->user() ? null : $request->input('email');
                // Held (occupying) — reserves the seats while we call Stripe with
                // the lock released. Flipped to Confirmed in Phase C, or discarded
                // on failure (releasing the seats).
                $booking->status = BookingStatus::Held;
                $booking->subtotal = 0;
                $booking->discount = 0;
                $booking->total = 0;
                $booking->idempotency_key = $idempotencyKey;
                $booking->save();

                $seatTotal = $this->seatService->reserveSeats($showtime, $seatIds, $booking);

                $subtotal = $seatTotal + $foodTotal;
                $promoDiscount = $promo
                    ? $this->promoCodeService->calculateDiscount($promo, $subtotal)
                    : 0;

                // Read the gift-card balance to size the PaymentIntent. Lock the
                // row (the showtime is already locked, so lock order is always
                // showtime → gift_card) so a concurrent redemption can't size the
                // PI against a stale balance. The redemption itself happens in
                // Phase C after payment succeeds.
                $giftCard = null;
                $giftCardAmount = 0;
                if ($giftCardCode) {
                    $giftCard = GiftCard::where('code', $giftCardCode)
                        ->where('status', GiftCardStatus::Active)
                        ->lockForUpdate()
                        ->first();

                    // Bail cases below MUST throw (not return): a `return` from a
                    // DB::transaction closure COMMITS, which would leak the Held
                    // booking + booking_seats written above. Throwing rolls back.
                    if (! $giftCard || $giftCard->current_balance <= 0) {
                        throw new BookingNotAllowedException(
                            'giftCardCode',
                            'Gift card is no longer valid or has been depleted.',
                            409,
                        );
                    }

                    $giftCardAmount = min($giftCard->current_balance, $subtotal - $promoDiscount);
                }

                $cardAmount = $subtotal - $promoDiscount - $giftCardAmount;
                $discount = $promoDiscount + $giftCardAmount;
                $total = $subtotal - $discount;

                if ($cardAmount > 0 && ! $paymentMethodId) {
                    throw new BookingNotAllowedException(
                        'paymentMethodId',
                        'A payment method is required when the gift card does not cover the full amount.',
                        422,
                    );
                }

                // Persist the real provisional amounts on the Held booking so
                // admin/reporting reads never see a $0 confirmed row.
                $booking->subtotal = $subtotal;
                $booking->discount = $discount;
                $booking->total = $total;
                $booking->payment_method = $this->determinePaymentMethod($cardAmount, $giftCardAmount);
                $booking->save();

                return [
                    'booking' => $booking,
                    'showtime' => $showtime,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'card_amount' => $cardAmount,
                    'gift_card_id' => $giftCard?->id,
                    'gift_card_amount' => $giftCardAmount,
                    'promo_code_id' => $promo?->id,
                ];
            });
        } catch (BookingNotAllowedException $e) {
            // Thrown from Phase A after the Held booking/seats were written — the
            // transaction has rolled them back. Shape the HTTP error.
            return $this->errorResponse([['field' => $e->field, 'message' => $e->reason]], $e->status);
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent double-submit of the same Idempotency-Key lost the race
            // on the unique index. Only replay a COMPLETED booking; a still-Held
            // winner (in flight) means "processing" — never report an unpaid Held
            // booking as a 201 success.
            if ($idempotencyKey) {
                $existing = Booking::where('idempotency_key', $idempotencyKey)->first();
                if ($existing && $existing->status === BookingStatus::Confirmed) {
                    $existing->load(self::BOOKING_RELATIONS);

                    return $this->successResponse(new BookingResource($existing), status: 201);
                }
                if ($existing) {
                    return $this->errorResponse([['message' => 'A booking with this key is already being processed. Please wait a moment and try again.']], 409);
                }
            }

            throw $e;
        }

        if ($reservation instanceof JsonResponse) {
            return $reservation; // Phase A bailed (showtime/gift-card/payment-method) — booking rolled back.
        }

        $booking = $reservation['booking'];
        $showtime = $reservation['showtime'];
        $cardAmount = $reservation['card_amount'];
        $giftCardAmount = $reservation['gift_card_amount'];

        // ── PHASE B — Stripe, with NO transaction and NO row lock held ──────
        $stripePaymentIntentId = null;
        if ($cardAmount > 0) {
            $authedUser = $request->user();
            $receiptEmail = $authedUser ? $authedUser->email : $request->input('email');

            try {
                $paymentIntent = $this->stripeService->createPaymentIntent(
                    $cardAmount,
                    $paymentMethodId,
                    $this->buildStripeMetadata($showtime, $location, $seatIds, $booking),
                    // Namespace the client key so a booking and a gift-card
                    // purchase that share a UUID can't collide in Stripe.
                    $idempotencyKey ? "booking:{$idempotencyKey}" : null,
                    $this->buildStripeDescription($showtime),
                    $receiptEmail,
                );
            } catch (CardException $e) {
                // Stripe card-decline messages are designed to be surfaced to the
                // cardholder (e.g. "Your card was declined"), so they pass through.
                $this->discardHeldBooking($booking);

                return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
            } catch (InvalidRequestException $e) {
                // Integration-facing message (param names, ids) — never leak to the
                // client; log it for operators and return a generic error.
                report($e);
                $this->discardHeldBooking($booking);

                return $this->errorResponse([['field' => 'payment', 'message' => 'We could not process your payment. Please try again or contact support.']], 400);
            } catch (ApiErrorException $e) {
                $this->discardHeldBooking($booking);
                report($e);

                return $this->errorResponse([['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
            }

            if ($paymentIntent->status === 'requires_action') {
                Cache::put("pending_booking:{$paymentIntent->id}", [
                    'location_id' => $location->id,
                    'showtime_id' => $showtime->id,
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $request->user()?->id,
                    'guest_email' => $request->user() ? null : $request->input('email'),
                    'seat_ids' => $seatIds,
                    'food_items' => $resolvedFoodItems,
                    'subtotal' => $reservation['subtotal'],
                    'discount' => $reservation['discount'],
                    'total' => $reservation['total'],
                    'card_amount' => $cardAmount,
                    'gift_card_id' => $reservation['gift_card_id'],
                    'gift_card_amount' => $giftCardAmount,
                    'promo_code_id' => $reservation['promo_code_id'],
                    'payment_method' => $this->determinePaymentMethod($cardAmount, $giftCardAmount),
                ], now()->addMinutes(15));

                // Release the seats during the 3DS wait: confirm() re-reserves
                // them. Keeps 3DS seat-hold semantics identical to before.
                $this->discardHeldBooking($booking);

                return response()->json([
                    'data' => [
                        'requiresAction' => true,
                        'clientSecret' => $paymentIntent->client_secret,
                        'paymentIntentId' => $paymentIntent->id,
                    ],
                ]);
            }

            if ($paymentIntent->status !== 'succeeded') {
                $this->discardHeldBooking($booking);

                return $this->errorResponse([['field' => 'payment', 'message' => 'Payment could not be completed. Please try again.']], 402);
            }

            $stripePaymentIntentId = $paymentIntent->id;
        }

        // ── PHASE C — finalize in a short transaction ───────────────────────
        // Payment is captured. Flip Held → Confirmed, redeem the gift card under
        // a fresh lock + balance re-validation, consume the promo, award loyalty.
        // Any failure → compensating refund + discard the Held booking.
        try {
            DB::transaction(function () use (
                $booking, $resolvedFoodItems, $reservation, $giftCardAmount, $request, $stripePaymentIntentId,
            ) {
                $giftCard = null;
                if ($reservation['gift_card_id']) {
                    $giftCard = GiftCard::lockForUpdate()
                        ->where('status', GiftCardStatus::Active)
                        ->find($reservation['gift_card_id']);

                    if (! $giftCard || ($giftCardAmount > 0 && $giftCard->current_balance < $giftCardAmount)) {
                        throw new GiftCardBalanceChangedException;
                    }
                }

                $promo = null;
                if ($reservation['promo_code_id']) {
                    $promo = PromoCode::query()->find($reservation['promo_code_id']);
                    if ($promo === null) {
                        throw new PromoCodeNotConsumableException(PromoCodeNotConsumableException::REASON_NOT_FOUND);
                    }
                }

                $booking->status = BookingStatus::Confirmed;
                $booking->stripe_payment_intent_id = $stripePaymentIntentId;
                $booking->promo_code_id = $promo?->id;
                $booking->save();

                $this->finalizeBooking(
                    $booking,
                    $resolvedFoodItems,
                    $giftCard,
                    $giftCardAmount,
                    $promo,
                    $request->user()?->id,
                    $reservation['total'],
                    $this->promoRedemptionIdentity($booking->user_id, $booking->guest_email, $booking->id),
                );
            });
        } catch (GiftCardBalanceChangedException $e) {
            if ($stripePaymentIntentId) {
                $this->refundOrReport($stripePaymentIntentId);
            }
            $this->discardHeldBooking($booking);

            return $this->errorResponse([[
                'field' => 'giftCardCode',
                'message' => 'Gift card balance changed during payment. Please start over.',
            ]], 409);
        } catch (PromoCodeNotConsumableException $e) {
            if ($stripePaymentIntentId) {
                $this->refundOrReport($stripePaymentIntentId);
            }
            $this->discardHeldBooking($booking);

            return $this->errorResponse([[
                'field' => 'promoCode',
                'message' => 'This promo code is no longer redeemable. Please remove it and try again.',
            ]], 409);
        } catch (\Throwable $e) {
            if ($stripePaymentIntentId) {
                $this->refundOrReport($stripePaymentIntentId);
            }
            $this->discardHeldBooking($booking);

            throw $e;
        }

        $booking->load(self::BOOKING_RELATIONS);

        // Best-effort, outside any transaction: tie the Stripe dashboard row to
        // the human-readable confirmation code. Payment already succeeded.
        if ($stripePaymentIntentId) {
            $this->attachConfirmationCodeToStripe($stripePaymentIntentId, $booking);
        }

        return $this->successResponse(new BookingResource($booking), status: 201);
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

        // Email is the shared secret. Match it against the guest_email on guest
        // bookings OR the account email on member bookings (which carry a NULL
        // guest_email and so could never be found by the guest_email match alone).
        $email = $request->query('email');

        $booking = Booking::with(self::BOOKING_RELATIONS)
            ->where('confirmation_code', $request->query('confirmation_code'))
            ->where(function ($query) use ($email) {
                $query->where('guest_email', $email)
                    ->orWhereHas('user', fn ($u) => $u->where('email', $email));
            })
            ->first();

        if (! $booking) {
            return $this->errorResponse([['message' => 'Booking not found']], 404);
        }

        return $this->successResponse(new BookingResource($booking));
    }

    public function confirm(Location $location, Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => 'required|string|max:512',
        ]);

        $paymentIntentId = $request->input('paymentIntentId');
        $cacheKey = "pending_booking:{$paymentIntentId}";
        $pendingData = Cache::get($cacheKey);

        if (! $pendingData) {
            return $this->errorResponse([['message' => 'Session expired. Please start over.']], 410);
        }

        $giftCardAmount = $pendingData['gift_card_amount'] ?? 0;
        $originalCardAmount = $pendingData['card_amount'] ?? $pendingData['total'];

        // ── PHASE A — validate before charging (lock, no Stripe, no writes) ──
        // Bail out (no money captured) if the seats were taken or the gift card
        // dropped during the 3DS window. Pending state is left intact for retry.
        $check = DB::transaction(function () use ($location, $pendingData, $giftCardAmount) {
            $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->find($pendingData['showtime_id']);

            if (! $showtime || $showtime->start_time->isPast()) {
                return $this->errorResponse([['message' => 'This showtime has expired or does not exist.']], 410);
            }

            // Seat availability BEFORE Stripe — SeatConflictException propagates
            // to the global handler as a 409; the uncaptured PI expires on its own.
            $unavailable = $this->seatService->checkAvailability($showtime->id, $pendingData['seat_ids']);
            if (! empty($unavailable)) {
                throw new SeatConflictException($unavailable);
            }

            // Gift-card balance BEFORE Stripe — if it dropped during 3DS, bail
            // without charging the now-incorrect PaymentIntent amount.
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

            return null;
        });

        if ($check instanceof JsonResponse) {
            return $check; // pending state preserved for retry
        }

        // ── PHASE B — confirm the PaymentIntent with NO lock held ───────────
        try {
            $paymentIntent = $this->stripeService->confirmPaymentIntent($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse([['field' => 'payment', 'message' => 'Payment confirmation failed.']], 402);
            }
        } catch (CardException $e) {
            // Card-decline messages are intended for the cardholder — pass through.
            return $this->errorResponse([['field' => 'payment', 'message' => $e->getMessage()]], 402);
        } catch (InvalidRequestException $e) {
            report($e);

            return $this->errorResponse([['field' => 'payment', 'message' => 'We could not process your payment. Please try again or contact support.']], 400);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->errorResponse([['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
        }

        // ── PHASE C — finalize in a short transaction ───────────────────────
        // Payment captured. Create the booking + reserve seats + finalize under
        // a fresh lock; re-validate the gift card and rehydrate the promo. Any
        // failure → compensating refund (pending preserved for retry).
        try {
            $booking = DB::transaction(function () use ($location, $pendingData, $paymentIntentId, $giftCardAmount, $originalCardAmount): Booking {
                $showtime = Showtime::whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
                    ->whereNull('cancelled_at')
                    ->lockForUpdate()
                    ->findOrFail($pendingData['showtime_id']);

                $giftCard = null;
                if ($pendingData['gift_card_id'] ?? null) {
                    $giftCard = GiftCard::lockForUpdate()
                        ->where('status', GiftCardStatus::Active)
                        ->find($pendingData['gift_card_id']);

                    if (! $giftCard || ($giftCardAmount > 0 && $giftCard->current_balance < $giftCardAmount)) {
                        throw new GiftCardBalanceChangedException;
                    }
                }

                // Rehydrate the promo so a hard-delete during 3DS fails fast;
                // consume() revalidates deactivated/expired/limit-reached cases.
                $promo = null;
                if (! empty($pendingData['promo_code_id'])) {
                    $promo = PromoCode::query()->find($pendingData['promo_code_id']);
                    if ($promo === null) {
                        throw new PromoCodeNotConsumableException(PromoCodeNotConsumableException::REASON_NOT_FOUND);
                    }

                    // Re-derive the discount from the LIVE promo. The PaymentIntent
                    // was sized during store(), so an admin editing the promo amount
                    // mid-3DS-window cannot change what was captured — detect the
                    // drift and refund+409 rather than booking at the stale figure.
                    $expectedPromoDiscount = $this->promoCodeService->calculateDiscount($promo, $pendingData['subtotal']);
                    $cachedPromoDiscount = $pendingData['discount'] - $giftCardAmount;
                    if ($cachedPromoDiscount !== $expectedPromoDiscount) {
                        throw new PromoCodeNotConsumableException(PromoCodeNotConsumableException::REASON_AMOUNT_CHANGED);
                    }
                }

                $booking = new Booking;
                $booking->showtime_id = $pendingData['showtime_id'];
                $booking->user_id = $pendingData['user_id'];
                $booking->guest_email = $pendingData['guest_email'];
                $booking->status = BookingStatus::Confirmed;
                $booking->subtotal = $pendingData['subtotal'];
                $booking->discount = $pendingData['discount'];
                $booking->total = $pendingData['total'];
                $booking->payment_method = $this->determinePaymentMethod($originalCardAmount, $giftCardAmount);
                $booking->stripe_payment_intent_id = $paymentIntentId;
                $booking->idempotency_key = $pendingData['idempotency_key'] ?? null;
                $booking->promo_code_id = $promo?->id;
                $booking->save();

                $this->seatService->reserveSeats($showtime, $pendingData['seat_ids'], $booking);

                $this->finalizeBooking(
                    $booking,
                    $pendingData['food_items'],
                    $giftCard,
                    $giftCardAmount,
                    $promo,
                    $pendingData['user_id'],
                    $pendingData['total'],
                    $this->promoRedemptionIdentity($pendingData['user_id'], $pendingData['guest_email'], $booking->id),
                );

                return $booking;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent confirm() for this same PaymentIntent already created
            // the booking (unique stripe_payment_intent_id). Replay it — do NOT
            // refund: the charge backs a real, confirmed booking.
            $existing = Booking::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($existing) {
                $existing->load(self::BOOKING_RELATIONS);
                Cache::forget($cacheKey);

                return $this->successResponse(new BookingResource($existing), status: 201);
            }

            throw $e;
        } catch (GiftCardBalanceChangedException $e) {
            $this->refundOrReport($paymentIntentId);
            // The captured PI is now refunded — a retry against it would fail at
            // Stripe with a raw error. Drop the pending state so the customer
            // gets a clean "session expired, start over" (410) instead.
            Cache::forget($cacheKey);

            return $this->errorResponse([[
                'field' => 'giftCardCode',
                'message' => 'Gift card balance changed during payment and your card was refunded. Please start over.',
            ]], 409);
        } catch (PromoCodeNotConsumableException $e) {
            $this->refundOrReport($paymentIntentId);
            Cache::forget($cacheKey);

            return $this->errorResponse([[
                'field' => 'promoCode',
                'message' => 'This promo code is no longer redeemable and your card was refunded. Please start over.',
            ]], 409);
        } catch (\Throwable $e) {
            // Seat conflict (rare A→C window) or any other post-charge failure:
            // refund, drop the (now-dead) pending state, and re-throw
            // (SeatConflictException → global 409).
            $this->refundOrReport($paymentIntentId);
            Cache::forget($cacheKey);

            throw $e;
        }

        $booking->load(self::BOOKING_RELATIONS);

        // Best-effort, outside the transaction: patch the confirmation_code onto
        // the now-confirmed PaymentIntent.
        $this->attachConfirmationCodeToStripe($paymentIntentId, $booking);

        // Only forget the pending state on success — error paths above preserve
        // it so the customer can retry/restart.
        Cache::forget($cacheKey);

        return $this->successResponse(new BookingResource($booking), status: 201);
    }

    private function finalizeBooking(
        Booking $booking,
        array $foodItems,
        ?GiftCard $giftCard,
        int $giftCardAmount,
        ?PromoCode $promo,
        ?string $userId,
        int $total,
        ?PromoRedemptionIdentity $promoIdentity = null,
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
            // expired, hit its usage limit, or this customer hit their
            // per_user_limit between pre-check and now — the booking transaction
            // rolls back and the outer try/catch refunds the captured PaymentIntent.
            $this->promoCodeService->consume($promo, null, $promoIdentity);
        }

        /** @var User|null $user */
        $user = $booking->user;
        if ($user) {
            $this->loyaltyService->awardPointsForPurchase($user, $total);
        }
    }

    /**
     * Build the customer identity used to enforce a promo's per_user_limit.
     *
     * The two directions must be SYMMETRIC, or the cap is trivially bypassed:
     *  - Authenticated user: carry the user id AND the lowercased account email,
     *    so prior GUEST redemptions under the same email still count (a guest who
     *    later registers can't reset their cap).
     *  - Guest: carry the canonical guest email (already lowercased+trimmed by
     *    CreateBookingRequest) AND, if that email belongs to a registered
     *    account, that account's user id — so prior AUTHED redemptions (whose
     *    booking rows have a NULL guest_email) still count. Without this, a
     *    logged-in redeemer bypasses the cap simply by checking out as a guest.
     *
     * `$guestEmail` is ignored when `$userId` is supplied (the two are mutually
     * exclusive on a booking row).
     */
    private function promoRedemptionIdentity(?string $userId, ?string $guestEmail, ?string $excludeBookingId = null): PromoRedemptionIdentity
    {
        if ($userId !== null) {
            $accountEmail = User::find($userId)?->email;
            $email = $accountEmail !== null ? strtolower(trim($accountEmail)) : null;
        } else {
            $email = $guestEmail !== null ? strtolower(trim($guestEmail)) : null;
            if ($email !== null) {
                // May be null (pure guest email) — then only the guest_email match applies.
                $userId = User::whereRaw('lower(email) = ?', [$email])->value('id');
            }
        }

        return new PromoRedemptionIdentity(userId: $userId, guestEmail: $email, excludeBookingId: $excludeBookingId);
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

    /**
     * Release a Held booking and its seat reservations after a failed payment
     * or finalize. Deleting cascades to booking_seats (freeing the seats) and
     * frees the idempotency_key so a genuine retry can re-process. Best-effort:
     * a failure here must not mask the original error being handled.
     */
    private function discardHeldBooking(Booking $booking): void
    {
        try {
            $booking->delete();
        } catch (\Throwable $e) {
            report($e);
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
        // store() and confirm() both load the showtime with `with('movie')`,
        // so the relation is never null on this path.
        $movieTitle = $showtime->movie->title;
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
        // store() loads the showtime with `with('auditorium', 'movie')`, so
        // both relations are always present on this path.
        return [
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'location_slug' => $location->slug,
            'auditorium' => $showtime->auditorium->name,
            'movie_title' => $showtime->movie->title,
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
