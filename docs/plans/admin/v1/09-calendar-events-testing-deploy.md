# Plan 09: Calendar Events, Testing & Hardening

> **Priority:** Should Have
> **Complexity:** L
> **Depends On:** Plans 04–08 (every domain plan contributes resources that need testing and hardening)
> **Unlocks:** Production admin deployment

## Overview

The finisher. `CalendarEventResource` rounds out domain coverage. Then the deployment hardening layer: nginx IP allowlist block, nginx rate limits on login, Fail2ban jail, a Laravel middleware that backs up the nginx allowlist (defense in depth), confirmation that the backend's queue worker + scheduler also service admin-dispatched work, CI workflow update to run admin tests, production nginx vhost rendering, and Let's Encrypt cert automation for the admin subdomain. Finally the docs layer: root `CLAUDE.md` admin section, `docs/README.md` entry, `docs/progress/admin-v1.md` finalization, and a post-launch runbook at `docs/runbooks/admin-operations.md`.

This plan is the gate between "admin works in dev" and "admin is safe to ship to prod." The security posture must be **fail-closed by default** — a missing env var should never quietly expose the admin panel.

No separate production Laravel app, no separate admin Docker service. Admin runs in the same `backend` PHP-FPM container as the customer API; isolation is the nginx vhost split and the Laravel route-domain scoping from Plan 01.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 7 Deployment
- `docs/plans/backend/v1/08-testing-and-seeding.md` — backend CI reference
- `docs/plans/admin/v1/01-admin-scaffold-and-docker.md` — nginx admin vhost + route-domain scoping baseline

---

## Tasks

