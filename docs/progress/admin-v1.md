# Progress Journal — Admin v1

Execution log for [docs/plans/admin/v1/](../plans/admin/v1/). One step per plan (01–09). Update in real time as work progresses — don't batch.

---

## Step 1: Admin Panel Scaffold & Nginx Vhost
**Status:** ✅ Complete
**Started:** 2026-04-22
**Completed:** 2026-04-22

### Work Done
- [2026-04-22] Installed `filament/filament: ^5.0` (see "Decisions" for the version pivot from the ^3.2 spec'd in the plan). `php artisan filament:install --panels` generated `app/Providers/Filament/AdminPanelProvider.php` with panel id `admin`. Published `config/filament.php` via `vendor:publish --tag=filament-config` and ran `php artisan filament:assets`.
- [2026-04-22] Wired `AdminPanelProvider::panel()` to the admin subdomain: `->domain(config('filament.admin_domain'))`, `->path('/')`, `->authGuard('admin')`, and prepended `App\Http\Middleware\ScopeAdminSession::class` as the first panel middleware.
- [2026-04-22] Added `ScopeAdminSession` middleware (`backend/app/Http/Middleware/ScopeAdminSession.php`). Rewrites `session.cookie`, `session.domain`, `session.connection` at request time so admin sessions stay disjoint from customer (Sanctum) sessions. Filament 5 still has no per-panel session config, so the workaround is carried forward from the original Filament-3-targeted plan.
- [2026-04-22] Added `admin_domain` to `config/filament.php` (defaults to `admin.finalcut.test`) and `primary_domain` to `config/app.php` (defaults to `finalcut.test`).
- [2026-04-22] Added the `session_admin` Redis connection to `config/database.php` (copied from the existing `session` block; `database` env key is `REDIS_ADMIN_SESSION_DB`, defaults to DB `3`, keeping it disjoint from default=0, cache=1, session=2).
- [2026-04-22] Extended `backend/.env.example` with `APP_PRIMARY_DOMAIN`, `ADMIN_DOMAIN`, `ADMIN_SESSION_COOKIE`, `ADMIN_SESSION_DOMAIN`, `ADMIN_SESSION_CONNECTION`, and `REDIS_ADMIN_SESSION_DB`.
- [2026-04-22] Replaced `bootstrap/app.php`'s `withRouting(web:, api:)` with a `then:` closure that wraps both route files inside `Route::domain(config('app.primary_domain'))->group(...)`. Customer API + web fallback no longer answer on the admin subdomain.
- [2026-04-22] Added `nginx/templates/conf.d/admin.conf.template` — dedicated `admin.${APP_DOMAIN}` vhost. Same SSL stack / security-header set as the customer vhost (CSP intentionally omitted — Filament ships its own inline scripts and asset URLs; a tight panel-side CSP is a Plan 09 hardening pass). Routes `/` + PHP via FastCGI to the existing `backend:9000` PHP-FPM container; caches `/css/filament/`, `/js/filament/`, and `/fonts/filament/` assets. Livewire assets intentionally fall through to PHP (see the static-asset regex tightening entry below).
- [2026-04-22] Added `./backend/public:/var/www/html/public:ro` to the nginx service in `docker-compose.yml` so nginx can serve Filament's published static assets directly (try_files in the new admin vhost needs `root /var/www/html/public`). Read-only.
- [2026-04-22] Added `APP_URL=http://localhost`, `APP_PRIMARY_DOMAIN=localhost`, and `ADMIN_DOMAIN=admin.localhost` overrides to `phpunit.xml`. Without these, the existing backend `.env` forces `APP_URL=https://backend.finalcut.test`, which made test HTTP requests hit Host `backend.finalcut.test` and miss the new domain-scoped routes.
- [2026-04-22] Added `->fallback()` to the `{fallbackPlaceholder}` route in `routes/web.php`. Reason below under Decisions.
- [2026-04-22] Wrote `tests/Feature/RouteDomainScopingTest.php` (4 tests). Locks in the customer/admin route separation.
- [2026-04-22] Added Makefile targets: `admin-shell`, `admin-migrate`, `admin-test`, `admin-create-user`, `admin-filament-assets`. Updated `.PHONY`. Also fixed the existing `test-backend`, `test-backend-unit`, `test-backend-feature` targets to pass `-u 1000` (matching the project's established pattern and the per-user `-u 1000` Pint hook). Without this, tests running as root collide with storage artifacts written by devuser runs (`/tmp/laravel-testing.log`), producing 25 log-write failures from a clean baseline.
- [2026-04-22] Updated `CLAUDE.md` to document the new admin-* make targets and the admin panel architecture (subdomain, three-layer isolation, bind-mount).
- [2026-04-22] Tightened the admin vhost's static-asset `location` regex from `^/(css/filament|js/filament|fonts/filament|livewire)` to `^/(css/filament|js/filament|fonts/filament)/`. Original match would have also caught Livewire's hashed dynamic endpoints (`/livewire-<hash>/update`, `/livewire-<hash>/upload-file`) and returned 404 via `try_files`, breaking form interactions inside Filament. Livewire's asset endpoints now fall through to `location /` → PHP-FPM, which correctly routes them via Livewire's registered Laravel routes. The trailing `/` in the regex also forbids a bare `/css/filament` match (which would never have existed on disk anyway).
- [2026-04-22] Full backend suite now 429 passing (was 425 pre-change; +4 from RouteDomainScopingTest).

### Decisions
- [2026-04-22] **Filament 5 instead of Filament 3.** Plan 01 specified `filament/filament: ^3.2`, but Filament 3 only supports Laravel 10–12 — composer resolver rejected it against this project's `laravel/framework: ^13.0`. User chose Filament 5 over Filament 4. The plan's own session-scoping workaround (`ScopeAdminSession` middleware + `session_admin` Redis connection) still applies unchanged because neither Filament 3 nor Filament 5 exposes per-panel session config; nothing about the workaround was Filament-3-specific. The `// TODO: migrate to Filament 4` comment referenced in the plan is obsolete — drop it when it appears in future plans. Plans 02–09 written against Filament 3's Resource/Page API will need to be re-checked against Filament 5's API surface.
- [2026-04-22] **`->fallback()` on the `{fallbackPlaceholder}` route.** Sanctum's `api/sanctum/csrf-cookie` is registered by `SanctumServiceProvider::boot()` *after* `withRouting`'s `then:` closure finishes — so the customer-domain `{fallbackPlaceholder}` (with `.*` pattern) would swallow it. Converting the catch-all to a Laravel fallback route makes it low-priority: Laravel tries all non-fallback routes first and only hits the fallback when nothing else matches. The route URI and middleware stack are unchanged, so the existing `ApiOnlySurfaceTest` assertions (`$fallbackRoute->uri()->toBe('{fallbackPlaceholder}')`) still pass.
- [2026-04-22] **`bootstrap/app.php` does not list `web:`/`api:` in `withRouting()`.** Only `commands:`, `health:`, and `then:`. The route files are loaded inside `then:`, wrapped in the domain group. This means the auto-registered `api` prefix and `web`/`api` middleware groups that `withRouting(web:, api:)` normally applies are re-established manually inside the closure (`Route::middleware('api')->prefix('api')->group(...)`, `Route::middleware('web')->group(...)`).
- [2026-04-22] **Stub `admin` guard in `config/auth.php`, not absent.** The plan's acceptance criterion was "Filament login page renders"; the plan assumed the `admin` guard could stay undefined until Plan 02. In practice, Filament 5 resolves the auth guard at panel **boot** (not just at login POST), so a missing `admin` guard turns `GET admin.finalcut.test/` into a 500 — which would fail Plan 01 smoke verification. Added a minimal `'admin' => ['driver' => 'session', 'provider' => 'users']` stub pointing at the existing `users` provider. This is purely to let Filament's routes resolve; Plan 02 will swap it for an `admin_users` provider + `AdminUser` model and layer `spatie/permission` on top. No login can actually succeed because the customer `users` provider can't authenticate anyone into the admin panel context, which is still consistent with the plan's "POST will fail" acceptance stance.
- [2026-04-22] **`RouteDomainScopingTest` exemption list is wider than the plan's two-URI allowlist.** Filament 5 registers global export/import download endpoints (`filament/exports/*`, `filament/imports/*`) and Livewire registers ~12 asset/update routes (`livewire-*`, `livewire/*`). These are vendor-provider infrastructure that must answer on any host — tightly analogous to the `sanctum.csrf-cookie` exemption the plan already called out. The test now exempts those by name prefix (`livewire.`, `default-livewire.`, `filament.exports.`, `filament.imports.`, `sanctum.`) and URI prefix, not just exact string matches. Application-registered routes (customer api/web and Filament panel pages) still must be domain-scoped.
- [2026-04-22] **Kept Filament's auto-added `@php artisan filament:upgrade` in `composer.json` `post-autoload-dump`.** Filament's installer auto-adds this hook and documents it as required — it republishes Filament's JS/CSS/translations after any composer operation that may have bumped Filament or one of its plugins. The command is idempotent when nothing needs upgrading, so the cost on a normal `composer install`/`dump-autoload` is negligible. Removing it would re-fight the installer on every upgrade and risk stale assets in production builds that `composer install` without running `filament:assets` explicitly.
- [2026-04-22] **`make admin-test` uses `--filter=Admin`.** In Plan 01 this incidentally matches exactly one test — `RouteDomainScopingTest::filament panel routes are scoped to the admin domain` — because Pest's `--filter` is a regex against test method names and the word "admin" appears there. The target is not tied to that one test: Plan 02+ will add tests under `tests/Feature/Admin/*` (or similarly-named classes), and `--filter=Admin` will pick them up organically as they arrive. If future admin-related tests don't carry "Admin" in their class or description, rename them or convert the filter to a directory path.

### Blockers
- None.

### Setup Notes
WSL2 developers must add `admin.finalcut.test` to `C:\Windows\System32\drivers\etc\hosts` (alongside the existing `finalcut.test` entry). The wildcard dev cert already covers `*.finalcut.test`, so no certificate regeneration is needed. From WSL, smoke-test curls can use `--resolve admin.finalcut.test:443:127.0.0.1` to avoid the hosts-file dependency entirely.

The `make admin-create-user` target is intentionally wired to an artisan command that does not exist yet — it will fail with "command not found" until Plan 02 ships `php artisan admin:create-user`.

### Files Changed
- `backend/composer.json`, `backend/composer.lock` — added `filament/filament: ^5.0` (pulls in `filament/forms`, `filament/infolists`, `filament/notifications`, `filament/query-builder`, `filament/schemas`, `filament/support`, `filament/tables`, `filament/widgets`, `livewire/livewire`, `kirschbaum-development/eloquent-power-joins`).
- `backend/bootstrap/providers.php` — `filament:install` auto-registered `App\Providers\Filament\AdminPanelProvider::class`.
- `backend/app/Providers/Filament/AdminPanelProvider.php` — new (generated by `filament:install --panels`, then edited to add domain/authGuard/middleware).
- `backend/app/Http/Middleware/ScopeAdminSession.php` — new.
- `backend/config/filament.php` — new (vendor:publish), with appended `admin_domain` key.
- `backend/config/app.php` — added `primary_domain` key.
- `backend/config/database.php` — added `session_admin` Redis connection.
- `backend/.env.example` — appended admin/session/Redis env vars.
- `backend/bootstrap/app.php` — replaced `withRouting(web:, api:)` with a `then:` closure that wraps both route files in `Route::domain(config('app.primary_domain'))->group(...)`.
- `backend/routes/web.php` — added `->fallback()` to the `{fallbackPlaceholder}` route.
- `backend/config/auth.php` — added stub `admin` session guard (see "Decisions"). Plan 02 replaces it.
- `backend/phpunit.xml` — added `APP_URL=http://localhost`, `APP_PRIMARY_DOMAIN=localhost`, `ADMIN_DOMAIN=admin.localhost` forced env overrides.
- `backend/tests/Feature/RouteDomainScopingTest.php` — new (4 tests).
- `backend/public/**` — Filament-published CSS/JS/fonts.
- `nginx/templates/conf.d/admin.conf.template` — new admin subdomain vhost.
- `docker-compose.yml` — added `./backend/public:/var/www/html/public:ro` to the nginx service.
- `Makefile` — added `admin-*` targets + updated `.PHONY` line; added `-u 1000` to existing `test-backend*` targets.
- `CLAUDE.md` — documented admin-* make targets + admin panel architecture (subdomain, three-layer isolation, bind-mount).
- `docs/progress/admin-v1.md` — this file.

---

## Step 2: Auth, Roles, Permissions & Audit Log
**Status:** 🔲 Not Started

---

## Step 3: Base Resource Class & Loyalty Adjustments
**Status:** 🔲 Not Started

---

## Step 4: Movie Catalog Management
**Status:** 🔲 Not Started

---

## Step 5: Locations, Auditoriums & Seat Editor
**Status:** 🔲 Not Started

---

## Step 6: Showtime Management
**Status:** 🔲 Not Started

---

## Step 7: Bookings, Customers & Loyalty
**Status:** 🔲 Not Started

---

## Step 8: Menu, Promo Codes & Gift Cards
**Status:** 🔲 Not Started

---

## Step 9: Calendar Events, Testing & Hardening
**Status:** 🔲 Not Started
