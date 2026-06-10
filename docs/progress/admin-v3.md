# Admin v3 Progress Journal

Execution journal for [`docs/plans/admin/v3/`](../plans/admin/v3/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 3.1: Admin ops polish
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Refund timestamps + Stripe refund id surfaced on the booking view
  (conditional placeholders — hidden on never-refunded bookings). New
  `GiftCardService::adjust()` (row lock, signed cents, overdraw/terminal-status guards,
  status follows balance, `Adjustment` ledger type finally used) + **Adjust balance**
  table action behind new `gift_cards.adjust` permission. New
  `PromoCodeService::reactivate()` + **Reactivate** action on deactivated promos.
  Backend suite **1269 passed**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Adjustment takes signed cents in one field (money convention: integers in
  cents) rather than a direction select + amount; the helper text spells out the sign.
- [2026-06-10] No finance outbox email for adjustments (unlike void) — they're support
  corrections; the ledger row + activity log are the audit trail.
- [2026-06-10] Gotcha: Filament v5 table-action form state binds at `mountedActions.0.data.*`
  (NOT `mountedTableActions`) — actions are unified across page/table in v5.

### Blockers
- none

### Files Changed
- `backend/app/Services/GiftCardService.php` — `adjust()` + exception import
- `backend/app/Exceptions/GiftCardNotAdjustableException.php` — new
- `backend/app/Services/PromoCodeService.php` — `reactivate()`
- `backend/app/Filament/Resources/GiftCardResource.php` — Adjust balance action
- `backend/app/Filament/Resources/PromoCodeResource.php` — Reactivate action
- `backend/app/Filament/Resources/BookingResource.php` — refund placeholders
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — `gift_cards.adjust`
- `backend/tests/Feature/Admin/Services/OpsPolishTest.php` — 11 tests
- `docs/plans/admin/v3/{00-index,01-ops-polish}.md` — plan docs
