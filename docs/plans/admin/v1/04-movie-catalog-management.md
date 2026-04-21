# Plan 04: Movie Catalog Management

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 03 (BaseResource, FormatsCurrency, TimestampColumns)
> **Unlocks:** Plan 06 (Showtime resource references Movie dropdown)

## Overview

Build the `MovieResource` — the first real Filament Resource in the admin panel. CRUD for movies with all enrichment-relevant fields (title, slug, tmdb_id, synopsis, tagline, runtime, rating, release_date, poster/backdrop URLs, trailer key, genres, cast). Adds a row action to trigger TMDB enrichment for a single movie via the existing `movies:enrich` artisan command, plus bulk actions for status changes. Includes a read-only upcoming-showtimes relation manager on the movie view page.

All mutations route through `App\Services\MovieService`, created in this plan and stored in `backend/app/Services/MovieService.php` alongside the existing `TmdbService`, `SeatAvailabilityService`, `StripeService`, `LoyaltyService`. Every write method accepts an optional `?AdminUser $actor = null` for audit attribution — Filament pages pass `auth('admin')->user()`; customer controllers pass `null`. The service writes `activity_log` rows when `$actor` is non-null and skips admin activity attribution otherwise.

Filament Resources consume `App\Models\Movie` directly — there is no admin-side model mirror, no shared package, no cross-app boundary. Service and model live in the same codebase and autoload via PSR-4.

## Reference Documents

- `docs/plans/backend/v1/03-movie-api.md` — backend movie API and TMDB enrichment
- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 admin-to-domain-logic boundary, § 5 Plan 04
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — BaseResource, FormatsCurrency, TimestampColumns
- `backend/app/Http/Controllers/Api/MovieController.php` — reference for extraction
- `backend/app/Services/TmdbService.php` — enrichment source

---

## Tasks

