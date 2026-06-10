# Plan 08 — Dispatch-outbox ops surface

## Goal

Close the audit's "parked outbox rows are CLI-only" gap: a read-only admin surface over
`dispatch_outbox` with a Retry/un-park action, complementing the dashboard's OpsHealth
counts (Step 1.7) with the actual rows. Admin-role only — outbox rows expose event payloads
(emails, ids), and retrying is an ops-level action.

## Design

- **`OutboxRetryService::retry(DispatchOutbox $row, User $actor)`** — write boundary:
  refuses non-parked rows (`OutboxRetryException`: only `failed_at IS NOT NULL` rows are
  retryable — pending rows are already the worker's job, processed rows are done), then in
  one transaction resets `attempts = 0`, `failed_at = null`, `last_error = null`,
  `available_at = now()` and logs `outbox.retried` activity. The next `outbox:dispatch`
  tick picks the row up via the unchanged `dispatchable()` scope.
- **`DispatchOutboxResource`** (`$permissionPrefix = 'outbox'`, System nav group, read-only:
  create/edit/delete all false): list with derived status badge (pending / parked /
  processed), event_type, attempts, timestamps, truncated last_error; status filter;
  nav badge = parked count; Retry record action (visible on parked rows with
  `outbox.retry`); View page exposing the full payload for diagnosis.
- **Permissions**: `outbox.view` + `outbox.retry` added to the master PERMISSIONS list only
  (the admin role syncs everything; manager/ops lists untouched → admin-only by
  construction).

## Tests (`tests/Feature/Admin/Resources/DispatchOutboxResourceTest.php`)

Admin lists rows + parked nav badge; manager and ops are denied (`canViewAny` false / 403);
retry resets a parked row, logs activity, and the next `outbox:dispatch` run processes it
end-to-end (Bus::fake against a real event type); pending and processed rows are refused by
the service and show no Retry action.
