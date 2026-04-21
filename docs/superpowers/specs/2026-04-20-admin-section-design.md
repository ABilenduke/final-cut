# Admin Section Design Spec

> **Date:** 2026-04-20 (revised 2026-04-21 — ADR-001 committed, spec §2.5/§2.6/§3.1 updated in lockstep with plan set)
> **Status:** Approved — plan set authored (`docs/plans/admin/v1/00-09`)
> **Scope:** Planning only. This spec defines the architecture and plan breakdown for the admin section. Implementation happens in a later cycle, plan-by-plan.
> **Plan set:** `docs/plans/admin/v1/00-index.md` (rewritten into the 9-plan structure defined by this spec)

---

## 1. Overview

Final Cut needs an admin tool so staff can manage the movie catalog, locations, auditoriums, showtimes, bookings, customers, loyalty, menu, promos, gift cards, and calendar events without shelling into `php artisan tinker`. This spec commits the architecture to **Laravel Filament 3** in a **separate top-level Laravel app**, documents the plan breakdown, and defines the testing and deployment shape.

The design deliberately isolates admin from the customer-facing apps: admin is a second Laravel app in its own top-level `admin/` directory, on its own subdomain (`admin.finalcut.test` / `admin.finalcut.com`), with its own `admin_users` table, session cookie, and deployment pipeline.

---

## 2. Architecture & Repo Topology

### 2.1 Repo layout

```
movie-theatre/
├── backend/          (existing Laravel API — unchanged by admin work)
├── frontend/         (existing Nuxt customer app — unchanged)
├── admin/            (NEW — separate Laravel 13 + Filament 3 app)
├── docker-compose.yml (+admin service, +admin.finalcut.test vhost)
└── Makefile          (+admin-shell, admin-migrate, admin-test, admin-create-user, ...)
```

### 2.2 Data sharing model

One PostgreSQL database, two Laravel apps:

- **Shared tables** (`movies`, `showtimes`, `bookings`, `users`, `locations`, `auditoriums`, `seats`, `menu_items`, `promo_codes`, `gift_cards`, `calendar_events`, etc.) are **owned by `backend/`**. All migrations for these tables live in `backend/database/migrations/`. The admin app is a read + curated-write consumer that mirrors the Eloquent models in `admin/app/Models/`.
- **Admin-only tables** (`admin_users`, `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`, `activity_log`, `loyalty_adjustments`) are owned by `admin/`. Their migrations live in `admin/database/migrations/`.

This ownership boundary prevents the two apps from stepping on each other's migrations during `make fresh`. The test database (`final_cut_test`) is shared — both apps run migrations against it, and a model-parity test (see § 6) asserts the admin Eloquent models don't drift from the backend schema.

### 2.3 Deployment topology

- Dev: `admin.finalcut.test` via nginx vhost, self-signed TLS via `make certs`
- Prod: `admin.finalcut.com` with Let's Encrypt
- Shares postgres + redis + mailpit containers
- Admin has its own PHP-FPM container (`admin` service in compose)
- Filament session cookie `admin_session` scoped to the admin subdomain — a compromised customer session cannot escalate to admin

### 2.4 Why this split works

- Backend + frontend deploy independently of admin. An admin bug cannot take down ticket sales.
- Customer API authorization surface doesn't grow — admin routes do not exist in the customer app.
- Admin can be gated behind VPN / IP allowlist in prod without affecting the customer path.

### 2.5 Accepted trade-offs

- **Eloquent model duplication (narrow scope).** The admin app mirrors a small set of models as `admin/app/Models/*` (column-level parity with timestamp/PK exposure); the canonical domain models live in the shared `packages/shared-domain/` package under `FinalCut\Domain\Models\*`. Admin mirrors stay thin — columns + relationships admin Resources need + Filament-friendly accessors. All writes go through shared-domain services (§ 2.6).
- **Two categories of drift risk:**
  - *Schema drift* — column/index/type changes land in one app but not the other. The `ModelParityTest` (§ 6.2) asserts column-level accessibility **and** `getCasts()` parity between the admin mirror and the shared-domain model.
  - *Behavioral drift* — scopes, accessors, mutators, observers, validation rules, and write-side invariants living in the shared domain but absent from admin. The parity test does not catch this. The write-boundary rule in § 2.6 (enforced mechanically by the Plan 03 Task 7 phpstan + deptrac rules) is the guardrail.
