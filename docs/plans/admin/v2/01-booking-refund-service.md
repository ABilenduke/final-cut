# Plan 01 — BookingRefundService (domain core, no UI)

## Goal

A service that cancels/refunds a booking end-to-end: real Stripe refund, gift-card balance
restoration, loyalty clawback, and seat release — the domain core that Steps 1.2 (outbox
notifications) and 1.3 (Filament actions) build on. No UI in this step.

## Design

`App\Services\BookingRefundService`, mirroring `BookingController::store`'s Phase A/B/C shape
(Stripe is never called inside an open DB transaction — `FakeStripeService` polices level 0):

- **Phase A (txn)** — `lockForUpdate` the booking; validate refundable; **claim** the refund by
  setting `refund_initiated_at` (excludes concurrent admins; a claim older than 15 min with no
  `stripe_refund_id` is treated as crashed and may be retaken); compute the split:
  - card portion = `booking.total` when `stripe_payment_intent_id` is present (`total` equals the
    card-captured amount exactly — gift-card + promo amounts live in `discount`);
  - gift portion = Σ `gift_card_ledger_entries` redemption rows for the booking, grouped per card;
  - loyalty clawback = `floor(total/100)` for authed bookings whose status is Confirmed or
    RefundPending (points were awarded at confirm time); Held bookings never earned points.
- **Phase B (no txn)** — `StripeService::refundPaymentIntent()` when card portion > 0; persist
  `stripe_refund_id` immediately (crash after this point resumes idempotently — a retry sees the
  id and skips Stripe). On Stripe failure: release the claim and rethrow.
- **Phase C (txn)** — flip status → `Refunded` (`Cancelled` for Held bookings: no money ever
  moved); the `booking_seats` `AFTER UPDATE` trigger releases the seats — **never mutate
  `booking_seats` directly**; restore each gift card under a row lock with a
  `GiftCardLedgerType::Refund` ledger entry (re-activate Depleted cards; **skip Voided cards** —
  logged + surfaced in activity properties for manual follow-up); loyalty clawback via
  `LoyaltyService::adjustPoints()` (row-locked, writes the `loyalty_adjustments` audit row);
  `logIfAdmin('booking.refunded'|'booking.cancelled', …)`.

`previewSplit(Booking): array` exposes the same computation read-only for Step 1.3's confirm modal.

## Schema (edit in place, pre-launch rule)

`create_bookings_table` gains: `stripe_refund_id` (nullable string), `refund_initiated_at`,
`refunded_at`, `cancelled_at` (nullable timestamps). `Booking` model: fillable + casts + hidden
(`stripe_refund_id` joins `stripe_payment_intent_id`).

## Supporting changes

- `StripeService::refundPaymentIntent(string $pi, ?int $amount = null)` — optional partial amount.
- `FakeStripeService`: records refund `amount` + `refundTransactionLevels`; new `shouldFailRefund()`.
- `GiftCardLedgerType`: new `Refund = 'refund'` case (plain string column — code-only change).
- New `App\Exceptions\BookingNotRefundableException` (reasons: `already_refunded`,
  `already_cancelled`, `in_progress`).

## Tests (`tests/Feature/Admin/Services/BookingRefundServiceTest.php`)

Full split refund (card+gift+loyalty+seat release+activity), gift-only (no Stripe call), guest
(no loyalty), Stripe failure leaves state untouched + releases claim, double-refund rejected,
concurrent-claim rejected, stale-claim retake, idempotent resume after Phase-C crash, Depleted
re-activation, Voided skip, RefundPending (showtime-cancelled) refund, Held → Cancelled,
zero-point skip, Stripe called at transaction level 0, previewSplit non-mutating.

## Risks

- Refund vs occupancy guard: seat release belongs to the DB trigger; service only flips status.
- Worst partial state (Stripe refunded, DB write failed) mitigated by persisting
  `stripe_refund_id` pre-finalize + idempotent resume.
- Lock order booking → gift_cards → users is disjoint from checkout's showtime → gift_cards order;
  no deadlock pairing.
