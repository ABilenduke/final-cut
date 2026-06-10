<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `BookingFlagService` when a flag transition is invalid: flagging
 * an already-flagged booking, unflagging an unflagged one, or attempting to
 * use the reserved `showtime_cancelled:` reason prefix (owned by
 * `ShowtimeService::cancel` — manual flags must never leak into the
 * cancellation follow-up queue's scope).
 */
class BookingFlagException extends DomainException
{
    public const REASON_ALREADY_FLAGGED = 'already_flagged';

    public const REASON_NOT_FLAGGED = 'not_flagged';

    public const REASON_RESERVED_PREFIX = 'reserved_prefix';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_ALREADY_FLAGGED => 'Booking is already flagged.',
            self::REASON_NOT_FLAGGED => 'Booking is not flagged.',
            self::REASON_RESERVED_PREFIX => "Flag reason uses the reserved 'showtime_cancelled:' prefix.",
            default => 'Booking flag state cannot be changed.',
        });
    }
}
