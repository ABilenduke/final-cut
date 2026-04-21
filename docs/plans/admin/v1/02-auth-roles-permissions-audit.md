# Plan 02: Auth, Roles, Permissions & Audit Log

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (Filament panel and subdomain exist)
> **Unlocks:** Plan 03 (base Resource class wires these permissions into every Resource)

## Overview

Create the `admin_users` table and model in `backend/`, configure the `admin` auth guard, wire Filament to use it, install Spatie Permission and Activity Log packages inside the backend app, seed the three roles (admin / manager / ops) with their permission sets, and ship the global activity log page plus the `admin:create-user` artisan command. End state: an admin can log in at `https://admin.finalcut.test/login`; auth events and admin-user model changes write rows to `activity_log`. Per-resource audit logging is *not* in scope for this plan — it arrives as each resource is registered in later plans via the `BaseResource` class from Plan 03.

All files land inside the existing `backend/` Laravel app. No separate admin codebase, no shared package.

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
  - `backend/database/migrations/YYYY_MM_DD_HHMMSS_create_admin_users_table.php`
  - `backend/app/Models/AdminUser.php`
  - `backend/database/factories/AdminUserFactory.php`
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

  **No `email_verified_at` column.** Admin users are created explicitly by another admin via `admin:create-user` (Task 6) — there is no self-signup flow that would need verification. Replacing it with a nullable `disabled_at` timestamp gives ops a real kill switch to deactivate a terminated employee's account without deleting the row, and the "when was this disabled" metadata comes for free (project convention: booleans as timestamps — see CLAUDE.md).

  `AdminUser` model extends `Illuminate\Foundation\Auth\User` and implements `Filament\Models\Contracts\FilamentUser`. In Task 1 the model is intentionally minimal — the `HasRoles` trait is added in Task 3 (after Spatie Permission is installed) and `LogsActivity` is added in Task 4 (after Spatie ActivityLog is installed). Keep this task focused on the schema and basic authentication surface.

  ```php
  namespace App\Models;

  use Filament\Models\Contracts\FilamentUser;
  use Filament\Panel;
  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use Illuminate\Foundation\Auth\User as Authenticatable;
  use Illuminate\Notifications\Notifiable;

  class AdminUser extends Authenticatable implements FilamentUser
  {
      use Notifiable, HasFactory;

      protected $table = 'admin_users';
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
  - [ ] `AdminUser` model references the `admin_users` table and lives at `backend/app/Models/AdminUser.php`
  - [ ] Password cast to `hashed`; `disabled_at` and `last_login_at` cast to `datetime`
  - [ ] `canAccessPanel` returns true only when `disabled_at` is null and the panel ID is `admin`
  - [ ] Factory generates valid AdminUser instances with `disabled_at = null` by default
  - [ ] Factory has a `disabled()` state that sets `disabled_at = now()`

---

### Task 2: Configure `admin` auth guard

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/config/auth.php` (modify)
  - `backend/.env.example` (modify)
