# Admin Section Design Spec

> **Date:** 2026-04-20
> **Status:** Approved — awaiting plan authoring
> **Scope:** Planning only. This spec defines the architecture and plan breakdown for the admin section. Implementation happens in a later cycle, plan-by-plan.
> **Predecessor:** `docs/plans/admin/v1/00-index.md` (6-plan skeleton — to be rewritten into 9 plans per this spec)

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

- **Eloquent model duplication.** Models appear in both `backend/app/Models/` and `admin/app/Models/`. Mitigation: admin models stay thin (mirror columns + relationships, add Filament-friendly accessors only). A Pest test in `admin/tests/Feature/ModelParityTest.php` asserts column parity against the canonical migrations.
- **No shared code via package.** We could factor shared models into a `packages/shared` Composer package, but that's premature. Duplication is acceptable until a second consumer appears.

---

## 3. Authentication & Authorization

### 3.1 `admin_users` table

Columns: `id, name, email (unique), password, email_verified_at, remember_token, last_login_at, last_login_ip, created_at, updated_at`.

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
| 05 | Locations, auditoriums & seat editor | Must | L | 03 |
| 06 | Showtime management | Must | L | 04, 05 |
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

### 4.4 Won't have in v1

Documented in the v1 index:

- Booking refunds / cancellations (read-only in v1)
- MFA / 2FA for admin login
- Customer impersonation
- Bulk CSV import / export
- Admin-managed blog posts (blog stays in `app/data/blog.ts` until v2)
- Rate limiting beyond login endpoint (VPN / IP allowlist handles broader admin access in prod)
- Multi-tenancy / white-label support
- Per-location manager scoping (all roles see all locations in v1)

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

`LocationResource` (name, slug, address, phone, email, timezone, lat, lng). `AuditoriumResource` scoped to location via a relation manager on `LocationResource` — form includes name, section config (Standard / Premium / Accessible with price multipliers). **Seat grid editor** is the complex piece: a custom Filament page (not a Resource) that renders a visual auditorium grid, lets admins click-and-drag to set seat type per cell, mark seats unavailable (aisle / gap), and bulk-update sections. Persists as `Seat` rows with row letter + number + section + type. Tests: grid persistence, section pricing math, unavailable seat masking, policy enforcement.

### Plan 06 — Showtime management

`ShowtimeResource` with form (movie select, location + auditorium cascade, start_time, price_standard / premium / accessible in cents). **Schedule planner** custom page: calendar view per-auditorium showing the week's showtimes; drag to create, click to edit. Conflict detection validates `end_time = start_time + movie.runtime + 20min cleanup` and blocks overlaps in the same auditorium. Bulk create: "Add this movie to Auditorium 1, Monday–Friday, 7pm and 9:30pm for two weeks" — dispatches a `ShowtimeBulkCreator` service. Cancel action soft-deletes and flags affected bookings (but does not refund — read-only in v1). Tests: conflict detection, bulk create date/time math, cancel flow.

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

### 6.2 Model parity test

`admin/tests/Feature/ModelParityTest.php` is the crown jewel. It loads backend + admin migrations against the test database, introspects the schema, and asserts that every column present in a backend model's table is also accessible from the admin model. Catches schema drift before it reaches prod.

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

## 8. Open questions

None. All architectural decisions captured during brainstorming are frozen in this spec.

---

## 9. Next step

Invoke `writing-plans` to author the nine plan files (`01-admin-scaffold-and-docker.md` through `09-calendar-events-testing-deploy.md`) and rewrite `00-index.md` per § 7.4.
