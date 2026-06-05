# Progress: P2 hardening batch

Plan: [`docs/superpowers/plans/2026-06-05-p2-hardening-batch.md`](../superpowers/plans/2026-06-05-p2-hardening-batch.md)
Branch: `chore/p2-hardening-batch` (off `main`). One of three post-P1 hardening follow-ups.

User scope decision: **widest** ("everything actionable + infra"). The design workflow's ground-truth verification still **CUT six items** as net-negative/test-breaking (documented below) — honoring those keeps the suite green and avoids destructive changes.

## Shipped
**Status:** ✅ Complete · **Completed:** 2026-06-05

### Backend (validation + DoS guards + pins)
- **A1-A3** — enum-validate API filters → 422: `?status`/`?per_page` (MovieController, validator placed BEFORE location resolution so a bad status isn't masked by a bad-location 422), `?type`/`?accessibility` (CalendarEventController; accessibility is a closure allowlist that skips empty CSV segments to keep trailing-comma tolerance), `?category` (FoodMenuController).
- **B1-B4** — length/count guards: password `max:72` (register + reset-password; bcrypt truncation), `paymentMethodId`/`paymentIntentId` `max:512`, booking `foodItems` `max:20`, rental `guestCount` `max:1000` + `email` `max:255`, contact `email` `max:255`.
- **C** — gift-card code case-sensitivity documented on `GiftCardService::findByCode` (the real contract; codes are generated + matched uppercase — no entropy loss, doc-only).
- **E1/E3** — regression PINS only (no logic change): booking-lookup wrong-email → 404 with no `guest_email` leak; cross-location confirm → 410 + `Booking::count()` unchanged + same-location positive control.
- **K** — `AdminIpAllowlist` null-IP fail-closed pin (verified `Request::create` + removing `REMOTE_ADDR` yields `ip() === null`; the middleware was already correct — test-only).
- Tests added to MovieController/CalendarEvent/FoodMenu/Auth/Booking/Rental/Contact/AdminIpAllowlist suites.

### Frontend
- **D1** — `safeJsonLd` also escapes `>` and `&` (defense-in-depth; new `safeJsonLd.test.ts`, U+2028/U+2029 built via `String.fromCharCode` to keep the source ASCII).
- **D2** — `formatCurrency` negative pass-through + `formatCurrencyParts` docblock corrected (no `mark` key); test coverage added (no code change).
- **H1** — deleted the dead `@deprecated getShowtimes` from `useShowtimes` + its two tests.
- **I2** — `frontend/.env.example` gains `NUXT_PUBLIC_SITE_URL` + `NUXT_PUBLIC_APP_TIME_ZONE` (NOT a session var — `auth-mechanism.test.ts` forbids it).

### Infra / CI
- **F2** — Mailpit host ports bound to `127.0.0.1` (in-network `mailpit:1025` unaffected).
- **F4** — `backend-worker` / `backend-scheduler` healthchecks via `pgrep -f 'queue:work'` / `'schedule:work'` (verified `pgrep` ships in the backend image — the dossier's "no pgrep" concern was wrong for this image).
- **F5** — dropped `--passWithNoTests` from the frontend-unit CI step (zero-discovery now fails loudly).
- **HSTS `preload`** — added the directive to both nginx vhosts (the user's explicit infra choice), documented as INERT until manual submission to hstspreload.org (the irreversible go-live step).

## Cut (documented, no code) — verified net-negative
- **J ($fillable loyalty removal)** — would silently corrupt `DatabaseSeeder` `firstOrCreate` (respects `$fillable`), turning the seeded premier fixture into a 0-pt member; factories use `Model::unguarded` so factory-based tests would stay green and hide the break. Real mass-assignment risk already mitigated.
- **E2 (auth:sanctum on /bookings/{id})** — flips the named "unauthenticated → 404" test to 401 and degrades the uniform-404 enumeration convention; ownership already 404s guests in-controller.
- **I1 (/whats-on ISR routeRule)** — `whats-on-date-hydration.test.ts` pins that /whats-on is intentionally SSR-fresh; the doc (ISR 900) is the stale artifact.
- **G2 (drop users.email plain index)** — used by case-sensitive `where('email', …)` lookups; not dead weight.
- **F1-as-irreversible / Postgres `listen_addresses`** — `listen_addresses='*'` is the in-container interface (host already 127.0.0.1-locked); narrowing breaks every DB connection.

### Verification
`make fresh` survives; `docker compose config` valid (dev + prod); full backend suite green; Pint + PHPStan clean (no `env()` introduced). Frontend tests verified by the `frontend-unit` CI job (local Deno vitest is environmentally broken — known caveat).
