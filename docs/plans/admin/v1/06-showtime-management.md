# Plan 06: Showtime Management

> **Priority:** Must Have
> **Complexity:** XL
> **Depends On:** Plan 04 (Movies), Plan 05 (Auditoriums with `cleanup_minutes`)
> **Unlocks:** Plan 07 (Bookings link to showtimes)

## Overview

The second-most-complex plan. Build `ShowtimeResource` (MVP: standard Filament table + form with conflict detection), the bulk-create dialog (`"Add this movie to Aud 1, Mon–Fri, 7pm + 9:30pm, for two weeks"`), the cancellation workflow (soft-delete + flag affected bookings + stubbed email + follow-up queue page), and — optionally — the visual weekly schedule planner. Per-auditorium `cleanup_minutes` from Plan 05 drives the conflict detection math.

Per spec § 2.6, all mutations route through `ShowtimeService`. Since this service likely does not exist in backend (booking-flow code handles showtimes today), this plan includes its extraction.

## Reference Documents

- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 5 Plan 06
- `docs/plans/backend/v1/04-booking-api.md` — existing showtime handling
- `docs/architecture/DATA_MODELS.md` — Showtime schema
- `backend/app/Http/Controllers/Api/BookingController.php` — likely source for extraction

---

## Tasks

### Task 1: Extract ShowtimeService into the shared-domain package

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `packages/shared-domain/src/Services/ShowtimeService.php` (new)
  - `packages/shared-domain/src/Exceptions/MovieRuntimeMissingException.php` (new)
  - `packages/shared-domain/src/Exceptions/ShowtimeAlreadyCancelledException.php` (new)
  - `packages/shared-domain/tests/Feature/ShowtimeServiceTest.php` (new)
  - `admin/app/Services/Backend/ShowtimeService.php` (new — admin facade)
- **Details:**
  Per Plan 03's ADR, `ShowtimeService` lives in `packages/shared-domain/src/Services/` under the `FinalCut\Domain\Services` namespace. Every write method takes an explicit `Causer $causer` argument per the Plan 02 Task 4 contract.

  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\Showtime;

  class ShowtimeService
  {
      public function create(array $attributes, Causer $causer): Showtime;
      public function update(Showtime $showtime, array $attributes, Causer $causer): Showtime;
      public function cancel(Showtime $showtime, string $reason, Causer $causer): void; // Task 5
      public function bulkCreate(BulkShowtimeRequest $request, Causer $causer): Collection; // Task 4
      public function detectConflicts(int $auditoriumId, Carbon $start, Carbon $end, ?int $ignoreShowtimeId = null): Collection;
  }
  ```

  **Conflict detection is a UX affordance, not the authoritative guard.** The DB's `EXCLUDE USING gist` constraint (added in Task 2) is the source of truth. `detectConflicts` exists so the admin form (Task 3) can show a friendly pre-submit error rather than a raw `ExclusionViolation` SQL exception. The service path in `create` / `update` catches `PDOException::SQLSTATE = '23P01'` (exclusion violation) and re-throws as a `ShowtimeConflictException` carrying the conflicting row data. **Do not rely on `detectConflicts` alone** — between `detectConflicts` returning empty and the subsequent insert, any other transaction (bulk create, concurrent admin, future customer self-service) could commit a conflicting row. The DB constraint closes that TOCTOU window.

  **Half-open interval rule (still applies to `detectConflicts` UI check):** two intervals `[a.start, a.end)` and `[b.start, b.end)` overlap iff `a.start < b.end AND a.end > b.start`. Back-to-back transitions (existing `end_time == new start_time`) correctly register as **no conflict**. The DB constraint uses `tstzrange(start_time, end_time, '[)')` for the same half-open semantics.

  ```php
  public function detectConflicts(int $auditoriumId, Carbon $start, Carbon $end, ?int $ignoreShowtimeId = null): Collection
  {
      return Showtime::where('auditorium_id', $auditoriumId)
          ->when($ignoreShowtimeId, fn ($q) => $q->where('id', '!=', $ignoreShowtimeId))
          ->whereNull('cancelled_at')
          ->where('start_time', '<', $end)
          ->where('end_time', '>', $start)
          ->get();
  }
  ```

  **End-time calculation:** `end_time = start_time + movie.runtime + auditorium.cleanup_minutes` (all in minutes). Service computes this automatically on create/update so admin doesn't pass `end_time` manually.

  **Runtime precondition (hard rule):** `movies.runtime` is nullable in the schema (enrichment-backfilled for TMDB-linked titles). A showtime **cannot** be scheduled against a movie with `runtime IS NULL` — conflict math is undefined without it. `ShowtimeService::create` and `::update` must throw a domain exception (`MovieRuntimeMissingException`) before attempting overlap detection. Surface this as a form-validation error at the UI layer (Task 3) with an actionable message linking to the movie edit page so staff can backfill runtime and retry. A companion rule in Plan 04 should warn (non-blocking) on movies lacking runtime so this surfaces before scheduling.

  Update admin facade.

- **Acceptance Criteria:**
  - [ ] `FinalCut\Domain\Services\ShowtimeService` exists in `packages/shared-domain/src/Services/` with documented methods
  - [ ] Every write method signature declares an explicit `Causer $causer` parameter
  - [ ] End-time computed from movie runtime + auditorium cleanup
  - [ ] `detectConflicts` uses the `start < other.end AND end > other.start` rule
  - [ ] `create` / `update` catch `PDOException` exclusion violations (`SQLSTATE 23P01`) and re-throw as `ShowtimeConflictException` with the conflicting row's details
  - [ ] Scheduling a movie with `runtime IS NULL` throws `MovieRuntimeMissingException`
  - [ ] Ignore-self logic for updates works
  - [ ] Backend tests cover conflict edge cases — **back-to-back (no conflict), exact-overlap, nested, one-minute overlap at both boundaries, and missing-runtime rejection**
  - [ ] **Concurrent-insert test:** two parallel transactions attempting to insert overlapping showtimes for the same auditorium — one succeeds, one fails with `ShowtimeConflictException`, no partial state written
  - [ ] Admin facade at `admin/app/Services/Backend/ShowtimeService.php` delegates to the domain service, resolves `Causer` from `auth()->user()`, imports from `FinalCut\Domain` — no `Backend\` namespace references

---

### Task 2: Add `cancelled_at`, `flagged_at`, `notes`, composite index, EXCLUDE constraint, `dispatch_outbox`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/database/migrations/*_create_showtimes_table.php` (modify in-place **or** additive — see guard below)
  - `backend/database/migrations/*_create_bookings_table.php` (modify in-place **or** additive — see guard below)
  - `backend/database/migrations/<timestamp>_add_showtime_exclusion_constraint.php` (new)
  - `backend/database/migrations/<timestamp>_create_dispatch_outbox_table.php` (new)
  - `backend/app/Models/Showtime.php` (modify — fillable, casts)
  - `backend/app/Models/Booking.php` (modify — fillable, casts)
  - `packages/shared-domain/src/Models/DispatchOutbox.php` (new — shared model)
  - `admin/app/Models/Showtime.php` (mirror)
  - `admin/app/Models/Booking.php` (mirror)
