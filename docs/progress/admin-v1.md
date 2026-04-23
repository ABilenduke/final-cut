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
**Status:** ✅ Complete
**Started:** 2026-04-22
**Completed:** 2026-04-22

### Work Done
- [2026-04-22] Created `admin_users` migration (`2026_04_22_000000_create_admin_users_table.php`) with the documented schema: `id`, `name`, `email` (unique), `password`, `rememberToken`, `last_login_at`, `last_login_ip` (45), `disabled_at`, `timestamps`. No `email_verified_at` column — admin users are created via `admin:create-user`, never self-signup.
- [2026-04-22] Added `App\Models\AdminUser` extending `Illuminate\Foundation\Auth\User` and implementing `Filament\Models\Contracts\FilamentUser`. Casts: `disabled_at`/`last_login_at` → `datetime`, `password` → `hashed`. `canAccessPanel()` returns true only for the `admin` panel and a non-disabled account.
- [2026-04-22] `AdminUserFactory` with default `disabled_at = null` and a `disabled()` state for the kill-switch test.
- [2026-04-22] Replaced the Plan 01 stub `admin` guard in `config/auth.php` (was pointing at `users` provider) with a real session guard against the new `admin_users` provider. Added the `admin_users` Eloquent provider and an `admin_users` password broker that reuses the existing `password_reset_tokens` table.
- [2026-04-22] Installed `spatie/laravel-permission` (`^7.3`). Published config + migration. Added `HasRoles` trait + `protected string $guard_name = 'admin'` to `AdminUser`.
- [2026-04-22] Wrote `AdminRolesAndPermissionsSeeder` — idempotent, seeds 45 permissions and 3 roles (`admin`, `manager`, `ops`), all with `guard_name = 'admin'`. The seeder calls `app(PermissionRegistrar::class)->forgetCachedPermissions()` first so `syncPermissions()` sees freshly-created records in the same run. Registered the new seeder in `DatabaseSeeder::run()` (outside the `local|testing` branch since admin roles are required in every environment).
- [2026-04-22] Installed `spatie/laravel-activitylog` (`^5.0`). Published config + migration. Rewrote `config/activitylog.php`: `clean_after_days = 180`, `default_log_name = 'admin'`, `default_auth_driver = 'admin'`. The published v5 config schema differs from the plan doc (`clean_after_days` not `delete_records_older_than_days`; `include_soft_deleted_subjects` not `subject_returns_soft_deleted_models`) — kept the v5 keys, retained the plan-doc semantics.
- [2026-04-22] Added `LogsActivity` trait + `getActivitylogOptions()` to `AdminUser` (`logOnly(['name','email','disabled_at'])->logOnlyDirty()->dontLogEmptyChanges()`). v5 namespaces: trait at `Spatie\Activitylog\Models\Concerns\LogsActivity`, options at `Spatie\Activitylog\Support\LogOptions`. Method name in v5 is `dontLogEmptyChanges()` — the plan doc's `dontSubmitEmptyLogs()` is from older versions.
- [2026-04-22] Finalized `AdminPanelProvider`: added `->authPasswordBroker('admin_users')` next to the existing `->authGuard('admin')`. Middleware stack untouched.
- [2026-04-22] Wired Laravel auth events in `AppServiceProvider::boot()` — `Login`, `Logout`, `Failed`. Each listener early-returns when `$event->guard !== 'admin'` to keep customer auth out of admin's audit log. `Login` writes a `'login'` row to `log_name = 'auth'`, then force-fills `last_login_at = now()` and `last_login_ip = request()->ip()`. `Logout` writes a `'logout'` row (and skips when no user is attached). `Failed` writes a `'login_failed'` row carrying the attempted email in `properties.email`.
- [2026-04-22] Created `App\Console\Commands\CreateAdminUser` (`admin:create-user`) — full create + reset paths. Without flags, prompts for name/email/password/role. With `--reset-password`, looks up by `--email` and re-hashes. With `--reset-password --reassign-role`, also re-syncs roles. Duplicate emails on the create path return FAILURE with a message naming the `--reset-password` flag. Unknown roles and missing accounts both return FAILURE with operator-actionable error text.
- [2026-04-22] Added `Schedule::command('activitylog:clean')->daily()` to `routes/console.php` next to the existing `movies:enrich` hourly. `php artisan schedule:list` now shows both. The 180-day retention from `clean_after_days` is what the command honours.
- [2026-04-22] Built the global `/activity` Filament page (`App\Filament\Pages\ActivityLog`) under the `System` navigation group. `canAccess()` gates on `activity.view`. Lists activity rows ordered by `latest()` (unbounded, paginated by Filament's default page size) with three filters: Admin (causer, scoped to `causer_type = AdminUser::class` so the numeric `causer_id` can't collide with non-admin morph rows), Resource (subject_type), and a Created Between date range with inclusive `endOfDay()` on the upper bound. Retention is handled out-of-band by Activitylog's `clean_after_days` (180d, `config/activitylog.php`) via the daily `activitylog:clean` schedule. Filament 5 quirks honoured: `protected string $view` is an instance property (the plan doc's `protected static $view` is from Filament 3 and doesn't compile in 5); `$navigationIcon` and `$navigationGroup` carry the `string|BackedEnum|null` and `string|UnitEnum|null` union types Filament 5 expects.
- [2026-04-22] Created `Tests\Helpers\AdminAuthHelper` trait with `actingAsAdmin()`, `actingAsManager()`, `actingAsOps()`, `actingAsNobody()` — each creates an `AdminUser` via factory, assigns the role (or none), and calls `actingAs($user, 'admin')`.
- [2026-04-22] Registered the trait in `tests/Pest.php` via `uses(AdminAuthHelper::class)->in('Feature/Admin')` (Pest rejects two `pest()->extend(TestCase::class)` calls in the same path tree, so a separate `pest()->extend()` call doesn't work). Added a sibling `uses()->beforeEach(fn () => $this->seed(AdminRolesAndPermissionsSeeder::class))->in('Feature/Admin')` so admin roles exist on a freshly-truncated DB before any `actingAs*` helper runs.
- [2026-04-22] Wrote 32 admin tests across six files under `tests/Feature/Admin/Auth/` and `tests/Feature/Admin/Console/`: LoginTest (6), PermissionEnforcementTest (5), RoleSeederTest (6), AuditLoggingTest (6), SessionCookieScopingTest (3), CreateAdminUserCommandTest (6). Combined with Plan 01's RouteDomainScopingTest the `--filter=Admin` set is now 33 tests.
- [2026-04-22] Bumped PHPUnit's PHP `memory_limit` to `512M` via `<ini name="memory_limit" value="512M"/>` in `phpunit.xml`. The default 128M cap (set by the container's `php.ini`) was sufficient for the 429-test pre-Plan-02 baseline; adding Spatie Permission + ActivityLog autoloading + 32 new tests pushed it just over. The phpunit-level ini override only applies to test runs — production CLI is untouched.
- [2026-04-22] Full backend suite now 461 passing (was 429 pre-Plan-02; +32 admin tests).