### Task 1: Create `MovieService` in `backend/app/Services/`

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/MovieService.php` (new)
  - `backend/app/Jobs/EnrichMovieJob.php` (new — if not already present)
  - `backend/app/Http/Controllers/Api/MovieController.php` (modify — delegate writes to the service)
  - `backend/tests/Unit/MovieServiceTest.php` (new)
- **Details:**
  Extract movie write orchestration out of the existing `MovieController` into a dedicated service. The customer API delegates to it; the admin panel (Task 2+) calls it too. Both call sites pass an optional `?AdminUser $actor`.

  Required methods:

  ```php
  namespace App\Services;

  use App\Jobs\EnrichMovieJob;
  use App\Models\AdminUser;
  use App\Models\Movie;
  use Illuminate\Support\Facades\Cache;

  class MovieService
  {
      /**
       * @param array{
       *   title: string, slug: string, status: 'now_showing'|'coming_soon',
       *   tmdb_id?: ?int, tagline?: ?string, synopsis?: ?string,
       *   runtime?: ?int, rating?: ?float, release_date?: ?string,
       *   poster_url?: ?string, backdrop_url?: ?string, trailer_key?: ?string,
       *   genres?: int[],                                                              // genre IDs for BelongsToMany::sync()
       *   cast?: array<int, array{name: string, character: string, profileUrl?: ?string}>  // JSON column payload
       * } $attributes
       */
      public function create(array $attributes, ?AdminUser $actor = null): Movie
      {
          $movie = Movie::create($this->assignableAttributes($attributes));

          if (isset($attributes['genres'])) {
              $movie->genres()->sync($attributes['genres']);
          }

          $this->logIfAdmin('movie.created', $movie, $actor, $attributes);

          return $movie->fresh(['genres']);
      }

      public function update(Movie $movie, array $attributes, ?AdminUser $actor = null): Movie
      {
          $original = $movie->getOriginal();
          $movie->fill($this->assignableAttributes($attributes))->save();

          if (array_key_exists('genres', $attributes)) {
              $movie->genres()->sync($attributes['genres']);
          }

          $this->logIfAdmin('movie.updated', $movie, $actor, [
              'before' => $original,
              'after' => $movie->getAttributes(),
          ]);

          return $movie->fresh(['genres']);
      }

      public function delete(Movie $movie, ?AdminUser $actor = null): void
      {
          $this->logIfAdmin('movie.deleted', $movie, $actor);
          $movie->delete();
      }

      public function triggerEnrichment(Movie $movie, ?AdminUser $actor = null): bool
      {
          $lockKey = "movie:enrich:{$movie->id}";
          $lock = Cache::lock($lockKey, 300);  // 5 minute TTL

          if (! $lock->get()) {
              return false;
          }

          EnrichMovieJob::dispatch($movie->id, $lockKey);
          $this->logIfAdmin('movie.enrichment_triggered', $movie, $actor);

          return true;
      }

      private function assignableAttributes(array $attributes): array
      {
          // Drop keys that the service handles via relationships, not mass-assignment.
          return array_diff_key($attributes, array_flip(['genres', 'cast_ordered']));
      }

      private function logIfAdmin(string $event, Movie $movie, ?AdminUser $actor, array $properties = []): void
      {
          if ($actor === null) return;

          activity('admin')
              ->causedBy($actor)
              ->performedOn($movie)
              ->withProperties($properties)
              ->log($event);
      }
  }
  ```

  **Input contract — genres and cast.** The `genres` key is an array of genre IDs; the service syncs the `movie->genres()` `BelongsToMany` relationship. The `cast` key is an ordered array of cast-member objects and is written to the `cast` JSON column (Eloquent cast handles the JSON serialization; the service does not split it into a separate table). Filament's form output in Task 3 (multi-select for genres, repeater for cast) must match these shapes.

  **`triggerEnrichment` contract.** Dispatches `EnrichMovieJob` so the admin request returns immediately. An idempotency lock (`movie:enrich:{id}`, 5 minute TTL) prevents duplicate dispatches from rapid clicks. If the lock is held, the method returns `false` without dispatching, and the admin UI surfaces a non-error notification ("Enrichment already in progress"). The lock is released by the job on completion.

  **Extraction principles:**
  - Validation stays at the HTTP boundary (Laravel `FormRequest` or `$request->validate()`) — the service accepts pre-validated arrays and enforces only domain invariants (unique slug at save time).
  - Mutation and orchestration move into the service — create the model, sync relationships, write JSON columns, dispatch enrichment, emit activity-log rows.
  - Existing customer API controllers continue to handle HTTP request parsing and response formatting; they pass `null` for `$actor` because customer API writes are not admin-attributed.

- **Acceptance Criteria:**
  - [ ] `App\Services\MovieService` exists with the four write methods and the documented `genres` / `cast` input shape
  - [ ] Every write method signature accepts `?AdminUser $actor = null` (last parameter)
  - [ ] `MovieController` delegates write operations to the service, passing `null` as `$actor`
  - [ ] `MovieService` tests cover create / update / delete / triggerEnrichment paths including genre sync, cast JSON round-trip, and idempotency
  - [ ] When `$actor` is set, each write emits an `activity_log` row with `causer` resolving to the admin user
  - [ ] When `$actor` is null, no `activity_log` row is written
  - [ ] `EnrichMovieJob` dispatches single-movie enrichment and releases the cache lock on completion
  - [ ] Existing backend test suite still green after the extraction

---

### Task 2: MovieResource (list, view, edit pages)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/MovieResource.php` (new)
  - `backend/app/Filament/Resources/MovieResource/Pages/ListMovies.php` (new)
  - `backend/app/Filament/Resources/MovieResource/Pages/CreateMovie.php` (new)
  - `backend/app/Filament/Resources/MovieResource/Pages/EditMovie.php` (new)
  - `backend/app/Filament/Resources/MovieResource/Pages/ViewMovie.php` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'movies'`.

  ```php
  namespace App\Filament\Resources;

  use App\Models\Movie;

  class MovieResource extends BaseResource
  {
      protected static ?string $model = Movie::class;
      protected static ?string $permissionPrefix = 'movies';
      protected static ?string $navigationIcon = 'heroicon-o-film';
      protected static ?string $navigationGroup = 'Catalog';
      protected static ?int $navigationSort = 10;

      public static function form(Form $form): Form { /* Task 3 */ }
      public static function table(Table $table): Table { /* Task 4 */ }

      public static function getRelations(): array
      {
          return [Pages\RelationManagers\UpcomingShowtimesRelationManager::class];
      }

      public static function getPages(): array
      {
          return [
              'index' => Pages\ListMovies::route('/'),
              'create' => Pages\CreateMovie::route('/create'),
              'view' => Pages\ViewMovie::route('/{record}'),
              'edit' => Pages\EditMovie::route('/{record}/edit'),
          ];
      }
  }
  ```

  Override `CreateMovie::handleRecordCreation` and `EditMovie::handleRecordUpdate` to call `MovieService` instead of letting Filament persist directly:

  ```php
  // CreateMovie.php
  protected function handleRecordCreation(array $data): Model
  {
      return app(\App\Services\MovieService::class)
          ->create($data, auth('admin')->user());
  }

  // EditMovie.php
  protected function handleRecordUpdate(Model $record, array $data): Model
  {
      return app(\App\Services\MovieService::class)
          ->update($record, $data, auth('admin')->user());
  }
  ```

  **Delete must route through the service too.** The table's `DeleteAction::make()` defaults to `$record->delete()` — a direct Eloquent write that bypasses the service and the audit-log attribution. Every delete call site in this Resource opts into the service via Filament's `->using()` hook:

  ```php
  DeleteAction::make()
      ->using(fn (Model $record) => app(\App\Services\MovieService::class)
          ->delete($record, auth('admin')->user()));
  ```

  This is a convention enforced by test (Task 6), not by static analysis. A stock `DeleteAction::make()` without `->using()` is a test-caught regression — the test asserts that `MovieService::delete` was called for any admin-originated delete.

- **Acceptance Criteria:**
  - [ ] Resource registers under Catalog navigation group
  - [ ] List / view / create / edit pages route correctly
  - [ ] `handleRecordCreation` and `handleRecordUpdate` call `MovieService`, passing `auth('admin')->user()` as actor
  - [ ] Every `DeleteAction` instance uses `->using()` to call `MovieService::delete`
  - [ ] Direct Eloquent writes removed from every mutation path on the Resource
  - [ ] Permission gating works per role (admin full, manager full, ops read-only)

---

### Task 3: Movie form schema

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/MovieResource.php` (modify — `form()` method)
- **Details:**

  ```php
  public static function form(Form $form): Form
  {
      return $form->schema([
          Section::make('Identity')
              ->schema([
                  TextInput::make('title')->required()->maxLength(255)
                      ->live(onBlur: true)
                      ->afterStateUpdated(function ($state, callable $set, ?Model $record) {
                          if ($record === null) {
                              $set('slug', Str::slug($state));
                          }
                      }),
                  TextInput::make('slug')->required()->unique(ignoreRecord: true)
                      ->helperText('Auto-derived from title on create. On edit the slug is stable — change it manually only if needed (public URLs depend on it).'),
                  Select::make('status')
                      ->options(['now_showing' => 'Now Showing', 'coming_soon' => 'Coming Soon'])
                      ->required(),
                  TextInput::make('tmdb_id')->numeric()->helperText('TMDB movie ID for metadata enrichment'),
              ])->columns(2),

          Section::make('Content')
              ->schema([
                  TextInput::make('tagline')->maxLength(500),
                  Textarea::make('synopsis')->rows(5),
                  TextInput::make('runtime')->numeric()->suffix('minutes'),
                  DatePicker::make('release_date'),
                  TextInput::make('rating')->numeric()->minValue(0)->maxValue(10)->step(0.1),
              ]),

          Section::make('Media')
              ->schema([
                  TextInput::make('poster_url')->url()->label('Poster URL'),
                  TextInput::make('backdrop_url')->url()->label('Backdrop URL'),
                  TextInput::make('trailer_key')->label('YouTube Trailer Key')
                      ->helperText('e.g., "dQw4w9WgXcQ" from youtube.com/watch?v=dQw4w9WgXcQ'),
              ])->columns(2),

          Section::make('Taxonomy')
              ->schema([
                  Select::make('genres')
                      ->multiple()
                      ->relationship('genres', 'name')
                      ->preload(),
              ]),

          Section::make('Cast')
              ->schema([
                  Repeater::make('cast')
                      ->schema([
                          TextInput::make('name')->required(),
                          TextInput::make('character')->required(),
                          TextInput::make('profileUrl')->url()->label('Profile URL'),
                      ])
                      ->columns(3)
                      ->reorderable()
                      ->collapsed(),
              ]),
      ]);
  }
  ```

  The genres multi-select emits an array of genre IDs; the cast repeater emits an array of `{ name, character, profileUrl }` objects that persists to the `cast` JSON column. Both shapes match `MovieService`'s input contract from Task 1.

  **Slug stability policy.** Title auto-slugs only on create. Editing a title on an existing record does not modify its slug — slugs feed public URLs and downstream references. Admins can still edit the slug by hand on the slug field itself.