- **Details:**

  **Migration strategy (environment-state guard — see CLAUDE.md "Pre-launch migrations"):** The in-place rule applies only while the project is pre-launch. Before editing the existing showtimes/bookings migrations:

  1. Confirm no non-local environment (staging, CI preview, another contributor's branch) has run the existing `create_showtimes_table` / `create_bookings_table` migrations. A quick check: `git log --all -- backend/database/migrations/*showtimes*.php` + a team sync, plus `make fresh` working locally.
  2. **If clean (still pre-launch):** edit the two migrations in place as shown below.
  3. **If post-launch or any shared environment has the old schema:** ship as an additive migration `2026_xx_xx_add_admin_showtime_booking_columns.php` (same columns, same indexes). Do not rewrite history that other people have already run.

  The EXCLUDE constraint and `dispatch_outbox` migrations are **always additive** (new migrations in both branches) — they don't exist in any prior schema.

  The rest of this task assumes the pre-launch clean case for the showtimes/bookings edits. Regardless of which path is taken, the **resulting schema** is identical.

  **showtimes table:**
  ```php
  $table->timestamp('cancelled_at')->nullable()->after('end_time');
  $table->string('cancellation_reason')->nullable()->after('cancelled_at');
  $table->index(['auditorium_id', 'start_time'], 'showtimes_aud_start_idx');
  ```

  The composite `(auditorium_id, start_time)` index supports conflict-detection queries on large schedules, but it does **not** prevent concurrent inserts. That is the EXCLUDE constraint's job.

  **EXCLUDE USING gist constraint (additive migration — always):**

  PostgreSQL's exclusion constraint is the authoritative guard against overlapping showtimes in the same auditorium. `detectConflicts` in Task 1 is a UX affordance; the DB is the source of truth.

  ```php
  // database/migrations/2026_xx_xx_add_showtime_exclusion_constraint.php
  public function up(): void
  {
      DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
      DB::statement(<<<'SQL'
          ALTER TABLE showtimes
            ADD CONSTRAINT showtimes_no_overlap EXCLUDE USING gist (
              auditorium_id WITH =,
              tstzrange(start_time, end_time, '[)') WITH &&
            ) WHERE (cancelled_at IS NULL);
      SQL);
  }

  public function down(): void
  {
      DB::statement('ALTER TABLE showtimes DROP CONSTRAINT IF EXISTS showtimes_no_overlap');
      // btree_gist extension is left in place — dropping a shared extension is not safe.
  }
  ```

  The half-open `tstzrange(start, end, '[)')` matches `ShowtimeService::detectConflicts`'s half-open interval semantics — back-to-back transitions are allowed; any actual overlap is rejected. The partial index `WHERE (cancelled_at IS NULL)` scopes the constraint to live showtimes only, so cancelling a showtime frees its slot for rescheduling without manual cleanup.

  Violations raise Postgres SQLSTATE `23P01` (exclusion_violation), which `ShowtimeService` (Task 1) translates into `ShowtimeConflictException`.

  **bookings table:**
  ```php
  $table->timestamp('flagged_at')->nullable()->after('status');
  $table->string('flag_reason')->nullable()->after('flagged_at');
  $table->text('notes')->nullable()->after('flag_reason');
  ```

  The `notes` column backs the "Mark refunded (manual)" resolution flow in Task 6. Long-form text uses `text` rather than `string` since resolution notes can be paragraph-length.

  **Generalized `dispatch_outbox` table (additive migration — always):**

  Task 5's showtime-cancellation notifications need at-least-once delivery even when Redis is unreachable at the moment `afterCommit` runs. The direct `dispatch()` path has no retry safety net — a dispatch failure silently drops the job. The outbox pattern closes this gap: the DB row and the "need to notify" intent are written in the same transaction; a worker (wired in Plan 09) drains the table and dispatches the jobs.

  This table is **generalized** from the start — not showtime-cancellation-specific — so future features (gift-card voids, loyalty notifications, menu-item availability changes) reuse it without a new migration each time.

  ```php
  // database/migrations/2026_xx_xx_create_dispatch_outbox_table.php
  Schema::create('dispatch_outbox', function (Blueprint $table) {
      $table->id();
      $table->string('event_type', 100);           // e.g., 'showtime.cancelled'
      $table->jsonb('payload');                    // event-specific body; schema owned by the event type
      $table->timestamp('available_at')->useCurrent();  // earliest time to dispatch (for delayed events)
      $table->timestamp('processed_at')->nullable();
      $table->timestamp('failed_at')->nullable();
      $table->unsignedSmallInteger('attempts')->default(0);
      $table->text('last_error')->nullable();
      $table->timestamps();

      // Worker index: find unprocessed rows ready to dispatch, ordered by creation
      $table->index(['processed_at', 'available_at', 'event_type'], 'dispatch_outbox_pending_idx');
  });
  ```

  The corresponding shared-domain model `FinalCut\Domain\Models\DispatchOutbox` declares the columns as fillable (except `attempts` / `failed_at` / `last_error`, which only the worker writes) and provides `payload` JSON casting. A `dispatchable()` query scope returns rows ready for the worker: `processed_at IS NULL AND available_at <= now() AND attempts < 5`.

  Using nullable timestamps (`cancelled_at`, `flagged_at`, `processed_at`, `failed_at`) instead of booleans follows project convention (CLAUDE.md "Booleans as timestamps").

  Update backend queries that list showtimes for customers to filter `whereNull('cancelled_at')` — ensure the customer API does not show cancelled showtimes. This touches `ShowtimeController` and `MovieController@showtimes`.

  Update `ModelParityTest` — add `DispatchOutbox` to the mirrored models list so admin-side cast parity is enforced. No changes needed for the showtimes/bookings additions (test is column-generic).

- **Acceptance Criteria:**
  - [ ] Environment-state check documented in PR description (pre-launch clean vs. additive path chosen for showtimes/bookings edits)
  - [ ] `showtimes.cancelled_at` and `cancellation_reason` added
  - [ ] `showtimes` composite index on `(auditorium_id, start_time)` exists
  - [ ] `btree_gist` extension enabled on the database (check via `SELECT * FROM pg_extension WHERE extname = 'btree_gist'`)
  - [ ] `showtimes_no_overlap` EXCLUDE constraint exists — attempting to insert two overlapping showtimes for the same auditorium raises SQLSTATE 23P01
  - [ ] EXCLUDE constraint is partial (`WHERE cancelled_at IS NULL`) — cancelling a showtime frees its slot
  - [ ] `bookings.flagged_at`, `flag_reason`, and `notes` added
  - [ ] `dispatch_outbox` table created with documented columns and the pending-rows index
  - [ ] `FinalCut\Domain\Models\DispatchOutbox` declares fillable / casts and a `dispatchable()` scope
  - [ ] Customer-facing showtime queries filter cancelled
  - [ ] Models expose new fields; `DispatchOutbox` added to `ModelParityTest` mirrored list
  - [ ] `ModelParityTest` passes

---

### Task 3: ShowtimeResource (MVP — Filament table + form)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/ShowtimeResource.php` (new)
  - `admin/app/Filament/Resources/ShowtimeResource/Pages/*` (list, create, edit, view)
- **Details:**
  Extends `BaseResource` with `$permissionPrefix = 'showtimes'`.

  **Form schema:** the cascading location → auditorium select is a real cascade, not just a label hint. `location_id` is a live form field (not persisted on the showtime — `auditorium_id` already resolves to a location via its foreign key) that filters the `auditorium_id` options as the user picks a location.

  ```php
  Section::make('Identity')->schema([
      Select::make('movie_id')
          ->relationship('movie', 'title')
          ->searchable()
          ->preload()
          ->required()
          ->reactive(),
      Select::make('location_id')
          ->label('Location')
          ->options(fn () => Location::orderBy('name')->pluck('name', 'id'))
          ->required()
          ->reactive()
          ->dehydrated(false) // form-only; not persisted on showtimes
          ->afterStateUpdated(fn ($set) => $set('auditorium_id', null))
          ->default(fn ($record) => $record?->auditorium?->location_id),
      Select::make('auditorium_id')
          ->relationship(
              name: 'auditorium',
              titleAttribute: 'name',
              modifyQueryUsing: fn ($query, $get) => $query
                  ->when($get('location_id'), fn ($q, $locId) => $q->where('location_id', $locId))
                  ->orderBy('name'),
          )
          ->searchable()
          ->preload()
          ->required()
          ->reactive()
          ->disabled(fn ($get) => ! $get('location_id')),
      DateTimePicker::make('start_time')
          ->required()
          ->seconds(false)
          ->minutesStep(5)
          ->reactive(),
      Placeholder::make('computed_end_time')
          ->label('End Time')
          ->content(fn ($get) => static::computeEndTime($get))
          ->reactive(),
  ])->columns(2),

  Section::make('Pricing (cents)')->schema([
      TextInput::make('price_standard')->numeric()->suffix(' ¢')->required()
          ->helperText('Store as cents: $12.99 → 1299'),
      TextInput::make('price_premium')->numeric()->suffix(' ¢')->required(),
      TextInput::make('price_accessible')->numeric()->suffix(' ¢')->required(),
  ])->columns(3),
  ```

  `computeEndTime` helper reads `movie_id`, `auditorium_id`, and `start_time` and returns a human-readable end time from service logic. It must state **why** a value is unavailable rather than silently rendering "—", because scheduling decisions hinge on it:

  - Any of the three fields empty → `"Pick a movie, auditorium, and start time to preview."`
  - Movie picked, but `movies.runtime` is `NULL` → `"This movie has no runtime set — edit the movie to add one before scheduling."` (link to the movie edit page)
  - Auditorium picked, `cleanup_minutes` resolvable → render `end_time` plus a subtle hint: `"9:30 PM (includes 20 min cleanup)"`
  - Any other service error → surface the exception message; don't swallow it.

  **Form validation:**
  - Custom rule on submit: call `ShowtimeService::detectConflicts()` and fail validation with a friendly message listing conflicting showtimes if any.

  **Table:**
  ```php
  TextColumn::make('movie.title')->searchable()->sortable(),
  TextColumn::make('auditorium.location.name')->label('Location')->sortable(),
  TextColumn::make('auditorium.name')->label('Auditorium')->sortable(),
  TextColumn::make('start_time')->dateTime()->sortable(),
  TextColumn::make('end_time')->dateTime()->toggleable(isToggledHiddenByDefault: true),
  TextColumn::make('price_standard')->formatStateUsing(fn ($s) => CurrencyFormatter::format($s))->label('Std'),
  BadgeColumn::make('status')
      ->getStateUsing(fn ($r) => $r->cancelled_at ? 'cancelled' : ($r->start_time->isPast() ? 'past' : 'scheduled'))
      ->colors(['success' => 'scheduled', 'gray' => 'past', 'danger' => 'cancelled']),
  ```

  **Filters:**
  - By location (cascade into auditorium)
  - By auditorium
  - By movie
  - By date range (default: next 7 days)
  - By status (scheduled / cancelled / past)

  **Actions:**
  - View, Edit (hidden for cancelled/past)
  - Cancel (Task 5)
  - Bulk create (Task 4) — header action

  `handleRecordCreation` / `handleRecordUpdate` call `ShowtimeService` facade.

- **Acceptance Criteria:**
  - [ ] Resource lists showtimes with movie/location/auditorium/time
  - [ ] Form has a real `location_id` select that filters `auditorium_id` options on change
  - [ ] Changing `location_id` clears any previously selected auditorium
  - [ ] `auditorium_id` is disabled until `location_id` is set
  - [ ] End time preview states the reason when it cannot be computed (missing input, missing runtime, service error)
  - [ ] Scheduling a movie with no runtime is blocked with a message linking to the movie edit page
  - [ ] Conflict validation fails submission with readable error listing conflicting showtimes
  - [ ] Filters work (location, auditorium, movie, date range, status)
  - [ ] Pricing stored/displayed in cents
  - [ ] Default sort: upcoming first

---

### Task 4: Bulk create dialog

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Resources/ShowtimeResource/Pages/BulkCreateShowtimes.php` (new)
  - `admin/app/Http/Requests/BulkShowtimeRequest.php` (data object)
- **Details:**
  Custom Filament page invoked from a header action on the showtime list. Generates many showtimes from a declarative config.

  **Form:**
  ```php
  Select::make('movie_id')->relationship('movie', 'title')->required()->searchable(),
  Select::make('auditorium_id')
      ->relationship('auditorium', 'name', fn ($q) => $q->with('location'))
      ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->location->name} — {$r->name}")
      ->required(),

  Section::make('Date Range')->schema([
      DatePicker::make('start_date')->required()->minDate(today()),
      DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
      CheckboxList::make('days_of_week')
          ->options([
              'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
              'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
          ])
          ->required(),
  ])->columns(3),

  Section::make('Times of Day')->schema([
      TagsInput::make('times')
          ->placeholder('19:00, 21:30')
          ->helperText('24-hour format, comma-separated')
          ->required(),
  ]),

  Section::make('Pricing (cents)')->schema([
      TextInput::make('price_standard')->numeric()->required(),
      TextInput::make('price_premium')->numeric()->required(),
      TextInput::make('price_accessible')->numeric()->required(),
  ])->columns(3),

  Placeholder::make('preview')
      ->label('Preview')
      ->content(fn ($get) => static::previewCount($get) . ' showtimes will be created')
      ->reactive(),
  ```

  **Submit handler — explicit transaction boundary:** pre-check selects a subset; the transaction applies to that subset and is all-or-nothing within it.

  1. Build array of (date, time) tuples from date range × days_of_week × times.
  2. Call `ShowtimeService::detectConflicts()` for each tuple. Split the tuples into **`conflicting`** and **`creatable`**.
  3. If `conflicting` is non-empty:
      - Present an error modal listing every conflict (date, time, colliding showtime).
      - The admin's only choice is **"Skip conflicts and create the rest"** (confirms creating `creatable` only) or **Cancel**. There is no "force-create" path.
  4. If the admin confirms (or there were no conflicts from the start), call `ShowtimeService::bulkCreate($creatable)` inside a **single DB transaction**.
      - The transaction's scope is exactly the `creatable` subset — nothing else.
      - If **any** creation inside the transaction fails (DB error, unexpected service exception, activity log failure), the **entire `creatable` subset rolls back** — no partial-success writes. Previously-skipped conflicts were never candidates for creation, so they are unaffected.
  5. Redirect to showtimes list with a notification: `"Created {N}, skipped {M} due to conflicts."` On transaction failure, stay on the page with an error notification and the form state preserved.

- **Acceptance Criteria:**
  - [ ] Form captures movie, auditorium, date range, days, times, pricing
  - [ ] Preview shows count of showtimes to create
  - [ ] Conflicts detected pre-submit, split into `conflicting` / `creatable` subsets
  - [ ] Admin can only proceed by skipping conflicts — no force-create
  - [ ] The transaction's scope is the `creatable` subset only
  - [ ] If any creation inside the transaction fails, the whole `creatable` subset rolls back; no partial writes land
  - [ ] Activity log entry per successfully created showtime (inside the same transaction)
  - [ ] On rollback, the admin is returned to the form with state preserved and a clear error

---

### Task 5: Cancel showtime action + affected-booking workflow

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Resources/ShowtimeResource.php` (modify — add cancel action)
  - `backend/app/Jobs/NotifyCustomerOfShowtimeCancellation.php` (new)
  - `backend/app/Mail/ShowtimeCancelledMail.php` (new)
  - `admin/resources/views/mail/showtime-cancelled.blade.php` (new)
  - `admin/app/Services/Backend/ShowtimeService.php` (modify — add cancel)