### Decisions
- [2026-04-22] **Filament 5 API drift from the plan doc.** The plan doc was drafted against Filament 3. Three concrete adjustments needed in Filament 5:
  - `App\Filament\Pages\ActivityLog`: `$view` is an instance property (`protected string $view`), not `protected static string $view`.
  - `$navigationIcon` and `$navigationGroup` use `string|\BackedEnum|null` and `string|\UnitEnum|null` union types in v5; the plan doc's plain `?string` will deprecate-warn and eventually error.
  - The `Filter::form([...])` builder is also exposed as `Filter::schema([...])` in v5 — both work; kept the doc's `->form()` alias for fidelity, then switched to `->schema()` because that's the v5-canonical name. (Filament keeps `form()` as an alias.)
- [2026-04-22] **`Spatie\Activitylog` v5 namespace + method changes.** The package's v5 release moved the trait to `Spatie\Activitylog\Models\Concerns\LogsActivity` (was `Spatie\Activitylog\Traits\LogsActivity`) and the helper to `Spatie\Activitylog\Support\LogOptions` (was `Spatie\Activitylog\LogOptions`). `dontSubmitEmptyLogs()` was renamed to `dontLogEmptyChanges()`. Also `clean_after_days` replaced `delete_records_older_than_days`. Plan doc reflected the v4 names; this code reflects v5.
- [2026-04-22] **Pest can't extend the same TestCase twice on overlapping path globs.** First attempt was `pest()->extend(TestCase::class)->use(AdminAuthHelper::class)->in('Feature/Admin')` alongside the existing `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature','Unit')` — Pest threw `Test case [Tests\TestCase] can not be used. The folder ... already uses the test case [Tests\TestCase].` Switched to `uses(AdminAuthHelper::class)->in('Feature/Admin')` which layers the trait onto the existing TestCase extension instead of redeclaring the base class binding.
- [2026-04-22] **Seeder runs in a per-test `beforeEach`, not in `DatabaseSeeder` for tests.** RefreshDatabase truncates the schema between tests, so seeder side effects don't carry over. Calling `$this->seed(AdminRolesAndPermissionsSeeder::class)` in a Feature/Admin-scoped `beforeEach` keeps the role catalog present at the start of every admin test, without dragging the rest of the customer-side seed data (movies, locations, bookings) into admin tests that don't need it.
- [2026-04-22] **Disabled-admin assertion is `assertForbidden`, not `assertRedirect('/login')`.** The plan-doc test #4 said "the Filament Authenticate middleware redirects them back to /login" — but Filament 5's `Authenticate` middleware actually `abort_if(!canAccessPanel, 403)` for an authenticated-but-disallowed user. Redirect-to-login only triggers for unauthenticated requests. The test asserts the real behaviour (403) rather than the doc's mistaken expectation. Both behaviours satisfy the underlying contract (a disabled admin can't use the panel).
- [2026-04-22] **Customer-User guard-mismatch test rewritten to assert trait absence, not exception.** Plan-doc test #5 expected `$customer->assignRole('admin')` to throw on guard mismatch. In practice the customer `App\Models\User` deliberately has no `HasRoles` trait — calling `assignRole()` on it throws `BadMethodCallException` (no such method), not Spatie's `RoleDoesNotExist`. The defensive surface is "User has no role-assignment method at all" combined with the separate `RoleSeederTest` assertion that every admin role is `guard_name = 'admin'`. The two together prove a customer user can't carry an admin role without code changes to the User model. Rewrote the test to assert `method_exists($customer, 'assignRole') === false`, which is the actual architectural defense.
- [2026-04-22] **SessionCookieScopingTest verifies config rewrites, not Redis keys.** `phpunit.xml` forces `SESSION_DRIVER=array`, so the plan-doc's `redis-cli -n 2`/`-n 3` style assertions can't run inside Pest (no live Redis sessions are written). The three tests instead assert what `ScopeAdminSession` deterministically does to `config('session.*')` on admin-host vs primary-host requests, and that an `ADMIN_SESSION_DOMAIN` override propagates through. The actual Redis DB isolation is a manual dev sanity check (`redis-cli -n 2 keys '*' | grep session` vs `-n 3` after a real login + admin login).
- [2026-04-22] **`memory_limit` bumped via phpunit.xml `<ini>`, not Dockerfile.** The container's `php.ini` keeps the default 128M (production CLI doesn't run the test suite). Tests need ~300–400M to load Filament + Spatie packages + the 461-test class graph. PHPUnit's `<ini>` directive applies only to the test process and leaves production CLI alone — least-invasive fix.
- [2026-04-22] **`session_admin` Redis connection is NOT exercised by the test suite.** It's defined in `config/database.php` per Plan 01 and is the live mechanism that holds admin sessions in dev/prod. Tests use `array` sessions, so the `session_admin` connection is dead code at test time. The middleware contract (`session.connection` is rewritten to `'session_admin'` on admin-host requests) is asserted in `SessionCookieScopingTest`, which is what guarantees the connection is consulted in non-test environments.

### Blockers
- None.

### Files Changed
- `backend/composer.json`, `backend/composer.lock` — added `spatie/laravel-permission: ^7.3` and `spatie/laravel-activitylog: ^5.0`.
- `backend/database/migrations/2026_04_22_000000_create_admin_users_table.php` — new.
- `backend/database/migrations/2026_04_22_171237_create_permission_tables.php` — vendor-published.
- `backend/database/migrations/2026_04_22_171356_create_activity_log_table.php` — vendor-published.
- `backend/app/Models/AdminUser.php` — new. `HasFactory`, `HasRoles`, `LogsActivity`, `Notifiable`. `protected string $guard_name = 'admin'`. `canAccessPanel()` + `getActivitylogOptions()`.
- `backend/database/factories/AdminUserFactory.php` — new (default state + `disabled()` state).
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — new (idempotent, 45 permissions + 3 roles, all guard_name=admin).
- `backend/database/seeders/DatabaseSeeder.php` — registered `AdminRolesAndPermissionsSeeder` in the always-run seeder list.
- `backend/config/auth.php` — replaced stub `admin` guard with the real `admin_users`-backed session guard. Added `admin_users` provider + `admin_users` password broker.
- `backend/config/permission.php` — intentionally **not** published. The Spatie Permission defaults (`Role` + `Permission` models, standard table names, no teams) are exactly what we want; republishing would fork an identical copy.
- `backend/config/activitylog.php` — rewritten: 180-day retention, `default_log_name = 'admin'`, `default_auth_driver = 'admin'`.
- `backend/app/Providers/Filament/AdminPanelProvider.php` — added `->authPasswordBroker('admin_users')`.
- `backend/app/Providers/AppServiceProvider.php` — registered Login/Logout/Failed event listeners (admin-guard scoped).
- `backend/app/Console/Commands/CreateAdminUser.php` — new. `admin:create-user` with `--reset-password` + `--reassign-role` paths.
- `backend/routes/console.php` — appended `Schedule::command('activitylog:clean')->daily()`.
- `backend/app/Filament/Pages/ActivityLog.php` — new. `/activity` page under System nav, gated on `activity.view`.
- `backend/resources/views/filament/pages/activity-log.blade.php` — new (one-line wrapper).
- `backend/tests/Pest.php` — registered `AdminAuthHelper` trait and `AdminRolesAndPermissionsSeeder` `beforeEach` for `Feature/Admin`.
- `backend/tests/Helpers/AdminAuthHelper.php` — new. Four `actingAs*` helpers.
- `backend/tests/Feature/Admin/Auth/LoginTest.php` — new (6 tests).
- `backend/tests/Feature/Admin/Auth/PermissionEnforcementTest.php` — new (5 tests).
- `backend/tests/Feature/Admin/Auth/RoleSeederTest.php` — new (6 tests).
- `backend/tests/Feature/Admin/Auth/AuditLoggingTest.php` — new (6 tests).
- `backend/tests/Feature/Admin/Auth/SessionCookieScopingTest.php` — new (3 tests).
- `backend/tests/Feature/Admin/Console/CreateAdminUserCommandTest.php` — new (6 tests).
- `backend/phpunit.xml` — added `<ini name="memory_limit" value="512M"/>`.

### Manual Dev Verification (out of automated test scope)
After `make fresh && make admin-create-user --name="Ops" --email=ops@finalcut.test --password=secret --role=ops`:
1. Browser: `https://admin.finalcut.test/login` accepts ops credentials → `/`.
2. `/activity` lists the `'login'` row with `log_name = 'auth'` and the ops user as causer.
3. Logout writes a matching `'logout'` row.
4. `update admin_users set disabled_at = now() where email = 'ops@finalcut.test';` — re-login is blocked (403).
5. Redis isolation check (manual, since tests use array sessions):
   ```bash
   redis-cli -n 2 KEYS '*' | grep session   # only customer sessions
   redis-cli -n 3 KEYS '*' | grep session   # only admin sessions
   ```

