# Plan 03: Shared Boundary, Minimal Model Mirror & Base Resources

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 01 (admin app), Plan 02 (auth for scoping tests)
> **Unlocks:** Plans 04–06 (the only plans whose models this plan mirrors). Plans 07–09 add their own models as they land.

## Overview

Plan 03 establishes the substrate that later domain plans extend. Its job is to execute on five already-committed decisions, not to rediscuss them:

1. **Audit the backend service surface and document the chosen shared-code boundary.** The boundary mechanism is pre-committed to a **shared Composer package** (Option 1 — see Task 1). Task 1's deliverable is the ADR recording the decision and the service audit that informs later extraction work, not an open evaluation.
2. **Mirror only the Eloquent models needed by Plans 04–06** (Movies + Locations/Auditoriums/Seats + Showtimes). Everything else is deferred to the plan that first touches it. The spec's "16 mirrored models on day one" framing was overbuilt.
3. **Define the audit-logging strategy** — specifically, reject blanket `LogsActivity` on every model. Audit is workflow behavior, not neutral plumbing. Each domain plan declares what it logs and where the log entry originates. All backend domain service write methods take an explicit `Causer` argument (see Plan 02 Task 4).
4. **Ship the thin substrate**: `BaseResource` for CRUD permission conventions only, a `CurrencyFormatter`, a schema-level `ModelParityTest` (narrowly scoped, with cast-parity assertions enabled by the shared-package boundary), and the admin-owned `loyalty_adjustments` table with a constrained `change_type` enum.
5. **Enforce the write boundary mechanically** (Task 7). The § 2.6 rule that Filament Resources cannot mutate shared-domain models directly is enforced by phpstan + deptrac, not left as policy.

This plan does not create any Filament Resources — those begin in Plan 04.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.5, § 2.6, § 6.2, § 8
- `backend/app/Models/` and `backend/app/Services/` — canonical definitions to mirror
- `docs/architecture/DATA_MODELS.md` — schema contract
- Spec § 2.6 "write boundary" — the governance rule this plan operationalizes

---

## Tasks

### Task 1: Audit backend service surface, document shared-Composer-package boundary in ADR

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `docs/plans/admin/v1/adr-001-shared-code-boundary.md` (new)
  - `packages/shared-domain/composer.json` (new)
  - `backend/composer.json` (modify — add path repository)
  - `admin/composer.json` (modify — add path repository)
  - `docs/progress/admin-v1.md` (modify — link the ADR, add the service audit table)
