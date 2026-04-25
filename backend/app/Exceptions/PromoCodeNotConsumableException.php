<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `PromoCodeService::consume()` when, after re-reading the row
 * under `lockForUpdate`, the code is no longer redeemable.
 *
 * The validate-then-increment split was racy: two confirmations could both
 * pass `validateCode()` against `usage_limit = N, uses_count = N - 1` and
 * then serialise through `incrementUsage()` to `uses_count = N + 1`. The
 * same window also lets a code that was deactivated, expired, or deleted
 * between validation and finalisation still consume a use. `consume()`
 * closes the gap by re-validating the locked row before incrementing; if
 * the row no longer satisfies the invariants this exception is raised so
 * the booking transaction rolls back and the customer-path refund kicks in.
 */
class PromoCodeNotConsumableException extends DomainException
{
    public const REASON_NOT_FOUND = 'not_found';

    public const REASON_INACTIVE = 'inactive';

    public const REASON_EXPIRED = 'expired';

    public const REASON_LIMIT_REACHED = 'limit_reached';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NOT_FOUND => 'Promo code no longer exists.',
            self::REASON_INACTIVE => 'Promo code is no longer active.',
            self::REASON_EXPIRED => 'Promo code has expired.',
            self::REASON_LIMIT_REACHED => 'Promo code has reached its usage limit.',
            default => 'Promo code cannot be redeemed.',
        });
    }
}
