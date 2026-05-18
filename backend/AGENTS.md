# Backend Agent Instructions

These instructions apply to files under `backend/`.

## Stack

- Laravel 13 on PHP 8.4.
- Pest for all backend tests.
- PostgreSQL 18 with timezone-aware timestamps.
- Redis for cache and sessions.
- Filament 5 for the admin panel.
- Stripe through `stripe/stripe-php`; tests use project fakes/helpers.

## Commands

- From the repo root, prefer `make test-backend` for the full backend suite.
- Use `make test-backend-unit` or `make test-backend-feature` for targeted suites.
- Use `make admin-test` for admin-namespaced work.
- Use `make admin-filament-assets` after editing admin theme CSS.
- Inside `backend/`, `composer test` is the backend-local equivalent.
- Use `php artisan test --filter=SomeTest` for targeted Pest tests when inside the backend container.
- Use `php artisan pint` or `vendor/bin/pint` for PHP style fixes.

## Laravel Rules

- Use Pest, not raw PHPUnit.
- Use `RefreshDatabase` for test isolation against `final_cut_test`.
- Keep controllers thin. Validation belongs in form requests, mutations belong in services/actions, and responses belong in resources.
- Do not serialize models directly in API responses when a resource is appropriate.
- Authorization must be explicit for admin and account-sensitive operations.
- Money is integer cents everywhere.
- TMDB enrichment runs offline through `movies:enrich`; never call TMDB in a request path.
- Public media values are disk-relative paths and should be resolved with `Storage::disk('public')->url()` or project helpers.

## Database And Domain Rules

- Location scope is mandatory for showtime, auditorium, seat, menu, and booking data.
- Prefer nullable timestamps for state transitions instead of booleans.
- Use timezone-aware timestamps for non-standard date/time columns.
- While the project remains pre-launch, schema changes may edit the original migration. If pre-launch status is uncertain, use an additive migration.
- Do not use floats or decimals for money.

## Admin Rules

- Filament admin runs at `admin.finalcut.test`.
- Use Filament 5 schema-first APIs (`Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`) for new admin surfaces.
- Filament resources are a UI layer. Invariant-bearing state changes should route through domain services/actions rather than direct resource saves.
- Keep admin copy plain, operator-focused, and free of emoji.
- Admin status colors must map to the Final Cut semantic tokens documented in `docs/design-system/DESIGN_SYSTEM.md`.
