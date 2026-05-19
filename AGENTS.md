# Final Cut Agent Instructions

This repository is Final Cut, a full-stack movie theatre application with a Nuxt 4 frontend and a Laravel 13 backend. These instructions are project-local and override the global Cylinder/Codex guidance when working in this repo.

## Project Profile

- Frontend: Nuxt 4, Vue 3 Composition API, TypeScript, Tailwind CSS 4, Vitest, Playwright.
- Backend: Laravel 13, PHP 8.4, Filament 5 admin, Pest, PostgreSQL 18, Redis, Stripe.
- Runtime: Docker Compose with Nginx, TLS-enabled PostgreSQL/Redis, Mailpit for local email.
- Domains: `finalcut.test` for the customer app and `admin.finalcut.test` for the Filament admin panel.

## Read Order

1. Read `CLAUDE.md` first for the full project context.
2. Read the nearest scoped `AGENTS.md` (`frontend/AGENTS.md` or `backend/AGENTS.md`) before changing files in that tree.
3. Use `.codex/config.toml`, `.codex/agents.toml`, and `.codex/skills.toml` as the project-local cowork manifest.
4. Use `.codex/skills/finalcut-design/SKILL.md` for customer-facing UI work and `.codex/skills/finalcut-admin-design/SKILL.md` for Filament admin UI work.

## Common Commands

- `make certs` - generate local TLS certs.
- `make up` / `make down` - start or stop the dev environment.
- `make build` - build containers.
- `make shell` - open a backend container shell.
- `make migrate` - run migrations.
- `make fresh` - reset database with fresh migrations and seeds.
- `make test` - run backend and frontend tests.
- `make test-backend` - run backend Pest tests.
- `make test-frontend` - run frontend Vitest tests.
- `make e2e` - run Playwright e2e tests.
- `make admin-test` - run admin-namespaced backend tests.
- `make admin-filament-assets` - republish Filament admin theme assets after admin CSS changes.

## Work Discipline

- Keep changes spec-driven and test-driven. Review or write the relevant plan before non-trivial implementation.
- Use Pest for backend tests and Vitest for frontend tests.
- Run the narrowest meaningful verification during development, then `make test` for cross-stack changes.
- Do not leave failing tests. If a change breaks a test, fix it before moving on.
- Preserve user changes in the worktree. Do not revert unrelated files.
- Use conventional commits if asked to commit (`feat:`, `fix:`, `docs:`, etc.).

## Architecture Rules

- Location scope matters. Showtimes, auditoriums, seats, menus, bookings, and related queries must be scoped to a theatre location.
- TMDB is enrichment-only. Never call TMDB in a request path.
- Store and transmit money as positive integer cents. Never use floats for prices, totals, balances, discounts, or Stripe amounts.
- Prefer nullable timestamps over booleans for state transitions.
- The project is pre-launch unless told otherwise. Pre-launch schema changes may edit original migrations; once any shared or production environment has run migrations, use additive migrations.
- Public media paths are disk-relative, such as `concessions/popcorn_sm.webp`. Resolve URLs through the backend public disk or the frontend `assetUrl()` helper.
- Customer-facing CSS uses `rem` units except borders, shadows, and sub-pixel technical cases.
- Design token rule: `primary` / `#FFB4A8` is text-on-dark only; `primary_container` / `#550000` is the fill/accent token.
- Admin UI is Filament 5. Use schema-first APIs and route invariant-bearing changes through domain actions rather than direct resource saves.

## Accessibility

- Target WCAG 2.1 AA or better.
- Use semantic HTML before ARIA.
- Provide keyboard navigation, visible focus, proper labels, and contrast-compliant states.
- Test responsive layouts and avoid text overlap at mobile and desktop widths.
