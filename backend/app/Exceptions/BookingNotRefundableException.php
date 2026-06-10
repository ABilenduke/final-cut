<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when `BookingRefundService::refund()` is called on a booking that is
 * not in a refundable state: already refunded/cancelled, or claimed by a
 * concurrent refund run that is still in flight.
 *
 * Refunds are intentionally NOT idempotent at the call level — a second
 * concurrent attempt fails loudly so two admins racing do not both see a
 * success toast. (A crashed run IS resumable: a claim older than the stale
 * threshold, or one that already carries a `stripe_refund_id`, may be retaken.)
 */
class BookingNotRefundableException extends DomainException
{
    public const REASON_ALREADY_REFUNDED = 'already_refunded';

    public const REASON_ALREADY_CANCELLED = 'already_cancelled';

    public const REASON_IN_PROGRESS = 'in_progress';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_ALREADY_REFUNDED => 'Booking has already been refunded.',
            self::REASON_ALREADY_CANCELLED => 'Booking has already been cancelled.',
            self::REASON_IN_PROGRESS => 'A refund for this booking is already in progress.',
            default => 'Booking cannot be refunded.',
        });
    }
}
