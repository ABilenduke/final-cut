# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Final Cut is a full-stack movie theatre web application with a Nuxt 4 frontend and Laravel 13 backend, orchestrated via Docker Compose. The domain is `finalcut.test`.

Final Cut operates **two physical theater locations** (with potential for more) sharing unified user accounts and loyalty. Showtimes, auditoriums, seats, and food menus are **location-scoped**.

## Tech Stack

- **Frontend**: Nuxt 4 (Vue 3, TypeScript), Tailwind CSS 4, Playwright e2e tests
- **Backend**: Laravel 13 (PHP 8.4), Pest testing framework
- **Database**: PostgreSQL 18 with TLS (database: `final_cut`, test db: `final_cut_test`)
- **Cache/Sessions**: Redis with TLS
- **Email (dev)**: Mailpit (SMTP capture for local email testing)
- **Infrastructure**: Docker Compose with Nginx reverse proxy, Fail2ban, TLS between all services

## Development Commands

Prefer running common workflows from the project root via Make:

```bash
make certs              # Generate SSL certs for nginx, redis, postgres (run first)
make trust-cert         # Trust CA cert in Windows (WSL2)
make up                 # Start dev environment
make down               # Stop all containers
make build              # Build containers
make shell              # Shell into backend container
make artisan list       # Run an Artisan command in the backend container
make migrate            # Run database migrations
make fresh              # Reset database with fresh migrations + seeds
make test               # Run backend + frontend test suites
make test-backend       # Run full backend test suite
make test-backend-unit  # Run backend Unit suite only
make test-backend-feature  # Run backend Feature suite only
make test-frontend      # Run frontend Vitest suite
make e2e                # Run Playwright e2e tests

# ── Admin panel (Filament on admin.finalcut.test) ─
make admin-shell        # Shell into backend container (devuser, UID 1000)
make admin-migrate      # Run migrations (same as make migrate but -u 1000)
make admin-test         # Run admin-namespaced backend tests (Feature/Admin + Unit/Admin, --filter=Admin)
make admin-create-user  # php artisan admin:create-user (added in Plan 02)
make admin-filament-assets  # Republish Filament CSS/JS/fonts to backend/public
```

Use `make test` as the default verification command for changes that touch both apps. For backend-only work, prefer the targeted `make test-backend*` commands instead of dropping into the container unless you specifically need direct PHP/Composer access.

### Admin Panel

