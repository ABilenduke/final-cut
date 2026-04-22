# Plan 03: Admin Base Resource Class & Loyalty Adjustments Table

> **Priority:** Must Have
> **Complexity:** S
> **Depends On:** Plan 01 (admin panel), Plan 02 (auth + permissions)
> **Unlocks:** Plans 04–08 (domain Resources extend BaseResource)

## Overview

Plan 03 establishes the thin substrate that every later domain plan consumes:

1. A `BaseResource` abstract class at `backend/app/Filament/Resources/BaseResource.php` that wires Filament's CRUD ability checks to the spatie/permission abilities seeded in Plan 02.
2. A `FormatsCurrency` trait + `TimestampColumns` helper so every Resource handles cents-to-display conversion and `created_at` / `updated_at` columns the same way.
3. The admin-owned `loyalty_adjustments` table, backed by a PHP enum for `change_type`, consumed by Plan 07 when the loyalty actions ship.

No Filament Resources are created in this plan — those begin in Plan 04. The Eloquent models that admin Resources will use (Movie, Location, Auditorium, Seat, Showtime, Booking, User, etc.) already exist in `backend/app/Models/` from the customer API; there is no mirror to create, no parity test to maintain, no cross-app boundary to police.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 admin-to-domain-logic boundary
- `backend/app/Models/` — existing Eloquent models (reused directly by Filament Resources)
- `backend/database/migrations/` — where the new `loyalty_adjustments` migration lands

---

## Tasks

### Task 1: `BaseResource` abstract class (CRUD permission conventions only)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Resources/BaseResource.php` (new)
- **Scope note.** `BaseResource` handles **only** the CRUD permission convention `{prefix}.{view,create,update,delete}`. It does **not** cover custom resource actions. Any non-CRUD permission — `movies.trigger_enrich`, `showtimes.cancel`, `gift_cards.void`, `loyalty.adjust_points`, `loyalty.adjust_tier` — must declare its own permission binding on the action, page, or relation-manager where it lives.
- **Details:**

  ```php
  namespace App\Filament\Resources;

  use Filament\Resources\Resource;
  use Illuminate\Database\Eloquent\Model;

  abstract class BaseResource extends Resource
  {
      /** Permission prefix for CRUD checks, e.g., 'movies', 'showtimes'. */
      protected static ?string $permissionPrefix = null;

      public static function canViewAny(): bool
      {
          return auth('admin')->user()?->can(static::crudPermission('view')) ?? false;
      }

      public static function canCreate(): bool
      {
          return auth('admin')->user()?->can(static::crudPermission('create')) ?? false;
      }

      public static function canEdit(Model $record): bool
      {
          return auth('admin')->user()?->can(static::crudPermission('update')) ?? false;
      }

      public static function canDelete(Model $record): bool
      {
          return auth('admin')->user()?->can(static::crudPermission('delete')) ?? false;
      }

      /**
       * CRUD-only permission resolver. Custom actions must bind their own permission
       * at the call site — do not extend this to arbitrary verbs.
       */
      protected static function crudPermission(string $action): string
      {
          if (! in_array($action, ['view', 'create', 'update', 'delete'], true)) {
              throw new \LogicException(
                  "BaseResource::crudPermission only handles CRUD verbs. "
                  . "Custom action '{$action}' must declare its own permission at the call site."
              );
          }
          $prefix = static::$permissionPrefix
              ?? throw new \LogicException(static::class . ' must declare $permissionPrefix');
          return "{$prefix}.{$action}";
      }
  }
  ```

  Explicitly calling `auth('admin')` (rather than the default guard) keeps the permission checks correct even if a future change alters `defaults.guard`. Plan 02 Task 2 leaves the default guard unchanged, so this is belt-and-braces.

- **Acceptance Criteria:**
  - [ ] `BaseResource` wires `canViewAny` / `canCreate` / `canEdit` / `canDelete` through `{prefix}.{view,create,update,delete}` only
  - [ ] `crudPermission()` throws `LogicException` for non-CRUD verbs — caught by a test
  - [ ] Subclasses without `$permissionPrefix` throw `LogicException` clearly
  - [ ] Every `auth()` call resolves via the `admin` guard explicitly

---

