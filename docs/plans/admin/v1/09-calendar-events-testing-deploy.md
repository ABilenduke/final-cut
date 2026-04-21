# Plan 09: Calendar Events, Testing & Deploy

> **Priority:** Should Have
> **Complexity:** L
> **Depends On:** Plans 04–08 (every domain plan contributes resources that need testing)
> **Unlocks:** Production admin deployment

## Overview

The finisher. `CalendarEventResource` rounds out domain coverage. Then the deployment hardening layer: IP allowlist middleware, nginx rate limits on login, Fail2ban jail, queue worker + scheduler containers, CI workflow update to include admin, production compose file, Let's Encrypt cert automation. Finally the docs layer: `CLAUDE.md` admin section, `docs/progress/admin-v1.md` finalization, and a post-launch runbook.

This plan is the gate between "admin works in dev" and "admin is safe to ship to prod."

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 7 Deployment
- `docs/plans/backend/v1/08-testing-and-seeding.md` — backend CI reference
- `docs/plans/admin/v1/01-admin-scaffold-and-docker.md` — Dockerfile/compose baseline

---

## Tasks

### Task 1: CalendarEventResource

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/CalendarEventResource.php` (new)
  - `admin/app/Filament/Resources/CalendarEventResource/Pages/*`
- **Details:**
  `$permissionPrefix = 'events'`. Standard CRUD, no custom services required (events are admin-driven content with no invariants today — direct Eloquent writes are allowed per spec § 2.6 exception).

  **Form schema:**
  ```php
  Section::make('Identity')->schema([
      TextInput::make('title')->required()->maxLength(255)
          ->live(onBlur: true)
          ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
      TextInput::make('slug')->required()->unique(ignoreRecord: true),
      Select::make('type')->options([
          'special_event' => 'Special Event',
          'loyalty_exclusive' => 'Loyalty Exclusive',
          'private_screening_blackout' => 'Private Screening Blackout',
      ])->required()->reactive(),
      Toggle::make('loyalty_only')->visible(fn ($get) => $get('type') === 'loyalty_exclusive')->default(false),
  ])->columns(2),

  Section::make('Schedule')->schema([
      DatePicker::make('date')->required(),
      TimePicker::make('start_time')->seconds(false),
      TimePicker::make('end_time')->seconds(false)->nullable()
          ->helperText('Leave blank for all-day events'),
  ])->columns(3),

  Section::make('Content')->schema([
      Textarea::make('description')->rows(5),
      FileUpload::make('image_path')->image()->directory('calendar-events')->disk('public'),
      TextInput::make('ticket_url')->url()->nullable()
          ->helperText('Optional external ticket/RSVP link'),
  ]),

  Section::make('Accessibility')->schema([
      CheckboxList::make('accessibility_tags')->options([
          'sensory_friendly' => 'Sensory Friendly',
          'open_caption' => 'Open Caption',
          'audio_described' => 'Audio Described',
      ]),
  ]),
  ```

  **Table:**
  ```php
  ImageColumn::make('image_path')->square(),
  TextColumn::make('title')->searchable()->sortable(),
  BadgeColumn::make('type')->colors([
      'warning' => 'special_event',
      'success' => 'loyalty_exclusive',
      'gray' => 'private_screening_blackout',
  ]),
  TextColumn::make('date')->date()->sortable(),
  TextColumn::make('start_time')->time(),
  IconColumn::make('loyalty_only')->boolean()->label('Members only'),
  TextColumn::make('accessibility_tags')->badge()->separator(','),
  ```

  **Filters:** type, date range, accessibility tag, loyalty-only.

- **Acceptance Criteria:**
  - [ ] Resource registers under "Content" navigation group
  - [ ] Form renders all documented fields
  - [ ] Slug auto-derived from title
  - [ ] Accessibility tags persisted as array
  - [ ] Loyalty-only toggle visible only for loyalty_exclusive type
  - [ ] Permission gating works per role

---

### Task 2: Full Pest test suite pass

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/CalendarEventResourceTest.php` (new)
  - All existing admin test files (modify if needed)
- **Details:**
  Every Filament Resource created in Plans 04–08 should already have CRUD + policy tests. Plan 09 adds Calendar and makes sure the full suite passes end-to-end.

  Checklist — must be green before shipping:
  - [ ] `ModelParityTest` (Plan 03)
  - [ ] `BaseResourceTest` (Plan 03)
  - [ ] Plan 02 auth suite
  - [ ] Plan 04 movie suite
  - [ ] Plan 05 location/auditorium/seat-generator suite (+ visual editor if shipped)
  - [ ] Plan 06 showtime suite + cancellation flow integration
  - [ ] Plan 07 booking/user/loyalty suite
  - [ ] Plan 08 menu/promo/gift-card suite + gift card void integration
  - [ ] Plan 09 calendar event suite

  Run `make admin-test` → zero failures. Any skipped/incomplete tests documented in the progress journal.

- **Acceptance Criteria:**
  - [ ] All admin Pest tests green
  - [ ] `make test-all` (backend + frontend + admin) green
  - [ ] No skipped tests without documented rationale

---

### Task 3: IP allowlist middleware

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Http/Middleware/AdminIpAllowlist.php` (new)
  - `admin/app/Providers/Filament/AdminPanelProvider.php` (modify — register middleware)
  - `admin/config/admin.php` (new config)
- **Details:**
  Production-only middleware reading a CIDR list from env and blocking requests from IPs outside the list.

  ```php
  class AdminIpAllowlist
  {
      public function handle(Request $request, Closure $next)
      {
          if (app()->environment('local', 'testing')) {
              return $next($request);
          }

          $allowlist = array_filter(array_map('trim', explode(',', config('admin.ip_allowlist', ''))));
          if (empty($allowlist)) {
              // Fail-open in dev/staging but log a warning
              logger()->warning('AdminIpAllowlist: no allowlist configured, allowing all IPs');
              return $next($request);
          }

          $clientIp = $request->ip();
          foreach ($allowlist as $cidr) {
              if ($this->ipInCidr($clientIp, $cidr)) {
                  return $next($request);
              }
          }

          logger()->info('AdminIpAllowlist: rejected IP', ['ip' => $clientIp]);
          abort(403, 'Access denied');
      }

      private function ipInCidr(string $ip, string $cidr): bool
      {
          if (!str_contains($cidr, '/')) {
              return $ip === $cidr;
          }
          [$subnet, $bits] = explode('/', $cidr);
          $ipLong = ip2long($ip);
          $subnetLong = ip2long($subnet);
          $mask = -1 << (32 - (int) $bits);
          return ($ipLong & $mask) === ($subnetLong & $mask);
      }
  }
  ```

  Config:
  ```php
  // config/admin.php
  return [
      'ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),
  ];
  ```

  Register middleware globally on the admin panel:
  ```php
  // AdminPanelProvider::panel()
  ->middleware([
      AdminIpAllowlist::class,
      // ... existing middleware stack
  ]);
  ```

  Document env configuration in `admin/.env.production.example`:
  ```
  ADMIN_IP_ALLOWLIST=203.0.113.0/24,198.51.100.10
  ```

- **Acceptance Criteria:**
  - [ ] Middleware blocks production requests from IPs outside the allowlist
  - [ ] Dev/test environments bypass
  - [ ] Empty allowlist in prod logs warning but allows (fail-open to avoid locking out during deploy)
  - [ ] CIDR matching works (e.g., `203.0.113.0/24` matches `203.0.113.42`)
  - [ ] Rejected requests return 403, logged with IP

---

### Task 4: Nginx rate limit on admin login

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `nginx/conf.d/admin.finalcut.test.conf` (modify — tighten from Plan 01)
  - `nginx/nginx.conf` or shared snippet (confirm `limit_req_zone` declared)
- **Details:**
  Plan 01 Task 4 declared the zone; Plan 09 applies the limit. Final config:

  ```nginx
  # nginx/nginx.conf or snippet
  http {
      limit_req_zone $binary_remote_addr zone=admin_login:10m rate=5r/m;
  }

  # nginx/conf.d/admin.finalcut.test.conf
  server {
      # ... existing TLS config

      location = /admin/login {
          limit_req zone=admin_login burst=3 nodelay;
          limit_req_status 429;
          try_files $uri /index.php?$query_string;
      }

      location ~ \.php$ {
          # ... existing fastcgi
      }
  }
  ```

  Rate: 5 requests per minute per IP, burst 3. Exceeding returns 429 Too Many Requests.

- **Acceptance Criteria:**
  - [ ] `/admin/login` POST returns 429 after 5 rapid attempts
  - [ ] Successful logins not penalized
  - [ ] Burst of 3 concurrent attempts allowed
  - [ ] Manual test: curl against login 10 times in 30 seconds → last 5 return 429

---

### Task 5: Fail2ban jail

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `fail2ban/jail.d/admin-login.conf` (new)
  - `fail2ban/filter.d/admin-login.conf` (new)
- **Details:**
  Existing Fail2ban container (per project infrastructure) gets a new jail matching failed admin login attempts.

  Filter — match Laravel's log pattern for failed admin logins:
  ```ini
  # fail2ban/filter.d/admin-login.conf
  [Definition]
  failregex = ^.*"ip":"<HOST>".*"message":"Failed admin login".*$
  ignoreregex =
  ```

  Jail:
  ```ini
  # fail2ban/jail.d/admin-login.conf
  [admin-login]
  enabled = true
  filter = admin-login
  logpath = /var/log/admin/laravel.log
  maxretry = 5
  findtime = 600
  bantime = 86400
  action = iptables-multiport[name=admin-login, port="80,443"]
  ```

  5 failed logins in 10 minutes → 24 hour IP ban.

  Add Laravel event listener to log failed admin logins in the structured JSON format the filter expects:
  ```php
  // admin/app/Providers/EventServiceProvider.php
  Event::listen(Failed::class, function (Failed $event) {
      if ($event->guard === 'admin') {
          logger()->info('Failed admin login', [
              'ip' => request()->ip(),
              'email' => $event->credentials['email'] ?? null,
          ]);
      }
  });
  ```

- **Acceptance Criteria:**
  - [ ] Fail2ban jail registered
  - [ ] Filter regex matches Laravel's log output
  - [ ] Event listener logs failed admin logins
  - [ ] Manual test: 6 failed logins from test IP → IP gets banned, cannot reach admin for 24h

---

### Task 6: Queue worker + scheduler containers

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/Dockerfile` (modify — add worker stage)
  - `docker-compose.yml` (modify — add admin-worker, admin-scheduler services)
- **Details:**
  Admin ships three runtime processes:
  1. `admin` (existing) — PHP-FPM serving HTTP
  2. `admin-worker` (new) — `php artisan queue:work` for async jobs (cancellation emails, gift card void mail, etc.)
  3. `admin-scheduler` (new) — `php artisan schedule:work` for `activitylog:clean` and other scheduled commands

  Add compose services:
  ```yaml
  admin-worker:
    build:
      context: ./admin
      target: development
    volumes:
      - ./admin:/app
      - ./backend:/backend:ro
      - admin-vendor:/app/vendor
    command: php artisan queue:work --tries=3 --timeout=60
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - finalcut

  admin-scheduler:
    build:
      context: ./admin
      target: development
    volumes:
      - ./admin:/app
      - ./backend:/backend:ro
      - admin-vendor:/app/vendor
    command: php artisan schedule:work
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - finalcut
  ```

  For production, use Laravel Horizon or Supervisor — document in `admin/.env.production.example` but don't block v1 on it.

- **Acceptance Criteria:**
  - [ ] `admin-worker` service processes jobs from redis queue
  - [ ] `admin-scheduler` runs `activitylog:clean` daily (verify via `make admin-shell` + `php artisan schedule:list`)
  - [ ] Both services restart on failure
  - [ ] Gift card void email (Plan 08) delivered via worker in dev

---

### Task 7: Production docker-compose

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `docker-compose.prod.yml` (modify — add admin services)
  - `admin/Dockerfile` (modify — add `production` stage)
  - `admin/.env.production.example` (new)
- **Details:**
  Add production variants of admin, admin-worker, admin-scheduler to `docker-compose.prod.yml`. Production stage in Dockerfile:

  ```dockerfile
  FROM base AS production
  COPY composer.json composer.lock /app/
  RUN composer install --no-dev --optimize-autoloader --no-interaction
  COPY . /app
  RUN php artisan config:cache && php artisan route:cache && php artisan view:cache
  RUN php artisan filament:assets
  RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
  USER www-data
  WORKDIR /app
  CMD ["php-fpm"]
  ```

  Production compose:
  ```yaml
  admin:
    build:
      context: ./admin
      target: production
    environment:
      APP_ENV: production
      APP_DEBUG: false
      DB_HOST: ${DB_HOST}
      REDIS_HOST: ${REDIS_HOST}
      ADMIN_IP_ALLOWLIST: ${ADMIN_IP_ALLOWLIST}
      SESSION_DOMAIN: .admin.finalcut.com
      SESSION_SECURE_COOKIE: true
    depends_on:
      - postgres
      - redis

  admin-worker: # similar, with production command
  admin-scheduler: # similar
  ```

  `.env.production.example` documents:
  - `APP_KEY` generation reminder
  - `ADMIN_IP_ALLOWLIST` with example CIDRs
  - `FINANCE_NOTIFICATION_EMAIL` real address
  - `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` production value
  - TLS cert paths for Let's Encrypt

- **Acceptance Criteria:**
  - [ ] Production Dockerfile stage builds cached artifacts
  - [ ] Production compose service references prod target
  - [ ] Env example documents every required variable
  - [ ] Debug disabled in prod
  - [ ] Session cookies secure + scoped to admin prod domain

---

### Task 8: CI workflow — include admin

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `.github/workflows/ci.yml` (modify)
- **Details:**
  Extend existing CI to run admin tests alongside backend + frontend:

  ```yaml
  jobs:
    # existing backend + frontend jobs

    admin:
      runs-on: ubuntu-latest
      services:
        postgres:
          image: postgres:18
          env:
            POSTGRES_PASSWORD: test
          options: >-
            --health-cmd pg_isready
          ports: [5432:5432]
        redis:
          image: redis:7
          ports: [6379:6379]
      steps:
        - uses: actions/checkout@v4
        - uses: shivammathur/setup-php@v2
          with:
            php-version: '8.4'
            extensions: pdo_pgsql, redis, intl, bcmath
        - name: Install backend (dependency for shared code)
          working-directory: backend
          run: composer install --no-interaction --no-scripts
        - name: Install admin
          working-directory: admin
          run: composer install --no-interaction --no-scripts
        - name: Setup admin env
          working-directory: admin
          run: cp .env.example .env && php artisan key:generate
        - name: Run migrations
          working-directory: admin
          env:
            DB_HOST: localhost
            DB_DATABASE: final_cut_test
          run: php artisan migrate --force
        - name: Run admin tests
          working-directory: admin
          env:
            DB_HOST: localhost
          run: php artisan test
  ```

  Ensure the shared backend code is mounted/available in the admin job — GitHub Actions doesn't use Docker volumes, so the CI job needs the backend directory checked out alongside admin (the monorepo layout handles this automatically).

- **Acceptance Criteria:**
  - [ ] `admin` job added to CI workflow
  - [ ] Shared postgres + redis services configured
  - [ ] Backend checkout available for admin's classmap
  - [ ] `php artisan test` runs in admin working directory
  - [ ] Full matrix (backend + frontend + admin) green on main

---

### Task 9: Documentation updates

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `CLAUDE.md` (modify)
  - `docs/README.md` (modify)
  - `docs/progress/admin-v1.md` (finalize)
  - `admin/README.md` (modify — add prod operations notes)
- **Details:**
  **Root CLAUDE.md** — confirm the "Admin app" section added in Plan 01 is current:
  - Domain dev + prod
  - Make commands (include `admin-create-user`, `test-all`)
  - Write boundary rule link to spec § 2.6
  - Points at `docs/plans/admin/v1/00-index.md` and the spec

  **docs/README.md** — admin section in navigation, with:
  - Link to `docs/plans/admin/v1/00-index.md`
  - Link to `docs/superpowers/specs/2026-04-20-admin-section-design.md`
  - Link to `docs/progress/admin-v1.md`

  **Progress journal** — mark each step complete with final notes, decisions made during execution, files changed. Keep this as the artifact of the admin v1 rollout for future reference.

  **admin/README.md** — add production operations section:
  - How to scale worker containers
  - How to review the activity log in prod
  - Where logs live
  - Point at the Fail2ban jail
  - Emergency procedure for unbanning a legitimate IP (manual jail command)

- **Acceptance Criteria:**
  - [ ] CLAUDE.md admin section current
  - [ ] docs/README.md lists admin planning + progress docs
  - [ ] Progress journal complete with per-step status
  - [ ] admin/README.md covers prod ops basics

---

### Task 10: Post-launch runbook

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `docs/runbooks/admin-operations.md` (new)
- **Details:**
  Short reference for common admin operations in production:

  - **Create a new admin user:** `make admin-create-user`
  - **Unban a legitimate IP:** `docker exec fail2ban fail2ban-client set admin-login unbanip X.X.X.X`
  - **View activity log:** navigate to `/admin/activity` as an admin user
  - **Process gift card void queue:** email forwarded to finance; no automated tool in v1
  - **Process cancelled showtime follow-up:** visit `/admin/cancelled-showtime-followup` queue page
  - **Adjust loyalty points for a single customer:** UserResource → user view → Adjust Points action
  - **Trigger TMDB enrichment for a single movie:** MovieResource → row action "Enrich from TMDB"
  - **Rotate admin password:** admin user uses "Forgot password" flow on `/admin/login`
  - **Emergency: disable admin panel:** set `ADMIN_IP_ALLOWLIST=127.0.0.1` in prod env and redeploy — only localhost can reach it

- **Acceptance Criteria:**
  - [ ] Runbook covers the 9 most common operations
  - [ ] Each procedure references the exact command / URL
  - [ ] Emergency shutdown documented

---

## Testing Requirements

- **Pest Feature Tests:** calendar event resource + full suite pass
- **Integration:** production deployment smoke test (separate task outside this plan — run against staging environment)
- **CI:** admin job passes on every PR to main

## Dependencies Map

```
Task 1 (calendar events) ← parallel
Task 2 (full test pass) ← needs Tasks 1 + all prior plans
Task 3 (IP allowlist) ← parallel
Task 4 (nginx rate limit) ← needs Plan 01 Task 4 baseline
Task 5 (Fail2ban) ← needs Task 4
Task 6 (worker + scheduler) ← needs existing Plan 01 compose
Task 7 (prod compose) ← needs Tasks 3, 6
Task 8 (CI) ← parallel
Task 9 (docs) ← parallel
Task 10 (runbook) ← needs Tasks 3, 5, 6
```

## Risks & Open Questions

1. **IP allowlist deployment chicken-and-egg.** If the allowlist is set too restrictively before the deploy completes, admins cannot reach the newly-deployed panel. Deploy sequence: empty allowlist → deploy → verify admin can log in → set real allowlist → restart container. Document in the runbook.
2. **Queue worker health.** If `admin-worker` crashes and doesn't restart, cancellation emails silently queue. Add a Horizon dashboard or a simple health check in a v2 plan. v1 relies on `restart: unless-stopped` + human monitoring.
3. **Fail2ban IPv6.** The jail config uses `iptables-multiport`; IPv6 requires `ip6tables-multiport`. If the production environment exposes IPv6, duplicate the jail with the IPv6 action.
4. **CI backend classmap.** The GitHub Actions admin job needs the `backend/` directory checked out for the Composer classmap to resolve. Since this is a monorepo, default checkout gets both — but if the CI is ever split into separate repos, the classmap approach breaks. Document the coupling.
5. **Production TLS certs.** Let's Encrypt automation for `admin.finalcut.com` uses the same Certbot setup as the customer domain. If the customer cert renewal fails silently, admin cert may too. Share the renewal monitoring across both domains.
