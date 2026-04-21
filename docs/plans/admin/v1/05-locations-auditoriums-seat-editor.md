# Plan 05: Locations, Auditoriums & Seat Editor

> **Priority:** Must Have
> **Complexity:** XL
> **Depends On:** Plan 03 (BaseResource, FormatsCurrency, TimestampColumns)
> **Unlocks:** Plan 06 (Showtime resource needs auditoriums), Plan 08 (menu is location-scoped)

## Overview

Build the `LocationResource` and `AuditoriumResource`, add the `cleanup_minutes` column to auditoriums (consumed by Plan 06's conflict detection), and ship the seat configuration tooling in two tracks: an MVP seat-generator form that covers ~90% of real configurations, and a visual seat editor that ships after the MVP if budget allows (otherwise deferred). The visual editor builds on the MVP data model so no work is wasted.

All mutations route through `App\Services\AuditoriumService`, created in this plan and stored in `backend/app/Services/AuditoriumService.php` alongside the existing `TmdbService`, `SeatAvailabilityService`, `StripeService`, `LoyaltyService`, and `MovieService` (Plan 04). Every write method accepts an optional `?AdminUser $actor = null` for audit attribution — Filament pages pass `auth('admin')->user()`; customer controllers pass `null`. The service writes `activity_log` rows when `$actor` is non-null and skips admin activity attribution otherwise.

Filament Resources consume `App\Models\Location`, `App\Models\Auditorium`, `App\Models\AuditoriumSection`, and `App\Models\Seat` directly — there is no admin-side model mirror, no shared package, no cross-app boundary. Service and models live in the same codebase and autoload via PSR-4.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 admin-to-domain-logic boundary, § 5 Plan 05
- `docs/architecture/DATA_MODELS.md` — Location, Auditorium, Seat, AuditoriumSection
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — BaseResource, FormatsCurrency, TimestampColumns
- `docs/plans/admin/v1/04-movie-catalog-management.md` — reference pattern for service + Resource structure
- `backend/database/migrations/*_create_auditoriums_table.php` — schema reference
- `backend/app/Models/Auditorium.php` — canonical model

---

## Tasks

### Task 1: Add `cleanup_minutes` to auditoriums

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/database/migrations/*_create_auditoriums_table.php` (modify in place — see environment-state condition below)
  - `backend/app/Models/Auditorium.php` (modify — add to fillable)
- **Details:**
  **Migration strategy (environment-state conditional):**

  The project convention is "migrations in place until launch." That rule applies here *only* if the existing auditoriums migration has not been applied to any shared environment (staging, QA, other developer machines) that you cannot rebase freely. Concretely:

  - **Safe to edit in place:** local dev only, no staging schema derived from the current migration, no teammate has a database at the current schema state that can't be dropped.
  - **Must add a new migration instead:** any shared environment has already run the current migration, or another developer holds data under the old schema. In that case, add `*_add_cleanup_minutes_to_auditoriums_table.php` and document the deviation in the progress journal.

  Check before editing: `git log -- backend/database/migrations/*_create_auditoriums_table.php` to see whether the file has been modified since the last shared deploy, and coordinate in the progress journal. Do not silently assume the in-place edit is safe just because CLAUDE.md lists the convention.

  Edit (or add) the migration to include:
  ```php
  $table->unsignedSmallInteger('cleanup_minutes')->default(20)->after('name');
  ```

  Update `App\Models\Auditorium`'s `$fillable` array.

  Update backend seeder (`DatabaseSeeder` / `AuditoriumSeeder`) to populate `cleanup_minutes => 20` for seeded auditoriums.

  Update the `AuditoriumResource` (Task 4 below) to expose this field in the form.

- **Acceptance Criteria:**
  - [ ] `cleanup_minutes` column added to auditoriums (default 20)
  - [ ] `App\Models\Auditorium` fillable includes the new column
  - [ ] Seeder produces auditoriums with the column populated
  - [ ] Existing backend test suite still green after the schema change
  - [ ] Plan 06 can reference `auditorium.cleanup_minutes`

---

### Task 2: Create `AuditoriumService` in `backend/app/Services/`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/AuditoriumService.php` (new)
  - `backend/app/Exceptions/AuditoriumSeatRegenerationBlockedException.php` (new)
  - `backend/tests/Unit/AuditoriumServiceTest.php` (new)
- **Details:**
  `AuditoriumService` owns write orchestration for locations, auditoriums, sections, and seats. The customer API and the admin panel (Tasks 3–5) both call it. Both call sites pass an optional `?AdminUser $actor`.

  ```php
  namespace App\Services;

  use App\Exceptions\AuditoriumSeatRegenerationBlockedException;
  use App\Models\AdminUser;
  use App\Models\Auditorium;
  use App\Models\Location;
  use App\Models\Seat;

  class AuditoriumService
  {
      public function createAuditorium(Location $location, array $attributes, ?AdminUser $actor = null): Auditorium;
      public function updateAuditorium(Auditorium $auditorium, array $attributes, ?AdminUser $actor = null): Auditorium;
      public function deleteAuditorium(Auditorium $auditorium, ?AdminUser $actor = null): void;
      public function generateSeats(Auditorium $auditorium, array $config, ?AdminUser $actor = null, bool $force = false): void; // Task 5
      public function updateSectionConfig(Auditorium $auditorium, array $sections, ?AdminUser $actor = null): void;
      public function markSeatUnavailable(Seat $seat, ?string $unavailable_reason = null, ?AdminUser $actor = null): void;
      public function markSeatAvailable(Seat $seat, ?AdminUser $actor = null): void;

      /**
       * Non-destructive batch update for existing seats — section reassignment and
       * unavailability flags. Promoted to MVP because the "reassign row A from Premium
       * to Accessible after opening" case is expected within the first operational
       * month and the full regenerate path is blocked by future showtimes. This
       * method does not add/remove seats; it only modifies section_id and
       * unavailable_at for existing seat rows.
       *
       * @param array<int, array{seat_id: int, section_id?: int, unavailable_at?: ?\DateTimeInterface}> $seatUpdates
       */
      public function updateSeatBatch(Auditorium $auditorium, array $seatUpdates, ?AdminUser $actor = null): void;
  }
  ```

  **`updateSeatBatch` contract (MVP):**

  - Operates on **existing** seat rows only. Does not create or delete seats. Does not change row labels or seat numbers.
  - Accepts an array of per-seat patches keyed by `seat_id`. Each patch may include `section_id` (reassignment) and/or `unavailable_at` (availability toggle).
  - Runs the whole batch inside `DB::transaction`. Any invalid seat ID or invalid section ID fails the entire batch — no partial updates.
  - Does **not** require the regeneration-safety checks (future showtimes, active bookings). Seat IDs are preserved, so existing bookings continue to point at the same physical seat; only the seat's section membership or availability flag changes. A Premium → Accessible reassignment updates the pricing tier for *future* showtimes and does not retroactively re-price sold tickets (those are locked at booking time).
  - Emits one activity row per seat changed when `$actor` is non-null, linked to the auditorium as the subject, with `causedBy($actor)` and the before/after diff in `properties`. When `$actor` is null, no activity rows are written.
  - Backend test coverage: happy-path reassignment, happy-path unavailability, invalid seat ID fails entire batch, existing showtimes/bookings do not block (this is the key MVP promise — regeneration blocks, batch does not).

  The corresponding Filament UI for `updateSeatBatch` is a simple table action on `AuditoriumResource` (shipped in Task 4) — **not** the visual seat editor. Task 6's visual editor (Could Have) is a better UX on top of the same service method but is not required for MVP.

  Seat generation (`generateSeats`) takes a config like:
  ```php
  [
      'rows' => 10,
      'seats_per_row' => 12,
      'section_map' => [
          ['rows' => ['A', 'B'], 'section_id' => $premiumSectionId, 'type' => 'premium'],
          ['rows' => ['C', 'D', 'E', 'F', 'G', 'H'], 'section_id' => $standardSectionId, 'type' => 'standard'],
          ['rows' => ['I', 'J'], 'section_id' => $accessibleSectionId, 'type' => 'accessible'],
      ],
      'unavailable_seats' => ['A3', 'A4', 'J11'], // aisles, gaps
  ]
  ```

  **Regeneration safety contract (critical — do not soften):**

  `generateSeats` is destructive: it deletes all existing seats for the auditorium before rebuilding. To protect downstream data, the service must refuse regeneration when any of the following are true, unless `force = true` is explicitly passed (and even then, the force path is reserved for future use — Plan 05 does **not** expose it to the admin UI):

  1. Any `Showtime` for this auditorium has `start_time >= now()` (future or currently-running shows).
  2. Any `Booking` with `status IN ('confirmed', 'held', 'refund_pending')` references a seat in this auditorium. (Past bookings tied to past showtimes are acceptable — they identify seats by ID and do not break if the ID is preserved, but a regeneration reshuffles seat IDs, so any non-terminal booking blocks the operation.)
  3. Any seat in this auditorium is currently held (seat holds from in-flight purchase sessions).

  On refusal, throw a typed exception (`AuditoriumSeatRegenerationBlockedException`) carrying the blocking reason and counts (e.g., "3 future showtimes, 12 active bookings"). The UI in Task 5 renders this as a blocking error — not a warning — and points staff at the showtime management page to cancel or reschedule first.

  **Transactional guarantee:** the entire operation (refusal check → delete → rebuild) runs inside `DB::transaction`. On any failure at any step, the transaction rolls back and the previous seat layout remains fully intact. This is a first-class promise the UI depends on (Task 5).

  **Idempotency with no existing dependencies:** when the auditorium has no seats yet, or only has seats with no references, the call is idempotent — repeated runs produce the same layout.

  **Section ↔ seat source-of-truth contract:**

  Sections (`auditorium_sections`) and seats (`seats`) are stored in separate tables. Sections are the master record for pricing multipliers and section names. Seats reference sections by `section_id`. This plan defines the following cascade rules:

  - `updateSectionConfig` (section rename, multiplier change, display order) **does not** touch existing seats. Multiplier changes take effect immediately for all future pricing lookups because seats reference the section record.
  - Deleting a section is refused while any seat references it — admin must reassign affected seats first (via regeneration in Task 5 or the visual editor in Task 6).
  - `generateSeats` is the only path that assigns seats to sections. Adding a new section does not auto-populate seats into it; staff must regenerate or use the visual editor to move seats.
  - When regeneration is blocked by the rules above, section edits still work (they don't cascade), so there is no scenario where a section change is stuck behind a regeneration block.

  Document this contract in the `AuditoriumService` class docblock so future maintainers see it before changing behavior.

  **Extraction principles (consistent with Plan 04):**
  - Validation stays at the HTTP / Filament boundary — the service accepts pre-validated arrays and enforces only domain invariants (unique slug at save time, regeneration blockers, batch validity).
  - Mutation and orchestration move into the service — create / update / delete the auditorium, sync section repeaters, bulk-insert seats, emit activity-log rows.
  - Customer API controllers (if any touch these tables) pass `null` for `$actor` because customer-side writes are not admin-attributed.

  Backend tests: service create/update/delete paths, seat generation produces correct row × col count, section assignment correct, unavailable seats flagged, **regeneration refused when future showtimes exist**, **regeneration refused when active bookings exist**, **rollback on mid-generation failure leaves prior layout intact**, section rename does not modify seats, `updateSeatBatch` happy paths, `updateSeatBatch` invalid-input rollback.

- **Acceptance Criteria:**
  - [ ] `App\Services\AuditoriumService` exists with all documented methods
  - [ ] Every write method signature accepts `?AdminUser $actor = null` as the last parameter
  - [ ] Class docblock captures the section↔seat cascade contract and the `updateSeatBatch` vs `generateSeats` distinction
  - [ ] `generateSeats` produces correct seat matrix
  - [ ] `generateSeats` throws `AuditoriumSeatRegenerationBlockedException` when future showtimes, active bookings, or held seats exist (unless `force = true`)
  - [ ] Blocking exception carries structured blocker counts (future showtimes, active bookings, held seats)
  - [ ] `updateSeatBatch` operates on existing seat rows only, runs in a single transaction, fails the whole batch on any invalid input, and is **not** blocked by future showtimes or active bookings
  - [ ] `updateSeatBatch` emits one activity row per changed seat with `causedBy($actor)` and a before/after diff when `$actor` is non-null
  - [ ] When `$actor` is null on any write, no `activity_log` row is written
  - [ ] Section config persisted correctly; section edits do not modify existing seats
  - [ ] Section deletion refused while any seat references it
  - [ ] Unavailable seats respected
  - [ ] Transactional rollback on failure leaves the previous seat layout fully intact
  - [ ] Backend test coverage green, including refusal paths, rollback, `updateSeatBatch` happy paths, and `updateSeatBatch` invalid-input rollback

---

### Task 3: LocationResource

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/LocationResource.php` (new)
  - `backend/app/Filament/Resources/LocationResource/Pages/ListLocations.php` (new)
  - `backend/app/Filament/Resources/LocationResource/Pages/CreateLocation.php` (new)
  - `backend/app/Filament/Resources/LocationResource/Pages/EditLocation.php` (new)
  - `backend/app/Filament/Resources/LocationResource/Pages/ViewLocation.php` (new)
