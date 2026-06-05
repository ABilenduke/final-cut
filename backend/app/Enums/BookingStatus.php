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
     * DB COUPLING: this list is mirrored by the SQL helper `fc_status_occupies()`
     * in migration 2026_06_05_000000_add_booking_seats_occupancy_guard, which
     * drives the `booking_seats.occupies_seat` flag behind the
     * `booking_seats_one_occupant_per_seat` partial unique index. The two lists
     * MUST change together — a parity test (SeatOccupancyTriggerTest) fails CI
     * on drift.
     *
     * @return list<self>
     */
    public static function occupyingStatuses(): array
    {
        return [self::Confirmed, self::Held, self::RefundPending];
    }
}
