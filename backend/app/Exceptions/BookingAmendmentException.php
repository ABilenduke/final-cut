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

    public const REASON_NOT_REASSIGNABLE = 'not_reassignable';

    public const REASON_SEAT_PRICE_MISMATCH = 'seat_price_mismatch';

    public const REASON_NO_SEATS = 'no_seats';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NOT_GUEST_BOOKING => 'Guest email can only be corrected on guest bookings; this booking belongs to a registered account.',
            self::REASON_NOT_REASSIGNABLE => 'Seats can only be reassigned on confirmed or held bookings.',
            self::REASON_SEAT_PRICE_MISMATCH => 'The new seats must cost exactly what the current seats cost. A price change requires a refund and rebooking.',
            self::REASON_NO_SEATS => 'At least one seat is required to reassign a booking.',
            default => 'Booking cannot be amended.',
        });
    }
}
