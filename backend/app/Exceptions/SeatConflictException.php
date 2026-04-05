<?php

namespace App\Exceptions;

use RuntimeException;

class SeatConflictException extends RuntimeException
{
    public function __construct(public readonly array $unavailableSeatIds)
    {
        parent::__construct('One or more seats are no longer available.');
    }
}
