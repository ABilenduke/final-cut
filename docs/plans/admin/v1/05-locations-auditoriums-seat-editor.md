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
  - `backend/database/migrations/*_create_auditoriums_table.php` (modify — pre-launch rule from CLAUDE.md memory)
  - `backend/app/Models/Auditorium.php` (modify — add to fillable)
  - `admin/app/Models/Auditorium.php` (modify — mirror)
- **Details:**
  Per project convention ("Migrations in place"), pre-launch migrations are edited directly rather than adding new migration files.

  Edit the existing auditoriums migration to add:
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

### Task 2: Audit/extract backend AuditoriumService

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/AuditoriumService.php` (new)
  - `backend/tests/Feature/AuditoriumServiceTest.php` (new)
  - `admin/app/Services/Backend/AuditoriumService.php` (modify — remove `@todo`)
- **Details:**
  Create `AuditoriumService` with the operations admin needs:

  ```php
  class AuditoriumService
  {
      public function createAuditorium(Location $location, array $attributes): Auditorium;
      public function updateAuditorium(Auditorium $auditorium, array $attributes): Auditorium;
      public function deleteAuditorium(Auditorium $auditorium): void;
      public function generateSeats(Auditorium $auditorium, array $config): void; // Task 5
      public function updateSectionConfig(Auditorium $auditorium, array $sections): void;
      public function markSeatUnavailable(Seat $seat, ?string $unavailable_reason = null): void;
      public function markSeatAvailable(Seat $seat): void;
  }
  ```

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

  Wraps everything in a transaction. Idempotent re-run — if seats already exist for the auditorium, deletes them first. Logs an activity entry with the config diff.

  Backend tests: service create/update/delete paths, seat generation produces correct row × col count, section assignment correct, unavailable seats flagged.

  Update admin facade.

- **Acceptance Criteria:**
  - [ ] `AuditoriumService` exists with documented methods
  - [ ] `generateSeats` produces correct seat matrix
  - [ ] Section config persisted correctly
  - [ ] Unavailable seats respected
  - [ ] Transactional rollback on failure
  - [ ] Backend test coverage green
  - [ ] Admin facade delegates correctly

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
      Select::make('timezone')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
          ->default('America/Los_Angeles')->searchable(),
      TextInput::make('latitude')->numeric()->step(0.000001),
      TextInput::make('longitude')->numeric()->step(0.000001),
  ])->columns(3),
  ```

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

  **Relation manager form:**
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
                  ->placeholder('A3, A4, J11')
                  ->helperText('Individual seat IDs to mark as unavailable (aisles, structural gaps)'),
          ]),

          Placeholder::make('warning')
              ->content('⚠️ Running this form will DELETE existing seats for this auditorium and regenerate them.')
              ->visible(fn () => $this->getAuditorium()->seats()->exists()),
      ]);
  }
  ```

  **Action:**
  - Parse row_range into array of row letters
  - Build config array for `AuditoriumService::generateSeats`
  - Call service
  - Redirect back to auditorium view with success notification
  - Activity log entry captures the config (so we can see historical seat layouts)

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
  - [ ] Form validates row count, seats_per_row
  - [ ] Section map repeater wired to auditorium's sections
  - [ ] Unavailable seats input accepts comma-separated list
  - [ ] Warning shown when regenerating existing seats
  - [ ] Submit calls `AuditoriumService::generateSeats`
  - [ ] Seat matrix correct post-generation (manual: create 10x12, verify 120 seats minus unavailable)
  - [ ] Activity log entry created

---

### Task 6: Visual seat editor (optional second pass)

- **MoSCoW:** Could Have (ships in this plan only if budget allows; otherwise spin off to a follow-up plan)
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Pages/VisualSeatEditor.php` (new)
  - `admin/resources/views/filament/pages/visual-seat-editor.blade.php` (new)
  - `admin/resources/views/filament/pages/partials/seat-grid.blade.php` (new)
- **Details:**
  Livewire-driven visual grid. Renders a grid of clickable squares matching the auditorium's row × col layout. Each cell represents a seat and shows its current type/section via color.

  **Interactions:**
  - Click seat → cycle through types (standard → premium → accessible → unavailable → standard)
  - Drag-select → apply type to selection (shift-click for range select)
  - Section dropdown in toolbar → change section for selected seats
  - Save button → batch update via `AuditoriumService::updateSeatBatch()` (new method in Task 2)

  **Alpine.js / Livewire pattern:**
  - Server-side state: Livewire property `$seats` keyed by seat ID
  - Client-side UX: Alpine `x-data` managing selection, hover, drag
  - Save: `wire:click="save"` sends the delta to the service

  Since this is "Could Have" gated on budget, document the scope but do not block Plan 05 completion on it.

  **MVP fallback if not shipped:** staff use Task 5's generator form to rebuild. Because the MVP data model stores seats identically, no future migration is needed to add the visual editor later.

- **Acceptance Criteria (only if shipped):**
  - [ ] Visual grid renders at `/admin/auditoriums/{id}/visual-editor`
  - [ ] Click cycles seat type
  - [ ] Drag-select applies type in bulk
  - [ ] Save persists via service facade
  - [ ] Unsaved changes prompt on navigation
  - [ ] Feature tests cover type cycling and bulk save

- **Explicit non-goal in this plan:** If budget is tight, document this task as "deferred to admin-v2" in the progress journal and remove from this plan's scope. The MVP form in Task 5 is sufficient for launch.

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
  - Test: unavailable seats comma list parsed correctly
  - Test: warning visible when auditorium already has seats
  - Test: activity log entry created post-generation

  **Permission tests:** ops role cannot configure seats; manager role can.

- **Acceptance Criteria:**
  - [ ] Location resource tests green (5+ tests)
  - [ ] Auditorium resource tests green (5+ tests)
  - [ ] Seat generator tests green (5+ tests)
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

1. **Seat count performance.** Auditoriums with 300+ seats mean 300 rows inserted per regeneration. Wrapped in transaction; measure on typical hardware. If slow, batch via `Seat::insert()` chunks.
2. **Soft-delete cascade.** If a location is soft-deleted with live showtimes, what happens? v1 behavior: `onDelete('restrict')` at the DB level prevents deletion — admin must first cancel or migrate showtimes. Document in the delete confirmation dialog.
3. **Visual editor scope creep.** The Livewire/Alpine UX is the most expensive piece in the whole admin plan set. Explicitly keep it in Could Have and ship the MVP form first. Decision point: after Task 5 tests pass, assess remaining budget.
4. **Section pricing coupling.** `AuditoriumSection.price_multiplier` is applied to `Showtime.price_standard` to derive seat pricing. Plan 06 conflict detection doesn't touch this, but Plan 08 (menu/promo) might. Verify no admin mutation breaks the downstream pricing formula.
