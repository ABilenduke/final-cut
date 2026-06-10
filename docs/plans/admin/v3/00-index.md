# Admin v3 — Second-Audit Remediation

Follow-up to [admin v2](../v2/00-index.md) (complete, PRs #66–#82). A fresh
three-way audit on 2026-06-10 found the bookings/scheduling core clean (no
genuine bugs; Stripe webhook and partial refunds remain documented MVP
deferrals) and surfaced four batches of remaining work, all user-approved.

| Step | Plan doc | Summary |
| ---- | -------- | ------- |
| 3.1 ✅ | [01](01-ops-polish.md) | Admin ops polish: refund timestamps in booking view; gift-card balance adjustment (service + action, uses the existing `GiftCardLedgerType::Adjustment` case); promo-code reactivate |
| 3.2 ✅ | [02](02-site-contacts.md) | Site contacts CMS: footer address/phone + support emails (hello/privacy/careers/accessibility/concierge) into the `site_settings` store with an admin form; frontend surfaces consume with fallbacks |
| 3.3 ✅ | [03](03-gift-card-payments.md) | Gift card payments: wire the purchase flow to Stripe end-to-end (composer → PaymentIntent → confirm → delivery email) |
| 3.4 ✅ | [04](04-checkout-cleanup.md) | Checkout cleanup: remove/wire dead fields (loyalty opt-in, contact extras), hold-timer alignment test, defensive comments from the audit |

## Conventions

Same as v2: one step = one branch = one PR; TDD with Pest/Vitest; mutations
through services with actor attribution + activity log; full suites + PHPStan
+ Pint before push; CI-watched merges gated on Copilot feedback; journal in
[`docs/progress/admin-v3.md`](../../../progress/admin-v3.md).

## Sequencing

3.1 → 3.2 → 3.3 → 3.4 (independent, ordered by operational value; 3.3 is the
largest and benefits from 3.2's contacts blob for the concierge email).
