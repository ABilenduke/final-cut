<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `OutboxRetryService::retry()` when the row is not parked.
 * Pending rows are already the worker's job; processed rows are done —
 * only `failed_at IS NOT NULL` rows are operator-retryable.
 */
class OutboxRetryException extends DomainException
{
    public const REASON_NOT_PARKED = 'not_parked';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_NOT_PARKED => 'Only parked outbox rows (failed) can be retried.',
            default => 'Outbox row cannot be retried.',
        });
    }
}
