# Plan 09: Calendar Events, Testing & Deploy

> **Priority:** Should Have
> **Complexity:** L
> **Depends On:** Plans 04–08 (every domain plan contributes resources that need testing). Tasks 6, 7, and 8 additionally inherit Plan 03's shared-code / backend classmap model — if that approach is revised, the Dockerfile, compose volumes, and CI checkout steps in this plan must be updated in lockstep.
> **Unlocks:** Production admin deployment

## Overview

The finisher. `CalendarEventResource` rounds out domain coverage. Then the deployment hardening layer: IP allowlist middleware, nginx rate limits on login, Fail2ban jail, queue worker + scheduler containers, CI workflow update to include admin, production compose file, Let's Encrypt cert automation. Finally the docs layer: `CLAUDE.md` admin section, `docs/progress/admin-v1.md` finalization, and a post-launch runbook.

This plan is the gate between "admin works in dev" and "admin is safe to ship to prod." The security posture must be **fail-closed by default** — a missing env var should never quietly expose the admin panel.

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
  `$permissionPrefix = 'events'`. Standard CRUD, no custom services required. Calendar events are **admin-authored editorial content** — not customer-owned operational data — so direct Eloquent writes are permitted per spec § 2.6 exception. This rationale is deliberately narrower than "no invariants today": scoping the exception to the *nature of the data* (editorial content with no customer-facing state transitions) rather than the *current absence of rules* makes it durable as the schema evolves.

  **Schema grounding:** All field names, types, and storage shapes — especially `accessibility_tags` (array/JSON column) and the `type` enum — must mirror the canonical `calendar_events` migration and `CalendarEvent` Eloquent model owned by the backend app. Before implementing the form schema, confirm the backend model's `$casts`, column types, and enum values, and adjust the Filament schema to match (e.g., if the model casts `accessibility_tags` to a specific array shape or uses a native Postgres enum for `type`). Do not assume storage shape — ground it against the backend migration.

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

  **Security posture:** **Fail-closed by default.** A missing or empty `ADMIN_IP_ALLOWLIST` in production denies all requests. An explicit, separately-scoped env flag (`ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true`) exists as a time-boxed deploy escape hatch; it is loud (error-level logging) and must be unset after bootstrap. This reverses the earlier draft's fail-open default — an admin hardening layer must never be silently disabled by a configuration mistake.

  **IPv4 scope:** v1 matches IPv4 only. The production admin domain is served behind an IPv4 load balancer; IPv6 ingress is explicitly out of scope for v1. If IPv6 ingress is ever added, this middleware must be replaced with a dual-stack library (e.g., `symfony/http-foundation`'s `IpUtils::checkIp`), and the Fail2ban jail (Task 5) must gain an `ip6tables-multiport` variant at the same time — the two must be revisited together.

  ```php
  class AdminIpAllowlist
  {
      public function handle(Request $request, Closure $next)
      {
          if (app()->environment('local', 'testing')) {
              return $next($request);
          }

          $clientIp = $request->ip();

          // v1 is IPv4-only. Reject IPv6 explicitly rather than letting ip2long()
          // silently return false and pattern-match as "not in allowlist" — the
          // explicit rejection is both clearer in logs and honest about scope.
          if ($clientIp !== null && str_contains($clientIp, ':')) {
              logger()->warning('AdminIpAllowlist: IPv6 request rejected (v1 is IPv4-only)', [
                  'ip' => $clientIp,
              ]);
              abort(403, 'Access denied');
          }

          $allowlist = array_filter(array_map('trim', explode(',', (string) config('admin.ip_allowlist', ''))));

          if (empty($allowlist)) {
              if (config('admin.ip_allowlist_emergency_open')) {
                  // Explicit, logged, time-boxed escape hatch. Must be unset after
                  // bootstrap; never leave true as a resting state.
                  logger()->error('AdminIpAllowlist: EMERGENCY_OPEN flag active — allowing all IPs', [
                      'ip' => $clientIp,
                  ]);
                  return $next($request);
              }

              // Fail closed. An unset env var is a misconfiguration, not permission.
              logger()->error('AdminIpAllowlist: no allowlist configured and EMERGENCY_OPEN not set — denying request', [
                  'ip' => $clientIp,
              ]);
              abort(403, 'Access denied');
          }

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
          if ($ipLong === false || $subnetLong === false) {
              return false;
          }
          $bits = (int) $bits;
          if ($bits < 0 || $bits > 32) {
              return false;
          }
          $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
          return ($ipLong & $mask) === ($subnetLong & $mask);
      }
  }
  ```

  Config:
  ```php
  // config/admin.php
  return [
      'ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),
      'ip_allowlist_emergency_open' => env('ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN', false),
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
  # Comma-separated list of IPv4 addresses or CIDR blocks allowed to reach the admin panel.
  # REQUIRED in production. Empty = deny all (unless emergency flag is also set).
  ADMIN_IP_ALLOWLIST=203.0.113.0/24,198.51.100.10

  # Emergency fail-open. Exists only to prevent lockout during initial deploy when
  # the allowlist has not yet been set. Allowed values:
  #   false (default) — fail closed if allowlist empty. This is the production resting state.
  #   true            — allow all requests when allowlist empty. Logs error-level warning.
  # NEVER leave this true outside a bootstrap window. Unset it the moment real CIDRs are live.
  ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false
  ```

- **Acceptance Criteria:**
  - [ ] Middleware blocks production requests from IPs outside the allowlist
  - [ ] Dev/test environments bypass
  - [ ] **Empty allowlist in production fails closed by default** — all requests return 403 with an error-level log line
  - [ ] `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` with empty allowlist allows requests but emits an error-level log entry on every request
  - [ ] IPv4 CIDR matching works (e.g., `203.0.113.0/24` matches `203.0.113.42`; `/32` and `/0` edge cases handled)
  - [ ] IPv6 requests are explicitly rejected with a logged warning (v1 scope)
  - [ ] Malformed CIDRs (bits outside 0–32, non-numeric) do not crash the request and log a warning
  - [ ] Rejected requests return 403, logged with IP
  - [ ] Pest test covers: allowed IP, blocked IP, empty allowlist fail-closed, emergency-open bypass, IPv6 rejection

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

  **Logging contract — the central guarantee of this task.** The Fail2ban filter and the Laravel log format are a tightly coupled pair. Rather than trying to regex against Laravel's *default* log channel (whose format depends on the stack, per-channel formatter, and Monolog version), the admin app ships a **dedicated logging channel** with a pinned Monolog JSON formatter. The filter matches that format exactly, and a sample log line is committed to the repo so the regex can be verified without running the full login flow.

  **Dedicated logging channel:**
  ```php
  // admin/config/logging.php — add to the 'channels' array
  'admin_auth_events' => [
      'driver' => 'single',
      'path' => storage_path('logs/admin-auth-events.log'),
      'level' => 'info',
      'formatter' => \Monolog\Formatter\JsonFormatter::class,
      'formatter_with' => [
          'batchMode' => \Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
          'appendNewline' => true,
      ],
      'permission' => 0640,
  ],
  ```

  **Event listener — writes only to the dedicated channel:**
  ```php
  // admin/app/Providers/EventServiceProvider.php
  use Illuminate\Auth\Events\Failed;
  use Illuminate\Support\Facades\Log;

  Event::listen(Failed::class, function (Failed $event) {
      if ($event->guard === 'admin') {
          Log::channel('admin_auth_events')->info('Failed admin login', [
              'ip' => request()->ip(),
              'email' => $event->credentials['email'] ?? null,
          ]);
      }
  });
  ```

  Monolog's `JsonFormatter` emits a single JSON object per line with a stable shape:
  ```json
  {"message":"Failed admin login","context":{"ip":"203.0.113.42","email":"attacker@example.com"},"level":200,"level_name":"INFO","channel":"admin_auth_events","datetime":"2026-04-21T12:34:56.000000+00:00","extra":[]}
  ```

  **Filter — pinned to that JSON shape:**
  ```ini
  # fail2ban/filter.d/admin-login.conf
  [Definition]
  # Matches Monolog JsonFormatter output from the admin_auth_events channel.
  # The "ip" field lives inside the "context" object.
  failregex = ^\{.*"message":"Failed admin login".*"context":\{[^}]*"ip":"<HOST>"[^}]*\}.*\}$
  ignoreregex =
  ```

  **Sample log line — CI-generated, not hand-written.** A committed static sample file will silently drift the first time Monolog changes its JSON output shape (a patch or major bump that reorders keys, renames fields, or adjusts `datetime` formatting). The filter would keep passing against the stale committed sample while the *actual* logs stop matching, and the gap only surfaces in production when a real attack isn't getting banned. To close that gap, CI generates the sample fresh on every run:

  - **CI step (`.github/workflows/ci.yml`, admin job):** spin up the admin container after migrations, fire a failed login via HTTP (`curl -X POST /admin/login --data 'email=nobody@example.com&password=wrong'` or the test-harness equivalent), tail `storage/logs/admin-auth-events.log` to capture the freshly-written line, write it to `/tmp/admin-login.sample.log`, and run `fail2ban-regex /tmp/admin-login.sample.log fail2ban/filter.d/admin-login.conf`. Any non-match fails CI.
  - **Committed reference file (dev convenience only):** `fail2ban/filter.d/admin-login.sample.log` remains in the repo as a dev aid — easy to run `fail2ban-regex` locally without spinning up the container — but is marked `# NON-AUTHORITATIVE: regenerated by CI from a live failed login. Do not trust without the CI check.` at the top of the file. If it drifts from the CI-generated version, CI fails first, not prod.

  ```
  # fail2ban/filter.d/admin-login.sample.log
  # NON-AUTHORITATIVE — dev convenience only. CI regenerates from a live failed
  # login on every run; that is the authoritative sample. If CI fails on this
  # check and the committed file is stale, regenerate locally:
  #   make admin-fail2ban-sample  # dispatches a failed login, captures the log line
  {"message":"Failed admin login","context":{"ip":"203.0.113.42","email":"attacker@example.com"},"level":200,"level_name":"INFO","channel":"admin_auth_events","datetime":"2026-04-21T12:34:56.000000+00:00","extra":[]}
  ```

  Verify the filter against the sample during development: `fail2ban-regex fail2ban/filter.d/admin-login.sample.log fail2ban/filter.d/admin-login.conf`. This is local-only; the authoritative check runs in CI.

  **Jail — logpath points at the dedicated file, not the catch-all Laravel log:**
  ```ini
  # fail2ban/jail.d/admin-login.conf
  [admin-login]
  enabled = true
  filter = admin-login
  logpath = /var/log/admin/admin-auth-events.log
  maxretry = 5
  findtime = 600
  bantime = 86400
  action = iptables-multiport[name=admin-login, port="80,443"]
  ```

  5 failed logins in 10 minutes → 24 hour IP ban. The admin container must mount `storage/logs/admin-auth-events.log` into the Fail2ban container at `/var/log/admin/admin-auth-events.log` (update compose volumes accordingly).

- **Acceptance Criteria:**
  - [ ] `admin_auth_events` logging channel defined with Monolog `JsonFormatter` and a stable output shape
  - [ ] Event listener writes failed admin logins to that channel (and *only* that channel)
  - [ ] **CI step generates the sample log from a live failed login and runs `fail2ban-regex` against it — Monolog version drift fails CI, not production**
  - [ ] Dev-convenience sample at `fail2ban/filter.d/admin-login.sample.log` carries a `NON-AUTHORITATIVE` header comment and passes `fail2ban-regex` locally
  - [ ] Fail2ban jail registered and pointed at the dedicated log file
  - [ ] Admin container mounts the dedicated log file into the Fail2ban container
  - [ ] Manual test: 6 failed logins from test IP → IP gets banned, cannot reach admin for 24h
  - [ ] IPv6 caveat documented: jail covers IPv4 only; if IPv6 ingress is introduced later, a parallel `ip6tables-multiport` jail and the matching update to `AdminIpAllowlist` (Task 3) must ship together

---

### Task 6: Queue worker + scheduler containers + dispatch_outbox worker

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/Dockerfile` (modify — add worker stage)
  - `docker-compose.yml` (modify — add admin-worker, admin-scheduler services)
  - `admin/app/Console/Commands/ProcessDispatchOutbox.php` (new — worker that drains Plan 06's `dispatch_outbox`)
  - `admin/routes/console.php` (modify — register outbox processing + outbox pruning on the schedule)
  - `packages/shared-domain/src/Outbox/OutboxDispatcher.php` (new — maps `event_type` to job class and dispatches)
- **Details:**
  Admin ships three runtime processes:
  1. `admin` (existing) — PHP-FPM serving HTTP
  2. `admin-worker` (new) — `php artisan queue:work` for async jobs (cancellation emails, gift card void mail, TMDB enrichment triggered from admin actions, etc.)
  3. `admin-scheduler` (new) — `php artisan schedule:work` for `activitylog:clean`, the dispatch-outbox processor (every minute), and the outbox pruning job

  **Shared-code wiring.** Per Plan 03's ADR, the shared `finalcut/domain` package is consumed via Composer path repository — no backend source is bind-mounted into the admin containers. The package ships inside the admin image via `composer install` (path repo resolves at build/install time) and is symlinked in dev via the composer path repo's symlink option. The dev compose below reflects this — there is no `./backend:/backend:ro` mount.

  **Queue ownership rule.** Any job *dispatched from an admin-originated action* is processed by `admin-worker`, even when the job body touches shared domain services owned by the backend app. The backend app retains its own worker pool for customer-originated async work (booking confirmations, loyalty point accrual, etc.). Both pools drain the same Redis instance today; ownership is determined by *where the job was dispatched from*, not what domain it touches.

  For v1, all admin-dispatched jobs go on the default queue. This is intentionally simple — when traffic warrants it, v2 should split named queues (e.g., `admin`, `customer`, `enrichment`) and point each worker pool at its own queue name via `queue:work --queue=admin`. Document the ownership boundary now so the split is straightforward later.

  **Dispatch-outbox processor (new — scheduled command).** Plan 06 introduced a generalized `dispatch_outbox` table for at-least-once delivery of post-commit events (starting with showtime cancellations; future features reuse the same table). The processor command `outbox:dispatch` runs every minute via the scheduler:

  1. Select up to 100 rows where `processed_at IS NULL AND available_at <= now() AND attempts < 5`.
  2. For each row, resolve `event_type` → job class via the `OutboxDispatcher` service. For `showtime.cancelled`, dispatch `NotifyCustomerOfShowtimeCancellation::dispatch($payload['booking_id'])`. Unknown event types log an error and mark the row `failed_at = now()`.
  3. On successful dispatch, update the row: `processed_at = now()`, `attempts = attempts + 1`.
  4. On dispatch failure (e.g., Redis unreachable), increment `attempts`, write `last_error`, leave `processed_at = null` so the row gets retried next minute. Rows with `attempts >= 5` get `failed_at` set and are excluded from future runs — the ops dashboard pages on-call for these.

  Registered in `admin/routes/console.php`:
  ```php
  Schedule::command('outbox:dispatch')
      ->everyMinute()
      ->withoutOverlapping(90)
      ->runInBackground();

  // Pruning: delete processed outbox rows older than 30 days. Also cleans up
  // failed_at rows older than 90 days (after incident response has landed on them).
  Schedule::command('outbox:prune')->daily();
  ```

  Add compose services (dev):
  ```yaml
  admin-worker:
    build:
      context: .
      dockerfile: admin/Dockerfile
      target: development
    volumes:
      - ./admin:/app
      - ./packages/shared-domain:/packages/shared-domain
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
      context: .
      dockerfile: admin/Dockerfile
      target: development
    volumes:
      - ./admin:/app
      - ./packages/shared-domain:/packages/shared-domain
      - admin-vendor:/app/vendor
    command: php artisan schedule:work
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - finalcut
  ```

  The `build.context` is the monorepo root so the shared-domain package is visible during `composer install`. Both services mount the package as a volume in dev so code changes take effect without rebuilding.

  For production, use Laravel Horizon or Supervisor — document in `admin/.env.production.example` but don't block v1 on it.

- **Acceptance Criteria:**
  - [ ] `admin-worker` service processes jobs from redis queue
  - [ ] `admin-scheduler` runs `activitylog:clean` daily, `outbox:dispatch` every minute, `outbox:prune` daily (verify via `make admin-shell` + `php artisan schedule:list`)
  - [ ] Both services restart on failure
  - [ ] Gift card void email (Plan 08) delivered via worker in dev
  - [ ] Showtime cancellation (Plan 06): an outbox row gets drained within 60s of the cancellation commit under normal load
  - [ ] Simulated Redis outage: outbox rows accumulate, worker retries, and once Redis returns, all pending rows process within two scheduler ticks (≤ 2 minutes)
  - [ ] Rows reaching `attempts >= 5` get `failed_at` set and generate an error-level log entry referencing the on-call alert path
  - [ ] Neither compose service mounts `./backend` — shared code flows through the `finalcut/domain` Composer package only
  - [ ] `rg 'backend:/backend' docker-compose*.yml` returns zero hits

---

### Task 7: Production docker-compose

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `docker-compose.prod.yml` (modify — add admin services)
  - `admin/Dockerfile` (modify — add `production` stage)
  - `admin/.env.production.example` (new)
- **Details:**
  Add production variants of admin, admin-worker, admin-scheduler to `docker-compose.prod.yml`.

  **Shared-code implication for the build.** Per Plan 03's ADR, admin consumes the shared `finalcut/domain` Composer package via a path repository. The production build context must include the package source — not a bind-mounted backend source. Build approach:

  - **Monorepo build context (required).** In `docker-compose.prod.yml`, set the admin service's `build.context` to the repo root and `build.dockerfile` to `admin/Dockerfile`. The Dockerfile copies `packages/shared-domain/` and `admin/` into the image before `composer install` so the path-repo dependency resolves at install time.

  There is no `COPY backend/src` — admin does **not** depend on backend source code at runtime. Backend runs as its own separate image with its own `composer install` against the same shared package.

  ```dockerfile
  # admin/Dockerfile (production stage) — expects build context = monorepo root

  FROM base AS production

  # Shared domain package — required for admin's composer install to resolve the
  # finalcut/domain path-repo dependency declared in admin/composer.json.
  COPY packages/shared-domain /packages/shared-domain

  # Admin app source and composer metadata.
  COPY admin/composer.json admin/composer.lock /app/
  WORKDIR /app
  RUN composer install --no-dev --optimize-autoloader --no-interaction

  COPY admin/ /app/
  RUN php artisan config:cache && php artisan route:cache && php artisan view:cache
  RUN php artisan filament:assets
  RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
  USER www-data
  CMD ["php-fpm"]
  ```

  Production compose:
  ```yaml
  admin:
    build:
      context: .                 # monorepo root so packages/shared-domain is visible
      dockerfile: admin/Dockerfile
      target: production
    environment:
      APP_ENV: production
      APP_DEBUG: false
      DB_HOST: ${DB_HOST}
      REDIS_HOST: ${REDIS_HOST}
      ADMIN_IP_ALLOWLIST: ${ADMIN_IP_ALLOWLIST}
      # Matches Plan 02 Task 2's explicit no-leading-dot rationale: isolation
      # from the customer app comes from the distinct subdomain + distinct
      # cookie name, not from cookie-scope manipulation. A leading dot would
      # widen the cookie scope to every sub-subdomain of admin.finalcut.com,
      # which we do not need and which expands attack surface.
      SESSION_DOMAIN: admin.finalcut.com
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
  - `SESSION_DOMAIN=admin.finalcut.com` (no leading dot — see Plan 02 Task 2 for rationale)

- **Acceptance Criteria:**
  - [ ] Production Dockerfile stage builds cached artifacts
  - [ ] Production Dockerfile copies `packages/shared-domain/` into the image before `composer install` so path-repo resolution succeeds
  - [ ] No `COPY backend/src` or `COPY backend/app` anywhere in the production Dockerfile
  - [ ] `rg 'backend/src|backend/app' admin/Dockerfile` returns zero hits
  - [ ] Production compose service references prod target and uses monorepo build context
  - [ ] `SESSION_DOMAIN` is `admin.finalcut.com` (no leading dot), matching Plan 02 Task 2's stated rationale
  - [ ] `rg 'SESSION_DOMAIN.*\.admin\.finalcut' .` returns zero hits (no leading-dot form anywhere)
  - [ ] Env example documents every required variable, including the `SESSION_DOMAIN` no-leading-dot convention
  - [ ] Debug disabled in prod
  - [ ] Session cookies secure + scoped to admin prod domain (no leading dot)

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
        - name: Setup backend env (shared schema source)
          working-directory: backend
          run: cp .env.example .env && php artisan key:generate
        - name: Setup admin env
          working-directory: admin
          run: cp .env.example .env && php artisan key:generate
        # The admin app uses tables owned by the backend app (users, locations, showtimes, etc.)
        # plus its own admin-specific tables (activity_log, admin-only pivots, etc.).
        # Both sets of migrations must run against the same test database, in order:
        # backend first so shared tables exist, then admin so admin-specific tables and
        # any admin-owned pivots layer on top.
        - name: Run backend migrations (shared schema)
          working-directory: backend
          env:
            DB_HOST: localhost
            DB_DATABASE: final_cut_test
          run: php artisan migrate --force
        - name: Run admin migrations (admin-owned schema)
          working-directory: admin
          env:
            DB_HOST: localhost
            DB_DATABASE: final_cut_test
          run: php artisan migrate --force
        - name: Run admin tests
          working-directory: admin
          env:
            DB_HOST: localhost
            DB_DATABASE: final_cut_test
          run: php artisan test
  ```

  Ensure the shared backend code is available in the admin job — GitHub Actions doesn't use Docker volumes, so the admin job relies on the default monorepo checkout placing `backend/` alongside `admin/`. This checkout is what lets admin's composer classmap resolve backend classes. **If the repo is ever split, this CI job and the Dockerfile in Task 7 break together.** Document the coupling so the split is a conscious decision, not an accident.

- **Acceptance Criteria:**
  - [ ] `admin` job added to CI workflow
  - [ ] Shared postgres + redis services configured
  - [ ] Backend checkout available for admin's classmap
  - [ ] **Backend migrations run before admin migrations against the same test database**
  - [ ] Admin tests run against a schema that includes all shared backend tables
  - [ ] `php artisan test` runs in admin working directory
  - [ ] Full matrix (backend + frontend + admin) green on main
  - [ ] Coupling between admin CI and monorepo layout is documented (in a comment or README) so a future repo split surfaces the dependency

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
  - **Rotate admin password:**
    - *Preferred (if production mail is verified working):* admin user clicks "Forgot password" on `/admin/login`; reset link is delivered via the configured `MAIL_*` driver. Before relying on this in prod, confirm the mail driver is not Mailpit and that a test reset email actually reaches a real inbox.
    - *Fallback (if reset mail is not yet production-grade at ship time):* run `make admin-create-user` with the `--reset-password` flag and the target email (e.g., `make admin-create-user -- --email=x@y --password=newpass --reset-password`). The command (Plan 02 Task 6) re-hashes the password on the existing account when the flag is set, and errors clearly when the account does not exist. This is the supported operational path. As a last resort if the command isn't available (e.g., during an incident), drop into the admin container (`docker exec -it admin php artisan tinker`) and run `AdminUser::where('email', 'x@y')->first()->update(['password' => Hash::make($new)])` — log the incident afterward since tinker leaves no audit trail of its own.
  - **Disable admin account:** set `disabled_at` on the target account — either via `UserResource` (if a disable action has shipped) or in tinker: `AdminUser::where('email', 'x@y')->update(['disabled_at' => now()])`. Re-enable with `['disabled_at' => null]`. `canAccessPanel` (Plan 02 Task 1) checks this field, so the account loses panel access on the next request without needing a cache bust.
  - **Emergency: disable admin panel:** set `ADMIN_IP_ALLOWLIST=127.0.0.1/32` and `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false` in prod env, then redeploy — only localhost can reach it. (Relying on an empty allowlist is not a shutdown mechanism; it fails closed, which accomplishes the same thing, but an explicit localhost-only CIDR is the documented intent.)

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

1. **IP allowlist deployment chicken-and-egg.** Because the allowlist fails closed when empty, you cannot "deploy with an empty allowlist and fill it in later." Two supported deploy sequences:
   1. *Preferred:* set the real `ADMIN_IP_ALLOWLIST` before the first production request (keep `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false`). The container starts with the allowlist already in place and nothing is ever exposed.
   2. *Emergency bootstrap:* set `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` with an empty allowlist, deploy, verify admin access from an expected IP, set the real allowlist, flip `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` back to `false`, restart. Every request during this window is logged at error level, which is the intended signal that something abnormal is happening.

   The runbook must include both sequences and a verification step that `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` is `false` after any emergency use. Treat a lingering `EMERGENCY_OPEN=true` as an incident, not a config drift.
2. **Queue worker health.** If `admin-worker` crashes and doesn't restart, cancellation emails silently queue. Add a Horizon dashboard or a simple health check in a v2 plan. v1 relies on `restart: unless-stopped` + human monitoring.
3. **IPv6 coupling across hardening layers.** v1 is IPv4-only across both `AdminIpAllowlist` (Task 3, which rejects IPv6 requests outright) and the Fail2ban jail (Task 5, which uses `iptables-multiport`). If the production environment ever exposes IPv6, **both** must be revisited in the same change: replace the CIDR helper with a dual-stack matcher, and add a parallel `ip6tables-multiport` jail. Shipping one without the other creates a silent enforcement gap.
4. **CI backend classmap.** The GitHub Actions admin job needs the `backend/` directory checked out for the Composer classmap to resolve. Since this is a monorepo, default checkout gets both — but if the CI is ever split into separate repos, the classmap approach breaks. Document the coupling.
5. **Production TLS certs.** Let's Encrypt automation for `admin.finalcut.com` uses the same Certbot setup as the customer domain. If the customer cert renewal fails silently, admin cert may too. Share the renewal monitoring across both domains.
