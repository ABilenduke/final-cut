# Plan 01: Admin Panel Scaffold & Nginx Vhost

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** None
> **Unlocks:** Plan 02 (auth layer needs the panel to exist)

## Overview

Install Filament 3 inside the existing `backend/` Laravel app, bind the panel to a dedicated `admin.${APP_DOMAIN}` subdomain via Filament's `->domain()` panel config, add a second nginx vhost that fastcgi-proxies to the same backend PHP-FPM container, and close the pre-existing route-scoping gap so customer API routes can never answer on the admin subdomain (and vice versa). Add a handful of Makefile targets as thin wrappers around `backend/` artisan, scaffold the progress journal, and verify isolation with a Pest test that enumerates the routing table.

At the end of this plan, hitting `https://admin.finalcut.test/login` renders Filament's default login page (no users exist yet — Plan 02 creates the `admin_users` table and the `admin:create-user` command). Hitting `https://finalcut.test/admin/login` returns 404. Hitting `https://admin.finalcut.test/api/movies` returns 404.

No new Laravel skeleton, no new Dockerfile, no new docker-compose service. Everything lands inside the existing `backend/` app.

## Version Matrix

Pin exactly — do not float on majors. Upgrades are a separate, intentional plan.

| Component | Version | Notes |
| --- | --- | --- |
| Filament | 3.2.x | `filament/filament:"^3.2"`. Do not move to 4.x in this plan. |
| Laravel | existing | Backend is already pinned; admin uses whatever backend uses. |
| PHP | existing | Same — `backend/Dockerfile` owns the PHP version. |

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2 Architecture, § 2.3 Subdomain isolation & security scoping
- `backend/bootstrap/app.php` — current route registration (no domain scoping today)
- `backend/routes/api.php` — customer API routes that need to be scoped to the primary domain
- `nginx/templates/conf.d/default.conf.template` — reference vhost format for the new admin vhost
- `nginx/certs/generate-certs.sh` — wildcard cert already covers `*.${APP_DOMAIN}`
- `Makefile` — existing target conventions (`make shell`, `make migrate`, `make test-backend`)

---

## Tasks

### Task 1: Install Filament 3 in `backend/`

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/composer.json` (modify)
  - `backend/composer.lock` (regenerated)
  - `backend/config/filament.php` (published)
  - `backend/bootstrap/providers.php` (modify — registers `AdminPanelProvider`)
- **Details:**
  Inside the backend container (`make shell`), run:

  ```bash
  composer require filament/filament:"^3.2" -W
  php artisan filament:install --panels
  ```

  When prompted for the panel ID, use `admin`. The installer generates `backend/app/Providers/Filament/AdminPanelProvider.php` and registers it in `bootstrap/providers.php`. Panel configuration happens in Task 2 — accept the installer defaults for now.

  Publish the Filament config and run the asset publisher:

  ```bash
  php artisan vendor:publish --tag=filament-config
  php artisan filament:assets
  ```

  Filament ships its own asset pipeline — no Vite/npm work needed for the panel itself. `filament:assets` runs once per deploy.

- **Acceptance Criteria:**
  - [ ] `filament/filament` 3.2.x present in `backend/composer.json`
  - [ ] `composer install` succeeds inside the backend container
  - [ ] `AdminPanelProvider` exists at `backend/app/Providers/Filament/AdminPanelProvider.php` and is registered in `backend/bootstrap/providers.php`
  - [ ] `php artisan filament:assets` completes without error

---

### Task 2: Configure the admin panel — subdomain binding, admin guard, session cookie

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Providers/Filament/AdminPanelProvider.php` (modify)
  - `backend/config/filament.php` (modify — read `admin_domain` from env)
  - `backend/config/app.php` (modify — add `primary_domain` key)
  - `backend/.env.example` (modify — add `ADMIN_DOMAIN`, `APP_PRIMARY_DOMAIN`, admin session vars)
