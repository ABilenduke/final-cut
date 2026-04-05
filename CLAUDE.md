# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Final Cut is a full-stack movie theatre web application with a Nuxt 4 frontend and Laravel 13 backend, orchestrated via Docker Compose. The domain is `finalcut.test`.

Final Cut operates **two physical theater locations** (with potential for more) sharing unified user accounts and loyalty. Showtimes, auditoriums, seats, and food menus are **location-scoped**.

## Tech Stack

- **Frontend**: Nuxt 4 (Vue 3, TypeScript), Tailwind CSS 4, Storybook, Playwright e2e tests
- **Backend**: Laravel 13 (PHP 8.4), Pest testing framework
- **Database**: PostgreSQL with TLS (database: `final_cut`, test db: `final_cut_test`)
- **Cache/Sessions**: Redis with TLS
- **Email (dev)**: Mailpit (SMTP capture for local email testing)
- **Infrastructure**: Docker Compose with Nginx reverse proxy, Fail2ban, TLS between all services

## Development Commands

All commands run from the project root via Make:

```bash
make certs              # Generate SSL certs for nginx, redis, postgres (run first)
make trust-cert         # Trust CA cert in Windows (WSL2)
make up                 # Start dev environment (includes postgres, redis, storybook)
make down               # Stop all containers
make build              # Build containers
make shell              # Shell into backend container
make migrate            # Run database migrations
make fresh              # Reset database with fresh migrations + seeds
make storybook          # Run Storybook inside its container
make e2e                # Run Playwright e2e tests
```

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

### Backend (inside container via `make shell`)

```bash
composer test            # Run Pest tests (clears config first)
php artisan test --filter=SomeTest   # Run a single test
php artisan pint         # Code style fixing (Laravel Pint)
```

### Frontend (inside frontend container)

```bash
npx nuxt dev             # Dev server (handled by Docker in dev mode)
npx playwright test      # Run e2e tests (prefer `make e2e` from host)
```

## Environment

Root `.env` holds Docker Compose variables (`APP_DOMAIN`, database/Redis credentials). Backend `.env` is standard Laravel. Copy from `.env.example` files. Certs are domain-stamped — regenerate with `make certs` if `APP_DOMAIN` changes.

## Key Domain Concepts

See @docs/DATA_MODELS.md for full schema. Core entities: Movie (auto-increment PK, optional `tmdb_id` for enrichment, `slug` for URLs), Location, Auditorium (aka "Screen"), AuditoriumSection (Standard/Premium/Accessible pricing zones), Seat (row letter + number), Showtime (Movie + Auditorium, pricing in cents), Booking (human-readable confirmationCode like "CVF-A3X9K2"), User (loyaltyTier, loyaltyPoints), GiftCard.

**Loyalty**: Two tiers — member (free, 1 pt/$1) and premier (paid annual, 10% food discount, birthday ticket, early seat access). Guest checkout offers post-purchase registration via magic link. See @docs/plans/backend/05-loyalty-system.md.

**Movie Catalog & TMDB Enrichment**: The theatre owns its movie catalog — movies are created locally with title, slug, status, and optional `tmdb_id`. TMDB is **enrichment-only**, never in the request path. The scheduled command `movies:enrich` (runs hourly via Laravel scheduler) calls `TmdbService` to backfill metadata (synopsis, cast, images, trailer, ratings) for movies that have a `tmdb_id`. Enrichment results are cached 24 hours; failures are cached 5 minutes to avoid hammering. API responses serve only local DB data. See @docs/plans/backend/03-movie-api.md.

## Design Decisions

