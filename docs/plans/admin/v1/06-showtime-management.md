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

### Task 1: Audit/extract backend ShowtimeService

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `backend/app/Services/ShowtimeService.php` (new)
  - `backend/tests/Feature/ShowtimeServiceTest.php` (new)
  - `admin/app/Services/Backend/ShowtimeService.php` (modify)
- **Details:**
  Audit backend for existing showtime handling. Extract into a service with:

  ```php
  class ShowtimeService
  {
      public function create(array $attributes): Showtime;
      public function update(Showtime $showtime, array $attributes): Showtime;
      public function cancel(Showtime $showtime, string $reason): void; // Task 5
      public function bulkCreate(BulkShowtimeRequest $request): Collection; // Task 4
      public function detectConflicts(int $auditoriumId, Carbon $start, Carbon $end, ?int $ignoreShowtimeId = null): Collection;
  }
  ```

  **Conflict detection:**
  ```php
  public function detectConflicts(int $auditoriumId, Carbon $start, Carbon $end, ?int $ignoreShowtimeId = null): Collection
  {
      return Showtime::where('auditorium_id', $auditoriumId)
          ->when($ignoreShowtimeId, fn ($q) => $q->where('id', '!=', $ignoreShowtimeId))
          ->whereNull('cancelled_at')
          ->where(function ($q) use ($start, $end) {
              $q->whereBetween('start_time', [$start, $end])
                  ->orWhereBetween('end_time', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_time', '<=', $start)
                          ->where('end_time', '>=', $end);
                  });
          })
          ->get();
  }
  ```

  **End-time calculation:** `end_time = start_time + movie.runtime + auditorium.cleanup_minutes` (all in minutes). Service computes this automatically on create/update so admin doesn't pass `end_time` manually.

  Update admin facade.

- **Acceptance Criteria:**
  - [ ] `ShowtimeService` exists with documented methods
  - [ ] End-time computed from movie runtime + auditorium cleanup
  - [ ] Conflict detection returns overlapping showtimes
  - [ ] Ignore-self logic for updates works
  - [ ] Backend tests cover conflict edge cases (back-to-back, exact-overlap, nested)
  - [ ] Admin facade delegates correctly

---

