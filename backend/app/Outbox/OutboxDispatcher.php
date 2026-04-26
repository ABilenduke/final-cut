<?php

namespace App\Outbox;

use App\Jobs\NotifyCustomerOfShowtimeCancellation;
use App\Jobs\NotifyFinanceOfGiftCardVoid;
use App\Models\DispatchOutbox;
use App\Services\GiftCardService;
use App\Services\ShowtimeService;
use InvalidArgumentException;

/**
 * Resolves a `dispatch_outbox.event_type` to a concrete job dispatch call.
 *
 * Adding a new event type:
 *   1. Define the event constant on the producing service (e.g.,
 *      `ShowtimeService::EVENT_CANCELLED`).
 *   2. Service writes a row to `dispatch_outbox` with that `event_type`
 *      inside its domain transaction.
 *   3. Add a `match` arm here mapping the event type to the queued job.
 *
 * The processor command (`outbox:dispatch`) calls `dispatch()` for each
 * unprocessed row. An `InvalidArgumentException` from `dispatch()` is the
 * processor's signal that the event_type is unknown — it parks the row
 * with `failed_at` set so a human can investigate without it cycling
 * through retries.
 */
class OutboxDispatcher
{
    /**
     * Dispatch the queued job for a single outbox row.
     *
     * @throws InvalidArgumentException if the row's `event_type` has no mapping.
     */
    public function dispatch(DispatchOutbox $row): void
    {
        $payload = $row->payload ?? [];

        match ($row->event_type) {
            ShowtimeService::EVENT_CANCELLED => NotifyCustomerOfShowtimeCancellation::dispatch(
                (string) $payload['booking_id'],
            ),
            GiftCardService::EVENT_VOIDED => NotifyFinanceOfGiftCardVoid::dispatch(
                (string) $payload['gift_card_id'],
                (string) $payload['reason'],
                (int) $payload['balance_voided'],
                isset($payload['voided_by_admin_user_id']) ? (int) $payload['voided_by_admin_user_id'] : null,
            ),
            default => throw new InvalidArgumentException(
                "Unknown outbox event_type: {$row->event_type}",
            ),
        };
    }
}