### Task 1: CalendarEventResource

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/CalendarEventResource.php` (new)
  - `backend/app/Filament/Resources/CalendarEventResource/Pages/*` (new)
- **Details:**
  `$permissionPrefix = 'events'`. Standard CRUD, no custom service required. Calendar events are **admin-authored editorial content** — not customer-owned operational data — so direct Eloquent writes are permitted per spec § 2.6. This rationale is deliberately narrower than "no invariants today": scoping the exception to the *nature of the data* (editorial content with no customer-facing state transitions) rather than the *current absence of rules* makes it durable as the schema evolves.

  **Schema grounding:** All field names, types, and storage shapes — especially `accessibility_tags` (array/JSON column) and the `type` enum — must mirror the canonical `calendar_events` migration and `App\Models\CalendarEvent` Eloquent model. Before implementing the form schema, confirm the model's `$casts`, column types, and enum values, and adjust the Filament schema to match. Do not assume storage shape — ground it against the backend migration.

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
  - `backend/tests/Feature/Admin/Resources/CalendarEventResourceTest.php` (new)
  - All existing admin test files (modify if needed)
- **Details:**
  Every Filament Resource from Plans 04–08 should already have CRUD + policy tests under `backend/tests/Feature/Admin/`. Plan 09 adds Calendar and makes sure the full admin suite passes end-to-end.

  Checklist — must be green before shipping:
  - [ ] Plan 01 `RouteDomainScopingTest` — every API route bound to primary domain, every Filament route bound to admin domain, no route with null domain
  - [ ] Plan 02 auth suite (LoginTest, PermissionEnforcementTest, RoleSeederTest, AuditLoggingTest, SessionCookieScopingTest, CreateAdminUserCommandTest)
  - [ ] Plan 03 substrate suite (FormatsCurrencyTest, BaseResourceTest, LoyaltyAdjustmentTest)
  - [ ] Plan 04 movie suite (MovieResourceTest, MovieResourcePermissionTest, MovieServiceIntegrationTest)
  - [ ] Plan 05 location/auditorium/seat-generator suite (+ visual editor if shipped)
  - [ ] Plan 06 showtime suite + cancellation flow integration
  - [ ] Plan 07 booking/user/loyalty suite
  - [ ] Plan 08 menu/promo/gift-card suite + gift card void integration
  - [ ] Plan 09 calendar event suite

  Run `make admin-test` → zero failures for the `Feature/Admin/` subset. Run `make test-backend` → zero failures across the full backend suite (customer API tests plus admin tests). Run `make test` → backend + frontend green.

- **Acceptance Criteria:**
  - [ ] All admin Pest tests green under `make admin-test`
  - [ ] `make test-backend` green (customer + admin)
  - [ ] `make test` green (backend + frontend)
  - [ ] No skipped tests without documented rationale

---

### Task 3: Nginx IP allowlist + Laravel middleware (defense in depth)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `nginx/templates/conf.d/admin.conf.template` (modify — add allow/deny block)
  - `backend/app/Http/Middleware/AdminIpAllowlist.php` (new)
  - `backend/config/admin.php` (new config)
  - `backend/app/Providers/Filament/AdminPanelProvider.php` (modify — register middleware)
  - `backend/.env.production.example` (new or extend)
- **Details:**
  Two layers that must both be misconfigured before the admin panel becomes reachable from an unexpected IP:

  **Layer 1 — nginx allow/deny.** Runs before PHP-FPM, so unauthorized requests are rejected without Laravel booting. Inserted into `admin.conf.template` between the `server_name` directive and the `location` blocks. The CIDR list is rendered at container start via `envsubst`:

  ```nginx
  server {
      listen 443 ssl;
      http2 on;
      server_name admin.${APP_DOMAIN};

      # ... existing TLS + root config

      ${ADMIN_ALLOWLIST_BLOCK}

      location / {
          # ...
      }
  }
  ```

  `${ADMIN_ALLOWLIST_BLOCK}` is one of:
  - In dev (`APP_ENV=local`): rendered to the empty string — no allowlist, nginx allows everything, Laravel middleware (Layer 2) also skips.
  - In prod: rendered to `allow 203.0.113.0/24; allow 198.51.100.10; deny all;` (or whatever the `ADMIN_IP_ALLOWLIST` env var specifies). A small shell wrapper at container start (e.g., extending the nginx image's `docker-entrypoint.d/` hooks) transforms `ADMIN_IP_ALLOWLIST=203.0.113.0/24,198.51.100.10` into the multi-line nginx block and exports `ADMIN_ALLOWLIST_BLOCK`. If `ADMIN_IP_ALLOWLIST` is unset or empty in a non-local environment, the wrapper exports `deny all;` — fail closed.

  **Layer 2 — Laravel middleware.** Runs inside PHP on every admin request. If nginx is ever misconfigured to send a request through, the middleware re-checks the IP and denies if necessary. Also catches requests that bypassed nginx (direct container access during incident response, etc.).

  **Security posture:** **Fail-closed by default.** A missing or empty `ADMIN_IP_ALLOWLIST` in production denies all requests at both layers. An explicit, separately-scoped env flag (`ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true`) exists as a time-boxed deploy escape hatch; it is loud (error-level logging) and must be unset after bootstrap.

  **IPv4 scope:** v1 matches IPv4 only. The production admin domain is served behind an IPv4 load balancer; IPv6 ingress is explicitly out of scope. If IPv6 ingress is ever added, both the nginx block and this middleware must be replaced with dual-stack handlers, and the Fail2ban jail (Task 5) must gain an `ip6tables-multiport` variant at the same time.

  ```php
  namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Http\Request;

  class AdminIpAllowlist
  {
      public function handle(Request $request, Closure $next)
      {
          if (app()->environment('local', 'testing')) {
              return $next($request);
          }

          $clientIp = $request->ip();

          // v1 is IPv4-only. Reject IPv6 explicitly.
          if ($clientIp !== null && str_contains($clientIp, ':')) {
              logger()->warning('AdminIpAllowlist: IPv6 request rejected (v1 is IPv4-only)', [
                  'ip' => $clientIp,
              ]);
              abort(403, 'Access denied');
          }

          $allowlist = array_filter(array_map('trim', explode(',', (string) config('admin.ip_allowlist', ''))));

          if (empty($allowlist)) {
              if (config('admin.ip_allowlist_emergency_open')) {
                  logger()->error('AdminIpAllowlist: EMERGENCY_OPEN flag active — allowing all IPs', [
                      'ip' => $clientIp,
                  ]);
                  return $next($request);
              }
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
  // backend/config/admin.php
  return [
      'ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),
      'ip_allowlist_emergency_open' => env('ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN', false),
  ];
  ```

  Register middleware at the top of the admin panel's middleware stack (so it runs before anything else):
  ```php
  // AdminPanelProvider::panel()->middleware([...])
  ->middleware([
      AdminIpAllowlist::class,
      ScopeAdminSession::class,
      // ... existing Filament stack
  ]);
  ```

  Document env configuration in `backend/.env.production.example`:
  ```
  # Comma-separated list of IPv4 addresses or CIDR blocks allowed to reach the admin panel.
  # REQUIRED in production. Empty = deny all (unless emergency flag is also set).
  # Enforced at two layers: nginx (in admin.conf.template) and Laravel middleware.
  ADMIN_IP_ALLOWLIST=203.0.113.0/24,198.51.100.10

  # Emergency fail-open. Exists only to prevent lockout during initial deploy when
  # the allowlist has not yet been set. Allowed values:
  #   false (default) — fail closed if allowlist empty. Production resting state.
  #   true            — allow all requests when allowlist empty. Logs error-level warning.
  # NEVER leave this true outside a bootstrap window. Unset it the moment real CIDRs are live.
  ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false
  ```

- **Acceptance Criteria:**
  - [ ] Nginx admin vhost renders `allow <cidr>; deny all;` block in prod from `ADMIN_IP_ALLOWLIST`
  - [ ] Nginx admin vhost renders empty block (no allowlist, allow all) in dev
  - [ ] `AdminIpAllowlist` middleware blocks production requests from IPs outside the allowlist
  - [ ] Dev/test environments bypass the middleware
  - [ ] **Empty allowlist in production fails closed by default** — all requests return 403 with an error-level log line
  - [ ] `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` with empty allowlist allows requests but emits an error-level log entry on every request
  - [ ] IPv4 CIDR matching works (e.g., `203.0.113.0/24` matches `203.0.113.42`; `/32` and `/0` edge cases handled)
  - [ ] IPv6 requests explicitly rejected with a logged warning (v1 scope)
  - [ ] Malformed CIDRs do not crash the request and log a warning
  - [ ] Pest test covers: allowed IP, blocked IP, empty allowlist fail-closed, emergency-open bypass, IPv6 rejection

---

### Task 4: Nginx rate limit on admin login

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `nginx/templates/conf.d/admin.conf.template` (modify)
  - `nginx/nginx.conf` (confirm `limit_req_zone` declared — may need to be added)
- **Details:**
  Add the `limit_req_zone` declaration to the http block in `nginx/nginx.conf` (or an equivalent shared snippet loaded by the image):

  ```nginx
  http {
      limit_req_zone $binary_remote_addr zone=admin_login:10m rate=5r/m;
  }
  ```

  In `admin.conf.template`, add a location block matching the Filament login path:

  ```nginx
  location = /login {
      limit_req zone=admin_login burst=3 nodelay;
      limit_req_status 429;
      try_files $uri /index.php?$query_string;
  }
  ```

  The path is `/login` (not `/admin/login`) because Filament is mounted at the subdomain root (Plan 01 Task 2 sets `->path('/')`).

  Rate: 5 requests per minute per IP, burst 3. Exceeding returns 429 Too Many Requests.

- **Acceptance Criteria:**
  - [ ] `limit_req_zone` declared in the http block
  - [ ] `/login` POST on admin subdomain returns 429 after 5 rapid attempts
  - [ ] Successful logins not penalized
  - [ ] Burst of 3 concurrent attempts allowed
  - [ ] Manual test: curl against `https://admin.finalcut.test/login` 10 times in 30 seconds → last 5 return 429

---

### Task 5: Fail2ban jail for admin login failures

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `fail2ban/jail.d/admin-login.conf` (new)
  - `fail2ban/filter.d/admin-login.conf` (new)
  - `fail2ban/filter.d/admin-login.sample.log` (new — dev convenience)
  - `backend/config/logging.php` (modify — add dedicated channel)
  - `backend/app/Providers/AppServiceProvider.php` (modify — failed-login listener writes to dedicated channel)
- **Details:**
  The existing Fail2ban container (already in `docker-compose.yml`) gets a new jail matching failed admin login attempts.

  **Logging contract — the central guarantee of this task.** The Fail2ban filter and the Laravel log format are a tightly coupled pair. Rather than regex against Laravel's default log channel (whose format depends on stack, per-channel formatter, and Monolog version), admin ships a **dedicated logging channel** with a pinned Monolog JSON formatter. The filter matches that format exactly, and a sample log line is regenerated in CI so drift surfaces before prod.

  **Dedicated logging channel** (additive change to backend config):
  ```php
  // backend/config/logging.php — add to the 'channels' array
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

  **Failed-login listener — writes to the dedicated channel** (extend the existing auth-event listener in `AppServiceProvider::boot()` from Plan 02 Task 5):
  ```php
  Event::listen(Failed::class, function (Failed $e) {
      if ($e->guard !== 'admin') return;

      // Existing Plan 02 activity-log write stays as-is (activity-log has different consumers).
      activity('auth')->withProperties(['email' => $e->credentials['email'] ?? null])->log('login_failed');

      // NEW — dedicated channel for Fail2ban to parse.
      Log::channel('admin_auth_events')->info('Failed admin login', [
          'ip' => request()->ip(),
          'email' => $e->credentials['email'] ?? null,
      ]);
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

  **Sample log line — CI-generated, not hand-trusted.** A committed static sample file will silently drift the first time Monolog changes its JSON output. To close that gap, CI regenerates the sample on every run:

  - CI step (see Task 7): after backend migrations, fire a failed login via HTTP, tail `backend/storage/logs/admin-auth-events.log` to capture the freshly-written line, run `fail2ban-regex` against it. Non-match fails CI.
  - Committed reference file (dev convenience only): `fail2ban/filter.d/admin-login.sample.log` carries a `NON-AUTHORITATIVE` header comment; CI regeneration is the real contract.

  ```
  # fail2ban/filter.d/admin-login.sample.log
  # NON-AUTHORITATIVE — dev convenience only. CI regenerates from a live failed
  # login on every run; that is the authoritative sample.
  {"message":"Failed admin login","context":{"ip":"203.0.113.42","email":"attacker@example.com"},"level":200,"level_name":"INFO","channel":"admin_auth_events","datetime":"2026-04-21T12:34:56.000000+00:00","extra":[]}
  ```

  **Jail:**
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

  Mount the backend container's `storage/logs/admin-auth-events.log` into the Fail2ban container at `/var/log/admin/admin-auth-events.log` via `docker-compose.yml` volumes.

- **Acceptance Criteria:**
  - [ ] `admin_auth_events` logging channel defined with Monolog `JsonFormatter`
  - [ ] Failed admin login event writes to that channel (and only that channel, in addition to the existing activity-log write)
  - [ ] **CI step generates the sample log from a live failed login and runs `fail2ban-regex` against it — Monolog version drift fails CI, not production**
  - [ ] Dev-convenience sample at `fail2ban/filter.d/admin-login.sample.log` carries a `NON-AUTHORITATIVE` header and passes `fail2ban-regex` locally
  - [ ] Fail2ban jail registered and points at the dedicated log file
  - [ ] Backend container mounts the dedicated log file into the Fail2ban container
  - [ ] Manual test: 6 failed logins from test IP → IP gets banned, cannot reach admin for 24h
  - [ ] IPv6 caveat documented: jail covers IPv4 only; dual-stack requires both `AdminIpAllowlist` and a parallel `ip6tables-multiport` jail

---

### Task 6: Queue worker + scheduler verification (no new containers)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `docker-compose.yml` (verify existing queue worker runs; if missing, add one that wraps the existing backend container)
  - `backend/routes/console.php` (confirm `outbox:dispatch`, `outbox:prune`, `activitylog:clean` registered)
  - `backend/app/Console/Commands/ProcessDispatchOutbox.php` (new if not created in Plan 06)
  - `backend/app/Outbox/OutboxDispatcher.php` (new — maps `event_type` to job class)
- **Details:**
  Admin does not ship its own worker or scheduler container. Admin-dispatched jobs (cancellation emails, gift card void mail, TMDB enrichment) run on the same queue the backend already consumes — the `backend` container's worker drains them. The scheduler that runs `activitylog:clean`, `outbox:dispatch`, and `outbox:prune` is also the existing backend scheduler.

  If the existing `docker-compose.yml` does not already include a `queue:work` service and a `schedule:work` service alongside the `backend` PHP-FPM container, Plan 09 adds them. The services reuse the existing `backend` image — no separate build context, no new Dockerfile:

  ```yaml
  backend-worker:
    image: ${COMPOSE_PROJECT_NAME:-finalcut}-backend  # same image as backend service
    build:
      context: ./backend
      target: development
    command: php artisan queue:work --tries=3 --timeout=60
    volumes:
      - ./backend:/app
      - backend-vendor:/app/vendor
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - finalcut

  backend-scheduler:
    image: ${COMPOSE_PROJECT_NAME:-finalcut}-backend
    build:
      context: ./backend
      target: development
    command: php artisan schedule:work
    volumes:
      - ./backend:/app
      - backend-vendor:/app/vendor
    depends_on:
      - postgres
      - redis
    restart: unless-stopped
    networks:
      - finalcut
  ```

  **Queue ownership.** In v1, all jobs (customer-originated and admin-originated) run on the default queue and are drained by `backend-worker`. When traffic warrants it, v2 can split named queues (`admin`, `customer`, `enrichment`) and scale each worker pool independently. Document the boundary now so the split is straightforward later.

  **Dispatch-outbox processor.** Plan 06 introduced a generalized `dispatch_outbox` table for at-least-once delivery of post-commit events (starting with showtime cancellations; future features reuse the same table). The processor command `outbox:dispatch` runs every minute via the scheduler:

  1. Select up to 100 rows where `processed_at IS NULL AND available_at <= now() AND attempts < 5`.
  2. For each row, resolve `event_type` → job class via `App\Outbox\OutboxDispatcher`. For `showtime.cancelled`, dispatch `NotifyCustomerOfShowtimeCancellation::dispatch($payload['booking_id'])`. Unknown event types log an error and mark the row `failed_at = now()`.
  3. On successful dispatch, update the row: `processed_at = now()`, `attempts = attempts + 1`.
  4. On dispatch failure (e.g., Redis unreachable), increment `attempts`, write `last_error`, leave `processed_at = null`. Rows with `attempts >= 5` get `failed_at` set and log error-level for on-call.

  Registered in `backend/routes/console.php` (may already be present from Plan 02 Task 7 and Plan 06):
  ```php
  Schedule::command('activitylog:clean')->daily();

  Schedule::command('outbox:dispatch')
      ->everyMinute()
      ->withoutOverlapping(90)
      ->runInBackground();

  Schedule::command('outbox:prune')->daily();
  ```

- **Acceptance Criteria:**
  - [ ] `backend-worker` service processes jobs from Redis (existing or newly added)
  - [ ] `backend-scheduler` runs `activitylog:clean` daily, `outbox:dispatch` every minute, `outbox:prune` daily (verify via `docker compose exec backend php artisan schedule:list`)
  - [ ] Both services restart on failure
  - [ ] Gift card void email (Plan 08) delivered via worker in dev
  - [ ] Showtime cancellation (Plan 06): an outbox row gets drained within 60s of the cancellation commit under normal load
  - [ ] Simulated Redis outage: outbox rows accumulate, worker retries, and once Redis returns, all pending rows process within two scheduler ticks (≤ 2 minutes)
  - [ ] Rows reaching `attempts >= 5` get `failed_at` set and generate an error-level log entry
  - [ ] No new Dockerfile or docker-compose build contexts introduced — worker/scheduler reuse the backend image

---

### Task 7: Production nginx + compose

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `docker-compose.prod.yml` (modify — admin subdomain env vars, no new service)
  - `nginx/templates/conf.d/admin.conf.template` (confirm prod renders correctly)
  - `backend/.env.production.example` (new or extend)
  - `scripts/letsencrypt-admin.sh` or equivalent cert automation (new, or extend existing)
- **Details:**
  There is no new production service to add for admin. The existing `backend` PHP-FPM service serves both customer API and admin panel; the existing `nginx` service serves both vhosts. Plan 09's prod-side work is environment configuration and cert automation.

  **Compose additions (production):**
  ```yaml
  backend:
    environment:
      APP_ENV: production
      APP_DEBUG: false
      APP_PRIMARY_DOMAIN: finalcut.com
      ADMIN_DOMAIN: admin.finalcut.com
      ADMIN_SESSION_COOKIE: admin_session
      ADMIN_SESSION_DOMAIN: admin.finalcut.com     # no leading dot — see Plan 02 Task 2
      ADMIN_IP_ALLOWLIST: ${ADMIN_IP_ALLOWLIST}
      ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN: false
      FINANCE_NOTIFICATION_EMAIL: ${FINANCE_NOTIFICATION_EMAIL}
      LOYALTY_LARGE_ADJUSTMENT_THRESHOLD: ${LOYALTY_LARGE_ADJUSTMENT_THRESHOLD}
      SESSION_ENCRYPT: true
      # ... existing backend env vars
  ```

  Same `backend` container, more env vars. No `admin` service.

  **Nginx prod.** The admin vhost template (`nginx/templates/conf.d/admin.conf.template` from Plan 01, extended in Tasks 3 and 4) renders in prod exactly as in dev, except:
  - `APP_DOMAIN=finalcut.com` (so `server_name admin.${APP_DOMAIN}` resolves to `admin.finalcut.com`)
  - `ADMIN_ALLOWLIST_BLOCK` renders real CIDRs
  - TLS cert paths point at Let's Encrypt output

  **Let's Encrypt.** Certbot renews both `finalcut.com` and `admin.finalcut.com`. Two options:
  - **Single cert with SAN** (simpler): one certbot invocation with `-d finalcut.com -d admin.finalcut.com -d www.finalcut.com`. Both vhosts point at the same `fullchain.pem` / `privkey.pem`.
  - **Separate certs**: two certbot invocations, two cert directories, two sets of `ssl_certificate` paths in nginx.

  Go with single cert + SAN — fewer moving parts, one renewal cron to monitor. Document the alternate path for the case where the admin subdomain moves to a different infra.

  `backend/.env.production.example` documents every required variable:
  ```
  APP_ENV=production
  APP_DEBUG=false
  APP_KEY=  # generate via php artisan key:generate
  APP_URL=https://finalcut.com
  APP_PRIMARY_DOMAIN=finalcut.com
  ADMIN_DOMAIN=admin.finalcut.com

  DB_CONNECTION=pgsql
  DB_HOST=...
  DB_DATABASE=final_cut
  # ...

  # Admin hardening
  ADMIN_SESSION_COOKIE=admin_session
  ADMIN_SESSION_DOMAIN=admin.finalcut.com
  ADMIN_IP_ALLOWLIST=203.0.113.0/24,198.51.100.10
  ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false

  # Ops
  FINANCE_NOTIFICATION_EMAIL=finance@finalcut.com
  LOYALTY_LARGE_ADJUSTMENT_THRESHOLD=500

  # Session
  SESSION_ENCRYPT=true
  SESSION_SECURE_COOKIE=true

  # TMDB + Stripe production keys
  TMDB_API_KEY=
  STRIPE_SECRET_KEY=
  STRIPE_PUBLISHABLE_KEY=
  ```

- **Acceptance Criteria:**
  - [ ] `docker-compose.prod.yml` adds admin-related env vars to the existing `backend` service — no new admin service
  - [ ] Admin nginx vhost template renders correctly in prod with real allowlist CIDRs
  - [ ] Let's Encrypt cert covers both `finalcut.com` and `admin.finalcut.com` (single cert with SAN, or two separate certs — document the choice)
  - [ ] `SESSION_DOMAIN` for admin is `admin.finalcut.com` (no leading dot)
  - [ ] `rg 'SESSION_DOMAIN.*\.admin\.finalcut' .` returns zero hits
  - [ ] Env example documents every required variable
  - [ ] `APP_DEBUG=false` in prod
  - [ ] Session cookies secure + scoped to admin prod domain

---

### Task 8: CI workflow — run admin tests in existing backend job

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `.github/workflows/ci.yml` (modify — extend existing backend job, if needed)
- **Details:**
  Admin tests live under `backend/tests/Feature/Admin/` and `backend/tests/Unit/Admin/`. The existing backend CI job runs `php artisan test` or an equivalent Pest invocation against `final_cut_test`. Admin tests are already included — they're part of the same test suite.

  Verify:
  - The existing backend migrations job runs `php artisan migrate --force` against `final_cut_test`. Admin migrations (admin_users, permission tables, activity_log, loyalty_adjustments) are in `backend/database/migrations/` and run with the rest.
  - The existing backend test step runs the full suite. Admin tests run as part of it.
  - If the CI wants admin-specific isolation (e.g., a named check in PR UI), add a second step that runs only `php artisan test --testsuite=Feature --filter=Admin` — but this is cosmetic; the real assurance is the full-suite run.

  Add the CI-regenerates-Fail2ban-sample step (from Task 5) to the same job:

  ```yaml
  - name: Regenerate and verify Fail2ban admin-login sample
    working-directory: backend
    run: |
      # boot backend, trigger a failed login, capture the log line
      php artisan serve --host=0.0.0.0 --port=8080 &
      SERVER_PID=$!
      sleep 2
      curl -X POST http://localhost:8080/login \
        -H "Host: admin.finalcut.test" \
        -d "email=ci@example.com&password=wrong" || true
      kill $SERVER_PID
      # Extract the last line of the dedicated log
      tail -1 storage/logs/admin-auth-events.log > /tmp/admin-login.sample.log
      # Verify Fail2ban regex matches the freshly-generated sample
      fail2ban-regex /tmp/admin-login.sample.log ../fail2ban/filter.d/admin-login.conf
  ```

  If the existing CI doesn't have `fail2ban-regex` available, install it as part of the job setup.

- **Acceptance Criteria:**
  - [ ] Existing backend CI job runs admin tests (no separate admin job)
  - [ ] `make test-backend` green on main (admin tests included)
  - [ ] Fail2ban sample regenerated and verified in CI (Task 5 contract)
  - [ ] Admin tests appear in the job's test output

---

### Task 9: Documentation updates

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `CLAUDE.md` (modify — add "Admin panel" section)
  - `docs/README.md` (modify — admin entry)
  - `docs/architecture/SITE_ARCHITECTURE.md` (modify — admin subdomain in routing map)
  - `docs/progress/admin-v1.md` (finalize — mark all steps complete)
- **Details:**

  **Root `CLAUDE.md`** — add an "Admin panel" section that covers:
  - Admin lives in `backend/` and is served at `admin.finalcut.test` in dev, `admin.finalcut.com` in prod — same Laravel app as the customer API, separated at the network edge via nginx vhost + Laravel route-domain scoping + session cookie scoping
  - Make commands: `make admin-shell`, `make admin-create-user`, `make admin-test`, `make admin-migrate`, `make admin-filament-assets`
  - Write boundary rule: admin mutations to shared tables go through `backend/app/Services/*` classes, passing `auth('admin')->user()` as the actor for audit attribution
  - Where the spec lives: `docs/superpowers/specs/2026-04-20-admin-section-design.md`
  - Where the plan set lives: `docs/plans/admin/v1/`
  - Where the progress journal lives: `docs/progress/admin-v1.md`

  **`docs/README.md`** — ensure the Plans section references admin:
  - Link to `docs/plans/admin/v1/00-index.md`
  - Link to `docs/superpowers/specs/2026-04-20-admin-section-design.md`
  - Link to `docs/progress/admin-v1.md`

  **`docs/architecture/SITE_ARCHITECTURE.md`** — add admin to the routing / rendering strategy table and the project structure overview:
  - Admin panel at `admin.${APP_DOMAIN}`, route prefix `/` (whole subdomain)
  - Customer API at `${APP_DOMAIN}/api/*`
  - Frontend Nuxt app at `${APP_DOMAIN}`
  - Note that all three are served by the same Laravel + Nuxt + nginx stack, differentiated by Host header

  **Progress journal** — mark each step complete with final notes, decisions made during execution, files changed. Keep this as the artifact of the admin v1 rollout.

- **Acceptance Criteria:**
  - [ ] CLAUDE.md "Admin panel" section committed
  - [ ] docs/README.md links admin planning + progress docs
  - [ ] docs/architecture/SITE_ARCHITECTURE.md mentions the admin subdomain
  - [ ] Progress journal complete with per-step status

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
  - **View activity log:** navigate to `https://admin.finalcut.com/activity` as an admin user
  - **Process gift card void queue:** email forwarded to finance; no automated tool in v1
  - **Process cancelled showtime follow-up:** visit `https://admin.finalcut.com/cancelled-showtime-followup` queue page
  - **Adjust loyalty points for a single customer:** UserResource → user view → Adjust Points action
  - **Trigger TMDB enrichment for a single movie:** MovieResource → row action "Enrich from TMDB"
  - **Rotate admin password:**
    - *Preferred (if production mail is verified working):* admin user clicks "Forgot password" on `https://admin.finalcut.com/login`; reset link is delivered via the configured `MAIL_*` driver. Before relying on this in prod, confirm the mail driver is not Mailpit and that a test reset email reaches a real inbox.
    - *Fallback (if reset mail is not yet production-grade at ship time):* run `make admin-create-user` with the `--reset-password` flag and the target email (e.g., `make admin-create-user -- --email=x@y --password=newpass --reset-password`). The command (Plan 02 Task 6) re-hashes the password on the existing account when the flag is set, and errors clearly when the account does not exist. As a last resort during an incident, drop into the backend container (`docker exec -it backend php artisan tinker`) and run `AdminUser::where('email', 'x@y')->first()->update(['password' => Hash::make($new)])` — log the incident afterward since tinker leaves no audit trail of its own.
  - **Disable admin account:** set `disabled_at` on the target account — via Filament if an admin-users Resource has shipped, otherwise in tinker: `AdminUser::where('email', 'x@y')->update(['disabled_at' => now()])`. Re-enable with `['disabled_at' => null]`. `canAccessPanel` (Plan 02 Task 1) checks this field, so the account loses panel access on the next request without a cache bust.
  - **Emergency: disable admin panel entirely:** set `ADMIN_IP_ALLOWLIST=127.0.0.1/32` and `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false` in prod env, then redeploy — only localhost can reach it. (Relying on an empty allowlist is not a shutdown mechanism; it fails closed, which accomplishes the same thing, but an explicit localhost-only CIDR is the documented intent.)

- **Acceptance Criteria:**
  - [ ] Runbook covers the 9 most common operations
  - [ ] Each procedure references the exact command / URL
  - [ ] Emergency shutdown documented

---

## Testing Requirements

- **Pest Feature Tests:** calendar event resource + full admin suite pass
- **Static integration:** production deployment smoke test against staging environment (out of scope for this plan — external infra)
- **CI:** admin tests run in the existing backend job; Fail2ban sample regenerates and verifies on every CI run

## Dependencies Map

```
Task 1 (calendar events resource) ← parallel
Task 2 (full test pass) ← needs Task 1 + all prior plans
Task 3 (IP allowlist — nginx + Laravel) ← parallel
Task 4 (nginx rate limit) ← needs Plan 01 admin vhost baseline
Task 5 (Fail2ban) ← needs Task 4
Task 6 (worker + scheduler verification) ← can start early; mostly verification
Task 7 (prod compose + nginx + cert) ← needs Tasks 3, 6
Task 8 (CI — admin tests in existing job) ← parallel
Task 9 (docs) ← parallel
Task 10 (runbook) ← needs Tasks 3, 5, 6
```

## Risks & Open Questions

1. **IP allowlist deployment chicken-and-egg.** Because the allowlist fails closed when empty, you cannot "deploy with an empty allowlist and fill it in later." Two supported deploy sequences:
   1. *Preferred:* set the real `ADMIN_IP_ALLOWLIST` before the first production request (keep `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=false`).
   2. *Emergency bootstrap:* set `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` with an empty allowlist, deploy, verify admin access from an expected IP, set the real allowlist, flip `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` back to `false`, restart. Every request during this window is logged at error level.

   The runbook must include both sequences and a verification step that `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` is `false` after any emergency use.
2. **Shared PHP-FPM process pool.** Customer API and admin panel run in the same PHP-FPM workers. A slow admin operation consumes a worker the customer API could have used. Mitigations in v1: heavy work is queued (bulk showtime create, TMDB enrichment, gift card void mail), and route-domain scoping means admin-only routes can't be triggered by customer traffic. If worker-pool exhaustion becomes a real problem, v2 can split into two PHP-FPM pools (`backend-fpm-customer`, `backend-fpm-admin`) serving the same codebase on the same image, and point each nginx vhost at its own upstream. Documented for v2 in the backend/admin progress journal.
3. **IPv6 coupling.** v1 is IPv4-only across `AdminIpAllowlist` (Task 3, rejects IPv6 outright), the nginx allow/deny block (Task 3), and the Fail2ban jail (Task 5). If IPv6 ingress is ever added, all three must be revisited in the same change — replace the CIDR helper with a dual-stack matcher, update nginx to handle IPv6 `allow`/`deny`, and add a parallel `ip6tables-multiport` jail.
4. **Production TLS cert renewal.** Single-cert-with-SAN covers `finalcut.com` + `admin.finalcut.com`. If certbot renewal fails silently, both domains go unsafe together. Renewal monitoring must cover both — document in the runbook.
5. **Queue worker health.** If `backend-worker` crashes and doesn't restart, cancellation emails silently queue. Add a Horizon dashboard or a simple health check in a v2 plan. v1 relies on `restart: unless-stopped` + human monitoring.
