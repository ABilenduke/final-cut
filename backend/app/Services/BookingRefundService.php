<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\BookingNotRefundableException;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Admin-initiated booking refund/cancel: real Stripe refund, gift-card balance
 * restoration, loyalty clawback, and seat release — end to end.
 *
 * Mirrors `BookingController::store`'s Phase A/B/C shape so the Stripe network
 * call NEVER runs inside an open DB transaction or while a row lock is held
 * (FakeStripeService records the transaction level on every call to police
 * this):
 *
 *  - Phase A (txn): row-lock the booking, validate it is refundable, and CLAIM
 *    the refund by setting `refund_initiated_at`. The claim excludes a
 *    concurrent admin from double-refunding; a claim older than
 *    CLAIM_STALE_MINUTES with no `stripe_refund_id` is a crashed run and may
 *    be retaken.
 *  - Phase B (no txn): issue the Stripe refund when a card amount was
 *    captured. `booking.total` IS the card-captured amount (gift-card + promo
 *    portions live in `discount`), so a full-PI refund returns exactly the
 *    card portion. The resulting `stripe_refund_id` is persisted IMMEDIATELY —
 *    a crash after this line resumes idempotently (retry sees the id and
 *    skips Stripe). A Stripe failure releases the claim and rethrows.
 *  - Phase C (txn): flip status → Refunded (Cancelled for Held bookings — no
 *    money ever moved on a Held row). The status flip alone releases the
 *    seats via the `booking_seats` occupancy trigger
 *    (2026_06_05_000000_add_booking_seats_occupancy_guard) — this service
 *    must NEVER write `booking_seats` directly. Gift cards are restored under
 *    row locks with `GiftCardLedgerType::Refund` ledger entries (Depleted
 *    cards re-activate; Voided cards are SKIPPED — their balance was already
 *    zeroed by the void — and surfaced for manual follow-up). Loyalty points
 *    earned at confirm time are clawed back via `LoyaltyService::adjustPoints`
 *    (row-locked, audited). Activity is logged when `$actor !== null`.
 *
 * Lock order within Phase C is booking → gift_cards → users; the checkout path
 * orders showtime → gift_cards, so the two flows never hold overlapping locks
 * in reversed order.
 */
class BookingRefundService
{
    use LogsAdminActivity;

    public const EVENT_REFUNDED = 'booking.refunded';

    public const EVENT_CANCELLED = 'booking.cancelled';

    /** A refund claim older than this with no Stripe refund id is a crashed run. */
    private const CLAIM_STALE_MINUTES = 15;

    public function __construct(
        private readonly StripeService $stripeService,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    /**
     * Read-only refund split for confirmation UIs — what would move where.
     *
     * @return array{
     *     card_refund: int,
     *     gift_restores: list<array{gift_card_id: string, code: string, status: GiftCardStatus, amount: int}>,
     *     loyalty_clawback: int,
     *     target_status: BookingStatus,
     * }
     */
    public function previewSplit(Booking $booking): array
    {
        return $this->computeSplit($booking);
    }

    /**
     * Refund (or, for a Held booking, cancel) end to end.
     *
     * @throws BookingNotRefundableException When already refunded/cancelled or claimed by an in-flight run.
     * @throws ApiErrorException When the Stripe refund fails — state is untouched and the claim released.
     */
    public function refund(Booking $booking, string $reason, ?User $actor = null): Booking
    {
        // ── PHASE A — claim the refund under a brief row lock ───────────────
        [$locked, $split] = DB::transaction(function () use ($booking): array {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $this->assertRefundable($locked);

            $locked->refund_initiated_at = now();
            $locked->save();

            return [$locked, $this->computeSplit($locked)];
        });

        // ── PHASE B — Stripe, with NO transaction and NO row lock held ──────
        if ($split['card_refund'] > 0 && $locked->stripe_refund_id === null) {
            try {
                $refund = $this->stripeService->refundPaymentIntent($locked->stripe_payment_intent_id);
            } catch (\Throwable $e) {
                // Nothing moved at Stripe — release the claim so a retry can run.
                Booking::whereKey($locked->id)->update(['refund_initiated_at' => null]);

                throw $e;
            }

            // Persist BEFORE finalize: a crash past this line resumes
            // idempotently (the retry sees the id and skips Stripe).
            Booking::whereKey($locked->id)->update(['stripe_refund_id' => $refund->id]);
            $locked->stripe_refund_id = $refund->id;
        }

        // ── PHASE C — finalize in a short transaction ────────────────────────
        DB::transaction(function () use ($locked, $split, $reason, $actor): void {
            /** @var Booking $fresh */
            $fresh = Booking::whereKey($locked->id)->lockForUpdate()->firstOrFail();

            $target = $split['target_status'];
            $fresh->status = $target;
            if ($target === BookingStatus::Refunded) {
                $fresh->refunded_at = now();
            } else {
                $fresh->cancelled_at = now();
            }
            // The status flip fires the booking_seats AFTER UPDATE trigger,
            // which releases the seats. Never touch booking_seats here.
            $fresh->save();

            $skipped = [];
            $restored = 0;
            foreach ($split['gift_restores'] as $restore) {
                /** @var GiftCard $card */
                $card = GiftCard::whereKey($restore['gift_card_id'])->lockForUpdate()->firstOrFail();

                if ($card->status === GiftCardStatus::Voided) {
                    // The void already zeroed the balance and notified finance —
                    // restoring here would resurrect money onto a dead card.
                    $skipped[] = $card->code;
                    Log::warning('Booking refund skipped restoring a voided gift card; manual follow-up required', [
                        'booking_id' => $fresh->id,
                        'confirmation_code' => $fresh->confirmation_code,
                        'gift_card_id' => $card->id,
                        'gift_card_code' => $card->code,
                        'amount_cents' => $restore['amount'],
                    ]);

                    continue;
                }

                $card->current_balance += $restore['amount'];
                if ($card->status === GiftCardStatus::Depleted && $card->current_balance > 0) {
                    $card->status = GiftCardStatus::Active;
                }
                $card->save();

                GiftCardLedgerEntry::create([
                    'gift_card_id' => $card->id,
                    'type' => GiftCardLedgerType::Refund,
                    'amount_cents' => $restore['amount'],
                    'balance_after_cents' => $card->current_balance,
                    'booking_id' => $fresh->id,
                    'admin_user_id' => $actor?->id,
                    'reason' => $reason,
                    'created_at' => now(),
                ]);
                $restored += $restore['amount'];
            }

            if ($split['loyalty_clawback'] > 0 && $fresh->user !== null) {
                $this->loyaltyService->adjustPoints(
                    $fresh->user,
                    -$split['loyalty_clawback'],
                    "Booking {$fresh->confirmation_code} refunded: {$reason}",
                    $actor,
                );
            }

            $this->logIfAdmin(
                $target === BookingStatus::Refunded ? self::EVENT_REFUNDED : self::EVENT_CANCELLED,
                $fresh,
                $actor,
                [
                    'confirmation_code' => $fresh->confirmation_code,
                    'reason' => $reason,
                    'card_refund' => $split['card_refund'],
                    'stripe_refund_id' => $locked->stripe_refund_id,
                    'gift_restored' => $restored,
                    'gift_skipped' => $skipped,
                    'loyalty_clawback' => $split['loyalty_clawback'],
                ],
            );
        });

        return $booking->refresh();
    }

    /**
     * @throws BookingNotRefundableException
     */
    private function assertRefundable(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Refunded || $booking->refunded_at !== null) {
            throw new BookingNotRefundableException(BookingNotRefundableException::REASON_ALREADY_REFUNDED);
        }

        if ($booking->status === BookingStatus::Cancelled || $booking->cancelled_at !== null) {
            throw new BookingNotRefundableException(BookingNotRefundableException::REASON_ALREADY_CANCELLED);
        }

        // An in-flight claim blocks; a stale one (crashed run) or one that
        // already carries a stripe_refund_id (resume path) may be retaken.
        if ($booking->refund_initiated_at !== null
            && $booking->stripe_refund_id === null
            && $booking->refund_initiated_at->gt(now()->subMinutes(self::CLAIM_STALE_MINUTES))) {
            throw new BookingNotRefundableException(BookingNotRefundableException::REASON_IN_PROGRESS);
        }
    }