- **Details:**
  Cancel action on ShowtimeResource row:

  ```php
  Action::make('cancel')
      ->label('Cancel Showtime')
      ->icon('heroicon-o-x-circle')
      ->color('danger')
      ->visible(fn ($record) => auth()->user()->can('showtimes.cancel') && !$record->cancelled_at && $record->start_time->isFuture())
      ->form([
          Textarea::make('reason')->required()->label('Cancellation reason')
              ->helperText('Customers will not see this; it is logged for staff reference.'),
      ])
      ->requiresConfirmation()
      ->modalDescription(fn ($record) =>
          'Cancelling this showtime will flag ' . $record->bookings()->count() . ' booking(s) for manual refund.')
      ->action(fn ($record, array $data) =>
          app(ShowtimeService::class)->cancel($record, $data['reason']))
      ->successNotificationTitle('Showtime cancelled. Follow-up queue updated.');
  ```

  **Service implementation (`ShowtimeService::cancel`):** the service must be idempotent — if a second admin submits cancellation while the first is still processing, the second call must be a no-op (or throw a domain exception the UI can render as "Already cancelled by {user} at {time}"). Customer email delivery uses the generalized `dispatch_outbox` (Task 2) — the cancellation row and the outbox row are written inside the same transaction, and Plan 09's worker drains the outbox to dispatch the actual jobs. This replaces the previous `DB::afterCommit` + direct `dispatch()` pattern, which silently dropped jobs when Redis was unreachable at the moment `afterCommit` fired.

  ```php
  public function cancel(Showtime $showtime, string $reason, Causer $causer): void
  {
      DB::transaction(function () use ($showtime, $reason, $causer) {
          // Pessimistic lock + re-read so two concurrent admins can't both "cancel"
          $fresh = Showtime::whereKey($showtime->id)->lockForUpdate()->first();

          if ($fresh->cancelled_at !== null) {
              // Idempotent no-op — surface as a domain exception so the UI can show
              // "Already cancelled by {causer} at {time}" and refresh the record.
              throw new ShowtimeAlreadyCancelledException($fresh);
          }

          $fresh->update([
              'cancelled_at' => now(),
              'cancellation_reason' => $reason,
          ]);

          // Flag affected bookings (only ones not already flagged — preserves prior flags)
          $fresh->bookings()->whereNull('flagged_at')->update([
              'flagged_at' => now(),
              'flag_reason' => "showtime_cancelled:{$fresh->id}",
          ]);

          $bookingIds = $fresh->bookings()->pluck('id');

          activity()->performedOn($fresh)
              ->causedBy($causer)
              ->withProperties(['reason' => $reason, 'flagged_bookings' => $bookingIds->count()])
              ->log('cancelled_showtime');

          // Write one outbox row per booking to notify. The worker (Plan 09) drains
          // these and dispatches NotifyCustomerOfShowtimeCancellation jobs. Writing
          // to the outbox inside the transaction means either everything commits
          // together (cancellation + outbox rows) or everything rolls back — no
          // scenario where customers get emailed about a cancellation that didn't
          // happen, and no scenario where a cancellation happens but emails are
          // silently dropped because Redis was briefly unreachable.
          foreach ($bookingIds as $id) {
              DispatchOutbox::create([
                  'event_type' => 'showtime.cancelled',
                  'payload' => [
                      'booking_id' => $id,
                      'showtime_id' => $fresh->id,
                  ],
              ]);
          }
      });
  }
  ```

  The outbox payload carries only IDs (not serialized models) so rows stay small and the worker reads fresh state when it dispatches. The outbox worker (wired in Plan 09) maps `event_type` to the corresponding job class — for `showtime.cancelled`, it dispatches `NotifyCustomerOfShowtimeCancellation::dispatch($payload['booking_id'])`.

  **Mail stub:**
  ```blade
  {{-- resources/views/mail/showtime-cancelled.blade.php --}}
  @component('mail::message')
  Hi {{ $booking->customer_name ?? 'there' }},

  Unfortunately, your {{ $booking->showtime->movie->title }} showing on
  {{ $booking->showtime->start_time->format('l F j, Y \a\t g:i a') }} has been cancelled.

  Our team will contact you within 2 business days about a refund to your original payment method.

  Your booking reference: **{{ $booking->confirmation_code }}**

  Questions? Reply to this email or call {{ $booking->showtime->auditorium->location->phone }}.

  Thanks for your understanding,
  {{ config('app.name') }}
  @endcomponent
  ```

  Uses the existing Mailpit SMTP service in dev.