### Task 2: Add `cancelled_at` and `flagged_at` columns

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/database/migrations/*_create_showtimes_table.php` (modify in-place)
  - `backend/database/migrations/*_create_bookings_table.php` (modify in-place)
  - `backend/app/Models/Showtime.php` (modify — fillable, casts)
  - `backend/app/Models/Booking.php` (modify — fillable, casts)
  - `admin/app/Models/Showtime.php` (mirror)
  - `admin/app/Models/Booking.php` (mirror)
- **Details:**
  Pre-launch migration edits per project convention.

  **showtimes table:**
  ```php
  $table->timestamp('cancelled_at')->nullable()->after('end_time');
  $table->string('cancellation_reason')->nullable()->after('cancelled_at');
  ```

  **bookings table:**
  ```php
  $table->timestamp('flagged_at')->nullable()->after('status');
  $table->string('flag_reason')->nullable()->after('flagged_at');
  ```

  Using nullable timestamps (`cancelled_at`, `flagged_at`) instead of booleans follows project convention (CLAUDE.md "Booleans as timestamps").

  Update backend queries that list showtimes for customers to filter `whereNull('cancelled_at')` — ensure the customer API does not show cancelled showtimes. This touches `ShowtimeController` and `MovieController@showtimes`.

  Update `ModelParityTest` — no changes needed (test is generic).

- **Acceptance Criteria:**
  - [ ] `showtimes.cancelled_at` and `cancellation_reason` added
  - [ ] `bookings.flagged_at` and `flag_reason` added
  - [ ] Customer-facing showtime queries filter cancelled
  - [ ] Models expose new fields
  - [ ] ModelParityTest passes

---

### Task 3: ShowtimeResource (MVP — Filament table + form)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/ShowtimeResource.php` (new)
  - `admin/app/Filament/Resources/ShowtimeResource/Pages/*` (list, create, edit, view)
- **Details:**
  Extends `BaseResource` with `$permissionPrefix = 'showtimes'`.

  **Form schema:**
  ```php
  Section::make('Identity')->schema([
      Select::make('movie_id')
          ->relationship('movie', 'title')
          ->searchable()
          ->preload()
          ->required()
          ->reactive(),
      Select::make('auditorium_id')
          ->relationship('auditorium', 'name', fn ($q) => $q->with('location'))
          ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->location->name} — {$r->name}")
          ->searchable()
          ->preload()
          ->required()
          ->reactive(),
      DateTimePicker::make('start_time')
          ->required()
          ->seconds(false)
          ->minutesStep(5),
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

  `computeEndTime` helper reads `movie_id`, `auditorium_id`, `start_time` and returns a human-readable end time from service logic (or "—" if inputs incomplete).

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
  - [ ] Form with cascading location → auditorium select
  - [ ] End time computed live from service logic
  - [ ] Conflict validation fails submission with readable error
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

  **Submit handler:**
  - Build array of (date, time) tuples from date range × days_of_week × times
  - For each tuple, call `ShowtimeService::detectConflicts()` to pre-check
  - If any conflicts, present them in an error modal with a "Skip conflicts and create the rest" button
  - Otherwise call `ShowtimeService::bulkCreate()` inside a transaction
  - Redirect to showtimes list with notification showing count created + count skipped

- **Acceptance Criteria:**
  - [ ] Form captures movie, auditorium, date range, days, times, pricing
  - [ ] Preview shows count of showtimes to create
  - [ ] Conflicts detected pre-submit with "skip conflicts" option
  - [ ] Successful submit creates all non-conflicting showtimes
  - [ ] Transaction rolls back on any service failure
  - [ ] Activity log entry per showtime created

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

  **Service implementation (`ShowtimeService::cancel`):**
  ```php
  public function cancel(Showtime $showtime, string $reason): void
  {
      DB::transaction(function () use ($showtime, $reason) {
          $showtime->update([
              'cancelled_at' => now(),
              'cancellation_reason' => $reason,
          ]);

          // Flag affected bookings
          $showtime->bookings()->whereNull('flagged_at')->update([
              'flagged_at' => now(),
              'flag_reason' => "showtime_cancelled:{$showtime->id}",
          ]);

          // Dispatch per-booking email job
          foreach ($showtime->bookings as $booking) {
              NotifyCustomerOfShowtimeCancellation::dispatch($booking);
          }

          activity()->performedOn($showtime)
              ->withProperties(['reason' => $reason, 'flagged_bookings' => $showtime->bookings->count()])
              ->log('cancelled_showtime');
      });
  }
  ```

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
  - [ ] Email job dispatched per booking
  - [ ] Mailpit captures the emails in dev
  - [ ] Activity log entry recorded

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
                      ->form([Textarea::make('notes')->label('Resolution notes')])
                      ->action(function ($record, array $data) {
                          $record->update(['status' => 'refunded', 'notes' => $data['notes']]);
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

- **Acceptance Criteria:**
  - [ ] Page at `/admin/cancelled-showtime-followup`
  - [ ] Lists all flagged-and-not-yet-refunded bookings
  - [ ] Navigation badge shows pending count
  - [ ] "Mark refunded" action transitions booking status
  - [ ] Activity log captures resolution

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

  **ShowtimeCancellationFlowTest (integration):**
  - Cancelling a showtime flags all its bookings
  - Email job dispatched per booking (assert `Queue::assertPushed`)
  - Cancelled showtime hidden from customer-facing API (backend integration)
  - Activity log entry created

  **CancellationFollowupQueueTest:**
  - Only shows flagged-not-refunded bookings
  - "Mark refunded" transitions status and logs

  **Permission tests:** ops cannot cancel, cannot bulk create; manager can.

- **Acceptance Criteria:**
  - [ ] All test files green
  - [ ] Cancellation integration covers every state transition
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

1. **Conflict detection cost.** On large schedules (500+ showtimes), the overlap query may be slow. Verify the auditorium + start_time composite index exists — if not, add via a pre-launch migration edit on `showtimes`.
2. **Email template review.** The stubbed cancellation email copy is functional but not marketing-approved. Plan 09 adds proper templates; Task 5's stub is placeholder text.
3. **Scheduler for email job.** Requires a queue worker running. Document in Plan 09's deployment hardening — needs `php artisan queue:work` supervisor process in the admin container.
4. **Backend showtime queries.** Task 2 requires updating multiple backend queries to filter `cancelled_at`. Miss one and cancelled showtimes still appear for customers. Add a Pest test in backend to assert customer-facing endpoints exclude cancelled showtimes.
5. **Racing with live bookings.** If a customer books the last seat while admin is cancelling the showtime, the booking is created then immediately flagged. This is correct behavior but may confuse the customer (they get a confirmation and a cancellation email seconds apart). Document as acceptable v1 behavior; v2 could pre-lock the showtime during cancellation.
