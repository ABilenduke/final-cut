<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by AuditoriumService when a section's price_multiplier is not strictly
 * positive. A 0.00 multiplier yields free seats (the booking charges nothing);
 * a negative multiplier yields a negative line price and a rejected Stripe
 * amount. The Filament form's minValue is a UI hint only — this is the domain
 * invariant, backed by a DB CHECK constraint as defense in depth.
 */
class InvalidPriceMultiplierException extends DomainException
{
    public function __construct(
        public readonly string $sectionName,
        public readonly float|string $value,
    ) {
        parent::__construct(sprintf(
            'Section [%s] price multiplier must be greater than 0; got [%s].',
            $sectionName,
            (string) $value,
        ));
    }
}
