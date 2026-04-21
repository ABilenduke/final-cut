# Plan 03: Shared Eloquent Models & Base Resources

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (admin app), Plan 02 (auth for scoping tests)
> **Unlocks:** Plans 04–09 (every domain plan needs models)

## Overview

Mirror the backend's Eloquent models inside the admin app, wire the shared-code mechanism so admin Resources can call backend domain services (per spec § 2.6), create the `BaseResource` abstract class that every future Filament Resource extends, add a `loyalty_adjustments` admin-owned table that Plan 07 will populate, and ship the `ModelParityTest` tripwire that catches schema drift between backend and admin migrations.

This plan does **not** create any Filament Resources — those begin in Plan 04. It establishes the reusable substrate.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.5, § 2.6, § 6.2
- `backend/app/Models/` — canonical model definitions to mirror
- `backend/app/Services/` — existing domain services admin will call
- `docs/architecture/DATA_MODELS.md` — schema contract

---

## Tasks

### Task 1: Resolve shared-code mechanism for backend services

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/composer.json` (modify)
  - `docker-compose.yml` (modify — mount backend into admin container)
  - `admin/app/Services/Backend/` (new directory with facades — one file per service)
- **Details:**
  This resolves spec § 8 open question #1.

  **Chosen approach:** PSR-4 facade wrappers in `admin/app/Services/Backend/` that delegate to the backend service classes via a shared Composer classmap. This keeps the explicit dependency visible in admin code (grep-able) while reusing backend implementations.

  Step 1 — mount backend into admin container:
  ```yaml
  # docker-compose.yml
  admin:
    volumes:
      - ./admin:/app
      - ./backend:/backend:ro    # read-only mount of backend source
      - admin-vendor:/app/vendor
  ```

  Step 2 — add classmap to admin composer.json:
  ```json
  {
    "autoload": {
      "psr-4": {
        "App\\": "app/",
        "Backend\\": "/backend/app/"
      },
      "classmap": [
        "/backend/app/Services/"
      ]
    }
  }
  ```

  Run `composer dump-autoload` after modifying.

  Step 3 — create thin facades. One facade per backend service, in `admin/app/Services/Backend/`:

  ```php
  // admin/app/Services/Backend/MovieService.php
  namespace App\Services\Backend;

  use Backend\App\Services\MovieService as BackendMovieService;

  class MovieService
  {
      public function __construct(private BackendMovieService $inner) {}

      public function create(array $attributes): \App\Models\Movie
      {
          $backendMovie = $this->inner->create($attributes);
          return \App\Models\Movie::find($backendMovie->id); // re-fetch via admin model
      }

      public function update(int $id, array $attributes): \App\Models\Movie { /* ... */ }
      public function delete(int $id): void { /* ... */ }
      public function triggerEnrich(int $id): void { /* dispatches movies:enrich job */ }
  }
  ```

  The facade translates between admin and backend model instances (since their classes differ) and gives us a single place to mock in tests.

  Repeat for: `ShowtimeService`, `LoyaltyService`, `GiftCardService`, `PromoCodeService`, `AuditoriumService`, `MenuService`.

  If a backend service does not yet exist (e.g., `ShowtimeService`), note it in the facade's docblock with a `@todo Extract from BookingController in Plan 06` comment.

- **Acceptance Criteria:**
  - [ ] docker-compose mounts backend into admin container as read-only
  - [ ] `admin/composer.json` includes backend classmap
  - [ ] `composer dump-autoload` succeeds
  - [ ] Seven facade classes exist in `App\Services\Backend\*`
  - [ ] Each facade has a pass-through constructor taking the backend service
  - [ ] Missing backend services documented with `@todo` comments

---

### Task 2: Mirror core domain models

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `admin/app/Models/Movie.php`
  - `admin/app/Models/Genre.php`
  - `admin/app/Models/CastMember.php`
  - `admin/app/Models/Location.php`
  - `admin/app/Models/Auditorium.php`
  - `admin/app/Models/AuditoriumSection.php`
  - `admin/app/Models/Seat.php`
  - `admin/app/Models/Showtime.php`
  - `admin/app/Models/Booking.php`
  - `admin/app/Models/BookingSeat.php`
  - `admin/app/Models/BookingFoodItem.php`
  - `admin/app/Models/User.php` (customer user, read-only from admin)
  - `admin/app/Models/MenuItem.php`
  - `admin/app/Models/PromoCode.php`
  - `admin/app/Models/GiftCard.php`
  - `admin/app/Models/CalendarEvent.php`
- **Details:**
  Each admin model:
  - Uses the same `$table` as the backend canonical model
  - Declares the same `$fillable`, `$casts`, and relationships
  - Adds Filament-friendly accessors (`display_title`, `formatted_price`, etc.)
  - Uses `LogsActivity` trait from `spatie/laravel-activitylog` to auto-log mutations
  - Does **not** duplicate business logic (observers, validation) — that lives in backend services

  Example `Movie` model:
  ```php
  namespace App\Models;

  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsToMany;
  use Illuminate\Database\Eloquent\Relations\HasMany;
  use Spatie\Activitylog\LogOptions;
  use Spatie\Activitylog\Traits\LogsActivity;

  class Movie extends Model
  {
      use LogsActivity;

      protected $table = 'movies';
      protected $fillable = [
          'title', 'slug', 'tagline', 'synopsis', 'runtime', 'rating',
          'release_date', 'status', 'tmdb_id', 'poster_url', 'backdrop_url',
          'trailer_key', 'cast', 'tmdb_enriched_at',
      ];
      protected $casts = [
          'release_date' => 'date',
          'tmdb_enriched_at' => 'datetime',
          'cast' => 'array',
          'rating' => 'decimal:1',
      ];

      public function genres(): BelongsToMany
      {
          return $this->belongsToMany(Genre::class, 'movie_genre');
      }

      public function showtimes(): HasMany
      {
          return $this->hasMany(Showtime::class);
      }

      public function getActivitylogOptions(): LogOptions
      {
          return LogOptions::defaults()
              ->logOnly(['title', 'status', 'tmdb_id', 'rating'])
              ->logOnlyDirty()
              ->dontSubmitEmptyLogs();
      }

      public function getDisplayTitleAttribute(): string
      {
          return $this->title . ($this->status === 'coming_soon' ? ' (Coming Soon)' : '');
      }
  }
  ```

  Verify each model against `backend/app/Models/*` before committing. Do not invent columns. Match casts exactly — if backend casts `price_standard` to `int`, admin must too.

  `User` model is read-only from admin — no LogsActivity, no fillable beyond `loyalty_tier`, `loyalty_points`, `premier_expiry` (because Plan 07 writes those).

- **Acceptance Criteria:**
  - [ ] All 16 models mirror their backend counterparts
  - [ ] Columns, casts, relationships match exactly
  - [ ] `LogsActivity` trait attached to every mutable model
  - [ ] `getActivitylogOptions()` configured per model to avoid noisy logs
  - [ ] No business logic duplicated from backend
  - [ ] All models referenced in ModelParityTest (Task 4)

---

### Task 3: `loyalty_adjustments` table + model

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/database/migrations/YYYY_MM_DD_HHMMSS_create_loyalty_adjustments_table.php`
  - `admin/app/Models/LoyaltyAdjustment.php`
- **Details:**
  Admin-owned table (not in backend schema) that tracks manual loyalty point adjustments separate from the customer-facing earn/redeem ledger. Plan 07 populates it.

  Migration:
  ```php
  Schema::create('loyalty_adjustments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->foreignId('admin_user_id')->constrained('admin_users');
      $table->integer('points_delta'); // can be negative
      $table->text('reason');
      $table->string('change_type'); // 'points', 'tier_upgrade', 'tier_revoke'
      $table->timestamps();
  });
  ```

  Model:
  ```php
  class LoyaltyAdjustment extends Model
  {
      protected $fillable = ['user_id', 'admin_user_id', 'points_delta', 'reason', 'change_type'];

      public function user(): BelongsTo
      { return $this->belongsTo(User::class); }

      public function adminUser(): BelongsTo
      { return $this->belongsTo(AdminUser::class); }
  }
  ```

- **Acceptance Criteria:**
  - [ ] Migration creates `loyalty_adjustments` table
  - [ ] Foreign keys to `users` (customer) and `admin_users`
  - [ ] `points_delta` is signed integer (negative for deductions)
  - [ ] `change_type` enum-like string
  - [ ] Model declares relationships to both user tables

---

### Task 4: BaseResource abstract class

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/BaseResource.php` (new abstract class)
  - `admin/app/Filament/Support/CurrencyFormatter.php` (helper)
  - `admin/app/Filament/Support/TimestampColumns.php` (trait)
- **Details:**
  Every future Filament Resource extends `BaseResource` to inherit:

  - Default `navigationGroup` convention
  - Consistent table column patterns (created_at, updated_at as default sortable, toggleable columns)
  - Currency formatter binding
  - Permission check wiring tied to the resource's permission prefix

  ```php
  abstract class BaseResource extends Resource
  {
      /** Permission prefix, e.g., 'movies', 'showtimes' */
      protected static ?string $permissionPrefix = null;

      public static function canViewAny(): bool
      {
          return auth()->user()?->can(static::permission('view')) ?? false;
      }

      public static function canCreate(): bool
      {
          return auth()->user()?->can(static::permission('create')) ?? false;
      }

      public static function canEdit(Model $record): bool
      {
          return auth()->user()?->can(static::permission('update')) ?? false;
      }

      public static function canDelete(Model $record): bool
      {
          return auth()->user()?->can(static::permission('delete')) ?? false;
      }

      protected static function permission(string $action): string
      {
          $prefix = static::$permissionPrefix ?? throw new \LogicException(static::class . ' must declare $permissionPrefix');
          return "{$prefix}.{$action}";
      }

      public static function getTimestampColumns(): array
      {
          return [
              TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
              TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
          ];
      }
  }
  ```

  `CurrencyFormatter` — static methods for cents-to-display conversion (`1299 → "$12.99"`) consumed in form fields and table columns. Reuses the cents-only rule from backend CLAUDE.md.

  ```php
  class CurrencyFormatter
  {
      public static function format(int $cents, string $currency = 'USD'): string
      {
          return '$' . number_format($cents / 100, 2);
      }

      public static function parse(string $display): int
      {
          return (int) round(((float) preg_replace('/[^\d.]/', '', $display)) * 100);
      }
  }
  ```

- **Acceptance Criteria:**
  - [ ] `BaseResource` abstract class exists
  - [ ] `canViewAny`, `canCreate`, `canEdit`, `canDelete` wire through `{prefix}.{action}` permissions
  - [ ] Subclasses without `$permissionPrefix` throw `LogicException` clearly
  - [ ] `CurrencyFormatter::format(1299) === '$12.99'`
  - [ ] `CurrencyFormatter::parse('$12.99') === 1299`

---

### Task 5: ModelParityTest

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/ModelParityTest.php` (new)
- **Details:**
  The cheap tripwire for schema drift. Introspects the database schema for every shared model and asserts the admin model exposes every column the backend model does.

  ```php
  use App\Models\Movie;
  use App\Models\Location;
  use App\Models\Auditorium;
  // ... import all shared models

  it('admin models expose every column of their backing tables', function () {
      $models = [
          Movie::class, Genre::class, CastMember::class,
          Location::class, Auditorium::class, AuditoriumSection::class, Seat::class,
          Showtime::class,
          Booking::class, BookingSeat::class, BookingFoodItem::class,
          User::class, MenuItem::class, PromoCode::class, GiftCard::class,
          CalendarEvent::class,
      ];

      foreach ($models as $modelClass) {
          $model = new $modelClass();
          $table = $model->getTable();

          $columns = Schema::getColumnListing($table);
          $fillable = $model->getFillable();
          $guarded = $model->getGuarded();
          $allAttributes = array_merge($fillable, ['id', 'created_at', 'updated_at']);

          foreach ($columns as $column) {
              // Column must be either fillable, guarded, a timestamp, or the primary key
              $accessible = in_array($column, $fillable)
                  || in_array('*', $guarded)
                  || in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at']);

              expect($accessible)->toBeTrue("Model {$modelClass} does not expose column '{$column}' on table '{$table}'");
          }
      }
  });
  ```

  Also assert the reverse: every fillable attribute on the admin model corresponds to a real column.

  Helpful test output — when the test fails, the failure message must name the model, the missing/extra column, and the table. This saves the engineer debugging time.

