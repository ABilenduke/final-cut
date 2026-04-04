# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Final Cut is a full-stack movie theatre web application with a Nuxt 4 frontend and Laravel 13 backend, orchestrated via Docker Compose. The domain is `finalcut.test`.

## Tech Stack

- **Frontend**: Nuxt 4 (Vue 3, TypeScript), Tailwind CSS 4, Storybook, Playwright e2e tests
- **Backend**: Laravel 13 (PHP 8.3), Pest testing framework
- **Database**: PostgreSQL with TLS (database: `final_cut`, test db: `final_cut_test`)
- **Cache/Sessions**: Redis with TLS
- **Reverse Proxy**: Nginx with SSL certificates
- **Security**: Fail2ban sidecar for rate limiting/IP banning
- **Containers**: Docker Compose with dev/prod/local-prod configurations

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

## Architecture

### Docker Compose Layering

- `docker-compose.yml` — Base services: nginx, fail2ban, frontend, backend
- `docker-compose.stack.yml` — Infrastructure: postgres, redis (included by override and local-prod)
- `docker-compose.override.yml` — Dev overrides: volume mounts, storybook, playwright, development build targets
- `docker-compose.prod.yml` — Production overrides (no local infra)
- `docker-compose.local-prod.yml` — Production builds with local postgres/redis

### Frontend (`frontend/`)

Nuxt 4 app using the `app/` directory convention. Storybook runs as a separate container sharing the same source via volume mounts.

### Backend (`backend/`)

Standard Laravel structure: `app/Http`, `app/Models`, `app/Providers`. Uses PHP-FPM (port 9000) behind nginx. Backend has its own npm/Vite setup for asset compilation (separate from the Nuxt frontend).

### Nginx

Acts as reverse proxy routing to frontend (:3000), backend (:9000 via FastCGI), and storybook (:6006). Uses envsubst templates in `nginx/templates/` with `APP_DOMAIN` variable. Banned IPs managed via shared volume with fail2ban.

### TLS Everywhere

All inter-service communication uses TLS: nginx↔client, redis, postgres. Certificates are generated per-service in their respective `*/certs/` directories. Certs are domain-stamped — regenerate with `make certs` if `APP_DOMAIN` changes.

## Design Decisions

- **Styling**: Use `rem` units (not `px`), except where technically required (borders, shadows, sub-pixel)
- **CSS**: CSS custom properties for theming (no CSS-in-JS)
- **API**: TMDB API for movie data
- **Payments**: Stripe integration
- **Auth**: nuxt-auth-utils

## Documentation

Detailed design docs live in `docs/`:
- `SITE_ARCHITECTURE.md` — Overall app structure and routing
- `DATA_MODELS.md` — Database schema and relationships
- `DESIGN_SYSTEM.md`, `DESIGN_SYSTEM_IMPLEMENTATION.md`, `DESIGN_SYSTEM_STRUCTURE.md` — Design tokens, components, patterns
- `COMPONENT_INVENTORY.md` — UI component catalog
- `PAGE_SPECS.md` — Page-level specifications
- `PURCHASE_FLOW.md` — Ticket purchase flow
- `STATE_MANAGEMENT.md` — Frontend state architecture
