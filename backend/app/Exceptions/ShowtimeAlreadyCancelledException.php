<?php

namespace App\Exceptions;

use App\Models\Showtime;
use DomainException;

/**
 * Thrown when `ShowtimeService::cancel` is called on a showtime whose
 * `cancelled_at` is already non-null. The pessimistic lock + re-read inside
 * the service ensures two concurrent cancels do not both flag bookings; the
 * second caller receives this exception, and the UI can render it as
 * "Already cancelled by {actor} at {time}" after refreshing the record.
 */
class ShowtimeAlreadyCancelledException extends DomainException
{
    public function __construct(public readonly Showtime $showtime)
    {
        parent::__construct(sprintf(
            'Showtime %s was already cancelled on %s.',
            $showtime->id,
            $showtime->cancelled_at?->toIso8601String() ?? 'an earlier pass',
        ));
    }
}
