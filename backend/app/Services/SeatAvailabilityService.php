<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Exceptions\SeatConflictException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatAvailabilityService
{
    /**
     * Return the subset of the given seat IDs that are unavailable for
     * reservation on this showtime. A seat is unavailable if any of:
     *   - it has an occupying booking (Confirmed / Held / RefundPending),
     *   - it is flagged out-of-service by admin (`seats.unavailable_at` set),
     *   - it does not exist / does not belong to this showtime's auditorium.
     *
     * The last case matters for the 3DS confirm window: if the layout was
     * regenerated between `store()` and `confirm()`, missing seat IDs
     * should be reported here so the confirm path can bail BEFORE the
     * payment is captured, rather than blowing up inside `reserveSeats`
     * and triggering a compensating refund.
     *
     * An empty array means all requested seats are available.
     *
     * @param  string[]  $seatIds
     * @return string[]
     */
    public function checkAvailability(string $showtimeId, array $seatIds): array
    {
        if (empty($seatIds)) {
            return [];
        }

        $auditoriumId = DB::table('showtimes')
            ->where('id', $showtimeId)
            ->value('auditorium_id');

        $validSeatIds = $auditoriumId === null
            ? []
            : Seat::whereIn('id', $seatIds)
                ->where('auditorium_id', $auditoriumId)
                ->pluck('id')
                ->all();

        $missingOrForeign = array_values(array_diff($seatIds, $validSeatIds));

        $occupied = BookingSeat::where('showtime_id', $showtimeId)
            ->whereIn('seat_id', $seatIds)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', BookingStatus::occupyingStatuses()))
            ->pluck('seat_id')
            ->all();

        $adminUnavailable = Seat::whereIn('id', $seatIds)
            ->whereNotNull('unavailable_at')
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($missingOrForeign, $occupied, $adminUnavailable)));
    }

    /**
     * Reserve seats for a booking within the showtime's auditorium.
     *
     * Precondition: the caller must hold a lockForUpdate on the Showtime row
     * before calling this method to prevent concurrent double-bookings.
     *
     * Each reserved seat is priced as `showtime.price_for(seat.type) × section.price_multiplier`
     * when the seat is assigned to an `AuditoriumSection`; otherwise the
     * multiplier defaults to 1.0. The `booking_seats.section` snapshot stores
     * the admin-defined section name when present (falling back to the seat's
     * SeatType for un-sectioned seats) so refund/audit payloads reflect what
     * the customer was actually charged.
     *
     * @param  string[]  $seatIds
     * @return int Total cost of all reserved seats in cents.
     *
     * @throws ValidationException When any seat does not belong to the showtime's auditorium.
     * @throws SeatConflictException When any seat is already taken or flagged unavailable.
     */
    public function reserveSeats(Showtime $showtime, array $seatIds, Booking $booking): int
    {
        $seatIds = array_values(array_unique($seatIds));

        $seats = Seat::with('section')
            ->whereIn('id', $seatIds)
            ->where('auditorium_id', $showtime->auditorium_id)
            ->get()
            ->keyBy('id');

        $foreignSeatIds = array_values(array_diff($seatIds, $seats->keys()->all()));

        if (! empty($foreignSeatIds)) {
            throw ValidationException::withMessages([
                'seatIds' => 'One or more seats do not belong to this showtime\'s auditorium.',
            ]);
        }

        $unavailableSeatIds = $this->checkAvailability($showtime->id, $seatIds);

        if (! empty($unavailableSeatIds)) {
            throw new SeatConflictException($unavailableSeatIds);
        }

        $total = 0;

        foreach ($seatIds as $seatId) {
            $seat = $seats->get($seatId);

            $price = self::priceForSeat($showtime, $seat);

            BookingSeat::create([
                'booking_id' => $booking->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seatId,
                'section' => $seat->section !== null ? $seat->section->name : $seat->type->value,
                'price' => $price,
            ]);

            $total += $price;
        }

        return $total;
    }

    /**
     * Final charged price for a seat at a given showtime: the showtime's
     * type-based base price multiplied by the seat's section multiplier when
     * one is assigned. Shared by reserveSeats (charge/snapshot) and
     * ShowtimeController (seat-map display) so customer-visible and
     * persisted prices stay in sync.
     */
    public static function priceForSeat(Showtime $showtime, Seat $seat): int
    {
        $base = match ($seat->type) {
            SeatType::Standard => $showtime->price_standard,
            SeatType::Premium => $showtime->price_premium,
            SeatType::Accessible => $showtime->price_accessible,
        };

        $multiplier = $seat->section?->price_multiplier;

        if ($multiplier === null) {
            return (int) $base;
        }

        return (int) round(((int) $base) * (float) $multiplier);
    }
}
