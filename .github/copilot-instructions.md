# Copilot Code Review Instructions

## CLAUDE.md Documentation Freshness

Every pull request must keep the project's `CLAUDE.md` file in sync with the codebase. There is one `CLAUDE.md` at the repo root covering architecture, Docker commands, domain concepts, design decisions, and project conventions.

### When to flag

If a PR modifies any of the following and does **not** include a corresponding `CLAUDE.md` update, flag it:

- `Makefile` — command changes (make targets)
- `docker-compose*.yml`, `Dockerfile*` — infrastructure, service topology, or port changes
- `frontend/nuxt.config.ts` — Nuxt modules, route rules, or runtime config changes
- `frontend/.storybook/*` — Storybook configuration changes
- `frontend/package.json` — dependency or script changes
- `backend/composer.json` — dependency or script changes
- `backend/phpunit.xml` — test configuration changes
- `.github/workflows/*` — CI/CD pipeline changes
- `.env.example` files — environment variable additions or removals
- New directories under `frontend/app/`, `backend/app/`, or `docs/` — structural changes
- New or renamed database migrations — schema changes that affect documented domain concepts

### How to flag

Leave a review comment on the PR requesting the author update `CLAUDE.md`. Be specific about what changed. For example:

> This PR adds a new Make target `make seed` but `CLAUDE.md` does not list it under Development Commands. Please update the docs.

### Do not flag

- Changes to application code (components, controllers, models, tests) that don't affect documented conventions or commands
- Migration file content changes during pre-launch development (migrations are edited in place per project convention)
- Documentation file changes under `docs/` — these are self-documenting

---

## Design System Compliance

### Color token misuse

Flag any use of `#FFB4A8` (the `primary` token) as a `background-color`, `fill`, `border-color`, or any visual surface. This salmon-pink color is **text-on-dark only**. The correct fill color is `#550000` (`primary_container`).

```css
/* Flag this */
background-color: var(--primary);      /* or #FFB4A8 */
background-color: #FFB4A8;

/* This is correct */
background-color: var(--primary-container);  /* #550000 */
```

### Unit usage

Flag any use of `px` units for spacing or sizing. This project uses `rem` exclusively. The only exceptions are borders, shadows, and sub-pixel adjustments.

```css
/* Flag this */
padding: 16px;
margin-bottom: 24px;
width: 320px;

/* These are acceptable */
border: 1px solid var(--outline-variant);
box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
```

---

## Location Scoping

Flag any showtime, auditorium, seat, or menu query that does not filter by location. This is a multi-location theater — all location-dependent data must be location-scoped. Hardcoding a single location or omitting the location filter is a bug.

---

## Testing Requirements

### Backend

- Flag any new API endpoint, service, or model behavior that does not include Pest tests
- Flag any use of raw PHPUnit (`$this->assert...`) — all backend tests must use Pest syntax
- Flag any PR where `composer test` would fail (e.g., broken imports, missing factories)

### Frontend

- Flag any new component or composable without corresponding Vitest tests
- Flag any new user-facing flow without Playwright e2e coverage

### General

- Flag any PR that skips, ignores, or marks tests as `todo`/`skip` without a linked issue explaining why
- Flag any PR where tests reference hardcoded IDs or timestamps instead of using factories/fixtures

---

## Conventional Commits

Flag any commit message that does not follow conventional commit format:

- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — documentation only
- `refactor:` — code restructuring without behavior change
- `test:` — adding or updating tests
- `chore:` — maintenance tasks (deps, config, CI)

---

## TMDB API Safety

Flag any code that:

- Returns raw TMDB API responses to the frontend — all TMDB data must be transformed through `TmdbService`
- Calls TMDB directly from frontend code — all external API calls go through backend server routes (BFF pattern)
- Hardcodes TMDB image URLs without using the configured base URL and size constants
