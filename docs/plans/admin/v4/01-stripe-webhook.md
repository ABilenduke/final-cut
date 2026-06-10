# Plan 01 (v4) — Stripe webhook

**Step:** 4.1 · **Status:** ✅ Complete

## Goal

Close the documented out-of-band payment window (the `routes/api.php` TODO
from v3 Plan 04): a charge that succeeds at Stripe while the API response
is lost mid-finalize previously left money taken with nothing sold, and
recovery depended on someone noticing in the Stripe dashboard.

## Design — deliberately conservative

The webhook **never finalizes** bookings or gift cards (the synchronous
confirm + idempotent replay own that path). It only reconciles:

- **`POST /api/webhooks/stripe`** (`StripeWebhookController`) — Stripe v1
  HMAC signature verification against `STRIPE_WEBHOOK_SECRET`
  (`services.stripe.webhook_secret`; fails closed with 400 when
  unconfigured). Non-`payment_intent.succeeded` events are acknowledged
  and ignored.
- On `payment_intent.succeeded`: if a booking or gift card already carries
  the PaymentIntent id → done (the normal case). Otherwise schedule a
  **deferred orphan check** — an outbox row with `available_at = +30 min`,
  deduped per PaymentIntent against unprocessed rows (Stripe retries
  webhooks). The defer matters: Stripe fires the event while the in-band
  Phase C transaction may still be running.
- **`CheckOrphanedCharge` job** (dispatcher arm `payment.orphan_check`):
  re-checks both tables and the `pending_booking:`/`pending_gift_card:`
  3DS caches; only a still-unmatched charge logs error-level and emails
  finance (`OrphanedChargeMail` → `FINANCE_NOTIFICATION_EMAIL`) with the
  PaymentIntent id and amount for refund/reconciliation.

`STRIPE_WEBHOOK_SECRET` added to `.env.example` (production example
already listed it).

## Tests

`backend/tests/Feature/Api/StripeWebhookTest.php` — 7 tests: invalid
signature → 400 + nothing written; matched event → no orphan row;
unmatched event → one deduped deferred row; unhandled types acknowledged;
dispatcher arm; job alerts only when still unmatched; job stays quiet
while a 3DS pending cache lives. Signatures are computed with Stripe's
real HMAC scheme — no network, no SDK fake needed.
