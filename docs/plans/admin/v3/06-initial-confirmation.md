# Plan 06 (v3) — Initial booking confirmation email

**Step:** 3.6 · **Status:** ✅ Complete

## Goal

The delta audit's second catch of this class: customers completing a
purchase never received a confirmation email. Only the **admin resend**
action (v2 Plan 02) sent one — a "resend" with no original. All the
machinery existed (`BookingConfirmationMail`, `SendBookingConfirmation`,
the outbox worker); the initial send was never wired.

## Design

- **`BookingNotificationService::EVENT_CONFIRMATION = 'booking.confirmation'`**
  + `queueConfirmation(Booking)`: writes the outbox row; silently no-ops
  when the booking has no recipient (walk-up sales without a captured
  email). No actor/activity log — customer-originated.
- **Call sites**: `BookingController::finalizeBooking()` (inside the
  Phase C transaction — covers both the immediate and the 3DS confirm
  paths atomically with the booking) and `WalkUpBookingService::create()`
  after the booking save.
- **Dispatcher**: the new event shares the existing
  `dispatchBookingConfirmationResend` arm → same `SendBookingConfirmation`
  job and mailable.

## Tests

`backend/tests/Feature/BookingInitialConfirmationTest.php` — 5 tests:
immediate checkout writes the row; the 3DS path writes it only at confirm;
walk-up with/without email; dispatcher arm; full `outbox:dispatch` round
trip (`Mail::assertSent` — the mailable is sent inline from the already-
queued job, unlike the ShouldQueue gift-card mailables).