- **Details:**
  Add an `admin` guard alongside the existing `web` and `sanctum` guards. Do **not** change `defaults.guard` — the customer API still uses `sanctum` (and `web` where session-backed). The admin panel binds to the `admin` guard via `AdminPanelProvider->authGuard('admin')` (done in Plan 01 Task 2).

  ```php
  // backend/config/auth.php — additive changes only
  'guards' => [
      'web' => ['driver' => 'session', 'provider' => 'users'],
      'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
      'admin' => ['driver' => 'session', 'provider' => 'admin_users'],
  ],

  'providers' => [
      'users' => [
          'driver' => 'eloquent',
          'model' => App\Models\User::class,
      ],
      'admin_users' => [
          'driver' => 'eloquent',
          'model' => App\Models\AdminUser::class,
      ],
  ],

  'passwords' => [
      'users' => [/* existing customer config */],
      'admin_users' => [
          'provider' => 'admin_users',
          'table' => 'password_reset_tokens',
          'expire' => 60,
          'throttle' => 60,
      ],
  ],
  ```

  **Session cookie scoping is handled by the `ScopeAdminSession` middleware registered in Plan 01 Task 2**, which sets `session.cookie`, `session.domain`, and `cache.prefix` on admin-subdomain requests. That middleware reads `ADMIN_SESSION_COOKIE`, `ADMIN_SESSION_DOMAIN`, and uses `admin_session` as the Redis prefix. No changes to `backend/config/session.php` are needed here — the global session config continues to serve the customer surface; admin requests override it per-request via the middleware.

  **Cookie domain note.** `ADMIN_SESSION_DOMAIN=admin.finalcut.test` (no leading dot) deliberately. A leading dot (`.admin.finalcut.test`) shares the cookie across every sub-subdomain, which we do not need. Isolation from the customer app (`finalcut.test`) is achieved by the distinct subdomain plus a distinct cookie name (`admin_session`), not by manipulating the leading dot.

  Redis key prefixes — scoped per subsystem:

  - `admin_session:*` — Laravel session keys (set by `ScopeAdminSession` middleware)
  - `admin_cache:*` — application cache entries (if admin-side caching is needed; set explicitly per call site)
  - `admin_queue:*` — queue payloads (added in Plan 09 if admin-specific queue isolation is needed)

  Verify env vars are declared in `backend/.env.example` (added in Plan 01 Task 2): `ADMIN_SESSION_COOKIE`, `ADMIN_SESSION_DOMAIN`, `APP_PRIMARY_DOMAIN`, `ADMIN_DOMAIN`.

- **Acceptance Criteria:**
  - [ ] `admin` guard added to `backend/config/auth.php` using the session driver and `admin_users` provider
  - [ ] Customer `web` and `sanctum` guards unchanged
  - [ ] `defaults.guard` unchanged (customer surface still uses the existing default)
  - [ ] Session cookie name for admin differs from customer (`admin_session` vs the customer default)
  - [ ] Session cookie domain for admin requests scoped to the admin subdomain without a leading dot
  - [ ] Redis session keys for admin land under `admin_session:*` — verified by `redis-cli keys '*'` after an admin login

---