Filament 5 admin panel lives at `https://admin.finalcut.test` (dev). Same Laravel backend as the customer app, isolated from customer routes at three layers: nginx vhost separation (`nginx/templates/conf.d/admin.conf.template`), Laravel route-domain scoping (`bootstrap/app.php` + `AdminPanelProvider`), and session cookie + Redis DB separation (`ScopeAdminSession` middleware, `session_admin` connection on Redis DB 3). WSL2 developers must add `admin.finalcut.test` to `C:\Windows\System32\drivers\etc\hosts` alongside `finalcut.test`; the wildcard dev cert already covers `*.finalcut.test`. The nginx service bind-mounts `./backend/public:/var/www/html/public:ro` so nginx can serve Filament-published assets directly without proxying through PHP-FPM. Admin auth layers on `spatie/laravel-permission` (`admin` guard, roles `admin`/`manager`/`ops` + granular permissions) and `spatie/laravel-activitylog` for the audit trail (`User` is the canonical identity for both customer and admin sides; `AdminProfile` (`admin_users` table, one-to-one keyed by `users.id`) is the entitlement row that gates admin access. The `admin` guard uses a custom `admin_eloquent` provider (`App\Auth\AdminUserProvider`) that rejects credential lookups for users without an active `AdminProfile`, so a customer's valid credentials cannot pass `Auth::guard('admin')->attempt()`; `User::canAccessPanel()` is the matching per-request check on already-authenticated sessions. Spatie morph keys are `string`-typed to support the `User` UUID PK. Activity log writes to channel `admin`; auth events log to `auth`; retention configured in `config/activitylog.php`. Provision admins with `php artisan admin:create-user` — idempotent, promotes existing customer users by attaching an `AdminProfile`). See [`docs/plans/admin/v1/`](docs/plans/admin/v1/) for the plan and [`docs/progress/admin-v1.md`](docs/progress/admin-v1.md) for execution notes.

**Admin theme (Cinematic Void brand):** The admin panel is branded via `backend/resources/css/filament/admin/theme.css` — plain CSS layered on top of Filament's compiled stylesheet, registered globally via `FilamentAsset::register([Css::make('finalcut-admin-theme', ...)], 'finalcut')` in `AppServiceProvider::boot()`. The panel's primary color is set by `Color::hex('#550000')` in `AdminPanelProvider::panel()`; Filament generates the 50–950 palette from that seed. `theme.css` mirrors `frontend/app/assets/css/tokens.css` token names (`--fc-surface-*`, `--fc-state-*`) so the eventual migration to a Vite-compiled `viteTheme()` is a 1:1 rename, not a redesign. After editing the CSS source, run `make admin-filament-assets` to republish the file to `backend/public/css/finalcut/finalcut-admin-theme.css`. State color semantics (sage success / gold warning / claret danger / steel info) are documented in `docs/design-system/DESIGN_SYSTEM.md` § State Semantics and exported as `--state-*` tokens on the customer side too.

Plan 07 admin surfaces (read-heavy Operations): `BookingResource` (read-only list/view, status column synthesizes `flagged` when `flagged_at IS NOT NULL` via `Booking::displayStatus()`); `UserResource` (read-only except for the three loyalty fields — `loyalty_points`, `loyalty_tier`, `premier_expiry` — routed through `LoyaltyService` for row-locked audit writes; `canEdit` gates on `loyalty.adjust_points || loyalty.adjust_tier` because `users.update` is intentionally not seeded); and the `BookingLookup` Operations page at `/booking-lookup` (confirmation-code case-insensitive with `CVF-` prefix optional, falls through to guest/user email).

Plan 09 admin surfaces and hardening:
- **`CalendarEventResource`** under the `Content` navigation group (`$permissionPrefix = 'events'`). Standard CRUD; uses `FileUpload` against `disk('public')` for `image_path`. The customer API derives `imageUrl` via `Storage::disk('public')->url($this->image_path)` so the wire contract is unchanged. The form `Type` Select hides `showtime` — those events are produced by the showtimes domain and must not diverge from the `showtimes` table.
- **Two-layer IP allowlist** (Layer 1: nginx `allow … ; deny all;` rendered from `ADMIN_IP_ALLOWLIST` by the entrypoint; Layer 2: `App\Http\Middleware\AdminIpAllowlist` registered as the FIRST middleware on the Filament panel so an IP-rejected request never touches `ScopeAdminSession`/Redis/auth). IPv4-only by design with explicit IPv6 rejection. **Fail-closed by default** — empty allowlist in non-local env returns 403 with error-level log. `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` is the loud escape hatch (every request logs error-level). See `backend/.env.production.example` for the deploy chicken-and-egg sequences and `docs/runbooks/admin-operations.md` for runbook procedures.
- **Admin login rate limit** at nginx (`admin_login` zone, 5 r/min, burst 3) on `location = /login`. Excess returns 429.
- **Fail2ban admin-login jail** matches Monolog `JsonFormatter` output from the dedicated `admin_auth_events` channel via `<HOST>` capture inside `context.ip`. 5 fails / 10 min → 24h ban via the existing `nginx-deny` action. CI regenerates the sample log on every run and fails if the regex stops matching.
- **Dispatch outbox** ships now: `App\Outbox\OutboxDispatcher` maps `event_type` → job, `outbox:dispatch` runs every minute (`withoutOverlapping(2)` + `runInBackground()`), `outbox:prune` runs daily (14-day retention aligned with activity_log). `backend-worker` and `backend-scheduler` compose services reuse the backend image — no separate Dockerfile.
- **Production env**: `docker-compose.prod.yml` layers admin env vars onto the existing `backend` service (no new admin service). Single Let's Encrypt cert with SAN covers primary domain + admin subdomain. `backend/.env.production.example` is the canonical inventory of every variable the backend reads in prod.

### Email (Mailpit)

Dev environment includes [Mailpit](https://mailpit.axllent.org/) for capturing outbound email. All mail sent by the backend (password resets, booking confirmations, etc.) is caught by Mailpit instead of being delivered.

- **Web UI**: http://localhost:8025 — browse and inspect captured emails
- **SMTP**: `mailpit:1025` (from within Docker network) / `localhost:1025` (from host)

Backend `.env` is pre-configured (`MAIL_HOST=mailpit`, `MAIL_PORT=1025`). No additional setup needed — `make up` starts Mailpit automatically.

### Production

```bash
make prod-build && make prod-up       # Production deployment
make local-prod-build && make local-prod-up   # Local production (includes postgres/redis)
```

### Backend (`backend/` directly, or inside `make shell` when you need direct access)

```bash
composer setup           # Install deps, create .env if missing, generate key, migrate
composer dev             # Run php artisan serve (process timeout disabled)
composer dev:queue       # Run the queue listener (process timeout disabled)
composer dev:logs        # Stream logs with Laravel Pail
composer test            # Clear config and run the full backend suite
php artisan test --filter=SomeTest   # Run a single test
php artisan pint         # Code style fixing (Laravel Pint)
```

### Frontend (inside frontend container)

```bash
npx nuxt dev             # Dev server (handled by Docker in dev mode)
npx playwright test      # Run e2e tests (prefer `make e2e` from host)
```

## Environment

Root `.env` holds Docker Compose variables (`APP_DOMAIN`, database/Redis credentials). Backend `.env` is standard Laravel plus a few API-surface toggles. Copy from `.env.example` files. Certs are domain-stamped — regenerate with `make certs` if `APP_DOMAIN` changes.

Backend env vars worth knowing:

- `BOOST_ENABLED=false` keeps browser-facing Laravel Boost routes disabled in local API development
- `BOOST_BROWSER_LOGS_WATCHER=false` keeps the browser logs watcher off unless intentionally enabled
- `DEFAULT_LOCATION_TIMEZONE` seeds the IANA timezone applied to newly created `Location` rows when the admin form leaves the field blank. Read through `config('app.default_location_timezone')`; falls back to `config('app.timezone')` (then UTC) when unset. Existing locations are never mutated — this only affects defaults at create-time
- `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` (default `1000`) is the absolute points delta at or above which the admin **Adjust Points** action surfaces an elevated confirmation modal. Read through `config('loyalty.large_adjustment_threshold')`. The threshold + the audit log are v1's compensating controls for large-delta corrections; dual-control approval is deferred to v2
- `FINANCE_NOTIFICATION_EMAIL` (default `finance@finalcut.test`) is the recipient for admin-triggered finance events — currently the gift-card void notification (Plan 08). Read through `config('finance.notification_email')`. In dev, Mailpit captures these; in prod, point this at an internal distribution list with appropriate access controls because the payload includes the recipient email and remaining balance (PII)

`backend/phpunit.xml` forces the backend test environment to stay isolated: `APP_ENV=testing`, PostgreSQL points at `final_cut_test`, cache/session use in-memory array drivers, the queue is `sync`, and observability tooling like Pulse, Telescope, and Nightwatch is disabled during tests.

### Dev Containers & File Permissions

Dev containers run as a `devuser` whose UID/GID matches the host user, avoiding bind-mount permission conflicts. Both the backend and frontend Dockerfiles create this user in their `development` stage using build args:

```
DEV_UID: ${DEV_UID:-1000}
DEV_GID: ${DEV_GID:-1000}
```

These default to `1000` (standard first user on Linux/WSL). If your host UID differs, set `DEV_UID` and `DEV_GID` in the root `.env` file before building.

**Backend:** PHP-FPM workers run as `devuser` (not `www-data`). A `dev-entrypoint.sh` script starts as root to fix ownership on `storage/` and `bootstrap/cache/` dirs that may have stale permissions, then exec's `php-fpm`. The vendor directory uses a named Docker volume (`backend-vendor`) owned by `devuser`.

**Frontend:** Deno runs as `devuser`. The Deno cache directory is a named volume (`frontend-deno-cache`) mounted at `/home/devuser/.cache/deno`.

**Hooks note:** Any `docker exec` commands in hooks (e.g., running Pint) must use `-u 1000` to match the `devuser` UID inside the container.

## Key Domain Concepts

See @docs/architecture/DATA_MODELS.md for full schema. Core entities: Movie (auto-increment PK, optional `tmdb_id` for enrichment, `slug` for URLs), Location, Auditorium (aka "Screen"), AuditoriumSection (Standard/Premium/Accessible pricing zones), Seat (row letter + number), Showtime (Movie + Auditorium, pricing in cents), Booking (human-readable confirmationCode like "CVF-A3X9K2"), User (loyaltyTier, loyaltyPoints), GiftCard.

**Loyalty**: Two tiers — member (free, 1 pt/$1) and premier (paid annual, 10% food discount, birthday ticket, early seat access). Guest checkout offers post-purchase registration via magic link. See @docs/plans/backend/v1/06-account-api.md.

**Movie Catalog & TMDB Enrichment**: The theatre owns its movie catalog — movies are created locally with title, slug, status, and optional `tmdb_id`. TMDB is **enrichment-only**, never in the request path. The scheduled command `movies:enrich` (runs hourly via Laravel scheduler) calls `TmdbService` to backfill metadata (synopsis, cast, images, trailer, ratings) for movies that have a `tmdb_id`. Enrichment results are cached 24 hours; failures are cached 5 minutes to avoid hammering. API responses serve only local DB data. See @docs/plans/backend/v1/03-movie-api.md.

## Design Decisions

- **Styling**: Use `rem` units (not `px`), except where technically required (borders, shadows, sub-pixel)
- **CSS**: CSS custom properties for theming (no CSS-in-JS). See @docs/design-system/DESIGN_SYSTEM.md for tokens, typography, and component specs
- **Color tokens**: `#FFB4A8` (primary) is a **text-on-dark color only**. `#550000` (primary_container) is the **fill color** for buttons, active states, hero accents. Tokens use underscores in docs (`primary_container`) but hyphens in CSS (`--primary-container`)
- **Booleans as timestamps**: Prefer nullable timestamps over booleans when the column represents a state transition (e.g., `unavailable_at` instead of `available`). This provides free metadata about *when* the state changed. Keep plain booleans for classification flags that don't represent events (e.g., `loyalty_only`)
- **Pre-launch migrations (edit in place)**: While the project is pre-launch, schema changes edit the original migration file rather than adding an additive migration. This keeps the schema history coherent before any external environment depends on it. **Pre-launch ends the first time any migration runs against an environment outside a developer's laptop** — staging, QA, shared CI with a persistent database, or production. Once ended, migrations become additive (`YYYY_MM_DD_HHMMSS_add_column_*.php`) and the in-place rule no longer applies. If you are unsure whether pre-launch has ended, default to additive — the cost of an extra migration file is trivial; the cost of rewriting a migration someone else has already run is not
- **Currency**: All monetary values (prices, totals, discounts, balances) are stored, calculated, and transmitted as **positive integers in cents** (USD only). `$12.99` = `1299`. This follows Stripe's standard and avoids floating-point errors. Never use floats for money. The frontend `formatCurrency` utility converts cents to display strings. API responses return cents; the client formats for display
- **Payments**: Stripe integration via `stripe/stripe-php` SDK. `StripeService` wraps `StripeClient` for PaymentIntent creation/confirmation. Configured via `STRIPE_SECRET_KEY` and `STRIPE_PUBLISHABLE_KEY` env vars in backend `.env` (mapped through `config/services.php`). Tests use `FakeStripeService` (in `tests/Helpers/`) which skips the real Stripe client — no API keys needed to run the test suite
- **Auth**: nuxt-auth-utils
- **Rendering**: `routeRules` in `nuxt.config.ts` control per-route rendering strategy — ISR for blog (`/blog/**`), prerender for static pages (`/contact`, `/faq`, `/accessibility`, `/careers`). See @docs/architecture/SITE_ARCHITECTURE.md for the full route map
- **Blog content**: Static TypeScript data in `app/data/blog.ts` (placeholder — will be replaced by admin-managed API content)
- **Commits**: conventional commits (`feat:`, `fix:`, `docs:`, etc.)

## Development Methodology

This project follows **spec-driven development** with **test-driven development (TDD)**:

1. **Spec first** — Review or write the relevant design doc / plan before writing implementation code
2. **Tests first** — Write failing tests that codify the spec's requirements before implementing the feature
3. **Implement to pass** — Write the minimum code to make tests pass
4. **Refactor** — Clean up while keeping tests green

### Progress Tracking

When executing any implementation plan, maintain a **progress journal** in `docs/progress/`. Use `docs/progress/backend-v1.md` for backend plans and `docs/progress/frontend-v1.md` for frontend plans. These files are checked into the repo and persist across sessions.

**Format per step:**

```markdown
## Step N: [Step Name]
**Status:** 🔲 Not Started | 🟡 In Progress | ✅ Complete | ⛔ Blocked
**Started:** YYYY-MM-DD
**Completed:** —

### Work Done
- [date] Description of what was implemented

### Decisions
- [date] Decision made and why

### Blockers
- [date] Blocker description → resolution

### Files Changed
- `path/to/file.ext` — what changed
```

**Rules:**

- Create the relevant progress file at the start of plan execution if it doesn't exist
- Update the journal as work progresses — don't batch updates at the end
- Log decisions and blockers in real time so future sessions have full context
- Mark steps complete only after verification passes

### Testing Requirements

- **Backend**: All tests **must** use [Pest](https://pestphp.com/) (not raw PHPUnit). Prefer `make test-backend` from the project root, or `make test-backend-unit` / `make test-backend-feature` for targeted runs. `composer test` is the backend-local equivalent when working directly in `backend/` or inside the backend container. Uses `RefreshDatabase` trait for isolation against `final_cut_test`. See @docs/plans/backend/v1/08-testing-and-seeding.md.
- **Backend test helpers**: `AuthHelper` trait (`actingAsUser()`, `actingAsPremierUser()`, `actingAsGuest()`) and `StripeHelper` trait (`mockStripeSuccess()`, `mockStripeDeclined()`, `mockStripe3DS()`).
- **Frontend unit/component**: All tests **must** use [Vitest](https://vitest.dev/) with `@nuxt/test-utils`. Prefer `make test-frontend` from the project root, or `npx vitest` inside the frontend container.
- **Frontend E2E**: Playwright. Run with `make e2e` from host or `npx playwright test` inside the frontend container.
- **No untested features** — Every new backend endpoint, service, or model behavior requires Pest tests. Every frontend component, composable, and user-facing flow requires Vitest and Playwright coverage.
- **Zero failing tests** — The full test suite must pass before any work is considered complete. Never skip, ignore, or defer failing tests. If a change causes a test failure, fix it before moving on. Run `make test` for cross-stack changes, or the relevant targeted command (`make test-backend`, `make test-backend-unit`, `make test-backend-feature`, `make test-frontend`, `make e2e`) after every meaningful change.

## Documentation

- @docs/README.md — Documentation navigation index
- @docs/architecture/SITE_ARCHITECTURE.md — Overall app structure and routing
- @docs/architecture/DATA_MODELS.md — Database schema and relationships
- @docs/architecture/STATE_MANAGEMENT.md — Frontend state architecture
- @docs/design-system/DESIGN_SYSTEM.md, @docs/design-system/DESIGN_SYSTEM_IMPLEMENTATION.md, @docs/design-system/DESIGN_SYSTEM_STRUCTURE.md — Design tokens, components, patterns
- @docs/specs/COMPONENT_INVENTORY.md — UI component catalog
- @docs/specs/PAGE_SPECS.md — Page-level specifications
- @docs/specs/PURCHASE_FLOW.md — Ticket purchase flow

## Common Pitfalls

- NEVER use `#FFB4A8` as a background/fill color — it is text-on-dark-maroon only. See @docs/design-system/DESIGN_SYSTEM.md Token Mapping.
- NEVER use `px` for spacing/sizing — use `rem` exclusively (exception: borders, shadows, sub-pixel).
- NEVER create API routes without corresponding Pest tests.
- NEVER hardcode a single location — all showtime, auditorium, seat, and menu queries must be location-scoped.
- NEVER call TMDB in the request path — API responses serve local DB data only. TMDB enrichment happens offline via `movies:enrich`.
- NEVER use floats or decimals for monetary values — all prices, totals, discounts, and balances are integers in cents.
- NEVER leave failing tests — if a change breaks a test, fix it immediately. No work is done until the full suite is green.
