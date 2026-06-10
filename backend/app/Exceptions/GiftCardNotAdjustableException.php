<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when `GiftCardService::adjust()` is refused: zero delta, a deduction
 * larger than the remaining balance, or a card whose status is terminal
 * (voided/expired). Like void, adjustments fail loudly under races — two
 * admins adjusting concurrently must not both see a success toast for a
 * single applied mutation.
 */
class GiftCardNotAdjustableException extends DomainException
{
    public const REASON_ZERO_DELTA = 'zero_delta';

    public const REASON_OVERDRAW = 'overdraw';

    public const REASON_VOIDED = 'voided';

    public const REASON_EXPIRED = 'expired';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_ZERO_DELTA => 'Adjustment amount must be non-zero.',
            self::REASON_OVERDRAW => 'Adjustment would take the balance below zero.',
            self::REASON_VOIDED => 'Voided gift cards cannot be adjusted.',
            self::REASON_EXPIRED => 'Expired gift cards cannot be adjusted.',
            default => 'Gift card cannot be adjusted.',
        });
    }
}
