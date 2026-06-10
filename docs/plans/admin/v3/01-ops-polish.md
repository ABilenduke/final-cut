# Plan 01 (v3) — Admin ops polish

**Step:** 3.1 · **Status:** ✅ Complete

Three small operational gaps from the 2026-06-10 second audit, one PR.

## Refund visibility on the booking view

`BookingResource`'s Payment section gains three conditional placeholders —
**Refund initiated** (`refund_initiated_at`), **Refunded at** (`refunded_at`),
and **Stripe refund** (`stripe_refund_id`) — each visible only when the field
is set, so untouched bookings render exactly as before. The data already
existed (written by `BookingRefundService`); it was simply not displayed.

## Gift card balance adjustment

`GiftCardService::adjust(GiftCard, int $deltaCents, string $reason, ?User $actor)`:
row lock; Active/Depleted only (Voided/Expired throw); rejects zero deltas and
overdraw (`GiftCardNotAdjustableException` with reason constants, mirroring
the void exception); status follows the balance (credited-above-zero
re-activates a Depleted card, deducted-to-zero flips to Depleted); writes the
previously unused `GiftCardLedgerType::Adjustment` ledger entry plus a
`gift_card.adjusted` activity row. Surfaced as an **Adjust balance** table
action (signed-cents input + required reason) gated on the new
`gift_cards.adjust` permission (admin + manager).

## Promo code reactivation

`PromoCodeService::reactivate()` clears `deactivated_at` (no-op when already
active) and logs `promo_code.reactivated`. Expiry is untouched — an expired
promo reactivates to active-but-expired, which `validateCode()` still
rejects. Paired **Reactivate** table action, visible only on deactivated
codes, gated on the existing `promos.update`.

## Tests

`backend/tests/Feature/Admin/Services/OpsPolishTest.php` — 11 tests covering
the service rules (credit/deduct/re-activate/deplete/overdraw/terminal
statuses), both table actions + ops-hidden permission checks, and the
booking-view refund fields (shown when set, hidden when never refunded).
