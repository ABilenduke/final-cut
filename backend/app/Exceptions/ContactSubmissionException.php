<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `ContactSubmissionService::markHandled()` on a double-handle:
 * two operators racing get a clean failure instead of silently re-stamping
 * each other's attribution.
 */
class ContactSubmissionException extends DomainException
{
    public const REASON_ALREADY_HANDLED = 'already_handled';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_ALREADY_HANDLED => 'This submission was already marked handled.',
            default => 'Contact submission operation refused.',
        });
    }
}
