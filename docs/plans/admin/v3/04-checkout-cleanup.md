# Plan 04 (v3) — Checkout cleanup

**Step:** 3.4 · **Status:** ✅ Complete

Closes out the second audit's dead-code and hardening recommendations.

## Dead checkout affordances removed

Controls that promised something the purchase never did:

- **Loyalty opt-in checkbox** ("Join Final Cut Rewards (free)") — sent
  `loyaltyOptIn` in the POST body but no backend code ever read it (the
  planned magic-link claim flow was never built). Removed from
  `CheckoutPaymentBay`, the page handler, and the
  `CreateBookingRequest` rules; `PURCHASE_FLOW.md` now marks the flow
  **deferred** with the original design preserved. The authenticated-side
  "save this card" checkbox stays — it fronts the planned saved-payment-
  methods feature (Stripe SetupIntent) documented in DATA_MODELS.
- **Phone + Reel Society ID inputs** (`CheckoutContactBay`) and the
  **newsletter checkbox** (`PromoCode`) — rendered "for design parity" but
  never transmitted. Name/email stay (email rides the POST; the terms
  checkbox stays — it gates submission).

## Cross-stack hold-timer guard

Frontend holds seats for `SESSION_HOLD_MINUTES = 8`; the
`bookings:expire-held` sweeper defaults to 20. The containers can't read
across the mount boundary, so each side pins its own value to the shared
8/20 contract with a cross-reference: `tests/architecture/
hold-timer-alignment.test.ts` (frontend) and
`tests/Unit/HoldTimerContractTest.php` (backend, via the command's option
definition). Either value changing breaks that side's pin.

## Defensive documentation

- `routes/api.php`: TODO block documenting the absent Stripe webhook and
  the exact out-of-band failure window it would close.
- `BookingResource::refundAction()`: why partial refunds are unsupported
  (atomic bookings; split-model prerequisite).
- `BookingController::store()`: the 3DS seat-release trade-off expanded —
  seats may be lost during the challenge; confirm() re-validates before
  capture so the card is never charged on a lost race.