---

## Step 3: Base Resource Class & Loyalty Adjustments
**Status:** ✅ Complete
**Started:** 2026-04-22
**Completed:** 2026-04-22

### Work Done
- [2026-04-22] Added `App\Filament\Concerns\FormatsCurrency` — `centsToDisplay(?int): string` (null → em-dash, otherwise `$` + `number_format/2`) and `displayToCents(?string): ?int` (null/empty → null, otherwise strip non-digit/dot and multiply by 100). Seven Pest unit tests cover the round-trip including the intentional "parses input as dollars not cents" case (`'1299.00' → 129900`).
- [2026-04-22] Added `App\Filament\Concerns\TimestampColumns::standardTimestamps()` returning two Filament `TextColumn` instances (`created_at`, `updated_at`), both sortable + toggleable(hidden by default). No dedicated test — trait returns pure-config Filament builder chains; smoke-checked via tinker-style `php -r` (count=2, correct names, toggleable=true, hiddenByDefault=true) rather than Pest.
- [2026-04-22] Added `App\Filament\Resources\BaseResource` abstract class. `canViewAny/canCreate/canEdit/canDelete` each delegate to `static::crudPermission('view'|'create'|'update'|'delete')` and resolve through `auth('admin')->user()?->can(...)`. `crudPermission()` is `protected static`, rejects non-CRUD verbs with a `LogicException` naming the offending verb, and throws a second `LogicException` naming the subclass when `$permissionPrefix` is null. Nine BaseResourceTest cases lock in: positive permission wiring via `actingAsManager`/`actingAsAdmin`, negative via `actingAsOps`/`actingAsNobody`, and both error paths. Test file declares two inline stub classes (`MoviesResourceStub`, `MissingPrefixResourceStub`) with no-op `getPages()`; `MoviesResourceStub::exposeCrudPermission()` is the public test-only window into the protected verb guard.
- [2026-04-22] Added `App\Enums\LoyaltyAdjustmentType` (string-backed): `PointsCorrection`, `TierUpgrade`, `TierRevoke`, `GoodwillCredit`, `FraudClawback`.
- [2026-04-22] Added migration `2026_04_23_000000_create_loyalty_adjustments_table.php`, model `App\Models\LoyaltyAdjustment`, and `LoyaltyAdjustmentFactory`. `change_type` cast to the enum; `LogsActivity` with `logOnly(['user_id','admin_user_id','points_delta','change_type'])->dontLogEmptyChanges()`. Five Pest feature tests cover: enum cast persists + reloads, unknown string raises `ValueError` at hydration, one `created` activity row per insert with correct `causer_id`/`causer_type` (admin auth driver resolves automatically), one `updated` row per mutation with `properties` keys scoped to `logOnly` columns, `user_id` cascade-delete drops the adjustment row.
- [2026-04-22] Widened `tests/Pest.php` — added `'Unit/Admin'` to both the `AdminAuthHelper` `uses()` binding and the `AdminRolesAndPermissionsSeeder` `beforeEach` seeder. `RefreshDatabase` already covers `Unit` globally via the existing `pest()->extend` chain, so no separate wiring needed.
- [2026-04-22] `make admin-test`: dropped `--testsuite=Feature` so `--filter=Admin` picks up tests in both `Feature/Admin/**` and `Unit/Admin/**`.
- [2026-04-22] `make admin-test` now green: 58 tests, 210 assertions (was 33). Full backend suite: 486 tests, 1672 assertions — 0 regressions. Pint clean on all 11 new/changed files.