### Task 2: `FormatsCurrency` trait and `TimestampColumns` helper

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Concerns/FormatsCurrency.php` (new)
  - `backend/app/Filament/Concerns/TimestampColumns.php` (new)
- **Details:**
  Cents-only monetary convention per backend CLAUDE.md. Filament Resources and custom pages reuse these helpers rather than duplicating the conversion math.

  ```php
  namespace App\Filament\Concerns;

  trait FormatsCurrency
  {
      public static function centsToDisplay(?int $cents, string $currency = 'USD'): string
      {
          if ($cents === null) return '—';
          return '$' . number_format($cents / 100, 2);
      }

      public static function displayToCents(?string $display): ?int
      {
          if ($display === null || $display === '') return null;
          $numeric = preg_replace('/[^\d.]/', '', $display);
          return (int) round(((float) $numeric) * 100);
      }
  }
  ```

  ```php
  namespace App\Filament\Concerns;

  use Filament\Tables\Columns\TextColumn;

  trait TimestampColumns
  {
      public static function standardTimestamps(): array
      {
          return [
              TextColumn::make('created_at')
                  ->dateTime('M j, Y g:i A')
                  ->sortable()
                  ->toggleable(isToggledHiddenByDefault: true),
              TextColumn::make('updated_at')
                  ->dateTime('M j, Y g:i A')
                  ->sortable()
                  ->toggleable(isToggledHiddenByDefault: true),
          ];
      }
  }
  ```

  Traits instead of a static utility class so consumers can mix them into Resources and custom pages without awkward static imports.

- **Acceptance Criteria:**
  - [ ] `FormatsCurrency::centsToDisplay(1299) === '$12.99'`
  - [ ] `FormatsCurrency::displayToCents('$12.99') === 1299`
  - [ ] `FormatsCurrency::centsToDisplay(null) === '—'`
  - [ ] `TimestampColumns::standardTimestamps()` returns exactly two `TextColumn` instances, both toggleable and hidden by default
  - [ ] Both traits live under `App\Filament\Concerns\*` so every Resource can `use` them

---

### Task 3: `loyalty_adjustments` table, model, and enum

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/database/migrations/YYYY_MM_DD_HHMMSS_create_loyalty_adjustments_table.php` (new)
  - `backend/app/Models/LoyaltyAdjustment.php` (new)
  - `backend/app/Enums/LoyaltyAdjustmentType.php` (new)
  - `backend/database/factories/LoyaltyAdjustmentFactory.php` (new)
- **Details:**
  Admin-authored manual loyalty adjustments, tracked separately from the customer-facing earn/redeem ledger. Plan 07 populates this table via the existing `backend/app/Services/LoyaltyService.php` when the "Adjust points" and "Upgrade to Premier" actions ship.

  Migration:
  ```php
  Schema::create('loyalty_adjustments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->foreignId('admin_user_id')->constrained('admin_users');
      $table->integer('points_delta');  // signed: negative for deductions
      $table->text('reason');
      $table->string('change_type');    // constrained by LoyaltyAdjustmentType enum in application code
      $table->timestamps();
      $table->index(['user_id', 'created_at']);
  });
  ```

  Enum — application-level constraint, not DB-level, so new change types can be added without a migration:
  ```php
  namespace App\Enums;

  enum LoyaltyAdjustmentType: string
  {
      case PointsCorrection = 'points_correction';
      case TierUpgrade      = 'tier_upgrade';
      case TierRevoke       = 'tier_revoke';
      case GoodwillCredit   = 'goodwill_credit';
      case FraudClawback    = 'fraud_clawback';
  }
  ```

  Model — uses `LogsActivity` because this is an admin-owned table whose *only* writer is admin. The activity-log row coincides with the business event.
  ```php
  namespace App\Models;

  use App\Enums\LoyaltyAdjustmentType;
  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Spatie\Activitylog\LogOptions;
  use Spatie\Activitylog\Traits\LogsActivity;

  class LoyaltyAdjustment extends Model
  {
      use HasFactory, LogsActivity;

      protected $fillable = [
          'user_id', 'admin_user_id', 'points_delta', 'reason', 'change_type',
      ];

      protected $casts = [
          'change_type' => LoyaltyAdjustmentType::class,
      ];

      public function user(): BelongsTo
      {
          return $this->belongsTo(User::class);
      }

      public function adminUser(): BelongsTo
      {
          return $this->belongsTo(AdminUser::class);
      }

      public function getActivitylogOptions(): LogOptions
      {
          return LogOptions::defaults()
              ->logOnly(['user_id', 'admin_user_id', 'points_delta', 'change_type'])
              ->dontSubmitEmptyLogs();
      }
  }
  ```

  Factory for tests:
  ```php
  namespace Database\Factories;

  use App\Enums\LoyaltyAdjustmentType;
  use App\Models\AdminUser;
  use App\Models\LoyaltyAdjustment;
  use App\Models\User;
  use Illuminate\Database\Eloquent\Factories\Factory;

  class LoyaltyAdjustmentFactory extends Factory
  {
      protected $model = LoyaltyAdjustment::class;

      public function definition(): array
      {
          return [
              'user_id' => User::factory(),
              'admin_user_id' => AdminUser::factory(),
              'points_delta' => $this->faker->numberBetween(-500, 500),
              'reason' => $this->faker->sentence(),
              'change_type' => $this->faker->randomElement(LoyaltyAdjustmentType::cases())->value,
          ];
      }
  }
  ```

