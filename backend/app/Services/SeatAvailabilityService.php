<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Exceptions\SeatConflictException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Validation\ValidationException;

class SeatAvailabilityService
{
    /**
     * Return the subset of the given seat IDs that are already taken
     * by a confirmed booking on this showtime.
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

        return BookingSeat::where('showtime_id', $showtimeId)
            ->whereIn('seat_id', $seatIds)
            ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Confirmed))
            ->pluck('seat_id')
            ->all();
    }

    /**
     * Reserve seats for a booking within the showtime's auditorium.
     *
     * Precondition: the caller must hold a lockForUpdate on the Showtime row
     * before calling this method to prevent concurrent double-bookings.
     *
     * @param  string[]  $seatIds
     * @return int Total cost of all reserved seats in cents.
     *
     * @throws ValidationException When any seat does not belong to the showtime's auditorium.
     * @throws SeatConflictException When any seat is already taken by a confirmed booking.
     */
    public function reserveSeats(Showtime $showtime, array $seatIds, Booking $booking): int
    {
        $seatIds = array_values(array_unique($seatIds));

        $seats = Seat::whereIn('id', $seatIds)
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

            $price = match ($seat->type) {
                SeatType::Standard => $showtime->price_standard,
                SeatType::Premium => $showtime->price_premium,
                SeatType::Accessible => $showtime->price_accessible,
            };

            BookingSeat::create([
                'booking_id' => $booking->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seatId,
                'section' => $seat->type->value,
                'price' => $price,
            ]);

            $total += $price;
        }

        return $total;
    }
}
