# Plan 03 (v3) — Gift card payments

**Step:** 3.3 · **Status:** ✅ Complete

## Goal

Make gift cards actually sellable. The composer UI existed but the Purchase
button ended in a "Payment integration coming soon" toast — while the
backend (`POST /api/gift-cards/purchase` with Idempotency-Key replay +
pending-state 3DS resume, `POST /api/gift-cards/confirm`, `FakeStripeService`
coverage) had been complete since backend-v1. This step is **frontend-only**.

## Design

- **`GiftCardPaymentModal.vue`** (new, `components/content/`): CvModal-based
  payment step. Stripe Elements card collection copied from
  `CheckoutPaymentBay` (same night theme tokens, `hidePostalCode`);
  `createPaymentMethod` → `purchase` with a fresh `crypto.randomUUID()`
  Idempotency-Key per attempt → on `requiresAction`,
  `stripe.handleCardAction(clientSecret)` then `confirm(paymentIntentId)` —
  the same contract as the booking checkout. Errors render inline: 402 →
  decline copy, 400 → first field message, else generic
  "card was not charged".
- **`gift-cards.vue`**: `GiftCardPreview @submit` now lifts the composed
  payload into `pendingPayload`, which conditionally renders the modal
  (fresh mount per attempt — Elements lifecycle stays simple). On
  `purchased`: success toast, composer reset, and an "Order confirmed"
  section with the card amount + recipient.
- **`GiftCardPreview.vue`**: the placeholder toast is gone; the component
  just emits.
- **No composable changes** — `useGiftCards.purchase` already sent the
  idempotency key as the header option (matching
  `PurchaseGiftCardRequest::prepareForValidation`).

## Tests

- `frontend/tests/components/content/GiftCardPaymentModal.test.ts` — happy
  path (payment method → purchase body + uuid header → `purchased` emit),
  3DS path (`handleCardAction` + confirm), 402 decline (inline error, no
  emit). Teleported-content queries follow the `CvModal.test.ts` idiom
  (`document.body.querySelector`).
- Backend untouched: the existing 92 gift-card tests pin the endpoints.
