# Admin v4 — Deferred-Backlog Round

Follow-up to [admin v3](../v3/00-index.md). With every audit finding
remediated (PRs #66–#89), round four works the intentionally-deferred
backlog — all four items user-approved on 2026-06-10.

| Step | Plan doc | Summary |
| ---- | -------- | ------- |
| 4.1 ✅ | [01](01-stripe-webhook.md) | Stripe webhook: signature-verified `POST /api/webhooks/stripe`; unmatched `payment_intent.succeeded` → deferred orphan check → finance alert |
| 4.2 ✅ | [02](02-saved-payment-methods.md) | Saved payment methods: real add-card Elements flow on the account page + checkout save-card via setup_future_usage |
| 4.3 ✅ | [03](03-movie-editorial.md) | Movie editorial CMS: credits/press-quotes/clips JSON columns + Editorial form section (replaces the movie-page stubs) |
| 4.4 | 04 | Live cinema readout: feed the what's-on telemetry panel from real data |

## Conventions

Same as v2/v3: one step = one branch = one PR; TDD; full suites + PHPStan +
Pint before push; CI-watched merges gated on Copilot feedback; journal in
[`docs/progress/admin-v4.md`](../../../progress/admin-v4.md).
