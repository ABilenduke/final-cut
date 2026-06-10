# Admin v5 Progress Journal

Execution journal for [`docs/plans/admin/v5/`](../plans/admin/v5/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 5.1: CI reliability — composer source fallback
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] `git` added to the three composer-running backend Dockerfile stages
  (`vendor`, `e2e-seeder`, `development`) so composer's source fallback works when a
  packagist dist download flakes — the diagnosed cause of one of today's three CI
  failures ("git was not found in your PATH, skipping source download"). Production
  runtime stage untouched. Local image rebuild clean; backend suite **1305 passed**
  on the rebuilt container.

### Decisions
- [2026-06-10] git scoped to build stages only — no prod image growth.
- [2026-06-10] Docker Hub pull timeouts (the other two flakes) left alone: registry-side,
  rerun-fixable; a mirror would be speculative infra (per the no-speculative-changes rule).

### Blockers
- [2026-06-10] Recreated dev container 404'd the suite — the documented boot race
  (entrypoint `optimize` finishing after a manual `optimize:clear`); second clear fixed it.

### Files Changed
- `backend/Dockerfile` — git in vendor/e2e-seeder/development stages
- `docs/plans/admin/v5/{00-index,01-ci-reliability}.md` — plan docs