### Decisions
- [2026-04-22] **`foreignUuid('user_id')`, not `foreignId('user_id')` as in the plan doc.** `users.id` is a UUID primary key (see `0001_01_01_000000_create_users_table.php`). `foreignId` would emit `unsignedBigInteger` and the FK constraint would fail at migration time. Matches the codebase convention used by `bookings.user_id` and every other `users` FK in the schema.
- [2026-04-22] **Spatie Activitylog v5 namespaces, not the v4 paths in the plan doc.** Plan doc specified `Spatie\Activitylog\Traits\LogsActivity` + `Spatie\Activitylog\LogOptions`; this codebase (matched to `AdminUser.php`) uses `Spatie\Activitylog\Models\Concerns\LogsActivity` + `Spatie\Activitylog\Support\LogOptions`. Also swapped `dontSubmitEmptyLogs()` → `dontLogEmptyChanges()` (v5 rename). Plan 02 already established this drift; Plan 03 follows the same convention.
- [2026-04-22] **Explicit `auth('admin')` in BaseResource**, even though `config/auth.defaults.guard` is still the default. Belt-and-braces: any future change that alters the default guard doesn't silently flip admin permission checks to a different guard's user. The call sites would compile and pass tests, but production would silently authorize the wrong accounts. Pinning the guard locally in every method is 8 characters of explicitness that can't drift.
- [2026-04-22] **Stub classes declared at the top of `BaseResourceTest.php`, not in separate fixture files.** Filament's `Resource::getPages()` is abstract-in-practice — instantiating a subclass requires overriding it. Two trivial stubs (each with `public static function getPages(): array { return []; }`) are cheaper than a fixtures directory for two throwaway test-only classes. `MoviesResourceStub::exposeCrudPermission()` is a public test-only window into the protected verb guard, avoiding the reflection boilerplate the plan alternate suggested.
- [2026-04-22] **Model `protected function casts(): array` instead of `protected $casts = []`.** Matches `AdminUser`'s Laravel 11+ idiom. Functionally identical for a single enum cast, but keeps the file consistent with the rest of the admin model suite.
- [2026-04-22] **Activity-log causer tests rely on the config-level `default_auth_driver = 'admin'`**, not an explicit `->causedBy($admin)` on the test factory. Spatie's `ActivitylogServiceProvider` reads `default_auth_driver` at log-write time; because `$this->actingAsAdmin()` sets the `admin` guard user, the causer resolves automatically without a mock. Tests delete `Activity::query()` before create/update steps to isolate the row under test from whatever the `AdminUser::factory()->create()` call inside `actingAsAdmin()` may emit.