- **Styling**: Use `rem` units (not `px`), except where technically required (borders, shadows, sub-pixel)
- **CSS**: CSS custom properties for theming (no CSS-in-JS). See @docs/DESIGN_SYSTEM.md for tokens, typography, and component specs
- **Color tokens**: `#FFB4A8` (primary) is a **text-on-dark color only**. `#550000` (primary_container) is the **fill color** for buttons, active states, hero accents. Tokens use underscores in docs (`primary_container`) but hyphens in CSS (`--primary-container`)
- **Booleans as timestamps**: Prefer nullable timestamps over booleans when the column represents a state transition (e.g., `unavailable_at` instead of `available`). This provides free metadata about *when* the state changed. Keep plain booleans for classification flags that don't represent events (e.g., `loyalty_only`)
- **Currency**: All monetary values (prices, totals, discounts, balances) are stored, calculated, and transmitted as **positive integers in cents** (USD only). `$12.99` = `1299`. This follows Stripe's standard and avoids floating-point errors. Never use floats for money. The frontend `formatCurrency` utility converts cents to display strings. API responses return cents; the client formats for display
- **Payments**: Stripe integration via `stripe/stripe-php` SDK. `StripeService` wraps `StripeClient` for PaymentIntent creation/confirmation. Configured via `STRIPE_SECRET_KEY` and `STRIPE_PUBLISHABLE_KEY` env vars in backend `.env` (mapped through `config/services.php`). Tests use `FakeStripeService` (in `tests/Helpers/`) which skips the real Stripe client — no API keys needed to run the test suite
- **Auth**: nuxt-auth-utils
- **Commits**: conventional commits (`feat:`, `fix:`, `docs:`, etc.)

## Development Methodology

This project follows **spec-driven development** with **test-driven development (TDD)**:

1. **Spec first** — Review or write the relevant design doc / plan before writing implementation code
2. **Tests first** — Write failing tests that codify the spec's requirements before implementing the feature
3. **Implement to pass** — Write the minimum code to make tests pass
4. **Refactor** — Clean up while keeping tests green

### Progress Tracking

When executing any implementation plan, maintain a **progress journal** at `docs/PROGRESS.md`. This file is checked into the repo and persists across sessions.

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

- Create `docs/PROGRESS.md` at the start of plan execution if it doesn't exist
- Update the journal as work progresses — don't batch updates at the end
- Log decisions and blockers in real time so future sessions have full context
- Mark steps complete only after verification passes

### Testing Requirements

- **Backend**: All tests **must** use [Pest](https://pestphp.com/) (not raw PHPUnit). Run with `composer test` inside the backend container. Uses `RefreshDatabase` trait for isolation against `final_cut_test`. See @docs/plans/backend/08-testing-and-seeding.md.
- **Backend test helpers**: `AuthHelper` trait (`actingAsUser()`, `actingAsPremierUser()`, `actingAsGuest()`) and `StripeHelper` trait (`mockStripeSuccess()`, `mockStripeDeclined()`, `mockStripe3DS()`).
- **Frontend unit/component**: All tests **must** use [Vitest](https://vitest.dev/) with `@nuxt/test-utils`. Run with `npx vitest` inside the frontend container.
- **Frontend E2E**: Playwright. Run with `make e2e` from host or `npx playwright test` inside the frontend container.
- **No untested features** — Every new backend endpoint, service, or model behavior requires Pest tests. Every frontend component, composable, and user-facing flow requires Vitest and Playwright coverage.
- **Zero failing tests** — The full test suite must pass before any work is considered complete. Never skip, ignore, or defer failing tests. If a change causes a test failure, fix it before moving on. Run `composer test` (backend) or the relevant test command after every meaningful change.

## Documentation

- @docs/SITE_ARCHITECTURE.md — Overall app structure and routing
- @docs/DATA_MODELS.md — Database schema and relationships
- @docs/DESIGN_SYSTEM.md, @docs/DESIGN_SYSTEM_IMPLEMENTATION.md, @docs/DESIGN_SYSTEM_STRUCTURE.md — Design tokens, components, patterns
- @docs/COMPONENT_INVENTORY.md — UI component catalog
- @docs/PAGE_SPECS.md — Page-level specifications
- @docs/PURCHASE_FLOW.md — Ticket purchase flow
- @docs/STATE_MANAGEMENT.md — Frontend state architecture

## Common Pitfalls

- NEVER use `#FFB4A8` as a background/fill color — it is text-on-dark-maroon only. See @docs/DESIGN_SYSTEM.md Token Mapping.
- NEVER use `px` for spacing/sizing — use `rem` exclusively (exception: borders, shadows, sub-pixel).
- NEVER create API routes without corresponding Pest tests.
- NEVER hardcode a single location — all showtime, auditorium, seat, and menu queries must be location-scoped.
- NEVER call TMDB in the request path — API responses serve local DB data only. TMDB enrichment happens offline via `movies:enrich`.
- NEVER use floats or decimals for monetary values — all prices, totals, discounts, and balances are integers in cents.
- NEVER leave failing tests — if a change breaks a test, fix it immediately. No work is done until the full suite is green.