- **Acceptance Criteria:**
  - [ ] All documented fields render
  - [ ] Title auto-slugs on blur during create only; editing a title on an existing record does not modify its slug
  - [ ] Slug validates uniqueness ignoring current record
  - [ ] TMDB ID optional but numeric-validated
  - [ ] Genres multi-select wired to relationship; payload is an array of genre IDs matching `MovieService` input contract
  - [ ] Cast repeater payload is an array of `{ name, character, profileUrl }` objects matching `MovieService` input contract and saves to the JSON column
  - [ ] Form submission routes through `MovieService`

---

### Task 4: Movie table (list page)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Filament/Resources/MovieResource.php` (modify — `table()` method)
- **Details:**

  ```php
  public static function table(Table $table): Table
  {
      return $table
          ->columns([
              ImageColumn::make('poster_url')->label('Poster')
                  ->defaultImageUrl('/images/movie-placeholder.png')
                  ->extraImgAttributes(['style' => 'max-height:3rem']),
              TextColumn::make('title')->searchable()->sortable(),
              TextColumn::make('status')
                  ->badge()
                  ->color(fn (string $state): string => match ($state) {
                      'now_showing' => 'success',
                      'coming_soon' => 'warning',
                  }),
              TextColumn::make('genres.name')->badge()->separator(','),
              TextColumn::make('runtime')->suffix(' min')->sortable(),
              TextColumn::make('rating')->formatStateUsing(fn ($state) => number_format($state, 1))->sortable(),
              TextColumn::make('tmdb_enriched_at')->label('Enriched')->since()->sortable(),
              ...TimestampColumns::standardTimestamps(),
          ])
          ->filters([
              SelectFilter::make('status')
                  ->options(['now_showing' => 'Now Showing', 'coming_soon' => 'Coming Soon']),
              SelectFilter::make('genres')->relationship('genres', 'name')->multiple(),
              Filter::make('needs_enrichment')
                  ->label('Never enriched')
                  ->query(fn (Builder $q) => $q->whereNull('tmdb_enriched_at')->whereNotNull('tmdb_id')),
          ])
          ->actions([
              ViewAction::make(),
              EditAction::make(),
              Action::make('enrich')
                  ->label('Enrich from TMDB')
                  ->icon('heroicon-o-arrow-path')
                  ->visible(fn ($record) => $record->tmdb_id && auth('admin')->user()->can('movies.trigger_enrich'))
                  ->requiresConfirmation()
                  ->action(function ($record) {
                      $dispatched = app(\App\Services\MovieService::class)
                          ->triggerEnrichment($record, auth('admin')->user());
                      Notification::make()
                          ->title($dispatched ? 'Enrichment queued' : 'Enrichment already in progress')
                          ->{$dispatched ? 'success' : 'warning'}()
                          ->send();
                  }),
              DeleteAction::make()
                  ->using(fn (Model $record) => app(\App\Services\MovieService::class)
                      ->delete($record, auth('admin')->user())),
          ])
          ->bulkActions([
              BulkAction::make('mark_now_showing')
                  ->label('Mark as Now Showing')
                  ->icon('heroicon-o-play')
                  ->visible(fn () => auth('admin')->user()->can('movies.update'))
                  ->action(fn (Collection $records) => $records->each(fn ($r) =>
                      app(\App\Services\MovieService::class)
                          ->update($r, ['status' => 'now_showing'], auth('admin')->user())
                  ))
                  ->deselectRecordsAfterCompletion(),
              BulkAction::make('mark_coming_soon')
                  ->label('Mark as Coming Soon')
                  ->icon('heroicon-o-clock')
                  ->visible(fn () => auth('admin')->user()->can('movies.update'))
                  ->action(fn (Collection $records) => $records->each(fn ($r) =>
                      app(\App\Services\MovieService::class)
                          ->update($r, ['status' => 'coming_soon'], auth('admin')->user())
                  )),
          ])
          ->defaultSort('release_date', 'desc');
  }
  ```

  **Bulk-action implementation note.** The `mark_now_showing` / `mark_coming_soon` bulk actions are implemented as N sequential single-record calls to `MovieService::update` — not a specialized batch method. Each record emits its own activity row and passes through the same domain code path as a single-record edit. For v1's expected admin usage this is acceptable; if real usage produces 100+ row bulk changes, revisit with a dedicated batch method.

  **Delete behavior.** `DeleteAction::make()->using(...)` routes deletion through `MovieService::delete`, passing the admin actor for audit attribution. A stock `DeleteAction::make()` without `->using()` is treated as a regression in tests (Task 6).

