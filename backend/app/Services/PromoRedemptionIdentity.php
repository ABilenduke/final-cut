<?php

namespace App\Services;

/**
 * Customer identity for per-user promo enforcement — distinct from the admin
 * `$actor` (who is performing an admin write). Carries the authenticated user
 * id and/or a canonicalized guest email so PromoCodeService can count a single
 * customer's prior redemptions of a code.
 *
 * `guestEmail` must already be lowercased+trimmed by the caller
 * (CreateBookingRequest canonicalizes it); the count matches case-insensitively
 * as a second line of defence. For an authenticated user, pass BOTH the user id
 * AND the lowercased account email so prior GUEST redemptions under the same
 * email still count (a guest who later registers can't reset their cap).
 */
final readonly class PromoRedemptionIdentity
{
    public function __construct(
        public ?string $userId = null,
        public ?string $guestEmail = null,
        public ?string $excludeBookingId = null,
    ) {}

    /** No identity to key on — per-user enforcement is skipped (only the global usage_limit applies). */
    public function isEmpty(): bool
    {
        return $this->userId === null && $this->guestEmail === null;
    }
}