### Blockers
- None.

### Files Changed
- `backend/app/Filament/Concerns/FormatsCurrency.php` — new.
- `backend/app/Filament/Concerns/TimestampColumns.php` — new.
- `backend/app/Filament/Resources/BaseResource.php` — new.
- `backend/app/Enums/LoyaltyAdjustmentType.php` — new.
- `backend/app/Models/LoyaltyAdjustment.php` — new.
- `backend/database/migrations/2026_04_23_000000_create_loyalty_adjustments_table.php` — new.
- `backend/database/factories/LoyaltyAdjustmentFactory.php` — new.
- `backend/tests/Unit/Admin/FormatsCurrencyTest.php` — new (7 tests).
- `backend/tests/Unit/Admin/BaseResourceTest.php` — new (9 tests).
- `backend/tests/Feature/Admin/LoyaltyAdjustmentTest.php` — new (5 tests).
- `backend/tests/Pest.php` — extended `AdminAuthHelper` + seeder bindings to include `Unit/Admin`.
- `Makefile` — dropped `--testsuite=Feature` from `admin-test`.
- `docs/progress/admin-v1.md` — this file.

---

## Step 4: Movie Catalog Management
**Status:** ✅ Complete
**Started:** 2026-04-22
**Completed:** 2026-04-23