- **Acceptance Criteria:**
  - [ ] Poster thumbnail renders in column
  - [ ] Title searchable, sortable
  - [ ] Status badge colored correctly
  - [ ] Genre badges display from relationship
  - [ ] Filters: status, genres, "needs enrichment"
  - [ ] Row action: View, Edit, Enrich (gated on permission + tmdb_id), Delete
  - [ ] Enrich action surfaces a distinct "already in progress" notification when the service returns `false`
  - [ ] Delete action routes through `MovieService::delete` via `->using()` — no direct Eloquent delete
  - [ ] Bulk actions: mark status, calling `MovieService::update` once per selected record with the admin actor

---

### Task 5: UpcomingShowtimesRelationManager (read-only)

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Filament/Resources/MovieResource/RelationManagers/UpcomingShowtimesRelationManager.php` (new)
- **Details:**
  Read-only relation manager on the movie view page showing a **preview** of up to the next 20 upcoming showtimes. The intent is a quick "where is this playing right now" glance alongside the movie's detail view; it is not a full schedule view. Staff who need the complete showtime list navigate to the Showtimes resource filtered by movie (Plan 06).

  ```php
  namespace App\Filament\Resources\MovieResource\RelationManagers;

  use App\Filament\Concerns\FormatsCurrency;
  use Filament\Resources\RelationManagers\RelationManager;
  use Filament\Tables\Columns\TextColumn;
  use Filament\Tables\Table;

  class UpcomingShowtimesRelationManager extends RelationManager
  {
      use FormatsCurrency;

      protected static string $relationship = 'showtimes';
      protected static ?string $title = 'Upcoming Showtimes (next 20)';

      public function table(Table $table): Table
      {
          return $table
              ->query(fn () => $this->getOwnerRecord()->showtimes()
                  ->where('start_time', '>=', now())
                  ->orderBy('start_time')
                  ->limit(20))
              ->columns([
                  TextColumn::make('start_time')->dateTime()->sortable(),
                  TextColumn::make('auditorium.location.name')->label('Location'),
                  TextColumn::make('auditorium.name')->label('Auditorium'),
                  TextColumn::make('price_standard')
                      ->label('Standard')
                      ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
              ])
              ->headerActions([]) // no create
              ->actions([]); // no edit/delete
      }

      public function isReadOnly(): bool
      {
          return true;
      }
  }
  ```

- **Acceptance Criteria:**
  - [ ] Relation manager renders on the movie view page
  - [ ] Shows up to the next 20 future showtimes as a preview (title clearly labels it a preview, not a complete list)
  - [ ] No create/edit/delete actions available
  - [ ] Location and auditorium displayed via nested relationship
  - [ ] Price rendered via `FormatsCurrency::centsToDisplay`

---

### Task 6: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Feature/Admin/Resources/MovieResourceTest.php` (new)
  - `backend/tests/Feature/Admin/Resources/MovieResourcePermissionTest.php` (new)
  - `backend/tests/Feature/Admin/Services/MovieServiceIntegrationTest.php` (new)
