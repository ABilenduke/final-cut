# Plan 03 — BookingResource actions + real refunds in CancellationFollowupQueue

## Goal

Wire Steps 1.1/1.2's primitives into the admin UI: cancel/refund, resend confirmation, and
manual flag/unflag on the booking view page; upgrade the CancellationFollowupQueue from
"mark refunded (manual)" to issuing **real Stripe refunds** through `BookingRefundService`.

## Design

Follows the `UserResource::adjustPointsAction()` precedent — static action factories on the
resource class, consumed by `ViewBooking::getHeaderActions()`:

- **`refundAction()`** — visible with `bookings.resolve_refund` on refundable bookings
  (occupying status, not claimed). Modal previews the computed split via
  `BookingRefundService::previewSplit()` (card refund / gift restore / loyalty clawback) and
  requires a 10+ char reason. Calls `refund()`; `BookingNotRefundableException` and Stripe
  `ApiErrorException` surface as danger notifications, not 500s. Held bookings show
  "Release hold" semantics (target status Cancelled, no money preview).
- **`resendConfirmationAction()`** — visible with new `bookings.resend_confirmation` on
  Confirmed bookings. Calls `BookingNotificationService::resendConfirmation()`.
- **`flagAction()` / `unflagAction()`** — visible with new `bookings.flag`. Flagging takes a
  required reason; the reserved `showtime_cancelled:` prefix is rejected at BOTH the form
  layer (regex rule, immediate feedback) and the service layer (defense in depth) so manual
  flags can never leak into the cancellation followup queue's scope. Both transitions run
  through a new `BookingFlagService` (transactional, activity-logged events `booking.flagged`
  / `booking.unflagged`, `BookingFlagException` reasons `already_flagged` / `not_flagged` /
  `reserved_reason`).

**CancellationFollowupQueue**: new primary `issue_refund` table action (same permission,
split preview + reason, calls the real service — Stripe + gift + loyalty + seat release in
one go). The legacy `mark_resolved` action remains ONLY for rows with no PaymentIntent and
no gift-card redemptions (nothing to move programmatically); its modal copy updated. Docblock
updated — the "v1 does not issue refunds" note is obsolete.

**Permissions**: seed `bookings.flag` + `bookings.resend_confirmation` for admin + manager
(ops stays read-only). Update the booking permission matrix test.

## Tests

`tests/Feature/Admin/Resources/BookingResourceActionsTest.php` (Livewire `ViewBooking`):
refund happy path (service effects + Stripe fake), Held release → Cancelled, visibility
matrix per role/status, Stripe-failure danger notification (state untouched), resend writes
outbox row, resend hidden for non-confirmed, flag/unflag round trip + activity, reserved
prefix rejected with a form error. `CancellationFollowupQueueTest` additions: issue_refund
end-to-end + row leaves queue; mark_resolved visibility split (PI/no-PI).
