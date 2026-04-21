# Plan 02: Auth, Roles, Permissions & Audit Log

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (admin app exists)
> **Unlocks:** Plan 03 (model layer needs auth to scope tests)

## Overview

Create the `admin_users` table and model, configure the `admin` auth guard, wire Filament to use it, install Spatie Permission and Activity Log packages, seed the three roles (admin / manager / ops) with their permission sets, and ship the global activity log page plus the `admin:create-user` artisan command. End state: an admin can log in at `https://admin.finalcut.test/admin/login`, and every action they take writes a row to `activity_log`.

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
      $table->timestamp('email_verified_at')->nullable();
      $table->string('password');
      $table->rememberToken();
      $table->timestamp('last_login_at')->nullable();
      $table->string('last_login_ip', 45)->nullable();
      $table->timestamps();
  });
  ```

  `AdminUser` model extends `Illuminate\Foundation\Auth\User` and implements `Filament\Models\Contracts\FilamentUser`:
  ```php
  class AdminUser extends Authenticatable implements FilamentUser
  {
      use Notifiable, HasFactory, HasRoles, LogsActivity; // HasRoles and LogsActivity come from packages in Tasks 3 and 4
      protected $fillable = ['name', 'email', 'password'];
      protected $hidden = ['password', 'remember_token'];
      protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

      public function canAccessPanel(Panel $panel): bool
      {
          return $panel->getId() === 'admin' && $this->email_verified_at !== null;
      }
  }
  ```

  `AdminUserFactory` follows the Laravel convention — bcrypt password `'password'`, unique email via `fake()->unique()->safeEmail()`.

- **Acceptance Criteria:**
  - [ ] Migration creates `admin_users` table with documented columns
  - [ ] `AdminUser` model references the `admin_users` table
  - [ ] Password cast to `hashed`
  - [ ] `canAccessPanel` returns true only for verified users
  - [ ] Factory generates valid AdminUser instances

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
  'domain' => env('SESSION_DOMAIN', '.admin.finalcut.test'),
  'secure' => true,
  'http_only' => true,
  'same_site' => 'lax',
  ```

  Redis session prefix to avoid collision with backend sessions:
  ```php
  // config/database.php — Redis default connection
  'prefix' => env('REDIS_PREFIX', 'admin_'),
  ```

  Add `SESSION_COOKIE=admin_session`, `SESSION_DOMAIN=.admin.finalcut.test`, `REDIS_PREFIX=admin_` to `admin/.env.example`.