### Task 3: Install Spatie Permission, seed roles

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/composer.json` (modify)
  - `backend/config/permission.php` (published)
  - `backend/database/migrations/*_create_permission_tables.php` (vendor-published)
  - `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` (new)
  - `backend/database/seeders/DatabaseSeeder.php` (modify — invoke the new seeder)
- **Details:**
  Install inside the backend container (`make shell`):
  ```bash
  composer require spatie/laravel-permission
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  ```

  Configure `backend/config/permission.php` — the default config uses the caller's active guard at runtime, which works for admin but also opens the door to accidentally assigning admin roles to customer `User` models. Lock the admin role scope by setting `guard_name => 'admin'` on every Role record at seed time (below). Spatie's `HasRoles` trait on `AdminUser` only matches roles whose `guard_name` equals the guard the user authenticated against, so this is sufficient to keep admin roles out of the customer user model.

  Add `HasRoles` to `backend/app/Models/AdminUser.php`:

  ```php
  use Spatie\Permission\Traits\HasRoles;

  class AdminUser extends Authenticatable implements FilamentUser
  {
      use Notifiable, HasFactory, HasRoles;

      protected string $guard_name = 'admin';  // explicit guard binding
      // ...
  }
  ```

  `AdminRolesAndPermissionsSeeder` — idempotent, uses `firstOrCreate`. Seeds all permissions with `guard_name = 'admin'`, then the three roles with their permission sets:

  **Permissions (grouped by resource):**
  ```
  movies.view, movies.create, movies.update, movies.delete, movies.trigger_enrich
  showtimes.view, showtimes.create, showtimes.update, showtimes.delete, showtimes.cancel
  locations.view, locations.create, locations.update, locations.delete
  auditoriums.view, auditoriums.create, auditoriums.update, auditoriums.delete
  seats.view, seats.update
  bookings.view, bookings.resolve_refund
  users.view
  loyalty.view, loyalty.adjust_points, loyalty.adjust_tier
  menu.view, menu.create, menu.update, menu.delete
  promos.view, promos.create, promos.update, promos.delete
  gift_cards.view, gift_cards.void
  events.view, events.create, events.update, events.delete
  admin_users.view, admin_users.create, admin_users.update, admin_users.delete
  activity.view
  ```

  **Loyalty writes are narrow, not broad.** Plan 07's loyalty actions (Adjust Points, Upgrade to Premier, Revoke Premier) are gated on `loyalty.adjust_points` (for point deltas) and `loyalty.adjust_tier` (for premier membership changes). There is **no** broad `loyalty.adjust` permission.

  **`bookings.resolve_refund`** gates the "Mark refunded (manual)" action on Plan 06 Task 6's follow-up queue. Separated from `bookings.view` so ops/support roles can browse the queue without being able to close financial cases.

  **Customer user write surface — deferred.** Customer users (the `users` table on the customer side) are read-only from admin in v1. No `users.update` permission is seeded. Loyalty writes are exposed through the two narrower permissions above.

  **Roles and their permission sets** — all scoped to the `admin` guard:
  ```php
  Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
  // ... for every permission in the list

  $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
  $admin->syncPermissions(Permission::where('guard_name', 'admin')->get());

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
      'bookings.view', 'bookings.resolve_refund',
      'gift_cards.view',
      'users.view',
      'loyalty.view', 'loyalty.adjust_points', 'loyalty.adjust_tier',
      'activity.view',
  ]);

  $ops = Role::firstOrCreate(['name' => 'ops', 'guard_name' => 'admin']);
  $ops->syncPermissions([
      'bookings.view', 'users.view', 'loyalty.view', 'gift_cards.view',
      'movies.view', 'showtimes.view', 'locations.view', 'auditoriums.view',
      'activity.view',
  ]);
  ```

  Register `AdminRolesAndPermissionsSeeder` from `DatabaseSeeder::run()` alongside the existing customer seeders — the admin seeder is idempotent, so running `make fresh` seeds both the customer test data and the admin roles without conflict.

- **Acceptance Criteria:**
  - [ ] `spatie/laravel-permission` installed and config published inside `backend/`
  - [ ] Permission tables migrated
  - [ ] `AdminUser` has `HasRoles` trait and `protected string $guard_name = 'admin'`
  - [ ] Seeder creates all documented permissions with `guard_name = 'admin'`
  - [ ] Three roles created with correct permission sets, all scoped to `admin` guard
  - [ ] Seeder is idempotent (running twice does not duplicate)
  - [ ] Assigning any admin role to a customer `App\Models\User` fails (guard mismatch)

---

### Task 4: Install Spatie ActivityLog

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/composer.json` (modify)
  - `backend/config/activitylog.php` (published)
  - `backend/database/migrations/*_create_activity_log_table.php` (vendor-published)
  - `backend/app/Models/AdminUser.php` (modify — add `LogsActivity` trait)
- **Details:**
  Install inside the backend container:
  ```bash
  composer require spatie/laravel-activitylog
  php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
  php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
  ```

  Configure `backend/config/activitylog.php`:
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

  Add the `LogsActivity` trait to the `AdminUser` model. **Scope:** this only logs model-level events on `AdminUser` itself — created / updated / deleted rows on the `admin_users` table. It does not create a global audit trail across the rest of the admin section. Per-resource audit logging is wired up resource-by-resource as each one is registered in Plans 04+, typically via the `BaseResource` class introduced in Plan 03. Explicit write flows that don't go through a model (artisan commands, service-level actions) have to log their own activity rows deliberately — the trait cannot catch those automatically.

  What Plan 02 actually produces in `activity_log`:
  - a row per login / logout / failed-login event (wired via Laravel auth events in Task 5)
  - a row per `AdminUser` model change (via `LogsActivity` on the model)
  - nothing else yet — the global activity page in Task 8 will mostly show auth events until the resource plans land

  **Causer resolution strategy.** Setting `default_auth_driver = 'admin'` lets the activity-log package resolve the currently-logged-in admin via `auth('admin')->user()` for events inside admin HTTP requests. Since admin code runs on its own subdomain with its own guard, the default-driver read is correct for admin-originated events. It is *wrong* for events originating from customer HTTP requests or scheduled commands — but those paths are not writing admin activity in v1.

  Services that write to shared tables (created in Plans 04–08) accept an optional `?AdminUser $actor = null` argument. When called from a Filament Resource, the Resource passes `auth('admin')->user()` explicitly. When called from a customer controller or scheduler, it's left null and the service skips admin activity attribution. This is the simple, contract-first approach — no interface gymnastics needed because the two call contexts share a codebase.

- **Acceptance Criteria:**
  - [ ] `spatie/laravel-activitylog` installed and config published inside `backend/`
  - [ ] `activity_log` table migrated
  - [ ] Retention set to 180 days
  - [ ] `default_auth_driver` set to `admin` — documented as correct for admin-originated events only
  - [ ] `LogsActivity` trait added to `AdminUser`; creating/updating an `AdminUser` in tinker writes a row to `activity_log`
  - [ ] Plan 02 does not claim to audit resources it has not yet introduced

---

### Task 5: Finalize AdminPanelProvider with auth + audit events

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Providers/Filament/AdminPanelProvider.php` (modify)
  - `backend/app/Providers/AppServiceProvider.php` (modify — wire auth events)
- **Details:**
  Plan 01 Task 2 set the panel's guard and domain. This task finalizes the auth surface:

  - Confirm `->authGuard('admin')` is set
  - Add `->authPasswordBroker('admin_users')`
  - Confirm `->login()` is enabled
  - Confirm auto-discovery scans `App\Filament\*`

  Do **not** hand-roll the middleware array; keep whatever `filament:install` produced. Filament's stack changes between point releases.

  Wire Laravel auth events to the activity log so login / logout / failed-login write rows. Add to `AppServiceProvider::boot()`:

  ```php
  use Illuminate\Auth\Events\Failed;
  use Illuminate\Auth\Events\Login;
  use Illuminate\Auth\Events\Logout;
  use Illuminate\Support\Facades\Event;

  Event::listen(Login::class, function (Login $e) {
      if ($e->guard !== 'admin') return;
      activity('auth')->causedBy($e->user)->log('login');
      $e->user->forceFill([
          'last_login_at' => now(),
          'last_login_ip' => request()->ip(),
      ])->save();
  });

  Event::listen(Logout::class, function (Logout $e) {
      if ($e->guard !== 'admin') return;
      activity('auth')->causedBy($e->user)->log('logout');
  });

  Event::listen(Failed::class, function (Failed $e) {
      if ($e->guard !== 'admin') return;
      activity('auth')->withProperties(['email' => $e->credentials['email'] ?? null])->log('login_failed');
  });
  ```

  The `if ($e->guard !== 'admin') return;` check keeps these listeners from firing on customer auth events. Customer auth attribution is out of scope for admin's audit log.

- **Acceptance Criteria:**
  - [ ] Panel uses the `admin` guard and the `admin_users` password broker
  - [ ] Login page enabled at `https://admin.finalcut.test/login`
  - [ ] Filament's default middleware stack is preserved (not hand-rolled in this plan)
  - [ ] Auto-discovery scans `App\Filament\Resources` and `App\Filament\Pages`
  - [ ] Visiting `https://admin.finalcut.test/` without auth redirects to `/login`
  - [ ] Admin login, logout, and failed-login events write rows to `activity_log` with `log_name = 'auth'`
  - [ ] Customer (non-admin) login events do **not** write to `activity_log`
  - [ ] `last_login_at` and `last_login_ip` are updated on successful admin login

---

### Task 6: `admin:create-user` artisan command

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Console/Commands/CreateAdminUser.php` (new)
- **Details:**
  Interactive command that prompts for name, email, password, and role. The `--reset-password` flag promotes the command into a password-reset tool for existing accounts, because Plan 09's operational runbook lists this as the fallback path when the mail-based reset flow isn't available. Without the flag, duplicate emails still error (guards against accidental overwrites).

  ```php
  namespace App\Console\Commands;

  use App\Models\AdminUser;
  use Illuminate\Console\Command;
  use Spatie\Permission\Models\Role;

  class CreateAdminUser extends Command
  {
      protected $signature = 'admin:create-user
          {--name= : Full name (ignored with --reset-password)}
          {--email= : Email address}
          {--password= : Password}
          {--role=admin : Role (admin, manager, ops) — ignored with --reset-password unless --reassign-role is also passed}
          {--reset-password : Reset the password of an existing admin user matched by --email. Creation is skipped; role is not changed unless --reassign-role is also set.}
          {--reassign-role : With --reset-password, also re-assign the role to the value of --role. Ignored without --reset-password.}';

      protected $description = 'Create or password-reset an admin user';

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

  The Make target `admin-create-user` was added in Plan 01 Task 5 — running `make admin-create-user` now works end-to-end.

  No `email_verified_at` write — the column does not exist (see Task 1).

- **Acceptance Criteria:**
  - [ ] Interactive prompts for name, email, password, role (when not passed via flags)
  - [ ] Supports non-interactive flags for all fields
  - [ ] Validates role exists before creation
  - [ ] Without `--reset-password`: rejects duplicate emails with a message pointing at the flag
  - [ ] With `--reset-password`: re-hashes password on an existing account matched by `--email`; errors clearly if the account does not exist
  - [ ] With `--reset-password --reassign-role`: additionally re-syncs the role to the value of `--role`
  - [ ] With `--reset-password` alone: role membership is left untouched
  - [ ] Does not write `email_verified_at` (column no longer exists)
  - [ ] `make admin-create-user` runs inside the backend container and completes successfully

---

### Task 7: `activitylog:clean` schedule entry

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `backend/routes/console.php` (modify)
- **Details:**
  `spatie/laravel-activitylog` ships a `CleanActivitylogCommand` that deletes rows older than `delete_records_older_than_days` (180). Register it on the daily schedule alongside the existing backend scheduled commands:

  ```php
  // backend/routes/console.php
  use Illuminate\Support\Facades\Schedule;

  Schedule::command('activitylog:clean')->daily();
  ```

  If the existing backend already runs `schedule:run` via cron or a sidecar container, this entry is live immediately. If not, Plan 09 verifies the scheduler setup — Task 7 here just adds the schedule line.

- **Acceptance Criteria:**
  - [ ] `activitylog:clean` registered on the daily schedule in `backend/routes/console.php`
  - [ ] Command runs manually without error: `php artisan activitylog:clean`
  - [ ] Retention matches 180 days from config

---

### Task 8: Global activity page on the admin panel

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Pages/ActivityLog.php` (new)
  - `backend/resources/views/filament/pages/activity-log.blade.php` (new)
- **Scope note.** This is a **v1 browsing tool**, not the long-term audit strategy. It gives admins a fast, read-only window into the last chunk of activity during initial rollout. A proper audit view — server-side pagination, full-text search, export, retention/compliance tooling — is out of scope for v1.
- **Details:**
  Custom Filament page at `/activity` (on the admin subdomain — Filament panel is mounted at path `/`) listing recent audit log entries with filters by causer, subject type, and date range.

  ```php
  namespace App\Filament\Pages;

  use App\Models\AdminUser;
  use Carbon\Carbon;
  use Filament\Forms\Components\DatePicker;
  use Filament\Pages\Page;
  use Filament\Tables\Columns\TextColumn;
  use Filament\Tables\Concerns\InteractsWithTable;
  use Filament\Tables\Contracts\HasTable;
  use Filament\Tables\Filters\Filter;
  use Filament\Tables\Filters\SelectFilter;
  use Filament\Tables\Table;
  use Illuminate\Database\Eloquent\Builder;
  use Spatie\Activitylog\Models\Activity;

  class ActivityLog extends Page implements HasTable
  {
      use InteractsWithTable;

      protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
      protected static string $view = 'filament.pages.activity-log';
      protected static ?int $navigationSort = 99;
      protected static ?string $navigationGroup = 'System';
      protected static ?string $slug = 'activity';

      public static function canAccess(): bool
      {
          return auth('admin')->user()?->can('activity.view') ?? false;
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
                      ->query(fn (Builder $q, array $data) => $q
                          ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
                          ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))),
              ]);
      }
  }
  ```

  Blade view is minimal — Filament renders the table widget.

- **Acceptance Criteria:**
  - [ ] `/activity` on the admin subdomain is accessible only to users with `activity.view` permission
  - [ ] Lists latest 1000 activity rows
  - [ ] Filters work: by causer, subject type, date range
  - [ ] `until` date filter is inclusive — rows created on the selected day at any time are returned
  - [ ] Navigation item visible in admin sidebar under "System" group
  - [ ] Page documented in the progress journal as a v1 browsing tool, not the long-term audit strategy

---

### Task 9: AdminAuthHelper trait + permission tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Helpers/AdminAuthHelper.php` (new)
  - `backend/tests/Pest.php` (modify — register trait)
  - `backend/tests/Feature/Admin/Auth/PermissionEnforcementTest.php` (new)
  - `backend/tests/Feature/Admin/Auth/LoginTest.php` (new)
  - `backend/tests/Feature/Admin/Auth/RoleSeederTest.php` (new)
  - `backend/tests/Feature/Admin/Auth/AuditLoggingTest.php` (new)
  - `backend/tests/Feature/Admin/Auth/SessionCookieScopingTest.php` (new)
  - `backend/tests/Feature/Admin/Console/CreateAdminUserCommandTest.php` (new)
- **Details:**
  `AdminAuthHelper` trait mirrors the backend's existing `AuthHelper`:

  ```php
  namespace Tests\Helpers;

  use App\Models\AdminUser;

  trait AdminAuthHelper
  {
      protected function actingAsAdmin(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $user->assignRole('admin');
          $this->actingAs($user, 'admin');
          return $user;
      }

      protected function actingAsManager(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $user->assignRole('manager');
          $this->actingAs($user, 'admin');
          return $user;
      }

      protected function actingAsOps(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $user->assignRole('ops');
          $this->actingAs($user, 'admin');
          return $user;
      }

      protected function actingAsNobody(): AdminUser
      {
          $user = AdminUser::factory()->create();
          $this->actingAs($user, 'admin');
          return $user;
      }
  }
  ```

  Register in `backend/tests/Pest.php`:
  ```php
  pest()->extend(Tests\TestCase::class)->use(AdminAuthHelper::class)->in('Feature/Admin');
  ```

  **LoginTest:**
  - Visiting `https://admin.finalcut.test/` unauthenticated redirects to `/login`
  - Login with correct credentials redirects to `/`
  - Login with wrong password shows validation error
  - Disabled admin user cannot access panel (`canAccessPanel` returns false when `disabled_at` is set — uses `AdminUser::factory()->disabled()->create()`)
  - Re-enabling a disabled admin (setting `disabled_at = null`) restores panel access without any other change
  - Admin login does not grant access to customer `/api/*` routes (asserts 401/403 on `GET /api/account/profile` with admin session cookie)

  **PermissionEnforcementTest:**
  - `actingAsAdmin()` has all permissions
  - `actingAsManager()` can create movies (true) but cannot access `admin_users.create` (false)
  - `actingAsOps()` can view bookings (true) but cannot update movies (false)
  - `actingAsNobody()` has no permissions
  - Assigning an admin role to a customer `App\Models\User` throws / is rejected (guard mismatch)

  **RoleSeederTest:**
  - Running seeder twice is idempotent (role count stays at 3)
  - admin role has every permission
  - manager role has exactly the documented permission set
  - ops role has exactly the documented permission set
  - No role has a `users.update` permission — codifies the v1 "customer users are read-only" decision
  - All admin roles have `guard_name = 'admin'`

  **AuditLoggingTest:**
  - A successful admin login writes a row with `log_name = 'auth'`, `description = 'login'`, causer resolving to the `AdminUser`, and updates `last_login_at` / `last_login_ip`
  - A logout writes a `log_name = 'auth'`, `description = 'logout'` row
  - A failed login writes a `log_name = 'auth'`, `description = 'login_failed'` row with the attempted email in `properties`
  - Creating / updating / deleting an `AdminUser` writes one row per event via the `LogsActivity` trait
  - Customer `web`-guard login does **not** write an `activity_log` row (guard filter verified)
  - Plan 02 does not auto-log writes to unrelated models — creating a `Role` directly does not produce an activity row

  **SessionCookieScopingTest:**
  - Admin login sets a cookie named `admin_session` with domain `admin.finalcut.test`
  - Customer login sets a cookie without the `admin_session` name
  - Setting `ADMIN_SESSION_DOMAIN=admin.finalcut.com` propagates through to the rendered cookie (env override test)

  **CreateAdminUserCommandTest** (covers Task 6's command):
  - Non-interactive creation with `--name --email --password --role=manager` creates an admin with the right role
  - Duplicate email without `--reset-password` errors and returns `FAILURE`; the error message names the `--reset-password` flag
  - `--reset-password --email=... --password=...` on an existing account re-hashes the password without touching roles
  - `--reset-password --reassign-role --role=ops --email=... --password=...` re-hashes the password *and* syncs the role to `ops`, wiping prior roles
  - `--reset-password` targeting a non-existent email returns `FAILURE` and instructs the operator to drop the flag to create
  - Command does not set any `email_verified_at` value (column absence asserted via a schema check)

- **Acceptance Criteria:**
  - [ ] `AdminAuthHelper` trait registered in Pest and scoped to `Feature/Admin` tests
  - [ ] All four `actingAs*` helpers work
  - [ ] LoginTest passes (6 tests)
  - [ ] PermissionEnforcementTest passes (5 tests)
  - [ ] RoleSeederTest passes (6 tests)
  - [ ] AuditLoggingTest passes (6 tests)
  - [ ] SessionCookieScopingTest passes (3 tests)
  - [ ] CreateAdminUserCommandTest passes (6 tests)
  - [ ] `make admin-test` runs all admin auth tests green

---

## Testing Requirements

All admin tests live under `backend/tests/Feature/Admin/` and `backend/tests/Unit/Admin/`. `make admin-test` runs the `Feature/Admin` subset; `make test-backend` runs everything including the admin suite.

- **Pest Feature Tests:**
  - Login: unauth redirect, success, failure, disabled-admin blocked, re-enable restores access, no cross-guard customer-API access
  - Permissions: each role's capability matrix, guard isolation
  - Seeder: idempotency + correct permission sets + guard scoping
  - Auth-event audit: login / logout / failed-login write rows scoped to the admin guard only
  - AdminUser model audit: creating / updating / deleting an `AdminUser` writes a row
  - Session cookie scoping: cookie name + domain per config
  - `admin:create-user` command: creation path, duplicate-email error, `--reset-password` path, `--reset-password --reassign-role` path, unknown-email error
- **Out of scope here:** per-resource audit verification — those tests land alongside the resources themselves in Plans 04+.
- **Helpers:** `AdminAuthHelper` reusable across all future admin plans.

## Dependencies Map

```
Task 1 (admin_users migration + model + factory) ← foundational
Task 2 (admin guard config) ← needs Task 1
Task 3 (Spatie Permission install + seeder) ← needs Task 1
Task 4 (Spatie ActivityLog install) ← parallel to Task 3
Task 5 (AdminPanelProvider finalization + auth events) ← needs Tasks 2, 3, 4
Task 6 (admin:create-user command) ← needs Tasks 1, 3
Task 7 (activitylog:clean schedule entry) ← needs Task 4
Task 8 (global activity page) ← needs Tasks 4, 5
Task 9 (tests) ← needs all
```

## Risks & Open Questions

1. **Scheduler runner.** If the backend container does not already run `schedule:run` via cron, Task 7's schedule entry is dormant until Plan 09 verifies the scheduler setup. Document in the progress journal.
2. **Password reset flow.** Filament supports password reset out of the box but requires a configured mail driver. Dev uses Mailpit (already running). Prod setup handled in Plan 09. The `admin:create-user --reset-password` command (Task 6) is the operational fallback when the mail-based flow is unavailable.
3. **Customer + admin cookie interference.** Because admin and customer share a Redis instance, the `admin_session:*` prefix is the boundary that keeps them apart. Misconfiguring `ScopeAdminSession` middleware (e.g., not setting `cache.prefix`) would cause admin session reads to collide with the customer session store. The `SessionCookieScopingTest` in Task 9 catches this.
4. **Guard-scoped roles.** Spatie's `HasRoles` respects `guard_name` only when the *role record* has `guard_name = 'admin'` and the user's `$guard_name` matches. Any future seeder that creates an admin role without setting `guard_name` would open a path for customer users to be assigned admin roles. The `RoleSeederTest` asserts every admin role has `guard_name = 'admin'`.
