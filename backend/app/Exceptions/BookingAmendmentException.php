<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `BookingAmendmentService` for invalid record corrections — chiefly
 * attempting to set a guest email on a booking that belongs to a registered
 * user (whose contact email lives on the `User`, not the booking).
 */
class BookingAmendmentException extends DomainException
{
    public const REASON_NOT_GUEST_BOOKING = 'not_guest_booking';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NOT_GUEST_BOOKING => 'Guest email can only be corrected on guest bookings; this booking belongs to a registered account.',
            default => 'Booking cannot be amended.',
        });
    }
}
