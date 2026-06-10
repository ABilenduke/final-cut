# Plan 02 — Refund + booking-confirmation notifications via outbox

## Goal

Durable customer emails for the refund flow (Step 1.1) and an admin "resend confirmation"
primitive — **the first booking-confirmation email in the system** (customers previously only
received Stripe's hosted receipt). All delivery rides the existing dispatch-outbox: rows are
written inside domain transactions; `outbox:dispatch` drains them.

## Design

Follows the documented `OutboxDispatcher` extension recipe (its class docblock):

1. **Event constants on the producing services** — reuse `BookingRefundService::EVENT_REFUNDED`
   (`booking.refunded`, already the activity-log description, mirroring how
   `GiftCardService::EVENT_VOIDED` does double duty); new
   `BookingNotificationService::EVENT_CONFIRMATION_RESEND` (`booking.confirmation_resend`).
2. **Producers write outbox rows in-transaction** —
   - `BookingRefundService::refund()` Phase C writes a `booking.refunded` row (payload:
     `booking_id`, `card_refund`, `gift_restored`, `refunded_by_admin_user_id`) **only when the
     target status is Refunded** — a Held→Cancelled release moved no money and the customer
     never completed checkout, so no email.
   - New `BookingNotificationService::resendConfirmation(Booking, ?User $actor)` validates the
     booking is Confirmed and has a recipient (user or guest email), then writes a
     `booking.confirmation_resend` row + admin activity in one transaction. Invalid states throw
     `BookingNotResendableException` (reasons: `not_confirmed`, `no_recipient`) so Filament
     (Step 1.3) gets immediate feedback instead of a silently no-oping job.
3. **Dispatcher match arms** → new queued jobs `SendBookingRefundConfirmation`
   (`booking_id` + amounts) and `SendBookingConfirmation` (`booking_id`), both modeled on
   `NotifyCustomerOfShowtimeCancellation` (booking-gone → no-op; no-recipient → report + no-op).
4. **Mailables** `BookingRefundedMail` (amount split: card refunded vs gift-card balance
   restored) and `BookingConfirmationMail` (full ticket: movie, showtime, seats, food, totals,
   confirmation code), markdown views `mail.booking-refunded` / `mail.booking-confirmation`
   (cents formatted `number_format($c / 100, 2)` like `gift-card-voided`).

## Tests

- `tests/Feature/Outbox/BookingNotificationOutboxTest.php` — dispatcher arms (Bus::fake),
  malformed-payload parking, job behavior (Mail::fake: user email, guest email, booking gone,
  no recipient), `outbox:dispatch` round trip for both event types.
- `tests/Feature/Admin/Services/BookingNotificationServiceTest.php` — resend writes outbox row +
  activity with actor; rejects non-Confirmed; rejects no-recipient; null actor → no activity.
- `BookingRefundServiceTest` additions — refund writes the outbox row in-transaction with the
  correct split payload; Held→Cancelled writes none.

## Sequencing

Stacked on Step 1.1 (`feat/admin-v2-booking-refund-service`) — the refund service must never
emit an event type the dispatcher can't map, so both ship together or in order.
