# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 1.4: Per-showtime seat-occupancy map
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 7 tests first (`ShowtimeOccupancyTest`), then implementation. Full backend
  suite on this branch: **1131 passed** (main baseline 1124 + 7). PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-showtime-occupancy` is **off main** (independent of the
  refund stack #66→#68) so review of the stack doesn't block ops features.

### Decisions
- [2026-06-09] Dedicated custom resource page (`/{record}/occupancy`) following the
  `VisualEditor` precedent rather than embedding a grid in `ViewShowtime`'s schema — state in
  public Livewire properties (`$seatStates`, `$counts`) so tests `assertSet` data instead of
  scraping markup. Step 1.5's walk-up seat picker will grow from this grid.
- [2026-06-09] Seat state derives from `booking_seats.occupies_seat` (the occupancy guard) —
  the map can never disagree with checkout/refund logic. Sold/held/refund-pending are split
  visually: sold = `#550000` fill per the token-mapping rule, held = steel, pending = gold.
- [2026-06-09] List column `occupied / capacity` uses a filtered
  `withCount(['bookingSeats as occupied_seats_count'])` on `getEloquentQuery()` (new
  `Showtime::bookingSeats()` hasMany over the denormalized `booking_seats.showtime_id`).

### Files Changed
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ShowtimeOccupancy.php` — new page
- `backend/resources/views/filament/resources/showtime-resource/pages/occupancy.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource.php` — occupancy route + column + withCount
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ViewShowtime.php` — occupancy_map action
- `backend/app/Models/Showtime.php` — bookingSeats() relation
- `backend/tests/Feature/Admin/Pages/ShowtimeOccupancyTest.php` — new: 7 tests
- `docs/plans/admin/v2/04-showtime-occupancy.md` — new: Step 1.4 spec

## Step 1.3: BookingResource actions + real refunds in CancellationFollowupQueue
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 12 new tests (`BookingResourceActionsTest`) + 3 added to
  `CancellationFollowupQueueTest`; two existing queue tests updated (their fixtures now need
  `stripe_payment_intent_id => null` because mark_resolved is manual-only). Full backend
  suite green: **1173 passed / 4343 assertions**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-booking-admin-actions` stacked on Step 1.2's branch.

### Decisions
- [2026-06-09] Action factories live as statics on `BookingResource`
  (`refundAction()` etc.) per the `UserResource::adjustPointsAction()` precedent;
  `ViewBooking::getHeaderActions()` consumes them. `refundSplitSummary()` is shared between
  the view-page modal and the queue's issue_refund modal.
- [2026-06-09] Reserved `showtime_cancelled:` flag prefix rejected at BOTH the form layer
  (`not_regex` rule, immediate field error) and the service layer (`BookingFlagException`,
  defense in depth for non-UI callers).
- [2026-06-09] `mark_resolved` survives ONLY for rows with no PaymentIntent and no gift-card
  redemption (`hasProgrammaticRefund()` gate) — everything else must use `issue_refund`.
- [2026-06-09] New permissions `bookings.flag` + `bookings.resend_confirmation` seeded for
  admin + manager; ops stays read-only. `RoleSeederTest` derives from the seeder constants,
  so no separate matrix update needed.

### Blockers (testing gotchas, resolved)
- [2026-06-09] `callAction('x', data: [...])` does NOT bind modal-form data in this Filament
  version — use the established `mountAction → set('mountedActions.0.data.…') → callMountedAction`
  idiom (mirrors the existing queue test).
- [2026-06-09] `assertHasActionErrors()` auto-prefixes `mountedActions.0.data.` — pass bare
  field keys.
- [2026-06-09] Per-record table-action visibility assertions on a single Livewire instance can
  reuse a cached evaluation — use a fresh `Livewire::test()` per record.

### Files Changed
- `backend/app/Filament/Resources/BookingResource.php` — 4 action factories + refundSplitSummary
- `backend/app/Filament/Resources/BookingResource/Pages/ViewBooking.php` — header actions wired
- `backend/app/Filament/Pages/CancellationFollowupQueue.php` — issue_refund action;
  mark_resolved gated to manual-only rows; docblock updated
- `backend/app/Services/BookingFlagService.php` — new: flag/unflag with row locks + activity
- `backend/app/Exceptions/BookingFlagException.php` — new
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — bookings.flag,
  bookings.resend_confirmation (admin + manager)
- `backend/tests/Feature/Admin/Resources/BookingResourceActionsTest.php` — new: 12 tests
- `backend/tests/Feature/Admin/Pages/CancellationFollowupQueueTest.php` — +3 tests, fixtures
- `docs/plans/admin/v2/03-booking-admin-actions.md` — new: Step 1.3 spec

## Step 1.2: Refund + confirmation notifications via outbox
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 18 new tests (outbox round-trips, dispatcher arms, jobs with Mail::fake,
  mailable rendering, resend service) + 2 added to `BookingRefundServiceTest`. Full backend
  suite green: **1160 passed / 4252 assertions**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-refund-notifications` stacked on Step 1.1's branch
  (PR targets it — the refund service must never emit an event type the dispatcher can't map).

### Decisions
- [2026-06-09] `booking.refunded` outbox row written ONLY for Refunded targets — a
  Held→Cancelled release moved no money and the customer never finished checkout, so no email.
- [2026-06-09] Refund amounts ride in the outbox payload (not re-derived by the job) so the
  email always states what the refund actually moved.
- [2026-06-09] `BookingConfirmationMail` is the FIRST booking-confirmation email in the
  system (customers previously got only Stripe's hosted receipt). Auto-sending it from the
  customer checkout flow is deliberately out of scope here (no scope creep) — flagged as a
  candidate follow-up step for the backlog.
- [2026-06-09] `resendConfirmation` validates Confirmed status + recipient up front and throws
  `BookingNotResendableException` so Filament (Step 1.3) gets immediate feedback instead of a
  silently no-oping queued job.

### Blockers
- [2026-06-09] Pint PostToolUse hook strips imports added before their usages exist (two-edit
  sequences) — bit twice (`BookingRefundService`, `OutboxDispatcher`); symptom is
  `Class "App\Services\DispatchOutbox" not found` style errors or outbox rows cycling as
  "retryable failures". Fix: re-add imports after the usage edit. (Known gotcha, reconfirmed.)

### Files Changed
- `backend/app/Services/BookingNotificationService.php` — new: resendConfirmation
- `backend/app/Services/BookingRefundService.php` — Phase C writes booking.refunded outbox row
- `backend/app/Outbox/OutboxDispatcher.php` — two new match arms
- `backend/app/Jobs/SendBookingRefundConfirmation.php`, `SendBookingConfirmation.php` — new
- `backend/app/Mail/BookingRefundedMail.php`, `BookingConfirmationMail.php` — new
- `backend/resources/views/mail/booking-refunded.blade.php`, `booking-confirmation.blade.php` — new
- `backend/app/Exceptions/BookingNotResendableException.php` — new
- `backend/tests/Feature/Outbox/BookingNotificationOutboxTest.php` — new: 9 tests
- `backend/tests/Feature/Admin/Services/BookingNotificationServiceTest.php` — new: 7 tests
- `backend/tests/Feature/Admin/Services/BookingRefundServiceTest.php` — +2 outbox tests
- `docs/plans/admin/v2/02-refund-notifications.md` — new: Step 1.2 spec

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
