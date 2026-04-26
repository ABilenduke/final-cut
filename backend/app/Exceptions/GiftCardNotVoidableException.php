<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when `GiftCardService::void()` is called on a gift card whose status
 * is not `Active` (i.e., already voided, depleted to zero balance, or expired).
 *
 * Void is intentionally NOT idempotent — a second concurrent void call on the
 * same card must fail loudly so two admins racing do not both see a success
 * toast while only one transaction actually ran.
 */
class GiftCardNotVoidableException extends DomainException
{
    public const REASON_ALREADY_VOIDED = 'already_voided';

    public const REASON_DEPLETED = 'depleted';

    public const REASON_EXPIRED = 'expired';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_ALREADY_VOIDED => 'Gift card has already been voided.',
            self::REASON_DEPLETED => 'Gift card has a zero balance and cannot be voided.',
            self::REASON_EXPIRED => 'Gift card is expired and cannot be voided.',
            default => 'Gift card cannot be voided.',
        });
    }
}