- **Why first.** Every later task in this plan and Plans 04–08 depends on *how* admin calls backend code. The decision is **pre-committed**: a shared Composer package. Task 1's job is to document that decision in an ADR, produce the service audit that later plans reference when extracting each service into the package, and scaffold the empty package so Plans 04/06/07/08 can add to it without per-plan boilerplate.

  The pre-committed decision settles the contradiction between the previous draft of this plan (which rejected a synthetic `Backend\` classmap) and the originally-drafted Plan 04 / Plan 09 (which assumed that rejected approach). Plans 04 and 09 are re-authored against the shared-package namespace in their own batches; this plan hands them the scaffold.
- **Details:**

  **Step 1 — Audit.** Enumerate every service class and shared domain action that currently lives in `backend/app/Services/` and `backend/app/Http/Controllers/`. For each mutation in spec § 2.6's table, record:
  - Does a service class exist? (`backend/app/Services/*.php`)
  - If yes, does it already expose a method suitable for admin's call site, or is it coupled to HTTP request/response?
  - If no, where does the logic live today (controller, action, model observer), and what is the shape of the extraction?

  Record the audit as a table in the ADR. As of writing, backend ships: `TmdbService`, `SeatAvailabilityService`, `StripeService`, `LoyaltyService`. Movie / Showtime / GiftCard / PromoCode / Auditorium / Menu services do **not** exist yet and will each require an extraction inside the domain plan that first needs them. The audit table becomes the canonical extraction checklist referenced by Plans 04, 06, 07, and 08.

  **Step 2 — Document the chosen boundary mechanism.** The ADR records:

  - **Chosen:** **shared Composer package** at `packages/shared-domain/` with namespace `FinalCut\Domain\` (final name TBD in ADR — the rest of this plan uses `FinalCut\Domain` as the working name). Extracted domain services, Eloquent models whose writes must cross the admin/backend boundary, enums, and activity-log event classes live here. Backend retains its HTTP controllers and customer-facing services that don't need to be called by admin. Admin consumes the shared services as a normal Composer dependency, with no read-only bind mounts and no absolute-path classmaps.
  - **Wiring:** both `backend/composer.json` and `admin/composer.json` declare a path repository (`"repositories": [{ "type": "path", "url": "../packages/shared-domain", "options": { "symlink": true } }]`) and `"require": { "finalcut/domain": "*" }`. Dev-mode uses symlinks so changes to the package are picked up without reinstall. Production builds `composer install` normally — the path repo still resolves during image build because the package directory is copied before install.
  - **Why this option over the alternatives:** documented in the ADR with the audit evidence. Brief form: forces the extracted services to declare a stable public API (unlike classmap), keeps IDE indexing honest (unlike bind-mount), avoids the network overhead and duplicate-DTO cost of an internal HTTP API, works with the existing Pest test setup.
  - **Rejected non-options (recorded for history):**
    - Synthetic `Backend\` namespace plus absolute-path classmaps over a read-only bind mount — brittle (environment-sensitive), breaks IDE indexing and CI tooling, undermines the write-boundary principle by turning "admin calls backend through a stable domain service" into "admin imports backend's internals through an ad-hoc facade."
    - Internal HTTP API — evaluated, rejected on cost/overhead grounds given the same DB is reachable from both apps and transactional semantics matter for admin bulk operations.
    - Generic monorepo shared library (raw PSR-4 directory instead of a Composer package) — cheaper than (1) but loses the explicit-dependency hygiene and the ability to version the package if it ever graduates to a separate repo.

  **Step 3 — Scaffold the empty package.** Create `packages/shared-domain/` with:
  - `composer.json` declaring `"name": "finalcut/domain"`, PSR-4 autoload of `FinalCut\Domain\` → `src/`, and no implementation yet.
  - `src/` directory with a `.gitkeep` and nothing else. Services, models, enums, and activity-log events land here as Plans 04/06/07/08 extract them.
  - Path-repository wiring in `backend/composer.json` and `admin/composer.json` per Step 2. Run `composer update finalcut/domain` in both apps so `composer.lock` reflects the resolved path dependency.

  No service implementations land in this plan. Plans 04, 06, 07, 08 each include an explicit "extract X into `finalcut/domain`" task, referencing this ADR and the audit table.

  **Step 4 — Rescope spec § 2.6 and § 8.** The spec currently lists both "Composer classmap" and "facade" as candidates in § 2.6 and flags the resolution as open in § 8. Update both sections in the same PR to reference the ADR's chosen option and remove the rejected-option wording.

- **Acceptance Criteria:**
  - [ ] ADR committed at `docs/plans/admin/v1/adr-001-shared-code-boundary.md`
  - [ ] ADR records **shared Composer package** as the chosen approach — not open-ended "team will decide" language
  - [ ] Audit table enumerates every spec § 2.6 service and notes existence + extraction shape, referenced by later plans
  - [ ] ADR explicitly names the rejected alternatives (synthetic namespace + classmap, internal HTTP API, raw PSR-4 shared library) and the reason each was rejected
  - [ ] `packages/shared-domain/` exists with `composer.json`, `FinalCut\Domain\` namespace, and empty `src/`
  - [ ] `backend/composer.json` and `admin/composer.json` both declare the path repository and require `finalcut/domain`
  - [ ] `composer install` succeeds in both apps, resolving `finalcut/domain` from the path repo
  - [ ] Spec § 2.6 / § 8 updated in the same PR to reference the ADR decision
  - [ ] No `Backend\` synthetic namespace anywhere in the admin or backend codebases; `rg 'Backend\\' admin/ backend/` returns zero hits
  - [ ] No read-only bind mount of `./backend` into the admin container in any compose file

---

### Task 2: Mirror models needed by Plans 04–06 only

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Models/Movie.php`
  - `admin/app/Models/Location.php`
  - `admin/app/Models/Auditorium.php`
  - `admin/app/Models/Seat.php`
  - `admin/app/Models/Showtime.php`
- **Scope note.** The previous draft listed 16 models, including several (`Genre`, `CastMember`, `AuditoriumSection`, `PromoCode`) that do not exist as backend tables today — they're JSON columns, absent, or still to be designed. Mirroring every model up front bets heavily on duplication before any Resource exists. Instead, this plan mirrors **only the five models that Plans 04–06 actually consume**, and each later plan adds its own mirror as a sub-task:
  - Plan 07 adds `User`, `Booking`, `BookingSeat`, `BookingFoodItem`, `GiftCard`, `LoyaltyAdjustment` (already created in Task 4 below).
  - Plan 08 adds `MenuItem`, `PromoCode` (if the table is created there), `CalendarEvent`.
- **Details:**
  Before writing any model, re-read the matching `backend/app/Models/*.php` and `backend/database/migrations/*` to confirm table name, column list, casts, and relationships. Do not invent columns. Do not duplicate business logic (observers, validation, invariants) — that lives in backend/shared services per § 2.6.

  Each admin model:
  - Uses the same `$table` name
  - Declares `$fillable` and `$casts` that match backend exactly
  - Declares only the relationships admin Resources need for reads and select dropdowns
  - May add Filament-friendly accessors (`display_title`, `formatted_price`) — accessors only, never mutators that change write semantics
  - **Does not** use `LogsActivity` by default. Audit logging is addressed workflow-by-workflow in Task 3.

  Example `Movie` model (illustrative — validate columns against the current migration before committing):
  ```php
  namespace App\Models;

  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\HasMany;

  class Movie extends Model
  {
      protected $table = 'movies';

      protected $fillable = [
          'title', 'slug', 'tagline', 'synopsis', 'runtime',
          'rating', 'release_date', 'status', 'tmdb_id',
          'poster_url', 'backdrop_url', 'trailer_key',
          'cast', 'tmdb_enriched_at',
      ];

      protected $casts = [
          'release_date' => 'date',
          'tmdb_enriched_at' => 'datetime',
          'cast' => 'array',
          'rating' => 'decimal:1',
      ];

      public function showtimes(): HasMany
      {
          return $this->hasMany(Showtime::class);
      }

      public function getDisplayTitleAttribute(): string
      {
          return $this->title . ($this->status === 'coming_soon' ? ' (Coming Soon)' : '');
      }
  }
  ```

  The `User` model is **not** mirrored in this plan. Plan 07 mirrors it when the customers surface lands, and limits its fillable to the loyalty fields Plan 07 writes through `LoyaltyService` (`loyalty_tier`, `loyalty_points`, `premier_expiry`).

- **Acceptance Criteria:**
  - [ ] Five models exist: `Movie`, `Location`, `Auditorium`, `Seat`, `Showtime`
  - [ ] Each model's `$table`, `$fillable`, `$casts` match the corresponding backend model exactly (verified by diff)
  - [ ] Relationships declared only as needed by Plan 04/05/06 reads
  - [ ] No `LogsActivity` trait attached to any model in this task
  - [ ] No business-logic duplication from backend (observers, validators, invariants)
  - [ ] Plans 07 and 08 reference this task's scope note and add their own model mirrors
  - [ ] `ModelParityTest` (Task 6) covers all five models

---

### Task 3: Audit-logging strategy (workflow-aware, not blanket)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `docs/plans/admin/v1/audit-logging-strategy.md` (new)
  - `docs/progress/admin-v1.md` (modify — link the strategy doc)
- **Why a dedicated task.** The previous draft attached `LogsActivity` to "every mutable model" while also saying business logic must not be duplicated. These are in tension. Activity logging is behavior: when it fires, what it captures, and what identifiers appear on the activity row all affect what admins see and what compliance can rely on. Scattering `LogsActivity` across mirrored admin models produces one or more of: (a) duplicate logs if both backend and admin persist the same record, (b) missing logs if the write happens through a service that bypasses Eloquent events, (c) logs that describe admin-side persistence events but not the meaningful domain action (e.g. "Movie updated" instead of "TMDB enrichment triggered").

  Plan 02 already wires auth-event audit and `LogsActivity` on `AdminUser`. That is correct because `AdminUser` is admin-owned and the relevant events are model-level. Shared-table mutations need a different pattern.
- **Details:**

  Establish three categories of audit surface. Each domain plan picks the category that fits each of its write flows:

  1. **Service-emitted activity** — preferred for any mutation that goes through a domain service (spec § 2.6 list). The service (backend or shared package) writes the activity row itself with a workflow-specific description (`movie.enrich_triggered`, `showtime.cancelled`, `loyalty.points_adjusted`, `gift_card.voided`). This is the only pattern that captures the *domain action* rather than the persistence side-effect, and it is the only pattern that works when a single workflow mutates multiple rows in one transaction.

  2. **Admin-owned model events** — `LogsActivity` trait is appropriate on models whose *only* writer is admin (`AdminUser` today, `LoyaltyAdjustment` added in Task 4). For these, model-level events and domain actions coincide.

  3. **No audit** — read models, join-table models, and models whose writes always flow through a service already logging at the service level. Attaching `LogsActivity` here just duplicates the service row.

  The strategy doc declares:
  - The three categories above and how to choose between them
  - That Plan 03 does **not** attach `LogsActivity` to any mirrored shared-table model
  - That each of Plans 04–08 must declare, per write flow, which category applies and what the activity row looks like (description, subject, causer resolver, properties)
  - That the global `/admin/activity` page (Plan 02 Task 8) is the consumer; its filter list is only as useful as the `log_name` / `description` discipline in domain plans

  Also tighten Plan 02's `default_auth_driver = 'admin'` wiring: service-emitted rows written from the shared package / backend must use the same causer resolver. If the chosen boundary mechanism in Task 1 means backend code cannot reach admin's auth context, the strategy doc records the workaround (passing the `AdminUser` id through the service call explicitly).

- **Acceptance Criteria:**
  - [ ] Strategy doc committed, linked from the admin plan index
  - [ ] Three categories defined with a rule for choosing between them
  - [ ] No `LogsActivity` trait on any mirrored shared-table model in Task 2
  - [ ] Plans 04–08 (in their existing overviews) reference this strategy and commit to emitting audit rows from the service layer for § 2.6 mutations
  - [ ] Causer-resolver behaviour documented for both in-admin and service-emitted rows

---

### Task 4: `loyalty_adjustments` table + model (enum-backed change_type)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/database/migrations/YYYY_MM_DD_HHMMSS_create_loyalty_adjustments_table.php`
  - `admin/app/Models/LoyaltyAdjustment.php`
  - `admin/app/Enums/LoyaltyAdjustmentType.php`
- **Details:**
  Admin-owned table (not in backend schema) tracking manual loyalty adjustments separate from the customer-facing earn/redeem ledger. Plan 07 populates it via `LoyaltyService` (extracted in Plan 07).

  Migration:
  ```php
  Schema::create('loyalty_adjustments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->foreignId('admin_user_id')->constrained('admin_users');
      $table->integer('points_delta'); // signed: negative for deductions
      $table->text('reason');
      $table->string('change_type');   // constrained by LoyaltyAdjustmentType enum in application code
      $table->timestamps();
      $table->index(['user_id', 'created_at']);
  });
  ```

  PHP 8.1 backed enum — this is the governance your critique asked for. Free-form strings in an audit-sensitive table drift into arbitrary history within a quarter.

  ```php
  namespace App\Enums;

  enum LoyaltyAdjustmentType: string
  {
      case PointsCorrection = 'points_correction';
      case TierUpgrade       = 'tier_upgrade';
      case TierRevoke        = 'tier_revoke';
      case GoodwillCredit    = 'goodwill_credit';
      case FraudClawback     = 'fraud_clawback';
  }
  ```

  Model uses the enum cast, so any write of an unknown value throws at the persistence boundary — not months later when a report queries it.

  ```php
  class LoyaltyAdjustment extends Model
  {
      use LogsActivity; // admin-owned model, category (2) per Task 3

      protected $fillable = ['user_id', 'admin_user_id', 'points_delta', 'reason', 'change_type'];

      protected $casts = [
          'change_type' => LoyaltyAdjustmentType::class,
      ];

      public function user(): BelongsTo         { return $this->belongsTo(User::class); }
      public function adminUser(): BelongsTo    { return $this->belongsTo(AdminUser::class); }

      public function getActivitylogOptions(): LogOptions
      {
          return LogOptions::defaults()
              ->logOnly(['user_id', 'admin_user_id', 'points_delta', 'change_type'])
              ->dontSubmitEmptyLogs();
      }
  }
  ```

  DB-level enum or check constraint is deliberately not used — we want to add new `change_type` values without a migration when the business case appears. Application-level enum keeps the discipline without paying the migration cost.

- **Acceptance Criteria:**
  - [ ] Migration creates `loyalty_adjustments` with documented columns and the `(user_id, created_at)` index
  - [ ] `LoyaltyAdjustmentType` enum declared with the five starter values above
  - [ ] Model casts `change_type` to the enum — persisting an unknown string raises at the boundary
  - [ ] Model uses `LogsActivity` (category 2 from Task 3) with `logOnly` restricted to the meaningful columns
  - [ ] Foreign keys to `users` (customer) and `admin_users`
  - [ ] `points_delta` is a signed integer

---

### Task 5: `BaseResource` abstract class (CRUD permission conventions only)

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Resources/BaseResource.php` (new)
  - `admin/app/Filament/Support/CurrencyFormatter.php` (new)
  - `admin/app/Filament/Support/TimestampColumns.php` (new trait)
- **Scope (narrower than the previous draft).** `BaseResource` handles **only** the CRUD permission convention `{prefix}.{view,create,update,delete}` plus a few shared column helpers. It does **not** cover custom resource actions. Any non-CRUD permission — `movies.trigger_enrich`, `showtimes.cancel`, `gift_cards.void`, `loyalty.adjust_points`, `loyalty.adjust_tier` — must declare its own permission binding on the action, page, or relation-manager where it lives. This is called out so consumers don't over-assume what the abstraction handles.
- **Details:**

  ```php
  abstract class BaseResource extends Resource
  {
      /** Permission prefix for CRUD checks, e.g., 'movies', 'showtimes'. */
      protected static ?string $permissionPrefix = null;

      public static function canViewAny(): bool
      {
          return auth()->user()?->can(static::crudPermission('view')) ?? false;
      }

      public static function canCreate(): bool
      {
          return auth()->user()?->can(static::crudPermission('create')) ?? false;
      }

      public static function canEdit(Model $record): bool
      {
          return auth()->user()?->can(static::crudPermission('update')) ?? false;
      }

      public static function canDelete(Model $record): bool
      {
          return auth()->user()?->can(static::crudPermission('delete')) ?? false;
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

  `CurrencyFormatter` — static helpers for cents-to-display conversion, consumed by form fields and table columns. Reuses the cents-only rule from backend CLAUDE.md.

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

  `TimestampColumns` trait exposes `::standardTimestamps()` returning the conventional `created_at` / `updated_at` sortable-and-toggleable columns. Kept separate from `BaseResource` so non-Resource contexts (relation managers, custom pages) can reuse it.

- **Acceptance Criteria:**
  - [ ] `BaseResource` wires `canViewAny` / `canCreate` / `canEdit` / `canDelete` through `{prefix}.{view,create,update,delete}` only
  - [ ] `crudPermission()` throws `LogicException` for non-CRUD verbs — caught by a test
  - [ ] Subclasses without `$permissionPrefix` throw `LogicException` clearly
  - [ ] `CurrencyFormatter::format(1299) === '$12.99'`
  - [ ] `CurrencyFormatter::parse('$12.99') === 1299`
  - [ ] `TimestampColumns::standardTimestamps()` returns two `TextColumn` instances
  - [ ] Inline comment in `BaseResource` points at the audit-logging strategy doc (Task 3) and states that custom actions declare their own permission bindings

---

### Task 6: `ModelParityTest` — schema accessibility tripwire only

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/tests/Feature/ModelParityTest.php` (new)
- **Scope.** This test is a cheap, high-signal tripwire for schema drift. It asserts two things:
  1. Every DB column on the tables backing the mirrored models is reachable through the admin model's fillable / guarded / timestamp / primary-key set.
  2. Every mirrored admin model's `getCasts()` matches the corresponding `FinalCut\Domain\Models\*` cast map exactly. Enabled by the shared-package boundary (Task 1) — admin tests can load the domain models via normal Composer autoload and compare them directly.

  **It still does not verify:**
  - that relationships match (different scope between admin-needed joins and customer-needed joins is legitimate)
  - that observers / validation rules / invariants exist on the admin side (they shouldn't — those live in shared services per § 2.6)
  - behavioral parity of any kind

  If a model uses `$guarded = []` or `$guarded = ['id']`, the accessibility check passes trivially — the test message in those cases states plainly that accessibility was assumed, not proven. The strategy doc (Task 3) and the write-boundary enforcement task (Task 7) are the real guardrails against behavioural drift; this test is the schema + cast check.
- **Details:**

  ```php
  use App\Models\Movie;
  use App\Models\Location;
  use App\Models\Auditorium;
  use App\Models\Seat;
  use App\Models\Showtime;
  use Illuminate\Support\Facades\Schema;

  it('admin models expose every column on their backing tables', function () {
      $models = [Movie::class, Location::class, Auditorium::class, Seat::class, Showtime::class];

      foreach ($models as $modelClass) {
          $model = new $modelClass();
          $table = $model->getTable();
          $columns = Schema::getColumnListing($table);
          $fillable = $model->getFillable();
          $guarded = $model->getGuarded();
          $alwaysAccessible = ['id', 'created_at', 'updated_at', 'deleted_at'];

          foreach ($columns as $column) {
              $accessible = in_array($column, $fillable, true)
                  || in_array('*', $guarded, true)          // $guarded = ['*'] → nothing mass-assignable
                  || $guarded === []                        // $guarded = [] → everything accessible (weak signal)
                  || in_array($column, $alwaysAccessible, true);

              expect($accessible)->toBeTrue(
                  "Model {$modelClass} does not expose column '{$column}' on table '{$table}'. "
                  . "Add it to \$fillable, or document why it is intentionally hidden."
              );
          }
      }
  });

  it('every fillable attribute on mirrored admin models corresponds to a real column', function () {
      // Reverse direction — catches fillable entries that outlived the column.
      $models = [Movie::class, Location::class, Auditorium::class, Seat::class, Showtime::class];

      foreach ($models as $modelClass) {
          $model = new $modelClass();
          $columns = Schema::getColumnListing($model->getTable());

          foreach ($model->getFillable() as $fillable) {
              expect($columns)->toContain(
                  $fillable,
                  "Model {$modelClass} declares fillable '{$fillable}' with no matching column on '{$model->getTable()}'."
              );
          }
      }
  });

  it('admin mirrored models cast the same columns as their shared-domain counterparts', function () {
      // Enabled by the shared-package boundary (Task 1). Drift here means a write
      // through an admin Resource persists a different type than a write through
      // the shared domain service — the exact class of silent regression the
      // ModelParityTest is meant to catch.
      $pairs = [
          [\App\Models\Movie::class,       \FinalCut\Domain\Models\Movie::class],
          [\App\Models\Location::class,    \FinalCut\Domain\Models\Location::class],
          [\App\Models\Auditorium::class,  \FinalCut\Domain\Models\Auditorium::class],
          [\App\Models\Seat::class,        \FinalCut\Domain\Models\Seat::class],
          [\App\Models\Showtime::class,    \FinalCut\Domain\Models\Showtime::class],
      ];

      foreach ($pairs as [$adminClass, $domainClass]) {
          $adminCasts  = (new $adminClass())->getCasts();
          $domainCasts = (new $domainClass())->getCasts();

          ksort($adminCasts);
          ksort($domainCasts);

          expect($adminCasts)->toEqual(
              $domainCasts,
              "Cast drift between {$adminClass} and {$domainClass}. "
              . "Admin resources will persist a different type than shared-domain services."
          );
      }
  });
  ```

  As new models are mirrored in Plans 07/08, each plan must extend the `$models` array and the cast-parity `$pairs` array here as part of its own acceptance criteria. The test's class list is part of the substrate contract.

- **Acceptance Criteria:**
  - [ ] Test iterates the five models from Task 2
  - [ ] Forward check: every DB column is accessible via fillable, `guarded = ['*']` or `[]`, or timestamp/PK
  - [ ] Reverse check: every fillable corresponds to a real column
  - [ ] Cast-parity check: `admin $model->getCasts()` equals `FinalCut\Domain\Models\* ->getCasts()` for every mirrored pair (sorted by key before comparison)
  - [ ] Failure messages name the model, table, and column (or cast key)
  - [ ] Test passes when backend + admin schemas align
  - [ ] Test fails cleanly when a column is added to backend but not admin (manual smoke: add a column, run the test, expect failure)
  - [ ] Test fails cleanly when an admin cast diverges from the shared-domain cast (manual smoke: change one admin cast, run the test, expect failure)
  - [ ] File header comment states the test's scope: schema accessibility + cast parity, not relationship/behavioral parity, and points at Task 3's strategy doc and Task 7's write-boundary enforcement as the behavioral guardrails
  - [ ] Plans 07 and 08 reference this task in their own model-mirror tasks and extend both the `$models` array and the cast-parity `$pairs` array

---

### Task 7: Write-boundary enforcement (phpstan + deptrac)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/phpstan.neon` (new or modify — add banned method calls)
  - `admin/deptrac.yaml` (new — architectural layer rules)
  - `admin/composer.json` (modify — add `phpstan/phpstan`, `qossmic/deptrac` as dev deps)
  - `Makefile` (modify — add `make phpstan` and `make deptrac` targets)
  - `.github/workflows/admin-ci.yml` (modify — require both to pass on admin PRs)
- **Why this task exists.** Spec § 2.6's write-boundary rule says Filament Resources must route mutations through shared-domain services. Every plan 04–08 repeats the rule. **Nothing enforces it.** A Filament action written as `->action(fn ($record) => $record->update(['status' => 'archived']))` bypasses the entire service layer, bypasses audit logging, and is not caught by existing tests — Layer A Resource tests mock the service facade, so a direct Eloquent write alongside a mocked service call still passes. One narrow regression test in Plan 04 Task 7 asserts `Model::delete()` isn't called by the stock `DeleteAction`, but that only covers one specific pattern.

  This task adds a mechanical enforcement layer: phpstan's `banned_method_calls` rule catches direct writes at parse time, and deptrac catches cross-layer dependency violations. Both run in CI; both must pass before admin PRs merge.
- **Details:**

  **phpstan configuration** (`admin/phpstan.neon`):
  ```yaml
  includes:
      - vendor/spaze/phpstan-disallowed-calls/extension.neon

  parameters:
      level: 6
      paths:
          - app/

      disallowedMethodCalls:
          - method:
                - 'FinalCut\Domain\Models\*::save()'
                - 'FinalCut\Domain\Models\*::update()'
                - 'FinalCut\Domain\Models\*::delete()'
                - 'FinalCut\Domain\Models\*::forceDelete()'
                - 'FinalCut\Domain\Models\*::restore()'
                - 'FinalCut\Domain\Models\*::increment()'
                - 'FinalCut\Domain\Models\*::decrement()'
                - 'FinalCut\Domain\Models\*::push()'
                - 'FinalCut\Domain\Models\*::touch()'
            message: 'Shared-domain models must not be mutated directly. Route the write through a FinalCut\Domain\Services\* class. See spec § 2.6 and docs/plans/admin/v1/03-shared-models-and-base-resources.md Task 7.'
            allowIn:
                - app/Filament/Resources/BaseResource.php    # legitimate resource-level helpers (none today, reserved)
                - tests/*                                     # test setup / factories

      disallowedStaticCalls:
          - method:
                - 'FinalCut\Domain\Models\*::create()'
                - 'FinalCut\Domain\Models\*::insert()'
                - 'FinalCut\Domain\Models\*::firstOrCreate()'
                - 'FinalCut\Domain\Models\*::updateOrCreate()'
                - 'FinalCut\Domain\Models\*::destroy()'
            message: 'Shared-domain model writes must route through FinalCut\Domain\Services. See Task 7.'
            allowIn:
                - tests/*
  ```

  The `spaze/phpstan-disallowed-calls` extension handles the `disallowedMethodCalls` / `disallowedStaticCalls` syntax and is the standard PHPStan extension for exactly this use case.

  **deptrac configuration** (`admin/deptrac.yaml`):
  ```yaml
  deptrac:
      paths:
          - app/
          - ../packages/shared-domain/src/

      layers:
          - name: AdminResources
            collectors:
                - type: classLike
                  value: 'App\\Filament\\Resources\\.*'
          - name: AdminServices
            collectors:
                - type: classLike
                  value: 'App\\Services\\.*'
          - name: AdminPages
            collectors:
                - type: classLike
                  value: 'App\\Filament\\(Pages|Widgets|RelationManagers)\\.*'
          - name: SharedDomainServices
            collectors:
                - type: classLike
                  value: 'FinalCut\\Domain\\Services\\.*'
          - name: SharedDomainModels
            collectors:
                - type: classLike
                  value: 'FinalCut\\Domain\\Models\\.*'

      ruleset:
          AdminResources:
              - AdminServices
              - SharedDomainServices
              - SharedDomainModels    # reads only — writes caught by phpstan
          AdminPages:
              - AdminServices
              - SharedDomainServices
              - SharedDomainModels
          AdminServices:
              - SharedDomainServices
              - SharedDomainModels
          SharedDomainServices:
              - SharedDomainModels
          SharedDomainModels: []
  ```

  deptrac's role is architectural: it catches `AdminResources` importing a `SharedDomainServices` class that was meant to be internal, or an `AdminPages` class skipping the service layer. It doesn't catch write calls at the method level — that's phpstan's job. They complement each other.

  **Escape valve.** If a future Filament action legitimately needs direct model access (e.g., a bulk `touch()` on read-only metadata with no business invariants), the escape is a `// @phpstan-ignore-next-line` comment paired with an inline `// WRITE_BOUNDARY_EXEMPT: <reason>` comment. The exemption is reviewed at PR time. Arbitrary `@phpstan-ignore` lines without the `WRITE_BOUNDARY_EXEMPT` sibling comment are flagged by a separate lint step (grep-based) as an escape-valve abuse.

  **CI wiring.**
  - `make phpstan` runs `./admin/vendor/bin/phpstan analyse -c admin/phpstan.neon` inside the admin container.
  - `make deptrac` runs `./admin/vendor/bin/deptrac analyse --config-file=admin/deptrac.yaml`.
  - Both are added to `.github/workflows/admin-ci.yml` as required checks on admin-scoped PRs (any PR touching `admin/**` or `packages/shared-domain/**`).

  **Migration path for existing code.** There is no existing admin code to retrofit when this task lands — Plan 03 runs before any Filament Resource exists. Plans 04–08 write their Resources against the phpstan-enforced boundary from day one.

- **Acceptance Criteria:**
  - [ ] `admin/phpstan.neon` exists with `disallowedMethodCalls` / `disallowedStaticCalls` covering all mutation methods on `FinalCut\Domain\Models\*`
  - [ ] `admin/deptrac.yaml` exists with the five layers above and the documented ruleset
  - [ ] `make phpstan` and `make deptrac` targets run both tools inside the admin container
  - [ ] Admin CI workflow requires both to pass on any PR touching `admin/**` or `packages/shared-domain/**`
  - [ ] A deliberately-introduced violation (e.g., add `$movie->save()` in a throwaway test Resource) fails `make phpstan` with the configured error message
  - [ ] A deliberately-introduced cross-layer violation (e.g., `AdminResources` directly importing `SharedDomainModels` for a write) fails `make deptrac`
  - [ ] Escape-valve documented: the `WRITE_BOUNDARY_EXEMPT` sibling-comment convention and the lint step that flags bare `@phpstan-ignore` lines in Resource/Page files
  - [ ] Task references spec § 2.6 and Plans 04–08 as consumers of the enforcement

---

## Testing Requirements

- **Pest Feature Tests:**
  - `ModelParityTest` — schema accessibility tripwire + cast parity (Task 6)
  - `BaseResourceTest` — permission wiring fires for CRUD verbs, and `crudPermission()` rejects non-CRUD verbs
  - `CurrencyFormatterTest` — round-trip parse/format correctness
  - `LoyaltyAdjustmentTest` — persisting a valid enum value succeeds; persisting an unknown `change_type` string raises at the cast boundary

- **Static analysis (Task 7):**
  - phpstan with disallowed-calls — direct mutations on shared-domain models fail the build
  - deptrac with layered ruleset — cross-layer architectural violations fail the build

- **Out of scope here:**
  - Resource-level audit verification — lands in Plans 04–08 per the audit-logging strategy (Task 3)
  - Relationship / behavioral parity beyond casts — see Task 6 scope note

## Dependencies Map

```
Task 1 (ADR + audit + shared-package scaffold) ← prerequisite for every other task in this
                                                  plan and for the shared-package namespace
                                                  that Plans 04–08 consume
Task 3 (audit-logging strategy)               ← needed before Task 2, because it tells Task 2
                                                  not to attach LogsActivity by default
Task 2 (mirror models for Plans 04–06)        ← needs Tasks 1 and 3
Task 4 (loyalty_adjustments + enum)           ← parallel to Task 2
Task 5 (BaseResource + helpers)               ← parallel to Task 2
Task 6 (ModelParityTest w/ cast parity)       ← needs Task 2 + Task 1 (cast-parity
                                                  assertion requires shared-domain models
                                                  to be loadable via Composer autoload)
Task 7 (phpstan + deptrac enforcement)        ← needs Task 1 (shared-package namespace
                                                  must exist for the ban patterns to match
                                                  anything); runs ahead of Plan 04 so the
                                                  first Filament Resource lands on an
                                                  already-enforced boundary
```

## Risks & Open Questions

1. **Extraction cost lands in domain plans.** Plans 04, 06, 07, 08 each absorb the cost of extracting at least one service from backend controllers into `packages/shared-domain/`. Each plan must add an explicit "extract X into `finalcut/domain`" task and a corresponding audit-log description per Task 3. The audit table from Task 1 is the canonical extraction checklist.
2. **Service-emitted activity causer resolution.** Plan 02 Task 4's `default_auth_driver = 'admin'` covers admin-side auto-resolution, but shared-domain services run in both admin HTTP requests and backend scheduler/webhook requests. The audit-log strategy (Task 3) and Plan 02 Task 4 together require **explicit `Causer` arguments on every domain service write method** — auto-resolution is a convenience, not the contract. Plans 04/06/07/08 enforce this uniformly in their service signatures.
3. **Path-repository symlink mechanics in CI.** Running `composer install --no-dev --optimize-autoloader` in a production image requires the path repo's target directory to exist before install. Plan 09's Dockerfile copies `packages/shared-domain/` into the build context ahead of `composer install`. If Plan 09 drifts on this, admin's production build breaks — call out in Plan 09 review.
4. **Model mirror scope creep.** There will be pressure to "just add the other models while we're here." Resist. Each plan extends the mirror when it needs to. Task 2's acceptance criteria explicitly limit this plan to five models.
5. **`ModelParityTest` false confidence with `$guarded = []`.** The accessibility check reports a passing signal even when the admin model declares nothing. Accept this; the reverse check, cast-parity check, and Task 7's write-boundary enforcement compensate. If the false-confidence gap ever bites, add a warning assertion that flags models with empty `$fillable` *and* empty `$guarded`.
6. **Escape-valve abuse on Task 7's phpstan ban.** The `WRITE_BOUNDARY_EXEMPT` comment convention relies on reviewer attention. If exemptions proliferate, add a CI check that counts `WRITE_BOUNDARY_EXEMPT` occurrences across `admin/app/Filament/**` and fails if it exceeds a threshold — not pretty, but effective.
