<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when deleting an auditorium would cascade-destroy bookings still
 * carrying Stripe payment-intent IDs, confirmation codes, or historical
 * `booking_seats.section`/`price` snapshots needed for refunds and audit.
 *
 * The FK chain is `cascadeOnDelete` end-to-end (auditoriums → showtimes →
 * bookings → booking_seats), so without this guard `$auditorium->delete()`
 * silently wipes historical bookings.
 */
class AuditoriumHasBookingsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot delete an auditorium with existing bookings. Cancel or refund the affected bookings first.'
        );
    }
}
