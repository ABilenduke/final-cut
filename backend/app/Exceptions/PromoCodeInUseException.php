<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when `PromoCodeService::delete()` is called on a code whose
 * `uses_count > 0`. Used codes are operational history — deactivating is
 * the correct path; deleting would orphan audit references on past bookings.
 */
class PromoCodeInUseException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot delete a promo code that has been used. Deactivate it instead to preserve historical records.'
        );
    }
}