- **Acceptance Criteria:**
  - [ ] Test iterates every shared model
  - [ ] Asserts every DB column is accessible via fillable/guarded/timestamps/PK
  - [ ] Asserts every fillable is a real column
  - [ ] Descriptive failure messages naming model + column
  - [ ] Test passes when backend + admin schemas align
  - [ ] Test fails cleanly when a column is added to backend but not admin (manual test: add column, run test, expect failure)

---

### Task 6: Backend service extraction checklist (documentation task)

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `docs/progress/admin-v1.md` (modify — add a "Backend service extraction" checklist)
  - `admin/app/Services/Backend/README.md` (new)
- **Details:**
  Document which backend services exist and which need to be extracted. The admin facades created in Task 1 will point at these. Plans 04, 06, 07, 08 each pick up one extraction if needed.

  Services that definitely exist in backend today (reference `backend/app/Services/`): `TmdbService`, `StripeService`, `SeatAvailabilityService`, `FakeStripeService`.

  Services that likely need extraction (to be verified during Plan 04-08 execution):
  - `MovieService` — probably needs extracting from `MovieController` (create/update paths)
  - `ShowtimeService` — create/cancel logic lives in controllers today
  - `LoyaltyService` — point-earning logic in booking flow; extract the adjust-points path
  - `GiftCardService` — void and balance lookup likely in controller
  - `PromoCodeService` — validation logic may already be a class
  - `AuditoriumService` — probably does not exist yet
  - `MenuService` — simple CRUD; facade can delegate directly to Eloquent

  Add a checklist to the progress journal:
  ```markdown
  ### Backend Service Extraction Tracking
  - [ ] MovieService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] ShowtimeService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] LoyaltyService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] GiftCardService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] PromoCodeService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] AuditoriumService — audited on YYYY-MM-DD; exists / needs extraction
  - [ ] MenuService — audited on YYYY-MM-DD; exists / needs extraction
  ```

  Each plan 04-08 updates its row during execution.