- **Details:**
  Use Filament's Livewire test helpers. Tests split into two layers.

  **Layer A — Resource tests (service mocked).** Verify the Resource wires form / actions / permissions to the service. Mock `\App\Services\MovieService` via `$this->mock()` so no backend writes happen. These tests do not assert on `activity_log` — with a mocked service, no real mutation runs.

  **MovieResourceTest (service mocked):**
  - admin can list movies
  - admin can create a movie via form submission → asserts `MovieService::create` called with expected payload shape (including `genres` array and `cast` array) and `actor` = logged-in admin
  - admin can update a movie → asserts `MovieService::update` called with actor
  - **admin can delete a movie via the table `DeleteAction` → asserts `MovieService::delete` was called and `Model::delete()` was NOT called directly** (regression guard for stock `DeleteAction::make()` slipping in without `->using()`)
  - Enrich action dispatches the job for a movie with `tmdb_id` (asserts `MovieService::triggerEnrichment` returns `true`)
  - Enrich action surfaces "already in progress" notification when the service returns `false`
  - Enrich action hidden for movies without `tmdb_id`
  - Bulk action "mark as now_showing" calls `MovieService::update` once per selected record with actor
  - Editing a title on an existing record does not change its slug (slug stability)

  **MovieResourcePermissionTest (service mocked):**
  - ops cannot access create form (`canCreate` returns false)
  - ops cannot access edit page
  - ops cannot see enrich action
  - manager can perform all movie actions
  - nobody role cannot access list page

  **Layer B — Service integration tests (real service, real DB).** A small number of tests exercise the real `MovieService` end-to-end to verify activity-log attribution.

  **MovieServiceIntegrationTest:**
  - Creating a movie with `$actor` set writes an `activity_log` row with the expected description, causer, and subject
  - Creating a movie with `$actor = null` does NOT write an `activity_log` row
  - Updating a movie with `$actor` set writes an update activity row with the changed-attribute diff
  - Deleting a movie with `$actor` set writes a delete activity row
  - `triggerEnrichment` with `$actor` set writes an enrichment-triggered activity row
  - Second `triggerEnrichment` call within the cache-lock TTL returns `false`, does not dispatch a second job, and does not write a second activity row (idempotency)