- **Acceptance Criteria:**
  - [ ] Cancel action visible only for future, non-cancelled showtimes
  - [ ] Reason required
  - [ ] Confirm dialog shows affected booking count
  - [ ] Service sets `cancelled_at` and flags all bookings atomically
  - [ ] Calling `cancel()` on an already-cancelled showtime throws `ShowtimeAlreadyCancelledException` (idempotent — no double-flag, no duplicate outbox rows)
  - [ ] One `dispatch_outbox` row written per flagged booking, inside the same transaction as the cancellation — transactional rollback removes the outbox rows along with everything else
  - [ ] Simulated Redis unavailability at the time of cancellation does not drop notifications — outbox rows persist, worker delivers when Redis returns
  - [ ] Mailpit captures the emails in dev once the worker processes the outbox row
  - [ ] Activity log entry recorded with explicit `causedBy($causer)`

---

### Task 6: Cancelled-showtime follow-up queue page

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Pages/CancellationFollowupQueue.php` (new)
- **Details:**
  Dedicated page listing every booking with `flagged_at IS NOT NULL AND status != 'refunded'`. This is the manual finance queue. Staff work through it out-of-band until Stripe refund integration lands in v2.

  ```php
  class CancellationFollowupQueue extends Page implements HasTable
  {
      use InteractsWithTable;

      protected static string $view = 'filament.pages.cancellation-followup-queue';
      protected static ?string $slug = 'cancelled-showtime-followup';
      protected static ?string $title = 'Cancellation Follow-up';
      protected static ?string $navigationGroup = 'Operations';
      protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

      public static function canAccess(): bool
      {
          // Viewing the queue requires bookings.view; the resolution action below
          // requires the narrower bookings.resolve_refund permission.
          return auth()->user()?->can('bookings.view') ?? false;
      }

      public static function getNavigationBadge(): ?string
      {
          return Booking::whereNotNull('flagged_at')
              ->whereNotIn('status', ['refunded'])
              ->count() ?: null;
      }

      public function table(Table $table): Table
      {
          return $table
              ->query(Booking::query()
                  ->whereNotNull('flagged_at')
                  ->whereNotIn('status', ['refunded'])
                  ->with('showtime.movie', 'showtime.auditorium.location'))
              ->columns([
                  TextColumn::make('confirmation_code')->searchable()->copyable(),
                  TextColumn::make('customer_email')->searchable(),
                  TextColumn::make('showtime.movie.title')->label('Movie'),
                  TextColumn::make('showtime.start_time')->label('Originally scheduled')->dateTime(),
                  TextColumn::make('total_cents')->label('Amount')
                      ->formatStateUsing(fn ($s) => CurrencyFormatter::format($s)),
                  TextColumn::make('flagged_at')->label('Flagged')->since(),
                  TextColumn::make('flag_reason'),
              ])
              ->actions([
                  Action::make('mark_resolved')
                      ->label('Mark refunded (manual)')
                      ->icon('heroicon-o-check')
                      ->visible(fn () => auth()->user()?->can('bookings.resolve_refund') ?? false)
                      ->requiresConfirmation()
                      ->modalDescription('This records a manual refund. It does not issue a Stripe refund — v1 refunds are handled out-of-band.')
                      ->form([
                          Textarea::make('notes')
                              ->label('Resolution notes')
                              ->required()
                              ->minLength(10)
                              ->helperText('Required. Include Stripe refund reference or reason this is being closed without a refund.'),
                      ])
                      ->action(function ($record, array $data) {
                          // bookings.notes is added in Task 2; this write is valid only
                          // after that migration runs.
                          $record->update([
                              'status' => 'refunded',
                              'notes' => $data['notes'],
                          ]);
                          activity()->performedOn($record)
                              ->withProperties(['notes' => $data['notes']])
                              ->log('manually_marked_refunded');
                      }),
              ])
              ->filters([
                  Filter::make('recent')->query(fn ($q) => $q->where('flagged_at', '>=', now()->subDays(30))),
              ]);
      }
  }
  ```

  Navigation badge surfaces pending count so the queue is visible at a glance.

  **Permission note:** `bookings.resolve_refund` is a **new** permission this plan introduces. It must be registered in Plan 02's permission seeder and assigned only to roles authorized to close out refund cases (e.g., `manager`, `finance`). `bookings.view` alone must not imply the ability to mark bookings as refunded — status-to-refunded is a financial write, not a read-side convenience, and gating it separately keeps ops/support roles from inadvertently closing cases. Add this to Plan 02's permission matrix in the same PR that ships Task 6.

  `bookings.notes` is introduced in Task 2 — Task 6 depends on that migration having run.

- **Acceptance Criteria:**
  - [ ] Page at `/admin/cancelled-showtime-followup`
  - [ ] Lists all flagged-and-not-yet-refunded bookings
  - [ ] Navigation badge shows pending count
  - [ ] "Mark refunded" action requires the new `bookings.resolve_refund` permission and is hidden for users without it
  - [ ] `bookings.resolve_refund` is registered in the Plan 02 permission seeder and assigned to the intended roles
  - [ ] Resolution notes are required, min 10 characters, and written to `bookings.notes`
  - [ ] Activity log captures the resolution with the full note

---

### Task 7: Visual schedule planner (optional second pass)

- **MoSCoW:** Could Have (ships only if budget allows)
- **Complexity:** L
- **Files:**
  - `admin/app/Filament/Pages/SchedulePlanner.php` (new)
  - `admin/resources/views/filament/pages/schedule-planner.blade.php` (new)
- **Details:**
  Weekly calendar view per auditorium. Each auditorium is a column; days are rows. Drag a movie into an empty slot to create a showtime; click an existing showtime to edit.

  Built on the same `ShowtimeService`, so MVP and visual planner operate on identical data.

  **Explicit non-goal in this plan if budget tight:** Document as "deferred to admin-v2" and remove from plan scope. The MVP resource (Task 3) + bulk create (Task 4) cover core operations.

- **Acceptance Criteria (only if shipped):**
  - [ ] Planner at `/admin/schedule`
  - [ ] Week navigation + auditorium filter
  - [ ] Drag to create uses the same service
  - [ ] Click to edit opens modal
  - [ ] Conflict visualization highlights collisions

---

### Task 8: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/ShowtimeResourceTest.php` (new)
  - `admin/tests/Feature/Resources/ShowtimeResourcePermissionTest.php` (new)
  - `admin/tests/Feature/Pages/BulkCreateShowtimesTest.php` (new)
  - `admin/tests/Feature/Pages/CancellationFollowupQueueTest.php` (new)
  - `admin/tests/Feature/ShowtimeCancellationFlowTest.php` (new — integration)
