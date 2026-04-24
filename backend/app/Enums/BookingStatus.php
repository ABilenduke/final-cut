<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Held = 'held';
    case RefundPending = 'refund_pending';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Statuses that still occupy a seat from the house's perspective — anything
     * in this list blocks auditorium seat regeneration and (eventually) seat
     * availability lookups. `Held` and `RefundPending` are not yet produced by
     * the customer checkout flow; they exist so the admin-side regeneration
     * safety check can refuse honestly when the mechanisms that create them
     * land in a later plan.
     *
     * @return list<self>
     */
    public static function occupyingStatuses(): array
    {
        return [self::Confirmed, self::Held, self::RefundPending];
    }
}