- **Details:**
  Standard Filament Resource extending `BaseResource` with `$permissionPrefix = 'locations'`, registered under the "Operations" navigation group.

  ```php
  namespace App\Filament\Resources;

  use App\Models\Location;

  class LocationResource extends BaseResource
  {
      protected static ?string $model = Location::class;
      protected static ?string $permissionPrefix = 'locations';
      protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?int $navigationSort = 20;

      public static function form(Form $form): Form { /* see below */ }
      public static function table(Table $table): Table { /* see below */ }

      public static function getRelations(): array
      {
          return [RelationManagers\AuditoriumsRelationManager::class];
      }

      public static function getPages(): array
      {
          return [
              'index' => Pages\ListLocations::route('/'),
              'create' => Pages\CreateLocation::route('/create'),
              'view' => Pages\ViewLocation::route('/{record}'),
              'edit' => Pages\EditLocation::route('/{record}/edit'),
          ];
      }
  }
  ```

  **Form schema:**
  ```php
  Section::make('Identity')->schema([
      TextInput::make('name')->required()->maxLength(255),
      TextInput::make('slug')->required()->unique(ignoreRecord: true),
  ])->columns(2),

  Section::make('Contact')->schema([
      TextInput::make('phone')->tel(),
      TextInput::make('email')->email(),
  ])->columns(2),

  Section::make('Address')->schema([
      TextInput::make('street'),
      TextInput::make('city'),
      TextInput::make('state'),
      TextInput::make('postal_code'),
      TextInput::make('country')->default('US'),
  ])->columns(2),

  Section::make('Geography')->schema([
      Select::make('timezone')
          ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
          ->default(fn () => config('app.default_location_timezone') ?? config('app.timezone'))
          ->required()
          ->searchable()
          ->helperText('Timezone for this theater. Drives all showtime display and scheduling math.'),
      TextInput::make('latitude')->numeric()->step(0.000001),
      TextInput::make('longitude')->numeric()->step(0.000001),
  ])->columns(3),
  ```

  **Timezone default — deliberately not hardcoded.** Do not ship with `America/Los_Angeles` (or any arbitrary geographic default); it leaks the original developer's context into production data the first time staff click through without changing it. Instead:

  1. Pull from `config('app.default_location_timezone')` (add this key to `config/app.php` — blank by default).
  2. Fall back to `config('app.timezone')` (Laravel's global app timezone, usually `UTC`).
  3. Require the admin to make a conscious selection on create — `required()` is deliberate so `UTC` is only saved when an admin explicitly chose it.

  **Table:**
  ```php
  TextColumn::make('name')->searchable()->sortable(),
  TextColumn::make('city'),
  TextColumn::make('timezone'),
  TextColumn::make('auditoriums_count')->counts('auditoriums')->label('Auditoriums'),
  ...TimestampColumns::standardTimestamps(),
  ```

  Override `CreateLocation::handleRecordCreation`, `EditLocation::handleRecordUpdate`, and the table's `DeleteAction::make()` with `->using()` to route all mutations through the service:

  ```php
  // CreateLocation.php
  protected function handleRecordCreation(array $data): Model
  {
      // Location CRUD is straightforward — service wraps write + activity log.
      return app(\App\Services\AuditoriumService::class)
          ->createLocation($data, auth('admin')->user()); // add if needed, else direct Eloquent is acceptable per § 2.6
  }
  ```

  **Scope note.** `Location` is largely pure-content (name, address, contact). Per spec § 2.6, direct Eloquent is acceptable for pure-content writes with no invariants. This plan routes Location writes through the service anyway for consistency with the rest of the Resource, *and* to get audit-log attribution for free. Do not treat the service routing as load-bearing; the stronger routing rule applies to auditoriums and seats.

- **Acceptance Criteria:**
  - [ ] Resource registers under "Operations" navigation group
  - [ ] Form validates required fields
  - [ ] Timezone select is searchable, `required()`, and defaults via config — no hardcoded geographic value
  - [ ] Table shows auditorium count
  - [ ] AuditoriumsRelationManager attached (Task 4)
  - [ ] Permission gating works per role (admin full, manager create/update + view, ops read-only)

---

### Task 4: AuditoriumResource via relation manager on Location

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/LocationResource/RelationManagers/AuditoriumsRelationManager.php` (new)
  - `backend/app/Filament/Resources/AuditoriumResource.php` (new — standalone resource too)
  - `backend/app/Filament/Resources/AuditoriumResource/Pages/*` (list, create, edit, view)
- **Details:**
  Auditoriums can be accessed two ways:
  1. **Via Location view page** — relation manager with inline edit (most common path for staff)
  2. **Standalone resource** at `/auditoriums` — for bulk operations and schedule planner linking (Plan 06)

  **Form source of truth (no drift):** Both surfaces render the *same* form schema. Extract it into a shared static method — `AuditoriumResource::getFormSchema(): array` — and have both `AuditoriumResource::form()` and `AuditoriumsRelationManager::form()` call it. The relation manager is a convenience view on top of the shared schema; the standalone resource is the canonical surface for anything that isn't an inline quick-edit. Do not define the form independently in two places. Any field added to one surface lands in both automatically.

  **Shared form schema:**
  ```php
  TextInput::make('name')->required(),
  TextInput::make('slug')->required(),
  TextInput::make('cleanup_minutes')->numeric()->default(20)
      ->helperText('Minutes between showtimes for cleaning / turnover. Drives scheduling conflict detection.'),
  Textarea::make('notes')->nullable(),

  Section::make('Sections')->schema([
      Repeater::make('sections')
          ->relationship('sections')
          ->schema([
              TextInput::make('name')->required()->placeholder('Standard / Premium / Accessible'),
              TextInput::make('price_multiplier')->numeric()->step(0.01)->default(1.0)
                  ->helperText('1.0 = base price; 1.25 = 25% premium; 0.85 = 15% discount'),
              TextInput::make('display_order')->numeric()->default(0),
          ])
          ->columns(3)
          ->reorderable('display_order')
          ->defaultItems(3)
          ->collapsible(),
  ]),
  ```

  **Service routing (same pattern as Plan 04).** `CreateAuditorium::handleRecordCreation` and `EditAuditorium::handleRecordUpdate` call `AuditoriumService::createAuditorium` / `updateAuditorium`, passing `auth('admin')->user()` as the actor. The relation manager's inline create/edit actions do the same via Filament's `->using()` hook. The section repeater's persistence delegates to `updateSectionConfig` so section edits flow through the service and hit the cascade-rule guard (no section delete while seats reference it).

  **Delete must route through the service.** Every `DeleteAction::make()` for an auditorium opts into `->using()` so deletes emit an audit row attributed to the admin actor:

  ```php
  DeleteAction::make()
      ->using(fn (Model $record) => app(\App\Services\AuditoriumService::class)
          ->deleteAuditorium($record, auth('admin')->user()))
      ->requiresConfirmation()
      ->modalDescription('Deleting this auditorium will cascade to its showtimes. Past bookings keep their historical seat references.');
  ```

  This is a convention enforced by test (Task 7), not by static analysis. A stock `DeleteAction::make()` without `->using()` is a test-caught regression — the test asserts that `AuditoriumService::deleteAuditorium` was called for any admin-originated delete.

  **Row actions:**
  - Edit (inline form via shared schema)
  - Configure seats (opens seat generator — Task 5)
  - Fix seat sections (opens `updateSeatBatch` table action — see below)
  - Visual seat editor (Task 6, gated on feature flag)
  - Delete (via `->using()`, with cascade warning for associated showtimes)

  **`updateSeatBatch` MVP UI (table action).** A row action labelled "Fix seat sections" opens a Filament modal with a table of existing seats, each row offering a section select and an "Unavailable" toggle. Save submits the diff to `AuditoriumService::updateSeatBatch($auditorium, $patches, auth('admin')->user())`. This is the no-UX-risk fallback that ships with MVP. Task 6's visual editor is a better UX on top of the same service method.

- **Acceptance Criteria:**
  - [ ] Relation manager lists auditoriums for a location
  - [ ] Inline create/edit routes through `AuditoriumService`, passing `auth('admin')->user()` as actor
  - [ ] Both relation manager and standalone resource call the same `AuditoriumResource::getFormSchema()` — a field added to the method appears in both surfaces without further edits
  - [ ] Section repeater persists to `auditorium_sections` via `AuditoriumService::updateSectionConfig`
  - [ ] `cleanup_minutes` editable with helper text
  - [ ] "Configure seats" action visible per permission (`seats.update` or equivalent)
  - [ ] "Fix seat sections" row action calls `AuditoriumService::updateSeatBatch` with the admin actor
  - [ ] Every `DeleteAction` instance uses `->using()` to call `AuditoriumService::deleteAuditorium`
  - [ ] Direct Eloquent deletes removed from every auditorium mutation path
  - [ ] Standalone `/auditoriums` resource also accessible

---

### Task 5: Seat generator form (MVP)

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `backend/app/Filament/Pages/ConfigureAuditoriumSeats.php` (new custom page)
  - `backend/app/Filament/Resources/AuditoriumResource/Pages/ConfigureSeats.php` (new sub-page)
- **Details:**
  Custom Filament page invoked from the "Configure seats" action on an auditorium. Provides a form that generates seats in bulk.

  **Form:**
  ```php
  public function form(Form $form): Form
  {
      return $form->schema([
          TextInput::make('rows')->numeric()->minValue(1)->maxValue(26)->required()
              ->helperText('Number of rows (e.g., 10 creates rows A through J). Max 26 (A–Z).'),
          TextInput::make('seats_per_row')->numeric()->minValue(1)->maxValue(30)->required(),

          Section::make('Section Assignment')->schema([
              Repeater::make('section_map')
                  ->schema([
                      TextInput::make('row_range')->required()
                          ->placeholder('A-B or C-G')
                          ->helperText('Row letters inclusive, e.g., "A-B" or "C" for a single row'),
                      Select::make('section_id')
                          ->options(fn () => $this->getAuditorium()->sections->pluck('name', 'id'))
                          ->required(),
                      Select::make('type')
                          ->options([
                              'standard' => 'Standard',
                              'premium' => 'Premium',
                              'accessible' => 'Accessible',
                          ])->required(),
                  ])->columns(3),
          ]),

          Section::make('Unavailable Seats')->schema([
              TagsInput::make('unavailable_seats')
                  ->placeholder('Type A3 then Enter, then A4, then J11')
                  ->separator(',')
                  ->helperText('Enter seat IDs one at a time (press Enter or comma to tag). Pasting a comma-separated list like "A3, A4, J11" also works — each token becomes its own tag.'),
          ]),

          Placeholder::make('warning')
              ->content('⚠️ Submitting this form will DELETE the existing seat layout and rebuild it. If the rebuild fails at any step, the previous layout is kept — nothing is half-written.')
              ->visible(fn () => $this->getAuditorium()->seats()->exists()),

          Placeholder::make('blocker')
              ->content(fn () => $this->renderRegenerationBlockers())
              ->visible(fn () => $this->hasRegenerationBlockers()),
      ]);
  }
  ```

  **Row-range format (supported shapes for v1):**

  - Single letter: `A`, `B`, …, up to the row letter implied by the `rows` count.
  - Contiguous A–Z range: `A-C`, `D-H`. Both endpoints must be single uppercase letters and `start <= end` alphabetically.
  - **Not supported in v1:** multi-letter labels (`AA`, `AB`), reversed ranges (`C-A`), mixed case, numeric row names, gap-skipping unions (`A-B,D-E` — submit them as two repeater entries).
  - Maximum rows per auditorium: **26** (A–Z). The UI enforces `maxValue(26)` on the `rows` input. Anything larger requires the visual editor (Task 6) or a follow-up plan.

  Row-range input must be validated on submit — reject bad tokens with a field-level error before calling the service.

  **Action:**
  - Parse each `row_range` into an array of row letters (rejecting malformed tokens with field errors)
  - Build config array for `AuditoriumService::generateSeats`
  - Call the service with `auth('admin')->user()` as the actor and handle outcomes:
    - **Success:** redirect to auditorium view with a "Seat layout regenerated" notification. Activity log entry captures the full config so historical layouts are reviewable.
    - **`AuditoriumSeatRegenerationBlockedException`:** stay on the page, render a prominent error summary with the blocker counts from the exception (e.g., "Blocked: 3 future showtimes, 12 active bookings"), and include links to the relevant showtime/booking queues. The old layout is untouched.
    - **Any other service failure:** stay on the page, show a generic error notification. Because the service wraps generation in a transaction, the previous layout is guaranteed intact — surface that reassurance in the error copy: *"Regeneration failed. Your existing seat layout has not been changed."*
  - Before the form is rendered, the page runs the same refusal checks (`hasRegenerationBlockers`) and disables the submit button when any blocker is present, with the same explanatory text shown inline. The server-side check in the service is still the source of truth; the pre-check is a UX affordance only.

  Helper for row range parsing:
  ```php
  private function expandRowRange(string $range): array
  {
      if (!str_contains($range, '-')) return [$range];
      [$start, $end] = explode('-', $range);
      $startIdx = ord($start) - ord('A');
      $endIdx = ord($end) - ord('A');
      return array_map(fn ($i) => chr(ord('A') + $i), range($startIdx, $endIdx));
  }
  ```

- **Acceptance Criteria:**
  - [ ] Form validates row count (1–26), seats_per_row (1–30), and row-range tokens (single letter or `A-Z` contiguous range)
  - [ ] Section map repeater wired to auditorium's sections
  - [ ] Unavailable-seats tag input accepts individual tags *and* a pasted comma-separated list (`A3, A4, J11`). Form copy, helper text, and acceptance test all describe the same behavior — no drift between "tags" and "comma-separated list"
  - [ ] Destructive warning shown when regenerating existing seats, including the reassurance that transactional failure leaves the old layout intact
  - [ ] Submit button is disabled and blocker summary rendered when future showtimes, active bookings, or held seats exist for the auditorium
  - [ ] Submit calls `AuditoriumService::generateSeats` with the admin actor
  - [ ] On `AuditoriumSeatRegenerationBlockedException`, blocker counts are shown to the user; no destructive action runs
  - [ ] On any other generation failure, the UI explicitly states that the existing seat layout has not been changed
  - [ ] Seat matrix correct post-generation (manual: create 10x12, verify 120 seats minus unavailable)
  - [ ] Activity log entry created with `causedBy($actor)` attribution

---

### Task 6: Visual seat editor (optional UX layer on top of `updateSeatBatch`)

- **MoSCoW:** Could Have (ships in this plan only if budget allows; otherwise spin off to a follow-up plan)
- **Complexity:** L
- **Files:**
  - `backend/app/Filament/Pages/VisualSeatEditor.php` (new)
  - `backend/resources/views/filament/pages/visual-seat-editor.blade.php` (new)
  - `backend/resources/views/filament/pages/partials/seat-grid.blade.php` (new)
- **Scope note.** `updateSeatBatch` is not conditional on this task — it's promoted to MVP in Task 2 because the "reassign row A from Premium to Accessible after opening" case will hit within the first operational month and the regeneration path is blocked by future showtimes. Task 6's scope reduces to the visual UI layer on top of the already-present service method. The MVP UI for `updateSeatBatch` is a row action in `AuditoriumResource` (shipped in Task 4); this task is a better UX, not a new capability.
- **Details:**
  Livewire-driven visual grid. Renders a grid of clickable squares matching the auditorium's row × col layout. Each cell represents a seat and shows its current type/section via color.

  **Interactions:**
  - Click seat → cycle through types (standard → premium → accessible → unavailable → standard)
  - Drag-select → apply type to selection (shift-click for range select)
  - Section dropdown in toolbar → change section for selected seats
  - Save button → batch update via `AuditoriumService::updateSeatBatch()` (already present per Task 2), passing `auth('admin')->user()` as actor

  **Alpine.js / Livewire pattern:**
  - Server-side state: Livewire property `$seats` keyed by seat ID
  - Client-side UX: Alpine `x-data` managing selection, hover, drag
  - Save: `wire:click="save"` sends the delta to `AuditoriumService::updateSeatBatch` with the admin actor

  Since this is "Could Have" gated on budget, document the scope but do not block Plan 05 completion on it.

  **MVP fallback if not shipped:** staff use Task 4's "Fix seat sections" row action on `AuditoriumResource` to invoke `updateSeatBatch` for per-row reassignment. Task 5's generator form remains the path for full rebuilds. Because `updateSeatBatch` is in MVP, the visual editor can be added later as a pure UX enhancement with no service-layer changes.

- **Acceptance Criteria (only if shipped):**
  - [ ] Visual grid renders at `/auditoriums/{id}/visual-editor`
  - [ ] Click cycles seat type
  - [ ] Drag-select applies type in bulk
  - [ ] Save persists via the existing `updateSeatBatch` service method, passing the admin actor — no new service method added
  - [ ] Unsaved changes prompt on navigation
  - [ ] Feature tests cover type cycling and bulk save

- **Explicit non-goal in this plan:** If budget is tight, document this task as "deferred to admin-v2" in the progress journal and remove from this plan's scope. The MVP row-action UI on `AuditoriumResource` (Task 4) plus Task 5's generator form together cover the operational need.

---

### Task 7: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Feature/Admin/Resources/LocationResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/LocationResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/AuditoriumResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/AuditoriumResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/Pages/ConfigureAuditoriumSeatsTest.php` (new)
  - `backend/tests/Feature/Admin/Pages/VisualSeatEditorTest.php` (new — only if Task 6 ships)
  - `backend/tests/Feature/Admin/Services/AuditoriumServiceIntegrationTest.php` (new)
  - `backend/tests/Feature/Admin/Services/AuditoriumServiceRegenerationSafetyTest.php` (new — critical)
- **Details:**
  Use Filament's Livewire test helpers. Tests split into two layers (same pattern as Plan 04).

  **Layer A — Resource / Page tests (service mocked).** Verify the Resource and pages wire forms / actions / permissions to the service. Mock `\App\Services\AuditoriumService` via `$this->mock()` so no backend writes happen. These tests do not assert on `activity_log` — with a mocked service, no real mutation runs.

  **LocationResourceTest (service mocked):**
  - admin can list locations
  - admin can create a location via form submission → asserts the service create call (or direct write, depending on how Task 3 lands) happens with actor = logged-in admin
  - admin can update a location → asserts service update with actor
  - **admin can delete a location via the table `DeleteAction` → asserts `AuditoriumService::deleteLocation` (or equivalent) was called and `Model::delete()` was NOT called directly** (regression guard for stock `DeleteAction::make()` slipping in without `->using()`)
  - Timezone select is `required()` and has no hardcoded geographic default
  - AuditoriumsRelationManager renders under the location view page

  **LocationResourcePermissionTest (service mocked):**
  - ops cannot access create / edit
  - manager can create / update locations
  - nobody role cannot access list page

  **AuditoriumResourceTest (service mocked):**
  - admin can list auditoriums
  - admin can create an auditorium via the relation manager → asserts `AuditoriumService::createAuditorium` called with location + actor
  - admin can update an auditorium from the standalone resource → asserts `AuditoriumService::updateAuditorium` with actor
  - **admin can delete an auditorium via `DeleteAction` → asserts `AuditoriumService::deleteAuditorium` was called and `Model::delete()` was NOT called directly**
  - Section repeater persistence routes through `AuditoriumService::updateSectionConfig` with actor
  - "Fix seat sections" row action calls `AuditoriumService::updateSeatBatch` with actor
  - Relation manager and standalone resource render the same field set (guards against form drift by asserting the shared `getFormSchema` is reused)

  **AuditoriumResourcePermissionTest (service mocked):**
  - ops cannot configure seats
  - manager can configure seats
  - ops cannot see "Fix seat sections" or "Configure seats" row actions
  - ops can view the list

  **ConfigureAuditoriumSeatsTest (service mocked for most cases; real for blocker-path rendering):**
  - Generator form accepts valid config → calls `AuditoriumService::generateSeats` with expanded row array and actor
  - Row range "A-C" expands to ["A", "B", "C"]
  - Invalid row range tokens (`C-A`, `AA`, `1-3`) produce field-level errors and do not call the service
  - Unavailable-seat input accepts both individual tags and a pasted comma-separated string, producing the same array
  - Warning visible when auditorium already has seats
  - Submit button is disabled and blocker summary rendered when blockers exist
  - When the service throws `AuditoriumSeatRegenerationBlockedException`, the UI renders blocker counts and no destructive action is reported as completed
  - When the service throws a generic failure, the UI copy states that the existing layout has not been changed

  **Layer B — Service integration tests (real service, real DB).** Exercise the real `AuditoriumService` end-to-end to verify activity-log attribution and the cross-cutting invariants.

  **AuditoriumServiceIntegrationTest:**
  - Creating an auditorium with `$actor` set writes an `activity_log` row with the expected description, causer, and subject
  - Creating an auditorium with `$actor = null` does NOT write an `activity_log` row
  - Updating an auditorium with `$actor` set writes an update activity row with the changed-attribute diff
  - Deleting an auditorium with `$actor` set writes a delete activity row
  - `updateSectionConfig` with `$actor` set writes an activity row per section changed
  - `updateSeatBatch` happy-path reassignment writes one activity row per seat changed with actor attribution
  - `updateSeatBatch` happy-path unavailability toggle writes one activity row per seat changed
  - `updateSeatBatch` invalid seat ID or invalid section ID rolls back the entire batch; no seat modified; no activity rows written
  - `updateSeatBatch` is NOT blocked by future showtimes or active bookings (the key MVP promise)
  - Deleting a section referenced by seats is refused; no section row removed

  **AuditoriumServiceRegenerationSafetyTest (critical — highest-priority invariant):**
  - Regeneration blocked when a future `Showtime` exists; exception carries future-showtime count; no seats deleted
  - Regeneration blocked when a `Booking` with status `confirmed` / `held` / `refund_pending` references a seat in the auditorium; no seats deleted
  - Regeneration blocked when any seat in the auditorium is in `held` state
  - Regeneration succeeds when only past showtimes exist and all bookings are in terminal states
  - Mid-generation failure (inject a DB error after delete, before insert-complete) — previous seat layout remains fully intact, no partial state, activity log records the failure rather than a success
  - `force = true` path still refuses in this plan because the UI never exposes it — document as a guard, not a feature
  - Updating a section's `price_multiplier` does not modify any seat row (cascade contract)
  - Adding a new section does not auto-populate seats (cascade contract)

- **Acceptance Criteria:**
  - [ ] Layer A Location Resource tests cover list / create / update / delete (including stock-DeleteAction regression guard) and timezone required-default behavior
  - [ ] Layer A Location PermissionTest covers all three roles × all actions
  - [ ] Layer A Auditorium Resource tests cover list / create / update / delete (including stock-DeleteAction regression guard), section-repeater routing through the service, `updateSeatBatch` table action, and shared-schema drift guard
  - [ ] Layer A Auditorium PermissionTest covers all three roles × all actions
  - [ ] Layer A ConfigureAuditoriumSeatsTest covers generator validation, row-range expansion, blocker-aware UI, and exception rendering
  - [ ] Layer A service is mocked — no real writes; no `activity_log` assertions at this layer
  - [ ] Layer B `AuditoriumServiceIntegrationTest` runs the real service and verifies `activity_log` writes (including the `$actor = null` skip case), cascade rules, and `updateSeatBatch` behavior
  - [ ] Layer B `AuditoriumServiceRegenerationSafetyTest` green and non-trivial — regeneration safety is the highest-priority invariant in this plan
  - [ ] Visual editor tests green if Task 6 ships
  - [ ] `make admin-test` passes all location / auditorium / seat tests green

---

## Testing Requirements

- **Layer A (Resource / Page, service mocked):** Location CRUD / Auditorium CRUD via relation manager and standalone resource / seat generator / `updateSeatBatch` table action / full permission matrix / shared-schema drift guard. No `activity_log` assertions.
- **Layer B (Service integration, real DB):** activity-log attribution with / without actor, section-cascade contract, `updateSeatBatch` happy and invalid-input paths, regeneration safety (future showtimes, active bookings, held seats, mid-generation rollback).
- **Backend service tests (Task 2):** all write paths independent of Filament.

## Dependencies Map

```
Task 1 (cleanup_minutes) ← foundational
Task 2 (AuditoriumService) ← needs Task 1
Task 3 (LocationResource) ← needs Plan 03 BaseResource + Task 2
Task 4 (AuditoriumResource) ← needs Tasks 2, 3
Task 5 (seat generator) ← needs Task 4 — MVP
Task 6 (visual editor) ← needs Task 5 — OPTIONAL
Task 7 (tests) ← needs all
```

## Risks & Open Questions

1. **Regeneration safety is the top risk in this plan.** `generateSeats` is the most destructive admin action across v1. The Task 2 contract (refuse when future showtimes / active bookings / held seats exist, transactional rollback leaves old layout intact) is the primary guardrail. If that contract is softened or skipped during implementation, data loss becomes likely rather than possible. The dedicated `AuditoriumServiceRegenerationSafetyTest` in Task 7 exists to prevent regressions here — treat it as load-bearing.
2. **Seat count performance.** Auditoriums with 300+ seats mean 300 rows inserted per regeneration. Wrapped in transaction; measure on typical hardware. If slow, batch via `Seat::insert()` chunks.
3. **Soft-delete cascade.** If a location is soft-deleted with live showtimes, what happens? v1 behavior: `onDelete('restrict')` at the DB level prevents deletion — admin must first cancel or migrate showtimes. Document in the delete confirmation dialog.
4. **Visual editor scope creep.** The Livewire/Alpine UX is the most expensive piece in the whole admin plan set. Explicitly keep it in Could Have and ship the MVP generator form + `updateSeatBatch` row action first. Decision point: after Task 5 tests pass, assess remaining budget. `updateSeatBatch()` is always present in the service API (MVP promoted), so deferring Task 6 costs zero capability — only UX polish.
5. **Section pricing coupling.** `AuditoriumSection.price_multiplier` is applied to `Showtime.price_standard` to derive seat pricing. Section edits do not cascade to seat rows (seats reference sections by `section_id`, so multiplier changes take effect immediately without touching seats). Plan 06 conflict detection doesn't touch this, but Plan 08 (menu/promo) might. Verify no admin mutation breaks the downstream pricing formula.
6. **Migration coordination.** Task 1's in-place migration edit assumes the current schema has not propagated to a shared environment. If it has, Task 1 must fall back to an additive migration. Confirm before starting the task, not mid-implementation.
7. **Stock `DeleteAction` regression.** The write-boundary rule (all admin auditorium deletes go through `AuditoriumService` for audit attribution) is a convention enforced by Layer A Resource tests, not by static analysis. A future contributor adding a `DeleteAction::make()` without `->using()` slips through to a direct `$record->delete()` with no audit row. The Task 7 regression test catches it before merge. If regressions happen repeatedly, escalate tooling later — start with the test, keep tooling light.