### Work Done
- [2026-04-22] Added `App\Services\MovieService` (`backend/app/Services/MovieService.php`) with four write methods — `create`, `update`, `delete`, `triggerEnrichment` — all accepting an optional `?AdminUser $actor` as the last parameter. A private `logIfAdmin()` helper writes `activity('admin')->causedBy($actor)->performedOn($movie)->withProperties(...)->log($event)` when `$actor !== null` and short-circuits otherwise. `genres` and `cast` ride through `Movie::create/fill` as JSON columns (no pivot, no relationship sync).
- [2026-04-22] Added `App\Jobs\EnrichMovieJob` (`backend/app/Jobs/EnrichMovieJob.php`). Implements `ShouldQueue`, constructor captures `int $movieId` and `string $lockKey`, `handle(TmdbService $tmdb)` looks up the movie and delegates to the existing `TmdbService::enrichMovie`. A `finally` block always releases the cache lock so a failed enrichment does not strand the lock for the full 5-minute TTL.
- [2026-04-22] Built the Filament resource: `MovieResource` + four pages (`ListMovies`, `CreateMovie`, `EditMovie`, `ViewMovie`) and the read-only `UpcomingShowtimesRelationManager`. Resource navigates under `Catalog`, sort 10, `heroicon-o-film`. `CreateMovie::handleRecordCreation` and `EditMovie::handleRecordUpdate` divert Filament's default persistence into `MovieService`, passing `auth('admin')->user()` as the actor. Every `DeleteAction` on the resource (table row, EditPage header, ViewPage header) uses `->using(fn (Model $record) => app(MovieService::class)->delete($record, auth('admin')->user()))` so audit attribution fires — a stock `DeleteAction::make()` would bypass the service and is treated as a regression (guarded by `MovieResourceTest`).
- [2026-04-22] Form schema — Identity / Content / Media / Taxonomy / Cast sections. Title auto-slugs via `Str::slug` only when `$record === null`; on edit, the slug is stable. Genres and Cast repeaters write raw JSON (`{id, name}` and `{name, character, profileUrl}` shapes) straight to the `genres` / `cast` JSON columns.
- [2026-04-22] Table — poster thumbnail, searchable/sortable title, status badge (`now_showing` → success, `coming_soon` → warning — formatted through a `MovieStatus`-aware closure because the model casts status to the enum, not a string), genre badges via `collect($state)->pluck('name')`, enrichment timestamp with `->since()`. Filters: status, genre-name via `whereJsonContains('genres', [['name' => $name]])`, `needs_enrichment` (null `tmdb_enriched_at` AND non-null `tmdb_id`). Row actions: View, Edit, `enrich` (visible iff `tmdb_id` present AND `movies.trigger_enrich`), Delete. Bulk actions: `mark_now_showing` / `mark_coming_soon` (both N-sequential `MovieService::update` calls, gated on `movies.update`). Default sort: `release_date desc`.
- [2026-04-22] `UpcomingShowtimesRelationManager` — `relationship = 'showtimes'`, title `"Upcoming Showtimes (next 20)"`, `modifyQueryUsing(...)` scopes to `start_time >= now()` ordered ascending with `limit(20)`. Columns: start time, `auditorium.location.name`, `auditorium.name`, standard price through `FormatsCurrency::centsToDisplay`. `headerActions([])`, `recordActions([])`, `isReadOnly() => true`.
- [2026-04-22] Tests — three new files.
  - `tests/Unit/Services/MovieServiceTest.php` (5 tests): create persists JSON shapes, update persists and returns fresh model, delete removes row, triggerEnrichment is idempotent under `Bus::fake()` (sync queue would execute the job inline and release the lock — Bus::fake captures the dispatch so lock stays held), no activity rows when `$actor` is null.
  - `tests/Feature/Admin/Services/MovieServiceIntegrationTest.php` (5 tests): real service + real `activity_log`. Covers `movie.created`, `movie.updated` (with `before`/`after` properties), `movie.deleted`, `movie.enrichment_triggered`, and silence when `$actor` is null. Mirrors the `Activity::query()->delete()` isolation pattern from `LoyaltyAdjustmentTest`.
  - `tests/Feature/Admin/Resources/MovieResourceTest.php` (9 tests): drives the Filament Livewire components with `$this->mock(MovieService::class)`. Verifies list render, create routes through service with admin actor + genre/cast payload matches the repeater shape, edit routes through service, delete routes through `->using(...)` (regression guard — row must still exist after the mocked call), enrich dispatches surface a success notification and the lock-held case surfaces a warning notification, enrich action is hidden when `tmdb_id` is null, bulk mark_now_showing calls update once per record, and slug stability on edit.
  - `tests/Feature/Admin/Resources/MovieResourcePermissionTest.php` (3 tests): ops read-only with enrich/bulk hidden, manager full CRUD + bulk/enrich visible, roleless admin user forbidden from `ListMovies`.
