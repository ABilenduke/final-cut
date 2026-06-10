# Plan 10 — Rental-inquiry + contact-message inboxes

## Goal

Close the audit's two "invisible inbox" gaps: rental inquiries have a model + customer API
but no admin surface; contact-form submissions aren't even persisted
(`ContactController::store` only logs).

## Design

- **`ContactSubmission`** — new model + migration (uuid PK, name/email/subject/message,
  nullable `handled_at` + `handled_by` → users). `ContactController::store` now persists the
  row (the log line stays). Read-only `ContactSubmissionResource` (Operations group,
  unhandled-count nav badge) with a `mark_handled` action via `ContactSubmissionService`
  (double-handle refused, activity logged).
- **`RentalInquiryResource`** — read-only over the existing model (Operations group,
  pending-count nav badge, message on the View page). Status transitions through
  `RentalInquiryService` with an explicit transition map — pending → contacted/confirmed/
  declined, contacted → confirmed/declined, terminal states immutable
  (`InquiryTransitionException` otherwise); the action's status options are derived from
  the same map so the UI can't offer an illegal move.
- **Permissions**: `rentals.view`/`rentals.update_status`/`contact.view`/`contact.resolve`
  for admin + manager; ops gets the two views only.

## Tests (`InquiryInboxesTest`)

POST /api/contact persists; rental transition happy path + illegal-transition refusal +
activity; contact mark-handled + double-handle refusal; nav badges; ops sees but cannot act;
roleless denied.
