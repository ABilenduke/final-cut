# Admin Operations Runbook

Production operational procedures for the Final Cut admin panel
(`admin.finalcut.com`). This runbook covers the most common
operations encountered during normal operation and incident response.

> **Conventions:** Commands shown without a `cd` prefix run from the
> production deploy host's project root. `<HOST>` placeholders are
> per-environment (e.g. `admin.finalcut.com`). All commands assume a
> working `docker compose` stack started with the prod compose file:
> `docker compose -f docker-compose.yml -f docker-compose.prod.yml`.

---

## 1. Create a new admin user

`make admin-create-user` is the canonical entry point. The command
prompts for email, password, and role; the password is hashed before
the row is written. Roles are `admin`, `manager`, or `ops`.

```bash
make admin-create-user
```

The Plan 02 `CreateAdminUser` command also accepts non-interactive
flags for automation:

```bash
docker compose exec -u 1000 -it backend \
  php artisan admin:create-user --email=ops@finalcut.com --role=ops
```

---

## 2. Unban a legitimate IP

The Fail2ban `admin-login` jail bans IPs after 5 failed logins in 10
minutes for 24 hours. To release an IP early:

```bash
docker compose exec fail2ban \
  fail2ban-client set admin-login unbanip 203.0.113.42
```

To audit currently banned IPs across all jails:

```bash
docker compose exec fail2ban fail2ban-client banned
```

---

## 3. View activity log

Audit trail lives in the `activity_log` table and is rendered by the
admin panel's Activity Log page (visible to roles that hold
`activity.view`).

- URL: `https://admin.finalcut.com/activity`
- Retention: 14 days (configured in `config/activitylog.php`,
  enforced by the `activitylog:clean` daily schedule)

---

## 4. Process the gift card void mail queue

Gift card voids are irreversible operations. The void writes a
`dispatch_outbox` row with `event_type = 'gift_card.voided'`; the
`outbox:dispatch` worker drains it and dispatches
`NotifyFinanceOfGiftCardVoid` which mails `FINANCE_NOTIFICATION_EMAIL`.

There is no automated tooling in v1 to re-deliver to a different
recipient. If finance reports a missed notification, look at recent
gift_card.voided rows — `processed_at` IS NULL on pending or parked
(`failed_at`-set) rows, so filter on `created_at` rather than
`processed_at` so neither category is missed:

```bash
docker compose exec -u 1000 backend \
  php artisan tinker --execute "App\Models\DispatchOutbox::where('event_type','gift_card.voided')->where('created_at','>',now()->subDays(2))->get(['id','attempts','processed_at','last_error','failed_at'])"
```

A row with `failed_at IS NOT NULL` means the worker gave up after
`MAX_ATTEMPTS`. Investigate `last_error`, fix the underlying issue,
then null out `failed_at` and `attempts` to retry — the worker will
pick it up on the next tick:

```bash
docker compose exec -u 1000 backend \
  php artisan tinker --execute "App\Models\DispatchOutbox::find(<id>)->update(['failed_at' => null, 'attempts' => 0, 'last_error' => null]);"
```

---

## 5. Process cancelled showtime follow-up

Cancellation emails are dispatched the same way (outbox →
`NotifyCustomerOfShowtimeCancellation`). The admin panel exposes a
follow-up queue page for ops to mark non-email contact attempts:

- URL: `https://admin.finalcut.com/cancelled-showtime-followup`

---

## 6. Adjust loyalty points for a single customer

Done in the admin panel via the user's view page → **Adjust Points**
action. Adjustments are written through `LoyaltyService::adjustPoints`
(row-locked, audit-logged via activity_log).

Deltas at or above `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` (default
`1000` in cents-equivalent units) surface an elevated confirmation
modal. The modal + audit log are the v1 compensating controls; v2
adds dual-control approval for large deltas.

---

## 7. Trigger TMDB enrichment for a single movie

In the admin panel: `MovieResource` → row action **Enrich from TMDB**.
The action calls `TmdbService::enrich` synchronously; on failure
the action surfaces the error inline.