- [2026-04-22] Full backend suite green at 511 tests (up from ~495 pre-plan; +16 new tests). `make admin-test` green at 78 tests.

### Decisions
- [2026-04-22] **`MovieController` was not touched.** The plan's Task 1 language — "extract movie write orchestration out of the existing `MovieController`" and the matching acceptance criterion "MovieController delegates write operations to the service" — is vestigial. The customer-API `MovieController` is read-only (`index`, `show`, `showtimes`); there are no writes to extract. `MovieService` is a net-new write boundary, not a refactor. The acceptance criterion is treated as satisfied vacuously.
- [2026-04-22] **`EnrichMoviesCommand` (the hourly scheduler) was not touched.** Plan Risk #1 treated "command + admin single-trigger share one job class" as a nice-to-have; leaving the working command alone and adding `EnrichMovieJob` specifically for the admin action is simpler and keeps TMDB rate-limit pacing in the command untouched. The command still calls `TmdbService::enrichMovie` directly; the job wraps the same call and adds cache-lock release.
- [2026-04-22] **`activity_log` attribution uses `->causedBy($actor)` explicitly instead of relying on `default_auth_driver = 'admin'`.** The config default only fires when no causer is set; being explicit makes the service's contract independent of request-time guard state and passes correctly when called from non-Filament code paths (e.g. a future console command).
- [2026-04-22] **Bulk status actions are N sequential single-record updates, not a bulk DB query.** Each selected movie emits its own `activity_log` row and flows through the same code path as a single-record edit. Acceptable for v1's expected admin usage (tens of rows per bulk op). If real usage ever exceeds that scale, add a dedicated batch method to `MovieService` with a single aggregate activity row.
- [2026-04-22] **`status` column uses an enum-aware closure, not the typed `fn (string $state)` shown in the plan.** `Movie::casts()` hydrates `status` into `MovieStatus`, so the column receives an enum. The color resolver dereferences `$state->value` when it's an enum and falls back to the string otherwise.
- [2026-04-22] **`Notification::assertNotified(string $title)` not closure-form.** Filament 5's `assertNotified` signature accepts `Notification|string|null`, not `Closure` — the plan snippet using a closure predicate would fail with a TypeError on v5.
- [2026-04-22] **Filament 5 uses `Schema $schema` / `$schema->components([...])` for forms and `recordActions`/`toolbarActions` for table actions.** Plan snippets were written against Filament 3's `Form $form` / `$form->schema(...)` API. The v5 equivalents are a mechanical rewrite — same component classes, same builder methods — but the outer `form()` signature had to change.

