<?php

namespace App\Exceptions;

use DomainException;

class MovieHasBookingsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cannot delete a movie with existing bookings. Cancel or refund the affected bookings first.');
    }
}
