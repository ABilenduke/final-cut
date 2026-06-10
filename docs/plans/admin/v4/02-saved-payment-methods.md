# Plan 02 (v4) — Saved payment methods

**Step:** 4.2 · **Status:** ✅ Complete

## Goal

Finish the saved-payment-methods feature whose backend shipped in
backend-v1 but whose two customer touchpoints were broken or unwired:

1. The account page's **Add** button POSTed for a SetupIntent and then
   toasted "Payment method added" — **nothing ever collected a card**, so
   no card could actually be saved.
2. The checkout's **"Save this card"** checkbox (defaulting ON) was never
   transmitted; the booking PaymentIntent carried no customer, so Stripe
   retained nothing.

## Design

- **`AddPaymentMethodModal`** (new, `components/account/`): mints the
  SetupIntent via `POST /api/account/payment-methods`, collects the card
  with Stripe Elements (GiftCardPaymentModal lifecycle), and confirms with
  `stripe.confirmCardSetup` — attaching the card to the user's Stripe
  Customer. The page's Add button now opens it; the list refreshes on
  `added`. The bare `useAccount.addPaymentMethod()` helper was removed
  (the POST belongs to the modal flow).
- **Checkout save-card**: `CheckoutPaymentBay` includes `saveCard: true`
  in the submit payload for authenticated users who leave the box ticked;
  `checkout.vue` forwards it in the booking POST. Backend:
  `CreateBookingRequest` accepts `saveCard` (boolean);
  `BookingController::store` resolves/creates the Stripe Customer
  (persisting `users.stripe_customer_id`) and passes `customer` +
  `setup_future_usage: 'on_session'` into the PaymentIntent — the Stripe-
  recommended way to retain a card used for a payment. Guests are ignored
  (no account to attach to). `StripeService::createPaymentIntent` and the
  fake gained the two optional params.

## Out of scope

Paying **with** a saved card at checkout (a saved-card picker UI + off-
session confirm path) — the cards saved here surface on the account page
and in Stripe; the picker is a future step.

## Tests

- `backend/tests/Feature/Api/BookingSaveCardTest.php` — 4 tests: saveCard
  attaches customer + future usage and persists the customer id; absent
  flag → no customer; guests ignored; existing customer id reused.
- `frontend/tests/components/account/AddPaymentMethodModal.test.ts` —
  SetupIntent → confirmCardSetup → `added`; Stripe error path.
- `frontend/tests/components/booking/CheckoutPaymentBaySubmit.test.ts` —
  full submit through a working Stripe fake pins the payload shape
  (saveCard for authed, absent for guests).
