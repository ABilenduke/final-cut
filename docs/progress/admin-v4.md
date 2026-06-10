# Admin v4 Progress Journal

Execution journal for [`docs/plans/admin/v4/`](../plans/admin/v4/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 4.2: Saved payment methods
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Finished the feature whose backend shipped in backend-v1: the account
  page's Add button now opens `AddPaymentMethodModal` (SetupIntent → Stripe Elements →
  `confirmCardSetup`) instead of a bare POST that could never attach a card; the
  checkout "Save this card" checkbox now rides the booking POST and the backend
  attaches a Stripe Customer + `setup_future_usage: 'on_session'` to the PaymentIntent
  (persisting `users.stripe_customer_id`; guests ignored). Paying WITH a saved card
  (picker UI) deliberately out of scope.

### Decisions
- [2026-06-10] `setup_future_usage` on the payment PI (not a separate SetupIntent at
  checkout) — Stripe's recommended retain-on-payment path; one charge, one consent.
- [2026-06-10] Removed `useAccount.addPaymentMethod()` — the SetupIntent POST belongs
  inside the modal flow; a bare helper invites the old broken pattern back.
- [2026-06-10] Gotcha (recurring): two parallel vitest `docker compose run` invocations
  collided and hung silently — restart the frontend container, rerun serially.

### Blockers
- none

### Files Changed
- `backend/app/Services/StripeService.php`, `backend/tests/Helpers/FakeStripeService.php` — optional customer/setup_future_usage params
- `backend/app/Http/Controllers/Api/BookingController.php`, `backend/app/Http/Requests/CreateBookingRequest.php` — saveCard handling
- `backend/tests/Feature/Api/BookingSaveCardTest.php` — 4 tests
- `frontend/app/components/account/AddPaymentMethodModal.vue` (new), `frontend/app/pages/account/payment-methods.vue` — real add-card flow
- `frontend/app/components/booking/CheckoutPaymentBay.vue`, `frontend/app/pages/purchase/checkout.vue` — saveCard payload
- `frontend/app/composables/useAccount.ts` (+ test) — bare helper removed
- `frontend/tests/components/account/AddPaymentMethodModal.test.ts`, `frontend/tests/components/booking/CheckoutPaymentBaySubmit.test.ts` — new tests
- `docs/plans/admin/v4/02-saved-payment-methods.md` — step spec

## Step 4.1: Stripe webhook
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Signature-verified `POST /api/webhooks/stripe` (fails closed when the
  secret is unconfigured). Unmatched `payment_intent.succeeded` events schedule a
  **deferred** orphan check (+30 min via outbox `available_at`, deduped per
  PaymentIntent against Stripe's webhook retries); `CheckOrphanedCharge` re-checks
  bookings/gift cards/3DS pending caches and alerts finance (`OrphanedChargeMail`)
  only for charges still unmatched — money taken with nothing sold. The webhook never
  finalizes anything; the synchronous confirm + idempotent replay own that path.
  Backend **1295 passed**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Reconcile-and-alert, not auto-finalize: re-running finalize from webhook
  context would duplicate the confirm flow's locking/idempotency surface for a window
  that finance can close manually with full information.
- [2026-06-10] +30 min defer because Stripe fires `succeeded` while Phase C may still be
  in flight; the 15-min 3DS pending caches are also re-checked at run time.
- [2026-06-10] Tests sign payloads with Stripe's real v1 HMAC scheme (pure crypto) —
  no SDK fake required for `Webhook::constructEvent`.

### Blockers
- none

### Files Changed
- `backend/app/Http/Controllers/Api/StripeWebhookController.php` (new), `backend/routes/api.php` — endpoint (replaces the v3 TODO)
- `backend/app/Jobs/CheckOrphanedCharge.php`, `backend/app/Mail/OrphanedChargeMail.php`,
  `backend/resources/views/mail/orphaned-charge.blade.php` — deferred reconciliation
- `backend/app/Outbox/OutboxDispatcher.php` — `payment.orphan_check` arm
- `backend/config/services.php`, `backend/.env.example` — `STRIPE_WEBHOOK_SECRET`
- `backend/tests/Feature/Api/StripeWebhookTest.php` — 7 tests
- `docs/plans/admin/v4/{00-index,01-stripe-webhook}.md` — plan docs