### Blockers
- None.

### Files Changed
- `backend/app/Services/MovieService.php` — new; write boundary with optional `?AdminUser $actor` and activity-log emission.
- `backend/app/Jobs/EnrichMovieJob.php` — new; single-movie enrichment job with lock release in `finally`.
- `backend/app/Filament/Resources/MovieResource.php` — new; resource with form, table, actions, filters, bulk actions.
- `backend/app/Filament/Resources/MovieResource/Pages/ListMovies.php` — new.
- `backend/app/Filament/Resources/MovieResource/Pages/CreateMovie.php` — new; `handleRecordCreation` → `MovieService::create`.
- `backend/app/Filament/Resources/MovieResource/Pages/EditMovie.php` — new; `handleRecordUpdate` → `MovieService::update`; delete via `->using`.
- `backend/app/Filament/Resources/MovieResource/Pages/ViewMovie.php` — new; edit/delete header actions, delete via `->using`.
- `backend/app/Filament/Resources/MovieResource/RelationManagers/UpcomingShowtimesRelationManager.php` — new; read-only next-20 showtimes preview.
- `backend/tests/Unit/Services/MovieServiceTest.php` — new; 5 unit tests.
- `backend/tests/Feature/Admin/Services/MovieServiceIntegrationTest.php` — new; 5 real-DB activity-log tests.
- `backend/tests/Feature/Admin/Resources/MovieResourceTest.php` — new; 9 Livewire-driven resource tests (service mocked).
- `backend/tests/Feature/Admin/Resources/MovieResourcePermissionTest.php` — new; 3 per-role gating tests.

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
