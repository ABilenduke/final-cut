<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when deleting a location would cascade through its auditoriums and
 * showtimes to destroy bookings still carrying payment-intent IDs and audit
 * trail. Matches `AuditoriumHasBookingsException` (same rationale, deeper
 * cascade).
 */
class LocationHasBookingsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot delete a location with existing bookings. Cancel or refund the affected bookings first.'
        );
    }
}