To enrich the entire catalog manually:

```bash
docker compose exec -u 1000 backend php artisan movies:enrich
```

The command also runs hourly via the scheduler (`movies:enrich`).

---

## 8. Rotate an admin password

**Preferred** (production mail driver verified working): the user
clicks "Forgot password?" on the admin login page; the reset link is
delivered via the configured `MAIL_*` driver. Confirm with a test
reset before relying on this — a misconfigured `MAIL_*` driver fails
silently from the user's perspective.

**Fallback** (mail not yet production-grade): re-hash the password on
the existing account via the `--reset-password` flag on
`admin:create-user`:

```bash
docker compose exec -u 1000 -it backend \
  php artisan admin:create-user \
    --email=user@finalcut.com \
    --password='new-strong-password' \
    --reset-password
```

The command errors clearly when the email doesn't match an existing
account.

**Last-resort** (incident response only): drop into tinker. Tinker
leaves no audit trail, so log the incident afterward (`activity('auth')`
write or runbook ticket).

```bash
docker compose exec -u 1000 -it backend php artisan tinker
>>> App\Models\AdminUser::where('email','user@finalcut.com')->first()->update(['password' => Hash::make('new-strong-password')])
```

---

## 9. Disable an admin account

Set the `disabled_at` timestamp on the target row. `AdminUser::canAccessPanel`
(Plan 02) reads this on every request, so the account loses panel
access on the very next request — no cache bust or session purge
required.

If an `AdminUserResource` has shipped, do this in the panel. Until
then, tinker:

```bash
docker compose exec -u 1000 -it backend php artisan tinker
>>> App\Models\AdminUser::where('email','x@finalcut.com')->update(['disabled_at' => now()])
```

Re-enable with `['disabled_at' => null]`.

---

## 10. Emergency: disable the admin panel entirely

Set the IP allowlist to localhost-only and redeploy:

```
ADMIN_IP_ALLOWLIST=127.0.0.1/32
ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false
```

Both layers (nginx + Laravel middleware) will then refuse every
request that isn't from the production host itself. This is the
documented intent for "disable the admin panel"; relying on an empty
allowlist is functionally equivalent (fail closed) but less explicit
in the env config.

To re-enable after the incident, restore the real allowlist and
redeploy.

---

## Deploy chicken-and-egg: bootstrap sequences

Because the IP allowlist fails closed when empty, you cannot "deploy
with no allowlist and fill it in later" — the first request from any
IP returns 403.

### Preferred sequence

Set `ADMIN_IP_ALLOWLIST` to the real CIDR list **before** the first
production request. Keep `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false`.
This is the resting state for production.

### Emergency bootstrap (when CIDRs aren't known until after deploy)

1. Deploy with `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` and an empty
   `ADMIN_IP_ALLOWLIST`.
2. Verify admin access from an expected IP. Every request during this
   window is logged at error level.
3. Set the real `ADMIN_IP_ALLOWLIST`.
4. Flip `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` back to `false`.
5. Restart the backend service.
6. Confirm logs no longer carry the EMERGENCY_OPEN warning.

The runbook reviewer must verify step 4 before closing the deploy
ticket — leaving the flag on is the most expensive mistake this
runbook can prevent.

---

## v1 scope reminders

- **IPv4-only**: the Layer-2 `AdminIpAllowlist` middleware, the nginx
  Layer-1 allow/deny block, and the Fail2ban `admin-login` jail are
  all IPv4-only. Adding IPv6 ingress requires updating all three in
  the same change.
- **MFA / 2FA is deferred to v2**. The IP allowlist + Fail2ban + login
  rate limit are the v1 mitigations; treat the admin subdomain as
  defence-in-depth, not a single security boundary.
- **Booking write operations** (cancel, refund, seat modification)
  are read-only in v1. The interim manual cancellation workflow lives
  in `docs/plans/admin/v1/06-showtime-management.md`.
- **No customer impersonation**, no bulk CSV import/export, no
  admin-managed blog posts.