- **Acceptance Criteria:**
  - [ ] Progress journal lists all seven services with audit status
  - [ ] `admin/app/Services/Backend/README.md` explains the facade pattern
  - [ ] Engineers know to audit backend before authoring each domain plan's write tasks

---

## Testing Requirements

- **Pest Feature Tests:**
  - `ModelParityTest` — the crown tripwire (Task 5)
  - `BaseResourceTest` — ensures permission wiring fires correctly (can mock a concrete Resource)
  - `CurrencyFormatterTest` — round-trip parse/format correctness

## Dependencies Map

```
Task 1 (shared code mechanism) ← foundational
Task 2 (mirror models) ← needs Task 1
Task 3 (loyalty_adjustments) ← parallel to Task 2
Task 4 (BaseResource) ← parallel to Tasks 2, 3
Task 5 (ModelParityTest) ← needs Tasks 2, 3
Task 6 (service extraction docs) ← parallel
```

## Risks & Open Questions

1. **Composer classmap + bind-mount performance.** Mounting backend as read-only into admin plus classmap scanning could slow autoload generation in Docker. If `composer dump-autoload` exceeds 30 seconds, consider switching to PSR-4 with explicit namespace `Backend\` → `/backend/app/`.
2. **Model drift on rare columns.** Backend may add columns via ad-hoc migrations that never hit the spec. ModelParityTest catches this on the next CI run; document the remediation (add the column to the admin model, re-run test).
3. **Circular dependency.** Admin facades call backend services which may reference backend models. The `Backend\App\Models\*` namespace must not leak into admin code — facades always translate to admin models before returning. Enforce with a static analysis rule if Psalm/PHPStan is set up in Plan 09.