- **Acceptance Criteria:**
  - [ ] Default guard is `admin`
  - [ ] `admin` guard uses session driver + `admin_users` provider
  - [ ] Session cookie name differs from backend (`admin_session` vs customer's default)
  - [ ] Session cookie domain scoped to admin subdomain
  - [ ] Redis keys prefixed with `admin_` to avoid customer session collision

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
  users.view, users.update
  loyalty.view, loyalty.adjust
  menu.view, menu.create, menu.update, menu.delete
  promos.view, promos.create, promos.update, promos.delete
  gift_cards.view, gift_cards.void
  events.view, events.create, events.update, events.delete
  admin_users.view, admin_users.create, admin_users.update, admin_users.delete
  activity.view
  ```

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

  Add `LogsActivity` trait to `AdminUser` model — though admin user changes themselves are auto-logged when resources are registered in Plan 02.

  Resources will use the trait in Plans 04+ per model. The base trait config goes in `BaseResource` in Plan 03.

- **Acceptance Criteria:**
  - [ ] `spatie/laravel-activitylog` installed and config published
  - [ ] `activity_log` table migrated
  - [ ] Retention set to 180 days
  - [ ] Default auth driver set to `admin` so causer detection works

---

### Task 5: Configure Filament AdminPanelProvider

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Providers/Filament/AdminPanelProvider.php` (modify)
- **Details:**
  Update the panel provider to use the `admin` guard, enable login, and register resource auto-discovery:

  ```php
  public function panel(Panel $panel): Panel
  {
      return $panel
          ->id('admin')
          ->path('admin')
          ->default()
          ->login()
          ->authGuard('admin')
          ->authPasswordBroker('admin_users')
          ->colors(['primary' => Color::Amber])
          ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
          ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
          ->pages([Pages\Dashboard::class])
          ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
          ->widgets([Widgets\AccountWidget::class])
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

  Ensure `AdminPanelProvider` is registered in `bootstrap/providers.php`.

- **Acceptance Criteria:**
  - [ ] Panel uses `admin` guard
  - [ ] Login page enabled at `/admin/login`
  - [ ] Session auth middleware stack wired
  - [ ] Auto-discovery scans `App\Filament\Resources` and `App\Filament\Pages`
  - [ ] Visiting `/admin` without auth redirects to `/admin/login`

---

### Task 6: `admin:create-user` artisan command

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Console/Commands/CreateAdminUser.php` (new)
- **Details:**
  Interactive command that prompts for name, email, password, and role:

  ```php
  class CreateAdminUser extends Command
  {
      protected $signature = 'admin:create-user
          {--name= : Full name}
          {--email= : Email address}
          {--password= : Password}
          {--role=admin : Role (admin, manager, ops)}';

      public function handle(): int
      {
          $name = $this->option('name') ?: $this->ask('Name');
          $email = $this->option('email') ?: $this->ask('Email');
          $password = $this->option('password') ?: $this->secret('Password');
          $role = $this->option('role') ?: $this->choice('Role', ['admin', 'manager', 'ops'], 'admin');

          if (AdminUser::where('email', $email)->exists()) {
              $this->error("Email {$email} already exists.");
              return self::FAILURE;
          }

          if (!Role::where('name', $role)->where('guard_name', 'admin')->exists()) {
              $this->error("Role {$role} does not exist. Run `php artisan db:seed` first.");
              return self::FAILURE;
          }

          $user = AdminUser::create([
              'name' => $name,
              'email' => $email,
              'password' => $password, // hashed cast handles it
              'email_verified_at' => now(),
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

- **Acceptance Criteria:**
  - [ ] Interactive prompts for name, email, password, role
  - [ ] Supports non-interactive flags
  - [ ] Validates role exists before creation
  - [ ] Rejects duplicate emails
  - [ ] Sets `email_verified_at` automatically (admin users do not go through email verification)
  - [ ] `make admin-create-user` runs inside the container

---

### Task 7: `activity:prune` scheduled command

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `admin/app/Console/Kernel.php` (modify)
- **Details:**
  `spatie/laravel-activitylog` ships a `CleanActivitylogCommand` that deletes rows older than `delete_records_older_than_days` (180). Schedule it daily:

  ```php
  protected function schedule(Schedule $schedule): void
  {
      $schedule->command('activitylog:clean')->daily();
  }
  ```

  Confirm the admin container runs a `cron` or `supervisor` schedule runner. If not, document that Plan 09 adds the scheduler service.

- **Acceptance Criteria:**
  - [ ] `activitylog:clean` scheduled daily
  - [ ] Command runs manually without error: `php artisan activitylog:clean`
  - [ ] Retention matches 180 days from config

---

### Task 8: Global `/admin/activity` page

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Pages/ActivityLog.php` (new)
  - `admin/resources/views/filament/pages/activity-log.blade.php` (new)
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
                      ->formatStateUsing(fn ($state) => class_basename($state)),
                  TextColumn::make('subject_id')->label('ID'),
              ])
              ->filters([
                  SelectFilter::make('causer_id')
                      ->label('Admin')
                      ->options(AdminUser::pluck('email', 'id')),
                  SelectFilter::make('subject_type')
                      ->label('Resource')
                      ->options(fn () => Activity::distinct()->pluck('subject_type', 'subject_type')
                          ->mapWithKeys(fn ($v, $k) => [$k => class_basename($v)])),
                  Filter::make('created_at')
                      ->form([
                          DatePicker::make('from'),
                          DatePicker::make('until'),
                      ])
                      ->query(fn (Builder $q, array $data) => $q
                          ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                          ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d))),
              ]);
      }
  }
  ```

  Blade view is minimal — Filament renders the table widget.

- **Acceptance Criteria:**
  - [ ] `/admin/activity` accessible only to users with `activity.view` permission
  - [ ] Lists latest 1000 activity rows
  - [ ] Filters work: by causer, subject type, date range
  - [ ] Navigation item visible in admin sidebar under "System" group

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
- **Details:**
  `AdminAuthHelper` trait mirrors the backend's `AuthHelper`:
  ```php
  trait AdminAuthHelper
  {
      protected function actingAsAdmin(): AdminUser
      {
          $user = AdminUser::factory()->create(['email_verified_at' => now()]);
          $user->assignRole('admin');
          $this->actingAs($user, 'admin');
          return $user;
      }

      protected function actingAsManager(): AdminUser { /* same pattern, role 'manager' */ }
      protected function actingAsOps(): AdminUser { /* same pattern, role 'ops' */ }
      protected function actingAsNobody(): AdminUser
      {
          $user = AdminUser::factory()->create(['email_verified_at' => now()]);
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
  - Test: unverified admin user cannot access panel (`canAccessPanel` returns false)

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

- **Acceptance Criteria:**
  - [ ] `AdminAuthHelper` trait registered in Pest
  - [ ] All four `actingAs*` helpers work
  - [ ] LoginTest passes (4 tests)
  - [ ] PermissionEnforcementTest passes (~4 tests)
  - [ ] RoleSeederTest passes (~4 tests)
  - [ ] `make admin-test` runs all auth tests green

---

## Testing Requirements

- **Pest Feature Tests:**
  - Login: unauth redirect, success, failure, unverified user blocked
  - Permissions: each role's capability matrix
  - Seeder: idempotency + correct permission sets
  - Audit log: activity rows written on model events (stub until Plan 04 resources exist)
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
2. **Password reset flow.** Filament supports password reset out of the box, but we need to configure the mail driver. Dev uses Mailpit (already running). Prod setup deferred to Plan 09.
3. **Session cookie collision.** If both backend (`finalcut.test`) and admin (`admin.finalcut.test`) set cookies on `.finalcut.test`, they could collide. Scoping admin to `.admin.finalcut.test` (Task 2) prevents this — verify by logging into both simultaneously.