    /**
     * Compute what a refund of this booking moves where. Pure read — shared by
     * `previewSplit()` and the refund flow itself.
     *
     * @return array{
     *     card_refund: int,
     *     gift_restores: list<array{gift_card_id: string, code: string, status: GiftCardStatus, amount: int}>,
     *     loyalty_clawback: int,
     *     target_status: BookingStatus,
     * }
     */
    private function computeSplit(Booking $booking): array
    {
        // `total` equals the card-captured amount exactly (gift-card + promo
        // portions are folded into `discount` by the checkout flow), so a
        // full-PI refund returns precisely the card portion. Held bookings
        // never captured money (the PI is only attached at Confirmed), so they
        // are defensively excluded even if a PI were somehow present.
        $cardRefund = $booking->status !== BookingStatus::Held && $booking->stripe_payment_intent_id !== null
            ? $booking->total
            : 0;

        $redemptions = GiftCardLedgerEntry::query()
            ->with('giftCard')
            ->where('booking_id', $booking->id)
            ->where('type', GiftCardLedgerType::Redemption)
            ->get();

        // Sum per card, not first(): a booking can theoretically carry several
        // redemption rows against the same card. Redemptions are stored as
        // negative debits, hence the negation.
        /** @var array<string, array{gift_card_id: string, code: string, status: GiftCardStatus, amount: int}> $byCard */
        $byCard = [];
        foreach ($redemptions as $entry) {
            /** @var GiftCard $card */
            $card = $entry->giftCard;

            $byCard[$card->id] ??= [
                'gift_card_id' => $card->id,
                'code' => $card->code,
                'status' => $card->status,
                'amount' => 0,
            ];
            $byCard[$card->id]['amount'] += -$entry->amount_cents;
        }

        $giftRestores = array_values(array_filter(
            $byCard,
            fn (array $restore): bool => $restore['amount'] > 0,
        ));

        // Points are awarded at confirm time (1pt/$1 of `total`), so only
        // bookings that reached Confirmed — including those later flagged to
        // RefundPending by a showtime cancellation — have anything to claw
        // back. Held bookings never earned points.
        $clawback = 0;
        if ($booking->user_id !== null
            && in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::RefundPending], true)) {
            $clawback = intdiv($booking->total, 100);
        }

        return [
            'card_refund' => $cardRefund,
            'gift_restores' => $giftRestores,
            'loyalty_clawback' => $clawback,
            // A Held booking never captured money — releasing it is a cancel,
            // not a refund.
            'target_status' => $booking->status === BookingStatus::Held
                ? BookingStatus::Cancelled
                : BookingStatus::Refunded,
        ];
    }
}
