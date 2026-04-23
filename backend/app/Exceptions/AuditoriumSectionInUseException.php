<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when attempting to delete an `AuditoriumSection` that still has
 * seats referencing it. The cascade contract (documented on
 * `AuditoriumService`) says section deletion does not touch seats;
 * reassign seats first via `updateSeatBatch` or a full `generateSeats`.
 */
class AuditoriumSectionInUseException extends DomainException
{
    public function __construct(public readonly string $sectionName, public readonly int $seatCount)
    {
        parent::__construct(
            "Cannot delete section '{$sectionName}' — {$seatCount} seat"
            .($seatCount === 1 ? ' still references' : 's still reference')
            .' it. Reassign those seats to a different section first.'
        );
    }
}
