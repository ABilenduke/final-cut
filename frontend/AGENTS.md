# Frontend Agent Instructions

These instructions apply to files under `frontend/`.

## Stack

- Nuxt 4 with Vue 3 Composition API and TypeScript.
- Tailwind CSS 4 plus project CSS tokens in `frontend/app/assets/css/`.
- Vitest with `@nuxt/test-utils` for unit and component tests.
- Playwright for e2e tests through the root `make e2e` command.

## Commands

- From the repo root, prefer `make test-frontend` for frontend tests.
- Use `make e2e` for browser flows.
- Inside the frontend container, `npx vitest run` and `npx nuxt dev` are acceptable targeted commands.

## UI Rules

- Compose existing primitives from `frontend/app/components/ui/` before creating new primitives.
- Use `CvIcon` and icon names from `frontend/app/components/ui/icons.ts`.
- Use project tokens and utilities from `tokens.css`, `typography.css`, `utilities.css`, and `layouts.css`.
- Do not hardcode brand colors when a token exists.
- Never use `#FFB4A8` as a background or fill. It is a text color on dark maroon surfaces.
- Use `rem` for spacing and sizing, except borders, shadows, and sub-pixel technical cases.
- Keep customer-facing UI cinematic, dark, sparse, and intentional. Avoid generic gradient SaaS styling, nested cards, and decorative noise.

## Data And Rendering

- Use runtime config for API and CDN values. Do not hardcode API URLs or CDN hosts.
- Use `assetUrl()` for static fallback assets.
- Public content should be SSR, prerendered, or ISR according to `frontend/nuxt.config.ts` route rules and `docs/architecture/SITE_ARCHITECTURE.md`.
- Keep all pricing and totals as integer cents. Formatting belongs at display boundaries.
- Food menus, showtimes, auditoriums, seats, and bookings must respect location scope.

## Testing And Accessibility

- Every user-facing component or flow needs Vitest and, where appropriate, Playwright coverage.
- Use semantic HTML, logical heading hierarchy, keyboard operability, visible focus, and accessible names.
- Verify responsive behavior for mobile and desktop; text must not overflow or overlap.
