<?php

namespace App\Exceptions;

use App\Models\Showtime;
use DomainException;

/**
 * Thrown when `ShowtimeService::update` would move a sold showtime (change
 * `movie_id`, `auditorium_id`, or `start_time`) while bookings still point at
 * its current seat layout. Allowing the change would orphan `booking_seats`
 * rows on a seat layout the showtime no longer uses, so the admin has to
 * cancel the showtime first (which flags + refunds bookings) and rebuild.
 * Pricing-only edits are still allowed.
 */
class ShowtimeHasBookingsException extends DomainException
{
    public function __construct(
        public readonly Showtime $showtime,
        public readonly int $bookingCount,
    ) {
        parent::__construct(sprintf(
            'Cannot change this showtime\'s movie, auditorium, or start time — %d booking(s) are tied to the current layout. Cancel the showtime (which flags those bookings for refund) and schedule a replacement instead.',
            $bookingCount,
        ));
    }
}