- **Shared Composer package chosen (ADR-001).** Rather than duplicate domain services across both apps or invent a cross-app shared-code mechanism, backend and admin both depend on a local Composer package at `packages/shared-domain/` (namespace `FinalCut\Domain\`) via a path-repo dependency declaration in each app's `composer.json`. Domain services, Eloquent models whose writes cross the admin/backend boundary, enums, and activity-log event classes live in this package. Backend keeps its HTTP controllers and customer-facing services outside the package; admin consumes the package as a normal Composer dependency. Rejected alternatives (synthetic `Backend\` classmap over a read-only bind mount; internal HTTP API; raw PSR-4 shared library without package metadata) are documented in Plan 03 ADR-001.

### 2.6 Write boundary: admin calls shared-domain services

Admin writes to shared tables go **through shared-domain services** (`FinalCut\Domain\Services\*` classes in `packages/shared-domain/`), not through direct Eloquent mutations from Filament Resources. This is the primary defense against behavioral drift.

**Rule:**

- **Direct Eloquent writes permitted** on: admin-owned tables (`admin_users`, `roles`, `activity_log`, `loyalty_adjustments`) and simple label/description fields on shared tables that carry no invariants (e.g., `movies.tagline`, `menu_items.description`).
- **Domain service required** for every mutation that has invariants, side effects, or related-record implications. Non-exhaustive mapping:

| Resource action | Calls shared-domain service |
|-----------------|------------------------------|
| Movie create / update / delete / enrich | `FinalCut\Domain\Services\MovieService` |
| Showtime create / update / cancel / bulk-create | `FinalCut\Domain\Services\ShowtimeService` (extracted from backend booking logic in Plan 06) |
| Seat configuration writes | `FinalCut\Domain\Services\AuditoriumService` |
| Loyalty point adjustment, tier change | `FinalCut\Domain\Services\LoyaltyService` |
| Gift card void / redeem / purchase | `FinalCut\Domain\Services\GiftCardService` |
| Promo code create / deactivate | `FinalCut\Domain\Services\PromoCodeService` |
| Menu item availability toggle | `FinalCut\Domain\Services\MenuService` (only if domain gains price-change invariants; else direct write is fine) |

**Mechanism (committed in ADR-001).** Services are invoked via normal Composer autoload. Admin's `composer.json` declares a path repository pointing at `../packages/shared-domain`, and admin's code imports `FinalCut\Domain\Services\*` classes directly. A slim `admin/app/Services/Backend/*` facade per domain service resolves `auth()->user()` into a `FinalCut\Domain\Audit\Causer` and delegates to the shared-domain service — this is where admin-side Causer resolution lives, so Filament page handlers stay ergonomic. Every shared-domain write method takes an explicit `Causer $causer` argument so the service is unambiguous about attribution regardless of whether it's called from an admin HTTP request, a customer HTTP request, or a backend scheduler task.

The previously-contemplated synthetic `Backend\App\Services\X::method()` classmap over a read-only bind mount was explicitly rejected (see Plan 03 ADR-001 "Rejected alternatives"). No shared-code decision is open — §8 reflects the committed state.

**Enforcement.** Plan 03 Task 7 ships a phpstan `disallowedMethodCalls` rule plus a deptrac layer configuration that fails CI on direct mutations to `FinalCut\Domain\Models\*` from admin Filament Resources. The write-boundary rule is mechanically enforced on every admin PR, not left as policy.

**Consequence for plan authoring:** Plans 04, 06, 07, 08 each extract their domain service into the shared package and declare an explicit `Causer` signature on every write method. The extraction sub-task within each plan references the service audit table in Plan 03 ADR-001.

### 2.7 Future migrations flagged (known retrofits)

The following are *not* in v1 but the design deliberately leaves space for them. Plan authors must avoid decisions that would make these retrofits disproportionately expensive:

- **Location-scoped roles** — add `location_id` FK on `admin_users`, location-aware policies on every Resource, location filters on list pages, location scoping on seat editor / schedule planner / menu / showtimes. Not cheap when retrofitted; design policies now so they can accept a location predicate later.
- **MFA / 2FA** — add `mfa_secret`, `mfa_enabled_at`, `recovery_codes` columns on `admin_users`; middleware between login and panel; Filament login page customization.
- **Booking write operations** (cancel, refund, seat modify) — add permissions `bookings.cancel`, `bookings.refund`, `bookings.modify`; integrate Stripe Refund API via `StripeService`; credit-memo ledger; customer-notification flow.
- **Stripe webhook integration for refund state** — currently backend confirms synchronously; booking cancellations in v2 will want webhook reconciliation.

---

## 3. Authentication & Authorization

### 3.1 `admin_users` table

Columns: `id, name, email (unique), password, remember_token, last_login_at, last_login_ip, disabled_at, created_at, updated_at`.

No `email_verified_at` column. Admin users are created explicitly via `admin:create-user` (no self-signup), so verification has no flow to gate. `canAccessPanel` instead checks `disabled_at IS NULL` — a real account kill switch per the project's "booleans as timestamps" convention (CLAUDE.md). Deactivating a terminated employee's account is `AdminUser::update(['disabled_at' => now()])`; reactivating is `['disabled_at' => null]`. Full migration and `canAccessPanel` implementation in Plan 02 Task 1.

No `role` column — roles live in the Spatie pivot (`model_has_roles`).

### 3.2 Auth stack

- `config/auth.php` guard: `admin` (session driver, `admin_users` provider)
- Filament Panel Provider at `/admin` (root of admin app — the app has no other routes)
- Filament's built-in login page — no custom auth UI work
- Session cookie `admin_session`, domain-scoped to `admin.finalcut.test`
- Session driver: Redis with `admin_session:` key prefix (no collision with customer sessions)
- MFA/2FA deferred to v2 (listed in "Won't Have")

### 3.3 Roles and permissions

Uses `spatie/laravel-permission`.

| Role | Intent | Permission set |
|------|--------|----------------|
| `admin` | Full control | `*` (wildcard) |
| `manager` | Content & operations, no financial mutations | `movies.*`, `showtimes.*`, `locations.view`, `locations.update`, `auditoriums.*`, `menu.*`, `events.*`, `promos.*`, `bookings.view`, `gift_cards.view`, `users.view`, `loyalty.view` |
| `ops` | Read-only support | `bookings.view`, `users.view`, `loyalty.view`, `gift_cards.view`, `movies.view`, `showtimes.view`, `locations.view`, `auditoriums.view` |

Permissions follow `{resource}.{action}` naming where `action` is one of `view`, `create`, `update`, `delete`, or a resource-specific verb like `movies.trigger_enrich`, `loyalty.adjust`, `gift_cards.void`. Filament Resources gate themselves via policies generated from these permissions.

**Location scoping:** none. All roles see all locations. (If future requirements demand per-location managers, we add a `location_id` to `admin_users` and location-aware policies — v2 work.)

### 3.4 Audit log

Uses `spatie/laravel-activitylog`.

- Every Filament Resource write (`create`, `update`, `delete`) logs a row in `activity_log`
- Logs capture: causer (admin_user), subject (model + id), event, before/after diff, timestamp, IP address
- Each Filament Resource has an "Activity" relation manager on its view page — admins see the history of changes to any movie, showtime, booking, etc.
- A global `/admin/activity` page lists the last 30 days of activity across all resources, filterable by causer, resource type, and date range
- Retention: 180 days, pruned by a scheduled command `activity:prune --days=180` run daily

### 3.5 What admin cannot do (by design, v1)

- Log in as a customer (no access to customer `users` password hashes — separate guard, separate table)
- Issue customer-facing API tokens
- Mark bookings as refunded or cancelled (permission does not exist in v1; UI hides the action)
- Bypass `TmdbService` for movie enrichment — admin triggers the existing `movies:enrich` command but does not have a direct TMDB code path

---

## 4. Plan Structure

### 4.1 Plan summary

| # | Plan | MoSCoW | Complexity | Depends On |
|---|------|--------|------------|------------|
| 01 | Admin app scaffold & Docker | Must | L | None |
| 02 | Auth, roles, permissions & audit log | Must | M | 01 |
| 03 | Shared Eloquent models & base resources | Must | M | 01, 02 |
| 04 | Movie catalog management | Must | M | 03 |
| 05 | Locations, auditoriums & seat editor | Must | XL | 03 |
| 06 | Showtime management | Must | XL | 04, 05 |
| 07 | Bookings, customers & loyalty | Should | M | 03, 06 |
| 08 | Menu, promo codes & gift cards | Should | M | 05 |
| 09 | Calendar events, testing & deploy | Should | L | 04–08 |

### 4.2 Dependency graph

```
01 Admin scaffold & Docker
│
02 Auth, roles, audit log
│
03 Shared Eloquent models & base resources
│
├── 04 Movie catalog
│   └── 06 Showtime management
│        └── 07 Bookings, customers, loyalty
│
├── 05 Locations, auditoriums, seats
│   ├── 06 Showtime management (also blocks on 05)
│   └── 08 Menu, promos, gift cards
│
└── 09 Calendar events, testing & deploy ←── all domain plans
```

### 4.3 Critical path

Minimum to have a usable admin: `01 → 02 → 03 → 04 → 05 → 06`. After Plan 06, staff can manage movies, locations, auditoriums, and showtimes — enough to run the business day-to-day. Plans 07–09 round out support, merchandise, and events.

### 4.4 Scope boundaries

Two categories: strategic deferrals we intend to build soon, and out-of-scope indefinitely.

**Deferred but high priority post-v1** (documented in the index with explicit "next up" status):

- **MFA / 2FA for admin login** — admin holds customer, loyalty, and gift-card access; MFA is the most obvious v1 security gap. Mitigated short-term by IP allowlist + Fail2ban, but should land as an early v2 item.
- **Booking write operations** — cancel, refund (Stripe-integrated), seat modification. Read-only in v1; manual cancellation workflow in Plan 06 documents the interim process.
- **Per-location manager scoping** — all roles see all locations in v1. Likely to be needed as the theatre operates two locations; retrofit cost is tracked in § 2.7.

**Won't have** (out of scope indefinitely, or until explicit product-driven requirement):

- Customer impersonation
- Bulk CSV import / export
- Admin-managed blog posts (blog stays in `app/data/blog.ts` until v2)
- Rate limiting beyond login endpoint (VPN / IP allowlist handles broader admin access in prod)
- Multi-tenancy / white-label support

---

## 5. Per-plan scope summary

One-paragraph summary per plan. Full task/acceptance-criteria detail lives inside each plan file.

### Plan 01 — Admin app scaffold & Docker

Create `admin/` as a fresh Laravel 13 skeleton. Add the `admin` service to `docker-compose.yml` (PHP-FPM 8.4, shares postgres + redis + mailpit). Add `admin.finalcut.test` nginx vhost with TLS. Regenerate certs via `make certs`. Wire `admin/.env.example`. Install Filament 3. Run the Filament panel installer mounted at `/admin`. Add Makefile targets: `admin-shell`, `admin-install`, `admin-migrate`, `admin-fresh`, `admin-test`. Document in `admin/README.md`. Smoke test: `make up` brings the admin service online and renders the Filament login page at `https://admin.finalcut.test/admin/login`.

### Plan 02 — Auth, roles, permissions & audit log

Create `admin_users` migration + model + factory. Install `spatie/laravel-permission` and `spatie/laravel-activitylog`. Seed three roles (`admin`, `manager`, `ops`) with the permission sets from § 3.3. Configure the Filament panel to use the `admin` guard. Add `make admin-create-user` interactive artisan command. Add `activity:prune --days=180` command and schedule it daily. Global `/admin/activity` page lists recent activity across all resources, filterable. Pest tests: permission gate enforcement (actingAsOps cannot write), audit log writes, role seeding idempotency.

### Plan 03 — Shared Eloquent models & base resources

Mirror backend models in `admin/app/Models/`: `Movie`, `Location`, `Auditorium`, `AuditoriumSection`, `Seat`, `Showtime`, `Booking`, `BookingSeat`, `BookingFoodItem`, `User`, `MenuItem`, `PromoCode`, `GiftCard`, `CalendarEvent`, `Genre`, `CastMember`. Each model mirrors backend columns + relationships and adds Filament-friendly accessors (`display_title`, `formatted_price`, etc.). Write the single Pest test `ModelParityTest` that asserts every column present in backend models is also accessible from the admin models — this is the guardrail against drift. Create `BaseResource` abstract class with audit logging defaults, currency formatters (cents → `$X.XX`), and timestamp column conventions.

### Plan 04 — Movie catalog management

`MovieResource` with full CRUD form: title, slug, status, tmdb_id, synopsis, tagline, runtime, rating, release_date, poster/backdrop URLs, trailer key, genres multi-select, cast repeater. List page with filters (status, genre) and search. Row action: "Enrich from TMDB" dispatches the existing `movies:enrich --movie-id=X` command. Bulk action: "Mark as now_showing / coming_soon". Policy wired to `movies.*` permissions. Relation manager: upcoming showtimes for this movie (read-only preview). Tests: CRUD paths, enrich action dispatches the job, policy enforcement per role.

### Plan 05 — Locations, auditoriums & seat editor

`LocationResource` (name, slug, address, phone, email, timezone, lat, lng). `AuditoriumResource` scoped to location via a relation manager on `LocationResource` — form includes name, section config (Standard / Premium / Accessible with price multipliers), and a new **`cleanup_minutes`** column (default 20) consumed by Plan 06 conflict detection. Writes go through `AuditoriumService` per § 2.6.

**Seat configuration — two-track scope:**

- **MVP (ships first, inside this plan):** a **seat-generator form** — rows count × seats-per-row + section mapping (row letters A–D = Premium, E–J = Standard, etc.) → bulk-insert `Seat` rows. Covers ~90% of real configurations without custom UX risk. Includes an `unavailable_seats` text field (comma-separated IDs like "A3,A4") for aisle gaps.
- **Visual editor (ships after MVP, still inside this plan if budget allows; otherwise deferred to a follow-up):** custom Filament page rendering the auditorium grid, click-to-toggle seat type, drag-select bulk operations. Built on top of the MVP data model so the fallback is never wasted work.

Tests: generator correctness, section pricing math, unavailable-seat masking, `AuditoriumService` call path, policy enforcement. Visual editor tests gated on whether it ships in this plan vs later.

### Plan 06 — Showtime management

`ShowtimeResource` with form (movie select, location + auditorium cascade, start_time, price_standard / premium / accessible in cents). All mutations route through `ShowtimeService` per § 2.6. Conflict detection validates `end_time = start_time + movie.runtime + auditorium.cleanup_minutes` (the buffer is per-auditorium, not hardcoded — see Plan 05) and blocks overlaps in the same auditorium. Bulk create: "Add this movie to Auditorium 1, Monday–Friday, 7pm and 9:30pm for two weeks" — calls `ShowtimeService::bulkCreate()`.

**Schedule UX — two-track scope:**

- **MVP (ships first):** standard Filament table list with date-range filter + auditorium filter, plus the bulk-create dialog. Conflict validation runs server-side. Calendar view not required for initial usability.
- **Visual schedule planner (optional second pass):** custom Filament page with per-auditorium weekly calendar, drag-to-create, click-to-edit. Built on the same service, so the MVP is forward-compatible.

**Cancellation workflow** (v1, no automatic refund):

1. "Cancel showtime" action soft-deletes the `showtimes` row via `ShowtimeService::cancel()`.
2. Every affected `booking` row gets `flagged_at = now()` set (new column, Plan 06 adds the migration to backend via coordinated service change) and a reason string `"showtime_cancelled:{showtime_id}"`.
3. Activity log writes one entry per cancellation plus one per flagged booking.
4. A queued job dispatches a **stubbed email** (Mailpit in dev, real SMTP in prod) to each affected customer: "Your showtime was cancelled. Staff will contact you about a refund." Template lives in `admin/resources/views/mail/showtime-cancelled.blade.php`.
5. Admin gets a new page at `/admin/cancelled-showtime-followup` — lists every booking with `flagged_at IS NOT NULL AND status != 'refunded'`. This is the manual finance queue; staff work through it out-of-band until Stripe refund integration lands in v2.
6. Seats from cancelled showtimes are *not* auto-released; the soft-deleted showtime is filtered from all customer queries, so seats are effectively dead.

Tests: conflict detection with per-auditorium buffer, `bulkCreate` service call path, cancellation writes flag + activity entries, follow-up page shows only pending bookings, email job dispatched per booking.

### Plan 07 — Bookings, customers & loyalty

`BookingResource` is **read-only** — lookup by confirmation code, email, or showtime. View page shows seats, food items, payment breakdown, Stripe PaymentIntent ID, customer, linked showtime. Full-text search across email + confirmation code. `UserResource` (customer users, read-only except tier + points). Loyalty actions: "Adjust points" (with required reason, logged to activity), "Upgrade to premier" (with expiry date picker), "Revoke premier". `loyalty_adjustments` table tracks manual adjustments separately from earned/redeemed ledger. Tests: search, policy enforcement, loyalty math.

### Plan 08 — Menu, promo codes & gift cards

`MenuItemResource` scoped per-location (category, allergens, dietary tags, price in cents, image, availability toggle). `PromoCodeResource` (code, discount_type enum, amount, usage limit, expires_at, active). `GiftCardResource` is read-focused — list all, search by code / recipient, view balance history. Single write action: "Void gift card" sets `status=voided`, logs reason, and alerts finance via email stub (since bookings are read-only in v1, void marks inactive without triggering a real refund). Tests: per-location menu scoping, promo validation, gift card void audit trail.

### Plan 09 — Calendar events, testing & deploy

`CalendarEventResource` (title, slug, type, date, start / end time, description, image, loyalty_only flag, accessibility tags, ticket URL). Full Pest test suite pass — every Resource has CRUD + policy tests. Deployment hardening: production-only middleware for IP allowlist (configurable via env), `.env.production.example` documenting secrets, nginx rate limit on `/admin/login` (5/min), Fail2ban rule for admin login. CI workflow update: `admin/` joins backend + frontend in the test matrix. Update root `CLAUDE.md` with admin commands and scaffold `docs/progress/admin-v1.md`.

---

## 6. Testing

Pest is the testing framework for admin, matching backend.

### 6.1 Test layout

- `admin/tests/Feature/` — Filament Resource tests using `livewire/livewire` helpers (`Livewire::test(ListMovies::class)`). Covers CRUD paths, policy enforcement, form validation, relation managers, custom pages (seat editor, schedule planner).
- `admin/tests/Unit/` — services, policies, activity log formatters.
- `admin/tests/Helpers/AdminAuthHelper.php` — trait exposing `actingAsAdmin()`, `actingAsManager()`, `actingAsOps()`, `actingAsNobody()`. Mirrors the backend's `AuthHelper` pattern.
- `admin/phpunit.xml` mirrors backend: `APP_ENV=testing`, `final_cut_test` database, `array` cache, `sync` queue, observability off.

### 6.2 Model parity test — cheap tripwire, not the primary guardrail

`admin/tests/Feature/ModelParityTest.php` loads backend + admin migrations against the test database, introspects the schema, and asserts that every column present in a backend model's table is also accessible from the admin model. Catches *schema drift* (a column added in backend but forgotten in admin) before it reaches prod.

**What it does not catch:** casts, scopes, accessors, mutators, enum handling, observers, validation rules, invariants enforced in backend services. Those are *behavioral drift* and the primary guardrail is the write-boundary rule in § 2.6 — admin mutations go through backend services, so behavior stays centralized and tested once.

Treat the parity test as a cheap, high-signal tripwire; treat § 2.6 as the real defense.

### 6.3 What we do not test

- Filament's own form-builder internals (trust the framework)
- Livewire's session / render lifecycle (trust the framework)
- Spatie Permission's pivot math (trust the package)

We test our configuration, policies, custom actions, services, and the parity guardrail.

---

## 7. Deployment

### 7.1 Infrastructure

- Production `docker-compose.prod.yml` adds the `admin` service
- Nginx prod vhost: `admin.finalcut.com` with Let's Encrypt cert
- IP allowlist middleware on all `/admin/*` routes — env-driven CIDR list, empty in dev, populated in prod
- Fail2ban jail: 5 failed logins in 10 minutes = 24 hour ban
- Rate limit: `/admin/login` capped at 5 req/min per IP via Laravel `throttle:admin-login` middleware
- Secrets: admin has its own `APP_KEY`, `SESSION_ENCRYPT=true`, session driver `redis` with prefix `admin_session:`

### 7.2 Make targets

```
admin-shell             # Shell into admin container
admin-install           # composer install + artisan key:generate + storage link
admin-migrate           # Run admin-owned migrations
admin-fresh             # Reset admin-owned tables + seed roles + create default admin
admin-create-user       # Interactive prompt to create an admin_user
admin-test              # Run Pest in admin container
admin-test-filter=X     # Targeted test run
test-all                # backend + frontend + admin (replaces `make test`)
```

### 7.3 Progress tracking

New file `docs/progress/admin-v1.md` scaffolded in Plan 01, updated per-step per project convention (status, started, completed, work done, decisions, blockers, files changed).

### 7.4 Docs updates (Plan 09)

- Root `CLAUDE.md` gets an "Admin app" section with commands, domain `admin.finalcut.test`, and the "no admin code in backend/" boundary rule
- `docs/README.md` points at `docs/plans/admin/v1/00-index.md` as the entry for admin
- `docs/plans/admin/v1/00-index.md` is rewritten from the 6-plan skeleton into a 9-plan summary + dependency graph + build phases, matching the format of `backend/v1/00-index.md` and `frontend/v1/00-index.md`

---

## 8. Open questions — resolution status

The spec's original open questions from plan-authoring phase; noted here with their resolution for historical reference. The plan set has committed answers to the shared-code, service extraction, seat-editor, loyalty approval, and finance hand-off questions.

1. ~~**Shared-code mechanism for backend services** (§ 2.6).~~ **Resolved in Plan 03 ADR-001:** shared Composer package at `packages/shared-domain/` (namespace `FinalCut\Domain\`), wired into both apps via a path repository. Synthetic-namespace classmap over a read-only bind mount was evaluated and rejected. See § 2.6 for the current wording.
2. ~~**Exact `ShowtimeService` API surface.**~~ **Resolved in Plan 06 Task 1:** extracted from `BookingController` / `SeatAvailabilityService` into the shared package, with every write method taking an explicit `Causer` and a DB-level `EXCLUDE USING gist` constraint as the authoritative conflict guard.
3. **Cancellation email template content and sender identity** — still open, Plan 06 Task 5's copy is placeholder pending marketing review. Plan 09 deployment docs finalize.
4. ~~**MVP shape of seat editor** (§ Plan 05).~~ **Resolved in Plan 05:** MVP is the seat-generator form plus a `updateSeatBatch` service method (promoted from conditional to MVP for post-opening reassignment). Visual editor stays as Could Have in Task 6.
5. ~~**Loyalty adjustment approval policy.**~~ **Resolved in Plan 07:** single-admin with activity-log traceability + configurable large-adjustment warning threshold. Narrower permissions `loyalty.adjust_points` / `loyalty.adjust_tier` (Plan 02 Task 3) replace the broad `loyalty.adjust` split originally contemplated.
6. ~~**Gift card void finance hand-off.**~~ **Resolved in Plan 08:** queued `GiftCardVoidedMail` to `config('finance.notification_email')` for v1. Dedicated finance page/export deferred to v2.

---

## 9. Next step

Invoke `writing-plans` to author the nine plan files (`01-admin-scaffold-and-docker.md` through `09-calendar-events-testing-deploy.md`) and rewrite `00-index.md` per § 7.4.
