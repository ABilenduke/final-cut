# Plan 03 (v5) — Pay with saved card

**Step:** 5.3 · **Status:** ✅ Complete

## Goal

Complete the saved-payment-methods story (v4 Plan 02): users could save
cards but nothing let them pay with one.

## Design

- **Backend**: optional `usingSavedCard` boolean on the booking POST. The
  PaymentIntent is created with the user's `stripe_customer_id` attached —
  a customer-attached PaymentMethod can only be charged on an intent that
  carries the same customer, and Stripe itself rejects a PM belonging to a
  different customer (no extra ownership check needed). Auth-only; a user
  with no Stripe customer gets a 400 before any Stripe call (Held booking
  discarded). No `setup_future_usage` — the card is already saved.
- **Frontend** (`CheckoutPaymentBay`): authenticated users with stored
  cards get a picker (radio per card: brand · last4 · expiry, first card
  preselected) above the card element, plus a "Use a different card"
  toggle. The Elements mount stays in the DOM (`v-show`) so switching
  needs no remount. Paying with a saved card emits
  `{paymentMethodId, usingSavedCard: true}` and never touches
  `createPaymentMethod`; the new-card path is unchanged (incl. saveCard).
  Lookup failure degrades silently to the plain card element. 3DS works
  unchanged — saved cards can still require a challenge.

## Tests

- `backend/tests/Feature/Api/BookingPayWithSavedCardTest.php` — customer
  attached to the intent (no setup_future_usage), no-customer 400 before
  Stripe, guests rejected.
- `CheckoutPaymentBaySubmit.test.ts` — saved-card submit (no Elements
  call), different-card fallback, guests never trigger the lookup.
