# Admin v4 Progress Journal

Execution journal for [`docs/plans/admin/v4/`](../plans/admin/v4/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

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
