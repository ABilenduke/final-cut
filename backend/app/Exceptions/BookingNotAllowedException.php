<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from inside the booking Phase A transaction to bail out AFTER the
 * provisional Held booking / seat rows were written, forcing DB::transaction
 * to roll them back (a plain `return` from the closure would COMMIT them and
 * leak the seat reservation). The controller catches it and shapes the HTTP
 * error from the carried field/status.
 */
class BookingNotAllowedException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        public readonly string $reason,
        public readonly int $status,
    ) {
        parent::__construct($reason);
    }
}
