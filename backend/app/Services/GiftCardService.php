<?php

namespace App\Services;

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotVoidableException;
use App\Mail\GiftCardVoidedMail;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Gift card domain service: customer purchase/redemption + admin void.
 *
 * Every write path opens a DB transaction, mutates the gift card row, writes
 * one `gift_card_ledger_entries` row in the same transaction, and emits an
 * `activity_log` entry when `$actor !== null`. The ledger is append-only —
 * balance state can always be reconstructed by replaying entries.
 *
 * Customer API callers pass `$actor = null`; only Filament actions pass a
 * non-null actor, so admin attribution is opt-in by caller.
 *
 * The controller still owns Stripe orchestration and idempotency-cache
 * handling (those are HTTP transport concerns, not domain).
 */
class GiftCardService
{
    use LogsAdminActivity;

    public const EVENT_PURCHASED = 'gift_card.purchased';

    public const EVENT_REDEEMED = 'gift_card.redeemed';

    public const EVENT_VOIDED = 'gift_card.voided';

    /**
     * Atomically create a gift card row + its opening ledger entry.
     *
     * `$attributes` MUST contain at minimum: `code`, `initial_balance`,
     * `current_balance`, `recipient_email`, `recipient_name`, `sender_name`,
     * `status`, `purchased_at`. Optional: `message`, `stripe_payment_intent_id`,
     * `idempotency_key`, `payload_hash`.
     *
     * On unique-constraint collision (duplicate code / idempotency_key / PI),
     * `UniqueConstraintViolationException` propagates — the customer API
     * caller catches it to run its own race/retry logic.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function purchase(array $attributes, ?AdminUser $actor = null): GiftCard
    {
        return DB::transaction(function () use ($attributes, $actor): GiftCard {
            /** @var GiftCard $giftCard */
            $giftCard = GiftCard::create($attributes);

            GiftCardLedgerEntry::create([
                'gift_card_id' => $giftCard->id,
                'type' => GiftCardLedgerType::Purchase,
                'amount_cents' => $giftCard->initial_balance,
                'balance_after_cents' => $giftCard->current_balance,
                'booking_id' => null,
                'admin_user_id' => $actor?->id,
                'reason' => null,
                'created_at' => now(),
            ]);

            $this->logIfAdmin(self::EVENT_PURCHASED, $giftCard, $actor, [
                'code' => $giftCard->code,
                'initial_balance' => $giftCard->initial_balance,
            ]);

            return $giftCard;
        });
    }

    /**
     * Redeem a portion (or all) of a gift card's balance against a booking.
     *
     * Caller must have already acquired a row lock on `$giftCard`
     * (`GiftCard::whereKey($id)->lockForUpdate()->firstOrFail()`) and must
     * execute this inside a surrounding transaction — the booking-confirmation
     * flow already does this (see `BookingController::finalizeBooking`). This
     * method mutates the passed-in `GiftCard` instance and writes the ledger
     * row in a nested savepoint; it does NOT re-lock.
     *
     * Deducts `min($amountCents, $giftCard->current_balance)`; sets status to
     * `Depleted` when balance hits zero.
     */
    public function redeemAgainstBooking(
        GiftCard $giftCard,
        int $amountCents,
        Booking $booking,
        ?AdminUser $actor = null,
    ): void {
        DB::transaction(function () use ($giftCard, $amountCents, $booking, $actor): void {
            $deduct = min($amountCents, $giftCard->current_balance);
            $giftCard->current_balance -= $deduct;
            if ($giftCard->current_balance <= 0) {
                $giftCard->status = GiftCardStatus::Depleted;
            }
            $giftCard->save();

            GiftCardLedgerEntry::create([
                'gift_card_id' => $giftCard->id,
                'type' => GiftCardLedgerType::Redemption,
                'amount_cents' => -$deduct,
                'balance_after_cents' => $giftCard->current_balance,
                'booking_id' => $booking->id,
                'admin_user_id' => $actor?->id,
                'reason' => null,
                'created_at' => now(),
            ]);

            $this->logIfAdmin(self::EVENT_REDEEMED, $giftCard, $actor, [
                'code' => $giftCard->code,
                'amount' => $deduct,
                'booking_id' => $booking->id,
            ]);
        });
    }

    public function findByCode(string $code): ?GiftCard
    {
        return GiftCard::query()->where('code', $code)->first();
    }

    public function getBalance(GiftCard $giftCard): int
    {
        return $giftCard->current_balance;
    }

    /**
     * Admin void: mark the card inactive and dispatch a queued finance
     * notification. Single transaction covers status mutation, ledger entry,
     * activity-log write, and queued mail (mail is queued via `ShouldQueue`
     * so the `send()` call does not block on SMTP).
     *
     * Voids are NOT idempotent — a second call on an already-voided card
     * throws `GiftCardNotVoidableException(reason: already_voided)`. Two
     * admins racing both receive a clear failure rather than both seeing
     * a "success" toast.
     *
     * @throws GiftCardNotVoidableException When status is not `Active`.
     */
    public function void(GiftCard $giftCard, string $reason, ?AdminUser $actor = null): void
    {
        DB::transaction(function () use ($giftCard, $reason, $actor): void {
            /** @var GiftCard $locked */
            $locked = GiftCard::query()
                ->whereKey($giftCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            match ($locked->status) {
                GiftCardStatus::Active => null,
                GiftCardStatus::Voided => throw new GiftCardNotVoidableException(
                    GiftCardNotVoidableException::REASON_ALREADY_VOIDED,
                ),
                GiftCardStatus::Depleted => throw new GiftCardNotVoidableException(
                    GiftCardNotVoidableException::REASON_DEPLETED,
                ),
                GiftCardStatus::Expired => throw new GiftCardNotVoidableException(
                    GiftCardNotVoidableException::REASON_EXPIRED,
                ),
            };

            $balanceBefore = $locked->current_balance;

            $locked->status = GiftCardStatus::Voided;
            $locked->voided_at = now();
            $locked->voided_reason = $reason;
            $locked->voided_by_admin_user_id = $actor?->id;
            $locked->current_balance = 0;
            $locked->save();

            GiftCardLedgerEntry::create([
                'gift_card_id' => $locked->id,
                'type' => GiftCardLedgerType::Void,
                'amount_cents' => -$balanceBefore,
                'balance_after_cents' => 0,
                'booking_id' => null,
                'admin_user_id' => $actor?->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $this->logIfAdmin(self::EVENT_VOIDED, $locked, $actor, [
                'code' => $locked->code,
                'balance_voided' => $balanceBefore,
                'reason' => $reason,
            ]);

            // Sync the caller's reference to the locked state so upstream
            // code sees the voided status without an extra fetch.
            $giftCard->setRawAttributes($locked->getAttributes(), sync: true);

            // Defer mail dispatch until after the transaction commits — queue
            // drivers (`config/queue.php` ships `after_commit: false` for every
            // connection) push the job at dispatch time, so an in-transaction
            // `Mail::send()` would deliver a "voided" email even if the
            // surrounding write rolls back.
            DB::afterCommit(fn () => Mail::to(config('finance.notification_email'))
                ->send(new GiftCardVoidedMail($locked, $reason, $actor, $balanceBefore)));
        });
    }
}
