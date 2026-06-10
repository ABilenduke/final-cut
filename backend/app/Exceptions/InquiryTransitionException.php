<?php

namespace App\Exceptions;

use App\Enums\InquiryStatus;
use DomainException;

/**
 * Thrown by `RentalInquiryService::transition()` for moves outside the
 * allowed transition map (e.g. re-opening a confirmed/declined inquiry).
 */
class InquiryTransitionException extends DomainException
{
    public function __construct(
        public readonly InquiryStatus $from,
        public readonly InquiryStatus $to,
    ) {
        parent::__construct("Inquiry cannot move from {$from->value} to {$to->value}.");
    }
}
