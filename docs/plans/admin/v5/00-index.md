# Admin v5 — Round Five

Follow-up to [admin v4](../v4/00-index.md). All previous rounds merged
(PRs #66–#93); round five works the next four user-approved items
(2026-06-10).

| Step | Plan doc | Summary |
| ---- | -------- | ------- |
| 5.1 ✅ | [01](01-ci-reliability.md) | CI reliability: git in the composer-running Docker build stages (source fallback for packagist dist flakes) |
| 5.2 | 02 | TMDB crew enrichment: auto-fill movie credits from the credits payload movies:enrich already fetches |
| 5.3 | 03 | Pay with saved card: checkout picker for stored cards |
| 5.4 | 04 | Calendar Week/List views: enable the two disabled Bridge Console view modes |

## Conventions

Same as v2–v4: one step = one branch = one PR; TDD; full suites + PHPStan +
Pint before push; merge watchers poll `mergeStateStatus` (auto-merge is
disabled repo-side); journal in
[`docs/progress/admin-v5.md`](../../../progress/admin-v5.md).
