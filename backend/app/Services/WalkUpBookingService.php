<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\SeatConflictException;
use App\Exceptions\WalkUpBookingException;
use App\Models\Booking;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Box-office walk-up sales (admin-v2 Plan 05): a staff member sells seats at
 * the counter, payment is collected physically (cash / comp / terminal card)
 * — never through Stripe, so unlike the customer checkout there is no Held
 * phase: the showtime lock is held for the whole (short) transaction and the
 * booking is created straight to Confirmed.
 *
 * `SeatAvailabilityService::reserveSeats` owns seat validation, section-
 * multiplier pricing, and the occupancy-guard TOCTOU translation
 * (`SeatConflictException`) — a conflict rolls the whole transaction back,
 * leaving no orphan booking.
 *
 * v1 scope: seats only. No food items, no promo codes, no gift cards, no
 * Stripe Terminal — those stay in the customer checkout.
 */
class WalkUpBookingService
{
    use LogsAdminActivity;

    public const EVENT_CREATED = 'booking.walkup_created';

    public function __construct(
        private readonly SeatAvailabilityService $seatService,
    ) {}

    /**
     * @param  list<string>  $seatIds
     *
     * @throws WalkUpBookingException When no seats, a non-POS method, or a started showtime.
     * @throws SeatConflictException When any seat is taken or unavailable.
     * @throws ModelNotFoundException When the showtime is unknown or cancelled.
     * @throws ValidationException When a seat belongs to another auditorium.
     */
    public function create(
        string $showtimeId,
        array $seatIds,
        PaymentMethod $paymentMethod,
        ?string $guestEmail,
        User $actor,
        ?string $notes = null,
    ): Booking {
        if ($seatIds === []) {
            throw new WalkUpBookingException(WalkUpBookingException::REASON_NO_SEATS);
        }

        if (! in_array($paymentMethod, PaymentMethod::posMethods(), true)) {
            throw new WalkUpBookingException(WalkUpBookingException::REASON_NOT_POS_METHOD);
        }

        return DB::transaction(function () use ($showtimeId, $seatIds, $paymentMethod, $guestEmail, $actor, $notes): Booking {
            // Same lock the customer checkout takes in its Phase A — walk-up
            // and online sales serialize on the showtime row.
            $showtime = Showtime::query()
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->findOrFail($showtimeId);

            if ($showtime->start_time->isPast()) {
                throw new WalkUpBookingException(WalkUpBookingException::REASON_SHOWTIME_PAST);
            }

            $booking = new Booking;
            $booking->showtime_id = $showtime->id;
            $booking->user_id = null;
            $booking->guest_email = $guestEmail;
            $booking->status = BookingStatus::Confirmed;
            $booking->subtotal = 0;
            $booking->discount = 0;
            $booking->total = 0;
            $booking->payment_method = $paymentMethod;
            $booking->notes = $notes;
            $booking->save();

            $seatTotal = $this->seatService->reserveSeats($showtime, $seatIds, $booking);

            // Comp: the value is recorded as discount and nothing is charged,
            // so revenue KPIs (which sum `total`) stay honest.
            $comped = $paymentMethod === PaymentMethod::Comp;
            $booking->subtotal = $seatTotal;
            $booking->discount = $comped ? $seatTotal : 0;
            $booking->total = $comped ? 0 : $seatTotal;
            $booking->save();

            $this->logIfAdmin(self::EVENT_CREATED, $booking, $actor, [
                'confirmation_code' => $booking->confirmation_code,
                'seat_ids' => $seatIds,
                'payment_method' => $paymentMethod->value,
                'subtotal' => $booking->subtotal,
                'total' => $booking->total,
            ]);

            return $booking;
        });
    }
}
