# Plan 02: Auth, Roles, Permissions & Audit Log

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (admin app exists)
> **Unlocks:** Plan 03 (model layer needs auth to scope tests)

## Overview

Create the `admin_users` table and model, configure the `admin` auth guard, wire Filament to use it, install Spatie Permission and Activity Log packages, seed the three roles (admin / manager / ops) with their permission sets, and ship the global activity log page plus the `admin:create-user` artisan command. End state: an admin can log in at `https://admin.finalcut.test/admin/login`; auth events and admin-user model changes write rows to `activity_log`, and the causer resolver is configured so resource-level audit logging (introduced in Plans 04+) attaches the logged-in admin automatically. Full per-resource audit coverage is *not* in scope for this plan — it arrives as each resource is registered in later plans.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 3 Authentication & Authorization
- [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission) — roles and permissions package
- [`spatie/laravel-activitylog`](https://spatie.be/docs/laravel-activitylog) — audit log package
- [Filament Panel Authentication docs](https://filamentphp.com/docs/3.x/panels/authentication)

---

## Tasks

### Task 1: admin_users migration, model, factory

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/database/migrations/YYYY_MM_DD_HHMMSS_create_admin_users_table.php`
  - `admin/app/Models/AdminUser.php`
  - `admin/database/factories/AdminUserFactory.php`
- **Details:**
  Migration schema:
  ```php
  Schema::create('admin_users', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('email')->unique();
      $table->string('password');
      $table->rememberToken();
      $table->timestamp('last_login_at')->nullable();
      $table->string('last_login_ip', 45)->nullable();
      $table->timestamp('disabled_at')->nullable();  // kill switch — see canAccessPanel
      $table->timestamps();
  });
  ```

  **No `email_verified_at` column.** Admin users are created explicitly by another admin via `admin:create-user` (Task 6) — there is no self-signup flow that would need verification. The previous draft set `email_verified_at => now()` at creation and checked `email_verified_at !== null` in `canAccessPanel`; since the field was written once and never cleared, the check was security theater. Replacing it with a nullable `disabled_at` timestamp gives ops a real kill switch to deactivate a terminated employee's account without deleting the row, and the "when was this disabled" metadata comes for free (project convention: booleans as timestamps — see CLAUDE.md).

  `AdminUser` model extends `Illuminate\Foundation\Auth\User` and implements `Filament\Models\Contracts\FilamentUser`. In Task 1 the model is intentionally minimal — the `HasRoles` trait is added in Task 3 (after Spatie Permission is installed) and `LogsActivity` is added in Task 4 (after Spatie ActivityLog is installed). Keep this task focused on the schema and basic authentication surface.

  ```php
  class AdminUser extends Authenticatable implements FilamentUser
  {
      use Notifiable, HasFactory;

      protected $fillable = ['name', 'email', 'password'];
      protected $hidden = ['password', 'remember_token'];
      protected $casts = [
          'disabled_at' => 'datetime',
          'last_login_at' => 'datetime',
          'password' => 'hashed',
      ];

      public function canAccessPanel(Panel $panel): bool
      {
          return $panel->getId() === 'admin' && is_null($this->disabled_at);
      }
  }
  ```

  `AdminUserFactory` follows the Laravel convention — bcrypt password `'password'`, unique email via `fake()->unique()->safeEmail()`. `disabled_at` defaults to `null`. A `disabled()` factory state sets it to `now()` for the "disabled admin cannot access panel" test in Task 9.

- **Acceptance Criteria:**
  - [ ] Migration creates `admin_users` table with documented columns including `disabled_at`
  - [ ] No `email_verified_at` column exists on `admin_users`
  - [ ] `AdminUser` model references the `admin_users` table
  - [ ] Password cast to `hashed`; `disabled_at` and `last_login_at` cast to `datetime`
  - [ ] `canAccessPanel` returns true only when `disabled_at` is null and the panel ID is `admin`
  - [ ] Factory generates valid AdminUser instances with `disabled_at = null` by default
  - [ ] Factory has a `disabled()` state that sets `disabled_at = now()`

---

### Task 2: Configure `admin` auth guard

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/config/auth.php` (modify)
  - `admin/config/session.php` (modify)
- **Details:**
  Add the `admin` guard and provider:
  ```php
  // config/auth.php
  'defaults' => ['guard' => 'admin', 'passwords' => 'admin_users'],

  'guards' => [
      'admin' => ['driver' => 'session', 'provider' => 'admin_users'],
  ],

  'providers' => [
      'admin_users' => [
          'driver' => 'eloquent',
          'model' => App\Models\AdminUser::class,
      ],
  ],

  'passwords' => [
      'admin_users' => [
          'provider' => 'admin_users',
          'table' => 'password_reset_tokens',
          'expire' => 60,
          'throttle' => 60,
      ],
  ],
  ```

  Session config — cookie name and domain isolated from customer app:
  ```php
  // config/session.php
  'driver' => env('SESSION_DRIVER', 'redis'),
  'connection' => env('SESSION_CONNECTION', 'default'),
  'cookie' => env('SESSION_COOKIE', 'admin_session'),
  'domain' => env('SESSION_DOMAIN', 'admin.finalcut.test'),
  'secure' => true,
  'http_only' => true,
  'same_site' => 'lax',
  ```

  **Cookie domain note.** The value is `admin.finalcut.test` (no leading dot) deliberately. A leading dot (`.admin.finalcut.test`) shares the cookie across every sub-subdomain of `admin.finalcut.test`, which we do not need and which expands the cookie's surface area. Isolation from the customer app (`finalcut.test`) is achieved by using a distinct subdomain plus a distinct cookie name (`admin_session`), not by manipulating the leading dot. If the admin app ever needs to serve multiple sub-subdomains (e.g. `us.admin.finalcut.test`) we can revisit then.

  Redis prefixes — use deliberate, purpose-scoped names instead of a single blanket `admin_`. Sessions and cache belong to different subsystems and should be independently flushable.

  ```php
  // config/database.php — Redis default connection stays default-prefixed
  // Per-subsystem prefixes are set in their own configs:

  // config/session.php
  'connection' => env('SESSION_CONNECTION', 'default'),
  // Laravel's Redis session handler stores keys as {prefix}:{session_id}; use a dedicated
  // session connection if deeper isolation is required later.

  // config/cache.php
  'stores' => [
      'redis' => [
          'driver' => 'redis',
          'connection' => 'cache',
          'prefix' => env('CACHE_PREFIX', 'admin_cache'),
      ],
  ],
  ```

  The convention going forward:
  - `admin_session:*` — Laravel session keys (set via the session handler)
  - `admin_cache:*` — application cache entries
  - `admin_queue:*` — queue payloads (added in Plan 09 when the queue worker lands)

  Add `SESSION_COOKIE=admin_session`, `SESSION_DOMAIN=admin.finalcut.test`, `CACHE_PREFIX=admin_cache` to `admin/.env.example`.

- **Acceptance Criteria:**
  - [ ] Default guard is `admin`
  - [ ] `admin` guard uses session driver + `admin_users` provider
  - [ ] Session cookie name differs from backend (`admin_session` vs customer's default)
  - [ ] Session cookie domain scoped to the admin subdomain without a leading dot
  - [ ] Redis session keys land under `admin_session:*`; cache keys under `admin_cache:*` — verified by inspecting `redis-cli keys '*'` after a login + a cached read

---

### Task 3: Install Spatie Permission, seed roles

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/composer.json` (modify)
  - `admin/config/permission.php` (published)
  - `admin/database/migrations/*_create_permission_tables.php` (vendor-published)
  - `admin/database/seeders/RolesAndPermissionsSeeder.php` (new)
  - `admin/database/seeders/DatabaseSeeder.php` (modify)
- **Details:**
  Install:
  ```bash
  composer require spatie/laravel-permission
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  ```

  Configure `config/permission.php` to use the `admin` guard:
  ```php
  'guard_name' => 'admin',
  'models' => ['permission' => Spatie\Permission\Models\Permission::class, 'role' => Spatie\Permission\Models\Role::class],
  'table_names' => [...default...],
  'column_names' => [...default...],
  'teams' => false,
  ```

  `RolesAndPermissionsSeeder` — idempotent, uses `firstOrCreate`. Seeds:

  **Permissions (grouped by resource):**
  ```
  movies.view, movies.create, movies.update, movies.delete, movies.trigger_enrich
  showtimes.view, showtimes.create, showtimes.update, showtimes.delete, showtimes.cancel
  locations.view, locations.create, locations.update, locations.delete
  auditoriums.view, auditoriums.create, auditoriums.update, auditoriums.delete
  seats.view, seats.update
  bookings.view
  users.view
  loyalty.view
  menu.view, menu.create, menu.update, menu.delete
  promos.view, promos.create, promos.update, promos.delete
  gift_cards.view, gift_cards.void
  events.view, events.create, events.update, events.delete
  admin_users.view, admin_users.create, admin_users.update, admin_users.delete
  activity.view
  ```

  **Customer user write surface — deferred.** The admin section treats customer users (the `users` table on the customer side) as read-only in v1. No `users.update` permission is seeded here. Writes to customer user state — specifically loyalty tier adjustments and manual point corrections — will be introduced by the plan that actually implements those screens, under narrower permission names (e.g. `loyalty.adjust_tier`, `loyalty.adjust_points`) so the capability matrix stays tight. Do not add a broad `users.update` retroactively.

  **Roles and their permission sets:**
  ```php
  $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
  $admin->syncPermissions(Permission::all());

  $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'admin']);
  $manager->syncPermissions([
      'movies.view', 'movies.create', 'movies.update', 'movies.delete', 'movies.trigger_enrich',
      'showtimes.view', 'showtimes.create', 'showtimes.update', 'showtimes.delete', 'showtimes.cancel',
      'locations.view', 'locations.update',
      'auditoriums.view', 'auditoriums.create', 'auditoriums.update', 'auditoriums.delete',
      'seats.view', 'seats.update',
      'menu.view', 'menu.create', 'menu.update', 'menu.delete',
      'events.view', 'events.create', 'events.update', 'events.delete',
      'promos.view', 'promos.create', 'promos.update', 'promos.delete',
      'bookings.view',
      'gift_cards.view',
      'users.view',
      'loyalty.view',
      'activity.view',
  ]);

  $ops = Role::firstOrCreate(['name' => 'ops', 'guard_name' => 'admin']);
  $ops->syncPermissions([
      'bookings.view', 'users.view', 'loyalty.view', 'gift_cards.view',
      'movies.view', 'showtimes.view', 'locations.view', 'auditoriums.view',
      'activity.view',
  ]);
  ```

  Register `RolesAndPermissionsSeeder` in `DatabaseSeeder`:
  ```php
  public function run(): void
  {
      $this->call(RolesAndPermissionsSeeder::class);
  }
  ```

- **Acceptance Criteria:**
  - [ ] `spatie/laravel-permission` installed and config published
  - [ ] Permission tables migrated
  - [ ] `guard_name` in permission config is `admin`
  - [ ] Seeder creates all documented permissions
  - [ ] Three roles created with correct permission sets
  - [ ] Seeder is idempotent (running twice does not duplicate)

---

### Task 4: Install Spatie ActivityLog

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/composer.json` (modify)
  - `admin/config/activitylog.php` (published)
  - `admin/database/migrations/*_create_activity_log_table.php` (vendor-published)
  - `admin/app/Models/AdminUser.php` (modify — add `LogsActivity` trait)
- **Details:**
  Install:
  ```bash
  composer require spatie/laravel-activitylog
  php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
  php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
  ```

  Configure `config/activitylog.php`:
  ```php
  return [
      'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),
      'delete_records_older_than_days' => 180,
      'default_log_name' => 'admin',
      'default_auth_driver' => 'admin',
      'subject_returns_soft_deleted_models' => false,
      'activity_model' => \Spatie\Activitylog\Models\Activity::class,
      'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),
      'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
  ];
  ```

  Add the `LogsActivity` trait to the `AdminUser` model. **Scope:** this only logs model-level events on `AdminUser` itself — created / updated / deleted rows on the `admin_users` table. It does *not* create a global audit trail across the rest of the admin section. Per-resource audit logging is wired up resource-by-resource as each one is registered in Plans 04+, typically via the base resource class introduced in Plan 03. Explicit write flows that don't go through a model (artisan commands, service-level actions) have to log their own activity rows deliberately — the trait cannot catch those automatically.

  What Plan 02 actually produces in `activity_log`:
  - a row per login / logout / failed-login event (wired via Laravel auth events in Task 5)
  - a row per `AdminUser` model change (via `LogsActivity` on the model)
  - nothing else yet — the global activity page in Task 8 will mostly show auth events until the resource plans land

  Setting `default_auth_driver` to `admin` is important for admin-side convenience: when a service is called inside an admin HTTP request, the activity-log package can resolve the currently-logged-in admin without explicit wiring. **It is not the contract, however.** Shared-domain services (in the `finalcut/domain` package established by Plan 03) run in multiple contexts:

  - inside admin HTTP requests (causer = the acting `AdminUser`)
  - inside backend scheduler tasks (causer = a system user or nothing, definitely not a logged-in admin)
  - inside backend webhooks and customer-facing request handlers (causer = the customer `User`, or system)

  Auto-resolution via `default_auth_driver` reads whatever guard is configured in the calling app's `activitylog.php`, which is the wrong answer in every context except the first. To make the contract unambiguous:

  **Explicit `Causer` argument on every shared-domain service write method.** Introduce a `FinalCut\Domain\Audit\Causer` interface in the shared package (lands in Plan 03 Task 1's scaffold as a stub; actual implementations in Plans 04/06/07/08 as each service lands). `AdminUser` and the customer `User` both implement `Causer` via a trait that returns `$this` for `causedBy()`. Every shared-domain service method that writes an audit row signature-declares `Causer $causer` and calls `activity(...)->causedBy($causer)->log(...)` — no ambient guard reads.

  Admin-owned models that write via `LogsActivity` (`AdminUser`, `LoyaltyAdjustment`) continue to use auto-resolution — those writes only happen from admin HTTP requests, so the default-guard path is correct. `default_auth_driver = 'admin'` serves exactly that narrow convenience.

  This task's deliverable is the written contract and the interface stub. Plans 04/06/07/08 each implement the services against it; Plan 02 does not itself create any shared-domain services.

- **Acceptance Criteria:**
  - [ ] `spatie/laravel-activitylog` installed and config published
  - [ ] `activity_log` table migrated
  - [ ] Retention set to 180 days
  - [ ] `default_auth_driver` set to `admin` — documented as admin-side convenience only, not the shared-domain contract
  - [ ] `LogsActivity` trait added to `AdminUser`; creating/updating an `AdminUser` in tinker writes a row to `activity_log`
  - [ ] `FinalCut\Domain\Audit\Causer` interface stub committed in `packages/shared-domain/src/Audit/Causer.php` (per Plan 03 Task 1 scaffold) with a documented contract: implementers expose the subject/row used by `activity()->causedBy(...)`
  - [ ] Written note in this task's activity-log strategy section: every shared-domain service write method must accept `Causer $causer` explicitly. Auto-resolution from `default_auth_driver` is not the contract.
  - [ ] Plans 04/06/07/08 referenced in this task's prose as the consumers of the explicit-Causer contract
  - [ ] Plan 02 does not claim to audit resources it has not yet introduced

---

### Task 5: Configure Filament AdminPanelProvider

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Providers/Filament/AdminPanelProvider.php` (modify)
- **Details:**
  Update the panel provider so that the panel:

  - runs on the `admin` guard (`->authGuard('admin')`)
  - uses the `admin_users` password broker (`->authPasswordBroker('admin_users')`)
  - has login enabled (`->login()`) at `/admin/login`
  - auto-discovers resources, pages, and widgets under `App\Filament\*`
  - keeps the default Filament 3 middleware stack (session, CSRF, cookie encryption, route bindings, Filament event dispatch)
  - keeps the default Filament 3 auth middleware (`Authenticate`)

  Prefer starting from whatever `php artisan filament:install --panels` generated in Plan 01 and editing only the guard / broker / discovery lines. **Do not** copy a literal middleware array into this document — Filament's stack has changed between point releases and freezing a list here will drift from the installed version. If a future plan needs to customize middleware (e.g. to add the location-switcher middleware from Plan 07), that plan names the specific entry it inserts.

  Wire Laravel auth events to the activity log so login / logout / failed-login write rows (in an `EventServiceProvider` listener or an inline closure in `AppServiceProvider::boot()`):

  ```php
  Event::listen(Login::class,  fn ($e) => activity('auth')->causedBy($e->user)->log('login'));
  Event::listen(Logout::class, fn ($e) => activity('auth')->causedBy($e->user)->log('logout'));
  Event::listen(Failed::class, fn ($e) => activity('auth')->withProperties(['email' => $e->credentials['email'] ?? null])->log('login_failed'));
  ```

  Ensure `AdminPanelProvider` is registered in `bootstrap/providers.php`.

- **Acceptance Criteria:**
  - [ ] Panel uses the `admin` guard and the `admin_users` password broker
  - [ ] Login page enabled at `/admin/login`
  - [ ] Filament's default middleware stack is preserved (not hand-rolled in this plan)
  - [ ] Auto-discovery scans `App\Filament\Resources` and `App\Filament\Pages`
  - [ ] Visiting `/admin` without auth redirects to `/admin/login`
  - [ ] Login, logout, and failed-login events write rows to `activity_log` with `log_name = 'auth'`

---

### Task 6: `admin:create-user` artisan command

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Console/Commands/CreateAdminUser.php` (new)
- **Details:**
  Interactive command that prompts for name, email, password, and role. The `--reset-password` flag promotes the command into a password-reset tool for existing accounts, because Plan 09's operational runbook (Task 10) lists this as the fallback path when the mail-based reset flow isn't available. Without the flag, duplicate emails still error (guards against accidental overwrites).

  ```php
  class CreateAdminUser extends Command
  {
      protected $signature = 'admin:create-user
          {--name= : Full name (ignored with --reset-password)}
          {--email= : Email address}
          {--password= : Password}
          {--role=admin : Role (admin, manager, ops) — ignored with --reset-password unless --reassign-role is also passed}
          {--reset-password : Reset the password of an existing admin user matched by --email. Creation is skipped; role is not changed unless --reassign-role is also set.}
          {--reassign-role : With --reset-password, also re-assign the role to the value of --role. Ignored without --reset-password.}';

      public function handle(): int
      {
          $email = $this->option('email') ?: $this->ask('Email');
          $password = $this->option('password') ?: $this->secret('Password');

          if ($this->option('reset-password')) {
              $user = AdminUser::where('email', $email)->first();
              if (! $user) {
                  $this->error("No admin user found with email {$email}. Drop the --reset-password flag to create a new account.");
                  return self::FAILURE;
              }

              $user->update(['password' => $password]); // hashed cast handles it

              if ($this->option('reassign-role')) {
                  $role = $this->option('role');
                  if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
                      $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");
                      return self::FAILURE;
                  }
                  $user->syncRoles([$role]);
                  $this->info("Reset password and reassigned role to {$role} for {$email}.");
              } else {
                  $this->info("Reset password for {$email}.");
              }
              return self::SUCCESS;
          }

          $name = $this->option('name') ?: $this->ask('Name');
          $role = $this->option('role') ?: $this->choice('Role', ['admin', 'manager', 'ops'], 'admin');

          if (AdminUser::where('email', $email)->exists()) {
              $this->error("Email {$email} already exists. Pass --reset-password to reset the password of the existing account instead.");
              return self::FAILURE;
          }

          if (! Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
              $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");
              return self::FAILURE;
          }

          $user = AdminUser::create([
              'name' => $name,
              'email' => $email,
              'password' => $password, // hashed cast handles it
          ]);
          $user->assignRole($role);

          $this->info("Created admin user {$email} with role {$role}.");
          return self::SUCCESS;
      }
  }
  ```

  Add `make admin-create-user` Makefile target:
  ```makefile
  admin-create-user:
  	docker compose exec -u 1000 admin php artisan admin:create-user
  ```

  No `email_verified_at` write — the column no longer exists (see Task 1). Admin account activation is implicit (created = usable); deactivation is explicit via `disabled_at`.

- **Acceptance Criteria:**
  - [ ] Interactive prompts for name, email, password, role (when not passed via flags)
  - [ ] Supports non-interactive flags for all fields
  - [ ] Validates role exists before creation
  - [ ] Without `--reset-password`: rejects duplicate emails with a message pointing at the flag
  - [ ] With `--reset-password`: re-hashes password on an existing account matched by `--email`; errors clearly if the account does not exist
  - [ ] With `--reset-password --reassign-role`: additionally re-syncs the role to the value of `--role`
  - [ ] With `--reset-password` alone: role membership is left untouched
  - [ ] Does not write `email_verified_at` (column no longer exists)
  - [ ] `make admin-create-user` runs inside the container

---

### Task 7: `activity:prune` schedule entry (runner in Plan 09)

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `admin/app/Console/Kernel.php` (modify)
- **Scope note.** This task registers the schedule **entry only**. The admin container in Plan 01 does not yet run `schedule:run`; the scheduler / cron sidecar service is added in Plan 09. Until Plan 09 lands, the entry is dormant — it will be exercised manually in the acceptance criteria below rather than on a timer.
- **Details:**
  `spatie/laravel-activitylog` ships a `CleanActivitylogCommand` that deletes rows older than `delete_records_older_than_days` (180). Register it on the daily schedule:

  ```php
  protected function schedule(Schedule $schedule): void
  {
      $schedule->command('activitylog:clean')->daily();
  }
  ```

  Leave a TODO comment in `Kernel.php` pointing at Plan 09 so the reviewer there knows this entry already exists and only the runner is outstanding.

- **Acceptance Criteria:**
  - [ ] `activitylog:clean` registered on the daily schedule
  - [ ] Command runs manually without error: `php artisan activitylog:clean`
  - [ ] Retention matches 180 days from config
  - [ ] Plan explicitly notes that automatic daily execution depends on the scheduler service added in Plan 09

---

### Task 8: Global `/admin/activity` page

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Pages/ActivityLog.php` (new)
  - `admin/resources/views/filament/pages/activity-log.blade.php` (new)
- **Scope note.** This is a **v1 browsing tool**, not the long-term audit strategy. It exists to give admins a fast, read-only window into the last chunk of activity during initial rollout. A proper audit view — server-side pagination, full-text search, export, retention/compliance tooling — is out of scope for v1 and will be revisited once the resource plans produce a realistic volume of rows to design against.
- **Details:**
  Custom Filament page at `/admin/activity` listing recent audit log entries with filters by causer, subject type, and date range.

  ```php
  class ActivityLog extends Page implements HasTable
  {
      use InteractsWithTable;

      protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
      protected static string $view = 'filament.pages.activity-log';
      protected static ?int $navigationSort = 99;
      protected static ?string $navigationGroup = 'System';

      public static function canAccess(): bool
      {
          return auth()->user()?->can('activity.view') ?? false;
      }

      public function table(Table $table): Table
      {
          return $table
              ->query(Activity::query()->latest()->limit(1000))
              ->columns([
                  TextColumn::make('created_at')->dateTime()->sortable(),
                  TextColumn::make('causer.email')->label('Admin'),
                  TextColumn::make('description')->label('Action'),
                  TextColumn::make('subject_type')->label('Resource')
                      ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                  TextColumn::make('subject_id')->label('ID'),
              ])
              ->filters([
                  SelectFilter::make('causer_id')
                      ->label('Admin')
                      ->options(AdminUser::pluck('email', 'id')),
                  SelectFilter::make('subject_type')
                      ->label('Resource')
                      ->options(fn () => Activity::query()
                          ->whereNotNull('subject_type')
                          ->distinct()
                          ->pluck('subject_type', 'subject_type')
                          ->mapWithKeys(fn ($v, $k) => [$k => class_basename($v)])),
                  Filter::make('created_at')
                      ->form([
                          DatePicker::make('from'),
                          DatePicker::make('until'),
                      ])
                      // `until` is interpreted as an inclusive end-of-day: selecting
                      // 2026-04-21 means "up to and including 2026-04-21 23:59:59".
                      ->query(fn (Builder $q, array $data) => $q
                          ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
                          ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))),
              ]);
      }
  }
  ```

  Blade view is minimal — Filament renders the table widget.

- **Acceptance Criteria:**
  - [ ] `/admin/activity` accessible only to users with `activity.view` permission
  - [ ] Lists latest 1000 activity rows
  - [ ] Filters work: by causer, subject type, date range
  - [ ] `until` date filter is inclusive — rows created on the selected day at any time are returned
  - [ ] Navigation item visible in admin sidebar under "System" group
  - [ ] Page documented (inline comment or progress journal) as a v1 browsing tool, not the long-term audit strategy

---

### Task 9: AdminAuthHelper trait + permission tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Helpers/AdminAuthHelper.php` (new)
  - `admin/tests/Pest.php` (modify — register trait)
  - `admin/tests/Feature/Auth/PermissionEnforcementTest.php` (new)
  - `admin/tests/Feature/Auth/LoginTest.php` (new)
  - `admin/tests/Feature/Auth/RoleSeederTest.php` (new)
  - `admin/tests/Feature/Auth/AuditLoggingTest.php` (new)
  - `admin/tests/Feature/Console/CreateAdminUserCommandTest.php` (new)
- **Details:**
  `AdminAuthHelper` trait mirrors the backend's `AuthHelper`:
  ```php
  trait AdminAuthHelper
  {
      protected function actingAsAdmin(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $user->assignRole('admin');
          $this->actingAs($user, 'admin');
          return $user;
      }

      protected function actingAsManager(): AdminUser { /* same pattern, role 'manager' */ }
      protected function actingAsOps(): AdminUser { /* same pattern, role 'ops' */ }
      protected function actingAsNobody(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $this->actingAs($user, 'admin');
          return $user; // no role assigned
      }
  }
  ```

  Register in `tests/Pest.php`:
  ```php
  pest()->extend(Tests\TestCase::class)->use(AdminAuthHelper::class)->in('Feature');
  ```

  **LoginTest:**
  - Test: visiting `/admin` unauthenticated redirects to `/admin/login`
  - Test: login with correct credentials redirects to `/admin`
  - Test: login with wrong password shows validation error
  - Test: disabled admin user cannot access panel (`canAccessPanel` returns false when `disabled_at` is set — uses `AdminUser::factory()->disabled()->create()`)
  - Test: re-enabling a disabled admin (setting `disabled_at = null`) restores panel access without any other change

  **PermissionEnforcementTest:**
  - Test: `actingAsAdmin()` has all permissions
  - Test: `actingAsManager()` can create movies (true) but cannot access admin_users.create (false)
  - Test: `actingAsOps()` can view bookings (true) but cannot update movies (false)
  - Test: `actingAsNobody()` has no permissions

  **RoleSeederTest:**
  - Test: running seeder twice is idempotent (role count stays at 3)
  - Test: admin role has every permission
  - Test: manager role has exactly the documented permission set
  - Test: ops role has exactly the documented permission set
  - Test: no role (including admin) has a `users.update` permission — codifies the v1 "customer users are read-only" decision

  **AuditLoggingTest:**
  - Test: a successful login writes a row with `log_name = 'auth'`, `description = 'login'`, and `causer` resolving to the `AdminUser`
  - Test: a logout writes a `log_name = 'auth'`, `description = 'logout'` row
  - Test: a failed login writes a `log_name = 'auth'`, `description = 'login_failed'` row with the attempted email in `properties`
  - Test: creating / updating / deleting an `AdminUser` writes one row per event via the `LogsActivity` trait
  - Test (negative): Plan 02 does *not* auto-log writes to unrelated models — e.g. creating a `Role` directly does not produce an activity row. This guards against anyone assuming resource audit is already solved.

  **CreateAdminUserCommandTest** (covers Task 6's command):
  - Test: non-interactive creation with `--name --email --password --role=manager` creates an admin with the right role
  - Test: duplicate email without `--reset-password` errors and returns `FAILURE`; the error message names the `--reset-password` flag
  - Test: `--reset-password --email=... --password=...` on an existing account re-hashes the password without touching roles
  - Test: `--reset-password --reassign-role --role=ops --email=... --password=...` re-hashes the password *and* syncs the role to `ops`, wiping prior roles
  - Test: `--reset-password` targeting a non-existent email returns `FAILURE` and instructs the operator to drop the flag to create
  - Test: command does not set any `email_verified_at` value (asserts the column absence via a schema check, since the column no longer exists)

- **Acceptance Criteria:**
  - [ ] `AdminAuthHelper` trait registered in Pest
  - [ ] All four `actingAs*` helpers work
  - [ ] LoginTest passes (5 tests, including the disabled-admin and re-enable paths)
  - [ ] PermissionEnforcementTest passes (~4 tests)
  - [ ] RoleSeederTest passes (~5 tests)
  - [ ] AuditLoggingTest passes (~5 tests)
  - [ ] CreateAdminUserCommandTest passes (~6 tests)
  - [ ] `make admin-test` runs all auth tests green

---

## Testing Requirements

- **Pest Feature Tests:**
  - Login: unauth redirect, success, failure, disabled-admin blocked, re-enable restores access
  - Permissions: each role's capability matrix
  - Seeder: idempotency + correct permission sets
  - Auth-event audit: login / logout / failed-login write rows with `log_name = 'auth'`
  - AdminUser model audit: creating / updating / deleting an `AdminUser` writes a row
  - `admin:create-user` command: creation path, duplicate-email error, `--reset-password` path, `--reset-password --reassign-role` path, unknown-email error
- **Out of scope here:** per-resource audit verification — those tests land alongside the resources themselves in Plans 04+. The shared-domain-service explicit-Causer contract is tested in each of Plans 04/06/07/08.
- **Helpers:** `AdminAuthHelper` reusable across all future plans

## Dependencies Map

```
Task 1 (admin_users) ← foundational
Task 2 (auth guard) ← needs Task 1
Task 3 (Spatie Permission) ← needs Task 1
Task 4 (Spatie ActivityLog) ← parallel to Task 3
Task 5 (Filament panel) ← needs Tasks 2, 3
Task 6 (create-user command) ← needs Tasks 1, 3
Task 7 (activity prune) ← needs Task 4
Task 8 (activity page) ← needs Tasks 4, 5
Task 9 (tests) ← needs all
```

## Risks & Open Questions

1. **Scheduler container.** The admin container needs a process running `schedule:run` every minute for `activitylog:clean` to fire. Plan 09 adds a scheduler service; Task 7 above depends on that. Document in the plan's progress journal.
2. **Password reset flow.** Filament supports password reset out of the box, but we need to configure the mail driver. Dev uses Mailpit (already running). Prod setup deferred to Plan 09. Plan 09's runbook (Task 10) references the `admin:create-user --reset-password` flag (Task 6 here) as the operational fallback when the mail-based flow is unavailable.
3. **Session cookie collision.** If both backend (`finalcut.test`) and admin (`admin.finalcut.test`) set cookies on `.finalcut.test`, they could collide. Scoping admin to `admin.finalcut.test` (no leading dot, Task 2) prevents this. Plan 09's production `SESSION_DOMAIN` must use the same no-leading-dot form (`admin.finalcut.com`, not `.admin.finalcut.com`) so dev and prod agree — flagged in Plan 09 review.
4. **Shared-domain Causer contract propagation.** The explicit-Causer-argument rule landed in Task 4 applies to every shared-domain service method Plans 04/06/07/08 extract. If any service method in those plans omits the `Causer` parameter, the `/admin/activity` page will either mis-attribute rows (auto-resolution in the wrong guard) or drop the causer entirely. Each later plan's acceptance criteria must name the `Causer` argument explicitly; a PR-time grep (`rg 'public function (create|update|delete|cancel|void|adjust\w*|bulk\w+)\(' packages/shared-domain/src/Services/ | rg -v Causer`) is the mechanical check.