- **Details:**
  Bind the Filament panel to the admin subdomain and to a (not-yet-created) `admin` auth guard. The `admin` guard and the `admin_users` provider are introduced in Plan 02 — this task pre-configures Filament to use them once they exist; until then the panel's login page will render but login will fail (which is fine for Plan 01's acceptance criteria).

  Panel config (illustrative — adapt to Filament 3.2's current API):

  ```php
  // backend/app/Providers/Filament/AdminPanelProvider.php
  public function panel(Panel $panel): Panel
  {
      return $panel
          ->id('admin')
          ->path('/')                                          // whole subdomain is the panel
          ->domain(config('filament.admin_domain'))            // e.g. admin.finalcut.test
          ->authGuard('admin')                                 // created in Plan 02
          ->login()
          ->colors(['primary' => Color::Amber])
          ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
          ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
          ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
          ->middleware([
              EncryptCookies::class,
              AddQueuedCookiesToResponse::class,
              StartSession::class,
              AuthenticateSession::class,
              ShareErrorsFromSession::class,
              VerifyCsrfToken::class,
              SubstituteBindings::class,
              DisableBladeIconComponents::class,
              DispatchServingFilamentEvent::class,
          ])
          ->authMiddleware([Authenticate::class]);
  }
  ```

  Add to `backend/config/filament.php`:

  ```php
  'admin_domain' => env('ADMIN_DOMAIN', 'admin.finalcut.test'),
  ```

  Add to `backend/config/app.php`:

  ```php
  'primary_domain' => env('APP_PRIMARY_DOMAIN', 'finalcut.test'),
  ```

  Add to `backend/.env.example`:

  ```
  APP_PRIMARY_DOMAIN=finalcut.test
  ADMIN_DOMAIN=admin.finalcut.test
  # Session cookie config — admin session cookie is scoped to the admin subdomain
  # only, never to the customer domain. The customer session cookie (Sanctum +
  # nuxt-auth-utils) continues to use SESSION_DOMAIN=finalcut.test.
  ADMIN_SESSION_COOKIE=admin_session
  ADMIN_SESSION_DOMAIN=admin.finalcut.test
  ```

  **Session cookie scoping.** Filament 3 does not natively support per-panel session drivers, but `AdminPanelProvider` can push a middleware that overrides the cookie name + domain for requests inside the panel. Add a small `ScopeAdminSession` middleware that runs before `StartSession::class` and sets:

  ```php
  config()->set('session.cookie', env('ADMIN_SESSION_COOKIE', 'admin_session'));
  config()->set('session.domain', env('ADMIN_SESSION_DOMAIN', 'admin.finalcut.test'));
  config()->set('cache.prefix', 'admin_session');  // redis key prefix
  ```

  Register this middleware inside `AdminPanelProvider::panel()->middleware([...])` as the first entry. Document the approach in the middleware's class docblock and in the progress journal.

  Plan 02 will validate this with a session-cookie-scoping feature test — this task lays the configuration, Plan 02 tests it against a real `admin_users` login.

- **Acceptance Criteria:**
  - [ ] `config('filament.admin_domain')` resolves to `admin.finalcut.test` in dev
  - [ ] `config('app.primary_domain')` resolves to `finalcut.test` in dev
  - [ ] `AdminPanelProvider` binds `->domain(config('filament.admin_domain'))`
  - [ ] `AdminPanelProvider` binds `->authGuard('admin')` (even though the guard does not yet exist — it will resolve once Plan 02 lands)
  - [ ] `ScopeAdminSession` middleware registered and sets `session.cookie`, `session.domain`, `cache.prefix` for admin requests
  - [ ] `.env.example` documents the new env vars

---

### Task 3: Nginx admin vhost

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `nginx/templates/conf.d/admin.conf.template` (new)
- **Details:**
  Add a second template file alongside the existing `default.conf.template`. The nginx image auto-renders every `.template` file in `/etc/nginx/templates/` with `envsubst`, so the new file needs no compose changes.

  ```nginx
  # admin.conf.template — served on admin.${APP_DOMAIN}, fastcgi to the same
  # backend PHP-FPM container as the customer site. Never proxies to frontend.

  server {
      listen 80;
      listen [::]:80;
      server_name admin.${APP_DOMAIN};
      return 301 https://$host$request_uri;
  }

  upstream admin_backend {
      server backend:9000;
  }

  server {
      listen 443 ssl;
      http2 on;
      listen [::]:443 ssl;
      server_name admin.${APP_DOMAIN};

      ssl_certificate /etc/nginx/certs/server.pem;
      ssl_certificate_key /etc/nginx/certs/server.key;
      ssl_protocols TLSv1.2 TLSv1.3;

      root /var/www/html/public;
      index index.php;

      # --- Plan 09 will insert IP allowlist + rate-limit directives here ---
      # --- They are intentionally absent in Plan 01 to keep scope honest. ---

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          fastcgi_pass admin_backend;
          fastcgi_index index.php;
          fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
          fastcgi_param HTTP_HOST $host;
          include fastcgi_params;
      }

      # Static assets served directly from the backend container's public/ dir
      location ~* \.(?:css|js|woff2?|ttf|eot|ico|png|jpg|jpeg|gif|svg)$ {
          try_files $uri =404;
          expires 7d;
          access_log off;
      }

      # Never proxy_pass to the Nuxt frontend from the admin vhost.
      # The admin panel is server-rendered entirely by PHP-FPM.
  }
  ```

  **Certs.** `nginx/certs/generate-certs.sh:71` generates a wildcard SAN including `*.${APP_DOMAIN}`, so the admin subdomain is already covered. No cert regeneration required. If the script is later narrowed, the admin vhost will break — Plan 01's smoke tests will catch that.

  **Scope note.** IP allowlist, rate limiting, and Fail2ban belong to Plan 09. This task establishes only the vhost and TLS termination.

  **Windows hosts file (WSL2).** Dev users need `admin.finalcut.test` added to `C:\Windows\System32\drivers\etc\hosts` pointing at `127.0.0.1`. Document this in `docs/progress/admin-v1.md` setup notes (Task 6). No automation — it's a one-time user step.

- **Acceptance Criteria:**
  - [ ] `nginx/templates/conf.d/admin.conf.template` exists and is valid nginx syntax
  - [ ] Rendered vhost serves `admin.${APP_DOMAIN}` with TLS from the existing wildcard cert
  - [ ] HTTP → HTTPS redirect works
  - [ ] `location ~ \.php$` fastcgis to `backend:9000`
  - [ ] No `proxy_pass` to the frontend service in this vhost
  - [ ] No rate-limit or IP-allowlist directives (deferred to Plan 09)
  - [ ] After `make up`, `curl -ks https://admin.finalcut.test/` returns a response from Laravel (200 or 302 redirect to Filament login once Task 2's config is live)

---

### Task 4: Laravel route-domain scoping + `RouteDomainScopingTest`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/bootstrap/app.php` (modify)
  - `backend/tests/Feature/RouteDomainScopingTest.php` (new)
- **Details:**
  Today, every route in `backend/routes/api.php` answers on any `Host` header because `bootstrap/app.php` loads the API routes without a domain constraint. Once `admin.finalcut.test` proxies to the same PHP-FPM container, that means `GET https://admin.finalcut.test/api/movies` would return customer data.

  Replace the default API loader with a `then:` closure that wraps API routes in `Route::domain()`:

  ```php
  // backend/bootstrap/app.php
  return Application::configure(basePath: dirname(__DIR__))
      ->withRouting(
          web: __DIR__.'/../routes/web.php',
          commands: __DIR__.'/../routes/console.php',
          health: '/up',
          then: function () {
              Route::middleware('api')
                  ->domain(config('app.primary_domain'))
                  ->prefix('api')
                  ->group(base_path('routes/api.php'));
          },
      )
      // ...
  ```

  Drop the `api: __DIR__.'/../routes/api.php'` argument — the `then:` closure replaces it. The `withRouting()` call still registers `web.php` without a domain constraint because the `web.php` file currently only contains a catch-all 404 fallback. If `web.php` grows route content, it must be scoped too — Plan 09's docs update documents this convention for future contributors.

  Filament's `AdminPanelProvider->domain(...)` (Task 2) binds every Filament-registered route to the admin subdomain. No further work needed for admin routes.

  **Test — `backend/tests/Feature/RouteDomainScopingTest.php`:**

  ```php
  <?php

  use Illuminate\Support\Facades\Route;

  it('scopes every API route to the primary domain', function () {
      $primary = config('app.primary_domain');

      $apiRoutes = collect(Route::getRoutes())
          ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'));

      expect($apiRoutes)->not->toBeEmpty();

      foreach ($apiRoutes as $route) {
          expect($route->getDomain())
              ->toBe($primary, "Route {$route->uri()} leaks onto non-primary domain");
      }
  });

  it('scopes every Filament panel route to the admin domain', function () {
      $adminDomain = config('filament.admin_domain');

      $filamentRoutes = collect(Route::getRoutes())
          ->filter(fn ($r) => str_contains($r->getName() ?? '', 'filament.admin'));

      expect($filamentRoutes)->not->toBeEmpty();

      foreach ($filamentRoutes as $route) {
          expect($route->getDomain())
              ->toBe($adminDomain, "Filament route {$route->uri()} leaks off the admin domain");
      }
  });

  it('no registered route has a null domain', function () {
      $unscoped = collect(Route::getRoutes())
          ->filter(fn ($r) => $r->getDomain() === null)
          // Exempt internal framework routes that Laravel registers without a
          // domain (like /up health check). Keep this list tight.
          ->filter(fn ($r) => !in_array($r->uri(), ['up', 'sanctum/csrf-cookie']));

      expect($unscoped)->toBeEmpty(
          'Routes without a domain constraint: ' . $unscoped->pluck('uri')->implode(', ')
      );
  });
  ```

  The third test is the most important — it catches a future contributor adding an endpoint outside both domain groups.

- **Acceptance Criteria:**
  - [ ] `backend/bootstrap/app.php` uses a `then:` closure that wraps `api.php` in `Route::domain(config('app.primary_domain'))`
  - [ ] `php artisan route:list --domain=admin.finalcut.test` lists Filament routes and zero API routes
  - [ ] `php artisan route:list --domain=finalcut.test` lists API routes and zero Filament routes
  - [ ] `backend/tests/Feature/RouteDomainScopingTest.php` exists and all three tests pass under `make test-backend`
  - [ ] `curl -ks https://admin.finalcut.test/api/movies` returns 404
  - [ ] `curl -ks https://finalcut.test/admin/login` returns 404 (or 301/302 to customer site — never renders Filament)

---

### Task 5: Makefile targets

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `Makefile` (modify)
- **Details:**
  Admin runs inside the backend container, so admin Make targets are thin wrappers around the existing backend commands. Their value is clarity ("I want to do an admin-related thing") rather than new mechanics.

  Add to `Makefile`:

  ```makefile
  admin-shell:
  	docker compose exec -u 1000 backend sh

  admin-migrate:
  	docker compose exec -u 1000 backend php artisan migrate

  admin-test:
  	docker compose exec -u 1000 backend php artisan test --testsuite=Feature --filter=Admin

  admin-create-user:
  	docker compose exec -u 1000 -it backend php artisan admin:create-user

  admin-filament-assets:
  	docker compose exec -u 1000 backend php artisan filament:assets
  ```

  `admin-test` filters to the `Admin` namespace under `backend/tests/Feature/`. `admin-create-user` is defined here but the artisan command it calls is created in Plan 02 Task 2 — running it before Plan 02 fails with "command not found," which is fine.

  Do not add a new `test-all` target; the existing `make test` already runs backend (which includes admin tests) + frontend.

- **Acceptance Criteria:**
  - [ ] All five targets added to `Makefile`
  - [ ] `make admin-shell` opens a shell as UID 1000 in the backend container
  - [ ] `make admin-test` runs with no filter matches (no admin tests yet) and exits 0 (or reports "no tests executed" — both acceptable in Plan 01)
  - [ ] `make admin-create-user` fails cleanly with "command not found" until Plan 02 adds the artisan command
  - [ ] `make admin-filament-assets` runs and re-publishes Filament assets

---

### Task 6: Scaffold progress journal

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `docs/progress/admin-v1.md` (new)
- **Details:**
  Create `admin-v1.md` with the 9-step skeleton per project convention. Each step uses the format documented in root `CLAUDE.md`:

  ```markdown
  # Admin v1 Progress Journal

  Tracks execution of `docs/plans/admin/v1/*`.

  ## Step 1: Admin Panel Scaffold & Nginx Vhost
  **Status:** 🟡 In Progress
  **Started:** YYYY-MM-DD
  **Completed:** —

  ### Work Done
  - [date] Description

  ### Decisions
  - [date] Decision made and why

  ### Blockers
  - [date] Blocker description → resolution

  ### Files Changed
  - `path/to/file.ext` — what changed

  ## Step 2: Auth, Roles, Permissions & Audit Log
  **Status:** 🔲 Not Started
  ...
  ```

  All nine steps scaffolded with status `🔲 Not Started` except Step 1 which is `🟡 In Progress` during execution of this plan.

  Include a one-time WSL2 setup note in the Step 1 body: "Add `admin.finalcut.test 127.0.0.1` to `C:\Windows\System32\drivers\etc\hosts`."

- **Acceptance Criteria:**
  - [ ] `docs/progress/admin-v1.md` exists with all 9 step headers
  - [ ] Format matches `docs/progress/backend-v1.md`
  - [ ] Step 1 reflects in-progress state
  - [ ] WSL2 hosts-file setup step documented

---

## Testing Requirements

**Smoke tests** (run after `make up`):

- `curl -ks https://admin.finalcut.test/` returns an HTTP 200/302 from Laravel (Filament login redirect)
- `curl -ks https://admin.finalcut.test/login` renders Filament's default login page
- `curl -ks https://finalcut.test/admin/login` returns 404
- `curl -ks https://admin.finalcut.test/api/movies` returns 404

**Pest tests:**

- `backend/tests/Feature/RouteDomainScopingTest.php` — three tests, all pass under `make test-backend`
- No other unit tests in this plan — business logic starts in Plan 02.

## Dependencies Map

```text
Task 1 (Filament install) ← foundational
Task 2 (panel config) ← needs Task 1
Task 3 (nginx vhost) ← parallel to Task 2 (independent)
Task 4 (route-domain scoping + test) ← needs Task 2 (reads config('filament.admin_domain'))
Task 5 (Makefile) ← parallel (no dependency on the other tasks)
Task 6 (progress journal) ← parallel
```

## Risks & Open Questions

1. **Filament installer prompts interactively.** Use `--no-interaction` where possible and document any required manual answers in the progress journal.
2. **Session cookie scoping via middleware is Filament-3-specific.** If Filament 4 (or a Filament 3 minor bump) exposes a first-class per-panel session config, migrate `ScopeAdminSession` to that API. For now, the middleware approach is the supported path.
3. **`web.php` growth.** The current `web.php` is a 404 fallback with no routes; it isn't wrapped in `Route::domain()`. If future work adds content to `web.php`, those routes will answer on every domain. Plan 09 adds a docs note reminding contributors of the convention.
4. **Shared PHP-FPM process pool.** Admin and customer API run in the same PHP-FPM container. A slow admin operation consumes a worker that the customer API could have used. Not a Plan 01 concern, but Plan 09 documents the risk and its mitigations (queued heavy work, per-vhost resource limits via `location` blocks if needed).
