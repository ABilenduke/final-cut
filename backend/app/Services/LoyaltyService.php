<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\User;

class LoyaltyService
{
    /**
     * Get the user's current loyalty points.
     */
    public function getPoints(User $user): int
    {
        return $user->loyalty_points;
    }

    /**
     * Get the user's loyalty tier as a string.
     */
    public function getTier(User $user): string
    {
        return $user->loyalty_tier->value;
    }

    /**
     * Award loyalty points based on purchase total (1 point per dollar).
     *
     * @param  int  $totalCents  The purchase total in cents.
     * @return int The user's new total points.
     */
    public function awardPointsForPurchase(User $user, int $totalCents): int
    {
        $points = (int) floor($totalCents / 100);

        if ($points > 0) {
            $user->increment('loyalty_points', $points);
            $user->refresh();
        }

        return $user->loyalty_points;
    }

    /**
     * Get the user's loyalty points history from confirmed bookings.
     *
     * @return array<int, array{description: string, points: int, date: string, bookingId: string}>
     */
    public function getHistory(User $user): array
    {
        $bookings = $user->bookings()
            ->with('showtime.movie')
            ->where('status', BookingStatus::Confirmed)
            ->latest()
            ->get();

        return $bookings->map(fn ($booking) => [
            'description' => "Booking for {$booking->showtime->movie->title}",
            'points' => (int) floor($booking->total / 100),
            'date' => $booking->created_at->toIso8601String(),
            'bookingId' => $booking->id,
        ])->all();
    }
}