- **Acceptance Criteria:**
  - [ ] Layer A Resource tests cover list, create, update, delete (including stock-DeleteAction regression guard), enrich (dispatched and skipped outcomes), bulk, and slug stability
  - [ ] Layer A PermissionTest covers all three roles × all actions
  - [ ] Layer A service is mocked — no real writes; no `activity_log` assertions at this layer
  - [ ] Layer B integration tests run the real `MovieService` and verify `activity_log` writes (including the `$actor = null` skip case and enrichment idempotency)
  - [ ] `make admin-test` passes all movie tests green

---

## Testing Requirements

- **Layer A (Resource, service mocked):** list / create / update / delete / enrich / bulk / slug stability + full permission matrix. No `activity_log` assertions.
- **Layer B (Service integration, real DB):** activity-log attribution with / without actor, enrichment idempotency, genre sync, cast JSON round-trip.
- **Backend service tests (Task 1):** create / update / delete / triggerEnrichment paths independent of Filament.

## Dependencies Map

```
Task 1 (MovieService extraction) ← foundational
Task 2 (Resource skeleton) ← needs Plan 03 BaseResource + Task 1
Task 3 (form schema) ← needs Task 2
Task 4 (table + actions) ← needs Tasks 2, 3
Task 5 (relation manager) ← needs Task 2
Task 6 (tests) ← needs all
```

## Risks & Open Questions

1. **TMDB enrichment job.** If `EnrichMovieJob` doesn't exist yet, create it so that `movies:enrich` (the existing scheduled command) and the per-movie enrichment trigger share the same job class. The scheduled command passes `null` for `$actor` (system-initiated); the admin panel passes `auth('admin')->user()`.
2. **JSON column editing.** Filament's repeater on the `cast` JSON column can be finicky with large casts (50+ members). TMDB enrichment caps at 12 members, so this is not a real risk — document the cap in a form helper text.
3. **Poster URL validation.** Currently just `url()` validation. Consider tightening to TMDB or a whitelist of image hosts. Deferred — staff-only tool, not a threat surface.
4. **Stock `DeleteAction` regression.** The write-boundary rule (all admin deletes go through the service for audit attribution) is enforced only by Layer A Resource tests, not by static analysis. A future contributor adding a `DeleteAction::make()` without `->using()` slips through to a direct `$record->delete()` with no audit row. The Task 6 regression test catches it before merge. If regressions happen repeatedly, escalate to a phpstan rule — but start with the test, keep tooling light.