- **Details:**
  **ShowtimeResourceTest:**
  - List, create with conflict validation (success + failure), edit, delete hidden for past showtimes

  **BulkCreateShowtimesTest:**
  - Form submission creates correct number of showtimes
  - Conflict pre-check surfaces overlaps
  - "Skip conflicts" path creates non-conflicting subset
  - **Partial-conflict + subset-transaction-failure:** seed the schedule so a bulk request produces both `conflicting` and `creatable` tuples; admin chooses "skip conflicts"; the first tuple of the `creatable` subset succeeds but the second triggers a simulated service failure (e.g., activity log write throws). Assert: the entire `creatable` subset rolled back (zero new showtimes in DB), the pre-existing conflicting showtimes are untouched, the admin lands back on the form with a clear error, and no activity log entries were persisted for the attempted batch.

  **ShowtimeCancellationFlowTest (integration):**
  - Cancelling a showtime flags all its bookings
  - One `dispatch_outbox` row per booking is written inside the same transaction
  - Forced transaction rollback (e.g., simulated activity log write failure) removes the outbox rows along with the cancellation — no orphaned outbox entries
  - With Redis unavailable at cancel time (simulated by pointing the queue connection at an unreachable host): outbox rows still persist, and when the worker (invoked manually in the test) runs with Redis restored, the jobs dispatch
  - Second `cancel()` call on the same showtime throws `ShowtimeAlreadyCancelledException`, bookings are not re-flagged, no duplicate outbox rows
  - Cancelled showtime hidden from customer-facing API (backend integration)
  - Activity log entry created with `causer_id` = acting admin, `causer_type` = `AdminUser`

  **ShowtimeConflictConcurrencyTest (integration):**
  - Two parallel transactions insert overlapping showtimes for the same auditorium → first commits, second fails with `ShowtimeConflictException` (SQLSTATE 23P01 → domain exception)
  - `detectConflicts` returning empty does not guarantee insert success — a concurrent insert between the pre-check and the actual insert correctly fails on the EXCLUDE constraint
  - Bulk-create path: if a concurrent insert lands between `detectConflicts` and the batch insert, the entire batch rolls back with an error surfacing which tuple conflicted
  - Cancelling a showtime frees its slot — a new showtime at the same time can be inserted after the cancellation commits (partial EXCLUDE constraint verified)

  **CancellationFollowupQueueTest:**
  - Only shows flagged-not-refunded bookings
  - "Mark refunded" transitions status, writes `notes`, and logs
  - Users without `bookings.resolve_refund` cannot see or invoke the action even if they can view the queue

  **Permission tests:** ops cannot cancel, cannot bulk create, cannot resolve refunds; manager can do all three.