- **Acceptance Criteria:**
  - [ ] Migration creates `loyalty_adjustments` with documented columns and the `(user_id, created_at)` index
  - [ ] `LoyaltyAdjustmentType` enum declared with the five starter cases
  - [ ] Model casts `change_type` to the enum — persisting an unknown string raises at the boundary
  - [ ] Model uses `LogsActivity` with `logOnly` restricted to meaningful columns
  - [ ] Foreign keys to `users` (customer) and `admin_users`
  - [ ] `points_delta` is a signed integer
  - [ ] Factory works and generates only valid enum values

---

### Task 4: Tests

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/tests/Unit/Admin/FormatsCurrencyTest.php` (new)
  - `backend/tests/Unit/Admin/BaseResourceTest.php` (new)
  - `backend/tests/Feature/Admin/LoyaltyAdjustmentTest.php` (new)
- **Details:**

  **FormatsCurrencyTest** — round-trip parse/format correctness:
  - `centsToDisplay(1299) === '$12.99'`
  - `centsToDisplay(0) === '$0.00'`
  - `centsToDisplay(null) === '—'`
  - `displayToCents('$12.99') === 1299`
  - `displayToCents('1299.00') === 129900` (intentional — see test comment: parses as a dollar amount, not cents)
  - `displayToCents(null) === null`
  - Round-trip: `displayToCents(centsToDisplay(1299)) === 1299`

  **BaseResourceTest** — permission wiring and error paths:
  - A Resource that declares `$permissionPrefix = 'movies'` and `canViewAny()` returns true for a user with `movies.view`
  - A Resource that declares `$permissionPrefix = 'movies'` and `canCreate()` returns false for a user with only `movies.view`
  - A Resource missing `$permissionPrefix` throws `LogicException` with a message naming the Resource class
  - `BaseResource::crudPermission('trigger_enrich')` throws `LogicException` (guard against drift from Plan 04 which might try to funnel custom actions through it)

  **LoyaltyAdjustmentTest** — enum cast + activity log:
  - Creating a `LoyaltyAdjustment` with a valid enum-backed `change_type` persists correctly
  - Creating a `LoyaltyAdjustment` with an unknown string `change_type` raises at the cast boundary (PHP throws `ValueError` because `LoyaltyAdjustmentType::from('unknown')` fails)
  - Creating a `LoyaltyAdjustment` writes a row to `activity_log` attributed to the adjustment's `adminUser`
  - Updating a `LoyaltyAdjustment` writes a second `activity_log` row with the before/after diff limited to `logOnly` columns
  - The foreign key to `users` cascades on delete (deleting the customer deletes the adjustment row)

- **Acceptance Criteria:**
  - [ ] All three test files exist under `backend/tests/`
  - [ ] FormatsCurrencyTest covers at least the seven cases above
  - [ ] BaseResourceTest covers permission wiring for all four CRUD verbs plus the two error paths
  - [ ] LoyaltyAdjustmentTest covers enum cast success, enum cast failure, and activity-log attribution
  - [ ] `make admin-test` runs these tests green

---

## Testing Requirements

- **Pest Feature Tests:**
  - `LoyaltyAdjustmentTest` — enum cast + activity-log attribution
- **Pest Unit Tests:**
  - `FormatsCurrencyTest` — cents ↔ display round-trip
  - `BaseResourceTest` — permission wiring + error paths
- **Out of scope here:**
  - Filament Resource CRUD tests — land with each Resource in Plans 04–08
  - Service-layer tests — land with each service extraction in Plans 04/06/07/08

## Dependencies Map

```
Task 1 (BaseResource) ← foundational
Task 2 (traits) ← parallel to Task 1
Task 3 (loyalty_adjustments) ← parallel; depends on Plan 02 (AdminUser + users models)
Task 4 (tests) ← needs Tasks 1, 2, 3
```

## Risks & Open Questions

1. **BaseResource assumes spatie/permission is available.** Plan 02 Task 3 installs it. If Plan 02 ships without the `HasRoles` trait on `AdminUser`, every `->can()` call fails silently and every Resource shows empty — Task 4's BaseResourceTest catches this by asserting a positive permission check.
2. **`LoyaltyAdjustmentType` enum churn.** Adding new cases (e.g., `birthday_bonus`, `partner_promotion`) should be common. No migration required, but every new case needs a Plan 07 surface (UI option + test coverage). Document in the Plan 07 acceptance criteria.
3. **`LogsActivity` on `LoyaltyAdjustment` vs service-emitted activity.** Plan 07 writes adjustments through `LoyaltyService::adjustPoints()`. The service also emits its own activity row describing the *workflow* ("Adjusted points for user X by +100"). `LoyaltyAdjustment`'s `LogsActivity` trait then emits a second row describing the *persistence* event. Two rows is acceptable — the workflow row is human-readable for the activity page, the persistence row captures the before/after diff. Plan 07 documents the expected row pair in its test suite so future reviewers understand why there are two.
