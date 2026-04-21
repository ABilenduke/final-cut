# Plan 05: Locations, Auditoriums & Seat Editor

> **Priority:** Must Have
> **Complexity:** XL
> **Depends On:** Plan 03 (Location, Auditorium, Seat models, AuditoriumService facade)
> **Unlocks:** Plan 06 (Showtime resource needs auditoriums), Plan 08 (menu is location-scoped)

## Overview

Build the `LocationResource` and `AuditoriumResource`, add the `cleanup_minutes` column to auditoriums (consumed by Plan 06's conflict detection), and ship the seat configuration tooling in two tracks: an MVP seat-generator form that covers ~90% of real configurations, and a visual seat editor that ships after the MVP if budget allows (otherwise deferred). The visual editor builds on the MVP data model so no work is wasted.

Per spec § 2.6, all mutations go through `AuditoriumService`. Since `AuditoriumService` likely does not yet exist in the backend (verified in Plan 03 Task 6), this plan includes its extraction.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 5 Plan 05
- `docs/architecture/DATA_MODELS.md` — Location, Auditorium, Seat, AuditoriumSection
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
  - `admin/app/Models/Auditorium.php` (modify — mirror)
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

  Update both backend and admin `Auditorium` models' `$fillable` array.

  Update backend seeder (`DatabaseSeeder` / `AuditoriumSeeder`) to populate `cleanup_minutes => 20` for seeded auditoriums.

  Update the `AuditoriumResource` (Task 4 below) to expose this field in the form.

- **Acceptance Criteria:**
  - [ ] `cleanup_minutes` column added to auditoriums (default 20)
  - [ ] Backend + admin models fillable include it
  - [ ] Seeder produces auditoriums with the column populated
  - [ ] ModelParityTest still passes
  - [ ] Plan 06 can reference `auditorium.cleanup_minutes`

---

### Task 2: Extract AuditoriumService into the shared-domain package

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `packages/shared-domain/src/Services/AuditoriumService.php` (new)
  - `packages/shared-domain/src/Exceptions/AuditoriumSeatRegenerationBlockedException.php` (new)
  - `packages/shared-domain/tests/Feature/AuditoriumServiceTest.php` (new)
  - `admin/app/Services/Backend/AuditoriumService.php` (new — admin facade)
- **Details:**
  Per Plan 03's ADR, `AuditoriumService` lives in `packages/shared-domain/src/Services/` under the `FinalCut\Domain\Services` namespace. Every write method takes an explicit `Causer $causer` argument per the Plan 02 Task 4 contract.

  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\Auditorium;
  use FinalCut\Domain\Models\Location;
  use FinalCut\Domain\Models\Seat;

  class AuditoriumService
  {
      public function createAuditorium(Location $location, array $attributes, Causer $causer): Auditorium;
      public function updateAuditorium(Auditorium $auditorium, array $attributes, Causer $causer): Auditorium;
      public function deleteAuditorium(Auditorium $auditorium, Causer $causer): void;
      public function generateSeats(Auditorium $auditorium, array $config, Causer $causer, bool $force = false): void; // Task 5
      public function updateSectionConfig(Auditorium $auditorium, array $sections, Causer $causer): void;
      public function markSeatUnavailable(Seat $seat, Causer $causer, ?string $unavailable_reason = null): void;
      public function markSeatAvailable(Seat $seat, Causer $causer): void;

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
      public function updateSeatBatch(Auditorium $auditorium, array $seatUpdates, Causer $causer): void;
  }
  ```

  **`updateSeatBatch` contract (MVP — promoted from the previous "conditional on Task 6" scope):**

  - Operates on **existing** seat rows only. Does not create or delete seats. Does not change row labels or seat numbers.
  - Accepts an array of per-seat patches keyed by `seat_id`. Each patch may include `section_id` (reassignment) and/or `unavailable_at` (availability toggle).
  - Runs the whole batch inside `DB::transaction`. Any invalid seat ID or invalid section ID fails the entire batch — no partial updates.
  - Does **not** require the regeneration-safety checks (future showtimes, active bookings). Seat IDs are preserved, so existing bookings continue to point at the same physical seat; only the seat's section membership or availability flag changes. A Premium → Accessible reassignment updates the pricing tier for *future* showtimes and does not retroactively re-price sold tickets (those are locked at booking time).
  - Emits one activity row per seat changed, linked to the auditorium as the subject, with `causedBy($causer)` and the before/after diff in `properties`.
  - Backend test coverage: happy-path reassignment, happy-path unavailability, invalid seat ID fails entire batch, existing showtimes/bookings do not block (this is the key MVP promise — regeneration blocks, batch does not).

  The corresponding Filament UI for `updateSeatBatch` is a simple table action in Task 4's `AuditoriumResource` or a dedicated "Fix seat sections" page — **not** the visual seat editor. Task 6's visual editor (Could Have) is a better UX on top of the same service method but is not required for MVP.

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

  Backend tests: service create/update/delete paths, seat generation produces correct row × col count, section assignment correct, unavailable seats flagged, **regeneration refused when future showtimes exist**, **regeneration refused when active bookings exist**, **rollback on mid-generation failure leaves prior layout intact**, section rename does not modify seats.

  Update admin facade.

- **Acceptance Criteria:**
  - [ ] `FinalCut\Domain\Services\AuditoriumService` exists in `packages/shared-domain/src/Services/` with all documented methods
  - [ ] Every write method signature declares an explicit `Causer $causer` parameter
  - [ ] Class docblock captures the section↔seat cascade contract and the `updateSeatBatch` vs `generateSeats` distinction
  - [ ] `generateSeats` produces correct seat matrix
  - [ ] `generateSeats` throws `AuditoriumSeatRegenerationBlockedException` when future showtimes, active bookings, or held seats exist (unless `force = true`)
  - [ ] Blocking exception carries structured blocker counts (future showtimes, active bookings, held seats)
  - [ ] `updateSeatBatch` operates on existing seat rows only, runs in a single transaction, fails the whole batch on any invalid input, and is **not** blocked by future showtimes or active bookings
  - [ ] `updateSeatBatch` emits one activity row per changed seat with `causedBy($causer)` and a before/after diff
  - [ ] Section config persisted correctly; section edits do not modify existing seats
  - [ ] Section deletion refused while any seat references it
  - [ ] Unavailable seats respected
  - [ ] Transactional rollback on failure leaves the previous seat layout fully intact
  - [ ] Backend test coverage green, including refusal paths, rollback, `updateSeatBatch` happy paths, and `updateSeatBatch` invalid-input rollback
  - [ ] Admin facade at `admin/app/Services/Backend/AuditoriumService.php` delegates to the domain service, resolves `Causer` from `auth()->user()`, and imports from `FinalCut\Domain` — no `Backend\` namespace references

---

### Task 3: LocationResource

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/LocationResource.php` (new)
  - `admin/app/Filament/Resources/LocationResource/Pages/*` (list, create, edit, view)
- **Details:**
  Standard Filament Resource extending `BaseResource` with `$permissionPrefix = 'locations'`.

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
  TextColumn::make('created_at')->since()->sortable(),
  ```

  Relation managers: auditoriums (Task 4).

  Delete is soft-delete via `SoftDeletes` trait if backend uses it — otherwise confirm via spec + schema.

- **Acceptance Criteria:**
  - [ ] Resource registers under "Operations" navigation group
  - [ ] Form validates required fields
  - [ ] Timezone select searchable
  - [ ] Table shows auditorium count
  - [ ] AuditoriumsRelationManager attached (Task 4)

---

### Task 4: AuditoriumResource via relation manager on Location

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/LocationResource/RelationManagers/AuditoriumsRelationManager.php` (new)
  - `admin/app/Filament/Resources/AuditoriumResource.php` (new — standalone resource too)
- **Details:**
  Auditoriums can be accessed two ways:
  1. **Via Location view page** — relation manager with inline edit (most common path for staff)
  2. **Standalone resource** at `/admin/auditoriums` — for bulk operations and schedule planner linking (Plan 06)

  **Form source of truth (no drift):** Both surfaces render the *same* form schema. Extract it into a shared static method — `AuditoriumResource::getFormSchema(): array` — and have both `AuditoriumResource::form()` and `AuditoriumsRelationManager::form()` call it. The relation manager is a convenience view on top of the shared schema; the standalone resource is the canonical surface for anything that isn't an inline quick-edit. Do not define the form independently in two places. Any field added to one surface lands in both automatically.

  **Relation manager form (defined in the shared schema method):**
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

  **Row actions:**
  - Edit (inline form)
  - Configure seats (opens seat generator — Task 5)
  - Visual seat editor (Task 6, gated on feature flag)
  - Delete (confirms; cascade warning for associated showtimes)

  All mutations go through `AuditoriumService` facade.

- **Acceptance Criteria:**
  - [ ] Relation manager lists auditoriums for a location
  - [ ] Inline create/edit uses service facade
  - [ ] Both relation manager and standalone resource call the same `AuditoriumResource::getFormSchema()` — a field added to the method appears in both surfaces without further edits
  - [ ] Section repeater persists to `auditorium_sections` table
  - [ ] `cleanup_minutes` editable with helper text
  - [ ] "Configure seats" action visible per permission
  - [ ] Standalone `/admin/auditoriums` resource also accessible

---

### Task 5: Seat generator form (MVP)

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Pages/ConfigureAuditoriumSeats.php` (new custom page)
  - `admin/app/Filament/Resources/AuditoriumResource/Pages/ConfigureSeats.php` (new sub-page)
- **Details:**
  Custom Filament page invoked from the "Configure seats" action on an auditorium. Provides a form that generates seats in bulk.

  **Form:**
  ```php
  public function form(Form $form): Form
  {
      return $form->schema([
          TextInput::make('rows')->numeric()->minValue(1)->maxValue(30)->required()
              ->helperText('Number of rows (e.g., 10 creates rows A through J)'),
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
  - Call the service and handle outcomes:
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
  - [ ] Submit calls `AuditoriumService::generateSeats`
  - [ ] On `AuditoriumSeatRegenerationBlockedException`, blocker counts are shown to the user; no destructive action runs
  - [ ] On any other generation failure, the UI explicitly states that the existing seat layout has not been changed
  - [ ] Seat matrix correct post-generation (manual: create 10x12, verify 120 seats minus unavailable)
  - [ ] Activity log entry created

---

### Task 6: Visual seat editor (optional UX layer on top of `updateSeatBatch`)

- **MoSCoW:** Could Have (ships in this plan only if budget allows; otherwise spin off to a follow-up plan)
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Pages/VisualSeatEditor.php` (new)
  - `admin/resources/views/filament/pages/visual-seat-editor.blade.php` (new)
  - `admin/resources/views/filament/pages/partials/seat-grid.blade.php` (new)
- **Scope change vs. previous draft.** `updateSeatBatch` is no longer conditional on this task — it's promoted to MVP in Task 2 because the "reassign row A from Premium to Accessible after opening" case will hit within the first operational month and the regeneration path is blocked by future showtimes. Task 6's scope reduces to the visual UI layer on top of the already-present service method. The MVP UI for `updateSeatBatch` is a simple table action in `AuditoriumResource` (shipped in Task 4); this task is a better UX, not a new capability.
- **Details:**
  Livewire-driven visual grid. Renders a grid of clickable squares matching the auditorium's row × col layout. Each cell represents a seat and shows its current type/section via color.

  **Interactions:**
  - Click seat → cycle through types (standard → premium → accessible → unavailable → standard)
  - Drag-select → apply type to selection (shift-click for range select)
  - Section dropdown in toolbar → change section for selected seats
  - Save button → batch update via `AuditoriumService::updateSeatBatch()` (already present per Task 2)

  **Alpine.js / Livewire pattern:**
  - Server-side state: Livewire property `$seats` keyed by seat ID
  - Client-side UX: Alpine `x-data` managing selection, hover, drag
  - Save: `wire:click="save"` sends the delta to the service facade, which resolves `Causer` and delegates to `FinalCut\Domain\Services\AuditoriumService::updateSeatBatch`

  Since this is "Could Have" gated on budget, document the scope but do not block Plan 05 completion on it.

  **MVP fallback if not shipped:** staff use Task 4's table-action UI on `AuditoriumResource` to invoke `updateSeatBatch` for per-row reassignment. Task 5's generator form remains the path for full rebuilds. Because `updateSeatBatch` is in MVP, the visual editor can be added later as a pure UX enhancement with no service-layer changes.

- **Acceptance Criteria (only if shipped):**
  - [ ] Visual grid renders at `/admin/auditoriums/{id}/visual-editor`
  - [ ] Click cycles seat type
  - [ ] Drag-select applies type in bulk
  - [ ] Save persists via the existing `updateSeatBatch` service method — no new service method added
  - [ ] Unsaved changes prompt on navigation
  - [ ] Feature tests cover type cycling and bulk save

- **Explicit non-goal in this plan:** If budget is tight, document this task as "deferred to admin-v2" in the progress journal and remove from this plan's scope. The MVP table-action UI on `AuditoriumResource` (Task 4) plus Task 5's generator form together cover the operational need.

---

### Task 7: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/LocationResourceTest.php` (new)
  - `admin/tests/Feature/Resources/AuditoriumResourceTest.php` (new)
  - `admin/tests/Feature/Pages/ConfigureAuditoriumSeatsTest.php` (new)
  - `admin/tests/Feature/Pages/VisualSeatEditorTest.php` (new — only if Task 6 ships)
- **Details:**
  **LocationResourceTest:** list/create/update/delete, timezone select, auditorium count column.

  **AuditoriumResourceTest:** CRUD via relation manager on Location, section repeater persistence, `cleanup_minutes` default and edit.

  **ConfigureAuditoriumSeatsTest:**
  - Test: generator form accepts valid config → calls `AuditoriumService::generateSeats` with expanded row array
  - Test: row range "A-C" expands to ["A", "B", "C"]
  - Test: invalid row range tokens (`C-A`, `AA`, `1-3`) produce field-level errors and do not call the service
  - Test: unavailable-seat input accepts both individual tags and a pasted comma-separated string, producing the same array
  - Test: warning visible when auditorium already has seats
  - Test: activity log entry created post-generation

  **AuditoriumServiceRegenerationSafetyTest (new — critical):**
  - Test: regeneration blocked when a future `Showtime` exists; exception carries future-showtime count; no seats deleted
  - Test: regeneration blocked when a `Booking` with status `confirmed` / `held` / `refund_pending` references a seat in the auditorium; no seats deleted
  - Test: regeneration blocked when any seat in the auditorium is in `held` state
  - Test: regeneration succeeds when only past showtimes exist and all bookings are in terminal states
  - Test: mid-generation failure (inject a DB error after delete, before insert-complete) — previous seat layout remains fully intact, no partial state, activity log records the failure rather than a success
  - Test: `force = true` path still refuses in this plan because the UI never exposes it — document as a guard, not a feature
  - Test: deleting a section referenced by seats is refused; no section row removed
  - Test: updating a section's `price_multiplier` does not modify any seat row

  **ConfigureAuditoriumSeatsTest (UI-level regeneration safety):**
  - Test: submit button is disabled and blocker summary rendered when blockers exist
  - Test: when the service throws `AuditoriumSeatRegenerationBlockedException`, the UI renders blocker counts and no destructive action is reported as completed
  - Test: when the service throws a generic failure, the UI copy states that the existing layout has not been changed

  **Permission tests:** ops role cannot configure seats; manager role can.

- **Acceptance Criteria:**
  - [ ] Location resource tests green (5+ tests), including required-timezone selection and no hardcoded geographic default
  - [ ] Auditorium resource tests green (5+ tests), including a test that asserts relation manager and standalone resource render the same field set (guards against form drift)
  - [ ] Seat generator tests green (5+ tests), including row-range validation and blocker-aware UI
  - [ ] `AuditoriumServiceRegenerationSafetyTest` green and non-trivial — regeneration safety is the highest-priority invariant in this plan
  - [ ] Visual editor tests green if shipped
  - [ ] Permission matrix covered

---

## Testing Requirements

- **Pest Feature Tests:** location CRUD, auditorium CRUD via relation manager, seat generator correctness, permission matrix
- **Backend service tests:** `AuditoriumService` has independent backend coverage (Task 2)
- **Parity:** `ModelParityTest` catches `cleanup_minutes` addition (Task 1 verifies)

## Dependencies Map

```
Task 1 (cleanup_minutes) ← foundational
Task 2 (AuditoriumService) ← needs Task 1
Task 3 (LocationResource) ← needs Task 2
Task 4 (AuditoriumResource) ← needs Tasks 2, 3
Task 5 (seat generator) ← needs Task 4 — MVP
Task 6 (visual editor) ← needs Task 5 — OPTIONAL
Task 7 (tests) ← needs all
```

## Risks & Open Questions

1. **Regeneration safety is the top risk in this plan.** `generateSeats` is the most destructive admin action across v1. The Task 2 contract (refuse when future showtimes / active bookings / held seats exist, transactional rollback leaves old layout intact) is the primary guardrail. If that contract is softened or skipped during implementation, data loss becomes likely rather than possible. The dedicated `AuditoriumServiceRegenerationSafetyTest` in Task 7 exists to prevent regressions here — treat it as load-bearing.
2. **Seat count performance.** Auditoriums with 300+ seats mean 300 rows inserted per regeneration. Wrapped in transaction; measure on typical hardware. If slow, batch via `Seat::insert()` chunks.
3. **Soft-delete cascade.** If a location is soft-deleted with live showtimes, what happens? v1 behavior: `onDelete('restrict')` at the DB level prevents deletion — admin must first cancel or migrate showtimes. Document in the delete confirmation dialog.
4. **Visual editor scope creep.** The Livewire/Alpine UX is the most expensive piece in the whole admin plan set. Explicitly keep it in Could Have and ship the MVP form + table-action UI first. Decision point: after Task 5 tests pass, assess remaining budget. `updateSeatBatch()` is always present in the service API (MVP promoted), so deferring Task 6 costs zero capability — only UX polish.
5. **Section pricing coupling.** `AuditoriumSection.price_multiplier` is applied to `Showtime.price_standard` to derive seat pricing. Section edits do not cascade to seat rows (seats reference sections by `section_id`, so multiplier changes take effect immediately without touching seats). Plan 06 conflict detection doesn't touch this, but Plan 08 (menu/promo) might. Verify no admin mutation breaks the downstream pricing formula.
6. **Migration coordination.** Task 1's in-place migration edit assumes the current schema has not propagated to a shared environment. If it has, Task 1 must fall back to an additive migration. Confirm before starting the task, not mid-implementation.