- **Acceptance Criteria:**
  - [ ] All test files green
  - [ ] Cancellation integration covers every state transition, including outbox persistence and worker-driven dispatch under simulated Redis unavailability
  - [ ] Concurrency test (`ShowtimeConflictConcurrencyTest`) exercises the EXCLUDE constraint against parallel transactions — this is the TOCTOU guard
  - [ ] Queue page tests verify filtering correctness
  - [ ] Permission matrix covered

---

## Testing Requirements

- **Pest Feature Tests:** CRUD, conflict detection, bulk create, cancellation flow, follow-up queue, permission matrix
- **Integration:** cancellation end-to-end (service → DB → job → email → queue page)
- **Backend service tests:** Task 1 ensures `ShowtimeService` has independent coverage

## Dependencies Map

```
Task 1 (ShowtimeService) ← needs Plan 04 + Plan 05 (cleanup_minutes)
Task 2 (migrations) ← needs Task 1 design
Task 3 (Resource MVP) ← needs Tasks 1, 2
Task 4 (bulk create) ← needs Task 3
Task 5 (cancel action) ← needs Tasks 2, 3
Task 6 (follow-up queue) ← needs Tasks 2, 5
Task 7 (visual planner) ← needs Task 3 — OPTIONAL
Task 8 (tests) ← needs all
```

