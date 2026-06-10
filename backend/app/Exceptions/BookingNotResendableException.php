<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when `BookingNotificationService::resendConfirmation()` is called on
 * a booking whose confirmation cannot be re-sent: not in Confirmed status, or
 * carrying no recipient email at all. Validated up front so the admin UI gets
 * immediate feedback instead of queueing a job that would silently no-op.
 */
class BookingNotResendableException extends DomainException
{
    public const REASON_NOT_CONFIRMED = 'not_confirmed';

    public const REASON_NO_RECIPIENT = 'no_recipient';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NOT_CONFIRMED => 'Only confirmed bookings can have their confirmation re-sent.',
            self::REASON_NO_RECIPIENT => 'Booking has no recipient email address.',
            default => 'Booking confirmation cannot be re-sent.',
        });
    }
}
