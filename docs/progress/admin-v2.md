# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

## Step 1.1: BookingRefundService
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] Audit complete (3 explore agents + verification); plan approved; branch
  `feat/admin-v2-booking-refund-service` created; plan docs written.
- [2026-06-09] TDD: 18 failing tests written first (`BookingRefundServiceTest`), then the
  service + supporting changes. Full backend suite green: **1142 passed / 4207 assertions**.
- [2026-06-09] NOTE: the bookings migration was edited in place (4 new columns) — dev
  databases need `make fresh` (or a manual ALTER) after pulling this branch.

### Decisions
- [2026-06-09] Refund claim via `refund_initiated_at` timestamp (not a status) so concurrent
  admin refunds are excluded without disturbing the seat-occupancy status machine; 15-min
  stale-claim retake self-heals crashed runs (mirrors `bookings:expire-held` philosophy).
- [2026-06-09] Held bookings refund to **Cancelled** (no money ever captured on a Held row);
  Confirmed/RefundPending refund to **Refunded**. Seat release is owned entirely by the
  `booking_seats` occupancy trigger — the service only flips booking status.
- [2026-06-09] Voided gift cards are NOT restored (balance was already zeroed by the void);
  skipped cards surface in activity-log properties + a warning log for manual follow-up.
- [2026-06-09] Customer-side `getHistory()` only derives from Confirmed bookings, so a refunded
  booking's earn line disappears while the explicit clawback adjustment keeps the balance true.
  Net balance is correct; history presentation of clawbacks is a known v1 display quirk.

### Decisions (testing)
- [2026-06-09] `refundTransactionLevels` assertions compare against a captured
  `DB::transactionLevel()` baseline (RefreshDatabase wraps tests in a transaction, so the
  "no transaction" level inside a test is 1, not 0 — same idiom as
  `BookingStripeOutsideTransactionTest`).
- [2026-06-09] `LoyaltyAdjustment` self-logs an activity row via Spatie `LogsActivity` on
  every create regardless of actor — null-actor tests assert the absence of the
  *service-level* `booking.refunded` event, not zero activity rows.

### Blockers
- none

### Files Changed
- `docs/plans/admin/v2/00-index.md` — new: v2 plan index
- `docs/plans/admin/v2/01-booking-refund-service.md` — new: Step 1.1 spec
- `backend/app/Services/BookingRefundService.php` — new: Phase A/B/C refund service
- `backend/app/Exceptions/BookingNotRefundableException.php` — new
- `backend/app/Enums/GiftCardLedgerType.php` — new `Refund` case
- `backend/app/Models/Booking.php` — refund columns: fillable/casts/hidden/docblock
- `backend/app/Services/StripeService.php` — optional partial `amount` on `refundPaymentIntent`
- `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — in-place:
  `stripe_refund_id`, `refund_initiated_at`, `refunded_at`, `cancelled_at`
- `backend/tests/Helpers/FakeStripeService.php` — `shouldFailRefund()`, refund amounts,
  `refundTransactionLevels`
- `backend/tests/Feature/Admin/Services/BookingRefundServiceTest.php` — new: 18 tests
