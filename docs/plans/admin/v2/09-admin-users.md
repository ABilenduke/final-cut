# Plan 09 — AdminUserResource (staff management)

## Goal

Manage admin staff in-panel instead of via `php artisan admin:create-user`. The
`admin_users.*` permissions have been seeded since v1 with no UI — this closes that gap.

## Discovery that shrank the plan

`admin_profiles.disabled_at` already exists and is fully enforced: `User::isAdmin()`
requires `disabled_at IS NULL`, and `AdminUserProvider` re-checks on `retrieveById`, so a
disabled admin's live session dies on the next request. No schema change; the resource only
drives the existing flag.

## Design

- **`AdminUserService`** (write boundary, all activity-logged with actor):
  `provision()` mirrors the command's create-or-promote semantics (existing customer email →
  promote without touching their password unless one is given; new email → name+password
  required); `assignRole()` uses `syncRoles` (replace, not layer); `disable()`/`enable()`
  toggle `disabled_at`. Safety invariants live in the service (`AdminUserException`):
  **you cannot change your own role and cannot disable yourself** — the last-working-admin
  lockout scenario, plus the audit-integrity rule that privilege changes need a second pair
  of hands.
- **`AdminUserResource`** (`$permissionPrefix = 'admin_users'`, System group): query scoped
  to `User::whereHas('adminProfile')`. **Read-only rows** — identity (name/email/password)
  belongs to the shared customer account and is not edited from here. Mutations are
  actions: header `provision` (admin_users.create), per-row `assign_role` / `disable` /
  `enable` (admin_users.update, hidden on self). Status + role badges, last-login column.
  Only the admin role holds `admin_users.*` (manager/ops lists never included them).

## Tests (`AdminUserResourceTest`)

Provision new + promote existing (password preserved); self-role and self-disable refused
at the service and hidden in the UI; disabled admin fails `isAdmin()` and provider
`retrieveById` (session-rejection path); customers absent from the list; manager/ops denied.