## Risks & Open Questions

1. **Conflict detection cost.** On large schedules (500+ showtimes), the overlap query may be slow. Addressed in Task 2 by adding a composite `(auditorium_id, start_time)` index on `showtimes`. The `EXCLUDE USING gist` constraint has its own gist index and scales independently — a sequential `INSERT` cost of O(log n) per row rather than O(n). Revisit if query plans still show sequential scans under seeded load.
2. **Email template review.** The stubbed cancellation email copy is functional but not marketing-approved. Plan 09 adds proper templates; Task 5's stub is placeholder text.
3. **Queue + outbox worker.** Requires two workers running in admin-worker: `queue:work` for actual job execution and a scheduled/long-running outbox processor. Plan 09 wires both. Acceptance: outbox rows drained within 60s under normal load; rows with `attempts >= 5` and non-null `failed_at` page on-call.
4. **Backend showtime queries.** Task 2 requires updating multiple backend queries to filter `cancelled_at`. Miss one and cancelled showtimes still appear for customers. Add a Pest test in backend to assert customer-facing endpoints exclude cancelled showtimes.
5. **Racing with live bookings.** If a customer books the last seat while admin is cancelling the showtime, the booking is created then immediately flagged. This is correct behavior but may confuse the customer (they get a confirmation and a cancellation email seconds apart). Document as acceptable v1 behavior; v2 could pre-lock the showtime during cancellation.
6. **`btree_gist` availability.** The EXCLUDE constraint requires the `btree_gist` extension, which ships with PostgreSQL but isn't enabled by default. The migration enables it via `CREATE EXTENSION IF NOT EXISTS`. Confirm the database user has the `CREATE` privilege on the database (needed for extension installation) — in most setups this is true, but managed Postgres services sometimes require extensions to be pre-enabled via their control plane. Check at migration-run time, not in production.
7. **Outbox table growth.** The `dispatch_outbox` table grows unboundedly if processed rows aren't pruned. Add a `dispatch_outbox:prune` scheduled command that deletes `processed_at < now() - 30 days` rows, wired in Plan 09 alongside `activitylog:clean`.
