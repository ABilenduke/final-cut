<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `WalkUpBookingService::create()` for invalid walk-up sales:
 * no seats picked, a non-POS payment method, or a showtime that has already
 * started. (Cancelled/unknown showtimes surface as ModelNotFoundException
 * from the locked lookup instead.)
 */
class WalkUpBookingException extends DomainException
{
    public const REASON_NO_SEATS = 'no_seats';

    public const REASON_NOT_POS_METHOD = 'not_pos_method';

    public const REASON_SHOWTIME_PAST = 'showtime_past';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NO_SEATS => 'Pick at least one seat for the walk-up sale.',
            self::REASON_NOT_POS_METHOD => 'Walk-up sales only accept point-of-sale payment methods (cash, comp, terminal card).',
            self::REASON_SHOWTIME_PAST => 'This showtime has already started.',
            default => 'Walk-up sale cannot be created.',
        });
    }
}
