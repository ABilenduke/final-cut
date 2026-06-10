<?php

namespace App\Outbox;

use App\Jobs\NotifyCustomerOfShowtimeCancellation;
use App\Jobs\NotifyFinanceOfGiftCardVoid;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingRefundConfirmation;
use App\Jobs\SendGiftCardDelivery;
use App\Models\DispatchOutbox;
use App\Services\BookingNotificationService;
use App\Services\BookingRefundService;
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
 *   3. Add a `match` arm in `dispatch()` that delegates to a private
 *      method validating the payload's required keys before dispatching
 *      the queued job.
 *
 * The processor command (`outbox:dispatch`) treats an
 * `InvalidArgumentException` from `dispatch()` as a developer error —
 * unknown event_type OR a malformed payload — and parks the row
 * immediately with `failed_at` set so it doesn't cycle through retries.
 */
class OutboxDispatcher
{
    /**
     * Dispatch the queued job for a single outbox row.
     *
     * @throws InvalidArgumentException if the row's `event_type` has no
     *                                  mapping or a required payload key
     *                                  is missing.
     */
    public function dispatch(DispatchOutbox $row): void
    {
        $payload = $row->payload ?? [];

        match ($row->event_type) {
            ShowtimeService::EVENT_CANCELLED => $this->dispatchShowtimeCancelled($row, $payload),
            GiftCardService::EVENT_VOIDED => $this->dispatchGiftCardVoided($row, $payload),
            GiftCardService::EVENT_DELIVERY => $this->dispatchGiftCardDelivery($row, $payload),
            BookingRefundService::EVENT_REFUNDED => $this->dispatchBookingRefunded($row, $payload),
            BookingNotificationService::EVENT_CONFIRMATION_RESEND => $this->dispatchBookingConfirmationResend($row, $payload),
            default => throw new InvalidArgumentException(
                "Unknown outbox event_type: {$row->event_type}",
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchBookingRefunded(DispatchOutbox $row, array $payload): void
    {
        SendBookingRefundConfirmation::dispatch(
            (string) $this->requirePayloadValue($row, $payload, 'booking_id'),
            (int) $this->requirePayloadValue($row, $payload, 'card_refund'),
            (int) $this->requirePayloadValue($row, $payload, 'gift_restored'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchBookingConfirmationResend(DispatchOutbox $row, array $payload): void
    {
        SendBookingConfirmation::dispatch(
            (string) $this->requirePayloadValue($row, $payload, 'booking_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchShowtimeCancelled(DispatchOutbox $row, array $payload): void
    {
        NotifyCustomerOfShowtimeCancellation::dispatch(
            (string) $this->requirePayloadValue($row, $payload, 'booking_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchGiftCardDelivery(DispatchOutbox $row, array $payload): void
    {
        SendGiftCardDelivery::dispatch(
            (string) $this->requirePayloadValue($row, $payload, 'gift_card_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchGiftCardVoided(DispatchOutbox $row, array $payload): void
    {
        NotifyFinanceOfGiftCardVoid::dispatch(
            (string) $this->requirePayloadValue($row, $payload, 'gift_card_id'),
            (string) $this->requirePayloadValue($row, $payload, 'reason'),
            (int) $this->requirePayloadValue($row, $payload, 'balance_voided'),
            // Nullable: a customer-originated void wouldn't have an actor.
            array_key_exists('voided_by_admin_user_id', $payload) && $payload['voided_by_admin_user_id'] !== null
                ? (string) $payload['voided_by_admin_user_id']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requirePayloadValue(DispatchOutbox $row, array $payload, string $key): mixed
    {
        if (! array_key_exists($key, $payload)) {
            throw new InvalidArgumentException(
                "Malformed outbox payload for event_type {$row->event_type}: missing required key [{$key}]",
            );
        }

        return $payload[$key];
    }
}
