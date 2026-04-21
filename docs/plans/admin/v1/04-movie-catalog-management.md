# Plan 04: Movie Catalog Management

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 03 (shared-domain package scaffold, Movie mirror model, BaseResource, write-boundary enforcement)
> **Unlocks:** Plan 06 (Showtime resource references Movie dropdown)

## Overview

Build the `MovieResource` — the first real Filament Resource in the admin app. CRUD for movies with all enrichment-relevant fields (title, slug, tmdb_id, synopsis, tagline, runtime, rating, release_date, poster/backdrop URLs, trailer key, genres, cast). Adds a row action to trigger TMDB enrichment for a single movie via the existing `movies:enrich` artisan command, plus bulk actions for status changes. Includes a read-only upcoming-showtimes relation manager on the movie view page so staff can see what a movie is playing without leaving the screen.

All mutations route through `FinalCut\Domain\Services\MovieService` (extracted into the shared Composer package scaffolded by Plan 03). Every service write method accepts an explicit `Causer $causer` argument per the Plan 02 Task 4 contract; the admin-side facade resolves `auth()->user()` and passes it through so Filament page handlers remain ergonomic. The deptrac + phpstan rules from Plan 03 Task 7 enforce that Filament Resources cannot call Eloquent writes on shared-domain models directly — all mutations go through the facade.

## Reference Documents

- `docs/plans/backend/v1/03-movie-api.md` — backend movie API and TMDB enrichment
- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 write boundary, § 5 Plan 04
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — shared-domain package scaffold, Causer contract, write-boundary enforcement
- `backend/app/Http/Controllers/Api/MovieController.php` — reference for extraction
- `backend/app/Services/TmdbService.php` — enrichment source

---

## Tasks

### Task 1: Extract MovieService into the shared-domain package

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `packages/shared-domain/src/Services/MovieService.php` (new)
  - `packages/shared-domain/src/Jobs/EnrichMovieJob.php` (new — if not already extracted)
  - `backend/app/Http/Controllers/Api/MovieController.php` (modify — delegate to `FinalCut\Domain\Services\MovieService`)
  - `backend/tests/Feature/MovieServiceTest.php` (new or move to `packages/shared-domain/tests/`)
- **Details:**
  Per Plan 03's ADR (Option 1: shared Composer package), `MovieService` lives in `packages/shared-domain/src/Services/MovieService.php` under the `FinalCut\Domain\Services` namespace. Both admin and backend consume it as a normal Composer dependency. This task extracts the service from `MovieController` (which previously held the orchestration) into the shared package.

  Required methods — every write method takes an explicit `Causer $causer` argument per the Plan 02 Task 4 contract. The service writes `activity()->causedBy($causer)->log(...)` itself; no ambient guard reads.

  ```php
  namespace FinalCut\Domain\Services;

  use FinalCut\Domain\Audit\Causer;
  use FinalCut\Domain\Models\Movie;

  class MovieService
  {
      /**
       * @param array{
       *   title: string, slug: string, status: 'now_showing'|'coming_soon',
       *   tmdb_id?: ?int, tagline?: ?string, synopsis?: ?string,
       *   runtime?: ?int, rating?: ?float, release_date?: ?string,
       *   poster_url?: ?string, backdrop_url?: ?string, trailer_key?: ?string,
       *   genres?: int[],                                            // genre IDs — synced via BelongsToMany::sync()
       *   cast?: array<int, array{name: string, character: string, profileUrl?: ?string}>  // written to JSON column
       * } $attributes
       */
      public function create(array $attributes, Causer $causer): Movie;
      public function update(Movie $movie, array $attributes, Causer $causer): Movie;  // same attribute shape; partial updates allowed
      public function delete(Movie $movie, Causer $causer): void;
      public function triggerEnrichment(Movie $movie, Causer $causer): bool;  // true if dispatched, false if skipped (idempotency guard)
  }
  ```

  **Input contract — genres and cast.** The `genres` key (if present) is an array of genre IDs; the service syncs the `movie->genres()` `BelongsToMany` relationship via `sync()`. The `cast` key (if present) is an ordered array of cast-member objects and is written directly to the `cast` JSON column — the service does not split it into a separate table. Filament's form output in Task 4 (multi-select for genres, repeater for cast) must match these shapes exactly. Any transformation lives at the controller / Filament page layer, not inside the service.

  **`triggerEnrichment` contract.** Calls `TmdbService::enrichMovie()` via a queued job (`EnrichMovieJob`) so the admin request returns immediately. If the job class does not exist, create it — single-movie enrichment. **Idempotency guard:** before dispatching, set a short-lived cache lock keyed on `movie:enrich:{id}` with a TTL matching the enrichment's expected runtime (e.g., 5 minutes). If the lock is already held, the method returns `false` without dispatching, and the admin UI surfaces a non-error notification ("Enrichment already in progress"). This prevents duplicate jobs from rapid clicks or concurrent admin sessions. The lock is released at the end of the job (success or failure).

  Extraction principles:
  - **Validation stays at the HTTP boundary** — keep Laravel `FormRequest` classes (or inline `validate()` calls) in `MovieController`. The service accepts pre-validated associative arrays and performs no user-input validation. It may still enforce domain invariants (e.g., "slug must be unique at save time") as a last line of defense.
  - **Mutation and orchestration move into the service** — creating the model, syncing relationships, writing the cast JSON, dispatching enrichment, emitting audit rows via `activity()->causedBy($causer)->log(...)`.
  - Keep HTTP concerns (request parsing, response formatting) in the controller. The customer-facing controller resolves a `Causer` from the authenticated customer user (or a system `Causer` for unauthenticated writes, if any) and passes it explicitly to the service.
  - Preserve existing backend test coverage; add service-level tests for the extracted logic

  Update the progress journal's service extraction checklist.

- **Acceptance Criteria:**
  - [ ] `FinalCut\Domain\Services\MovieService` exists in `packages/shared-domain/src/Services/` with documented methods and the documented `genres` / `cast` input shape
  - [ ] Every write method signature declares an explicit `Causer $causer` parameter; no method uses `auth()->user()` or ambient guard reads
  - [ ] `MovieController` delegates write operations to the shared-domain service, passing an explicit `Causer`; request validation remains in `FormRequest` classes (or `$request->validate()`), not inside the service
  - [ ] Pest tests for `MovieService` cover create/update/delete/enrich paths, including genre sync, cast JSON round-trip, and that each write emits an activity row with the correct causer
  - [ ] `EnrichMovieJob` lives in the shared package and dispatches single-movie enrichment
  - [ ] `triggerEnrichment` is guarded by a per-movie cache lock; a test asserts a second call within the TTL returns `false`, does not dispatch a second job, and does not write a duplicate activity row
  - [ ] Existing backend test suite still green after the extraction
  - [ ] Progress journal updated with the extraction entry and a reference to Plan 03 Task 1's audit table

---

### Task 2: Admin MovieService facade

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Services/Backend/MovieService.php` (new)
  - `admin/app/Providers/AppServiceProvider.php` (modify — register the facade binding if needed)
- **Details:**
  The shared-domain `FinalCut\Domain\Services\MovieService` operates on `FinalCut\Domain\Models\Movie`. Admin mirrors `Movie` as `App\Models\Movie` (Plan 03 Task 2) for Filament Resource wiring. The facade translates between the two: it resolves the `Causer` from `auth()->user()` (always an `AdminUser` in admin's HTTP context), delegates to the domain service with the domain model, and returns the admin-mirrored model for Filament page consumers.

  ```php
  namespace App\Services\Backend;

  use App\Models\Movie;
  use FinalCut\Domain\Models\Movie as DomainMovie;
  use FinalCut\Domain\Services\MovieService as DomainMovieService;

  class MovieService
  {
      public function __construct(private DomainMovieService $inner) {}

      public function create(array $attributes): Movie
      {
          $domainMovie = $this->inner->create($attributes, $this->causer());
          return Movie::findOrFail($domainMovie->id);
      }

      public function update(Movie $movie, array $attributes): Movie
      {
          $domainMovie = DomainMovie::findOrFail($movie->id);
          $this->inner->update($domainMovie, $attributes, $this->causer());
          return $movie->fresh();
      }

      public function delete(Movie $movie): void
      {
          $domainMovie = DomainMovie::findOrFail($movie->id);
          $this->inner->delete($domainMovie, $this->causer());
      }

      public function triggerEnrichment(Movie $movie): bool
      {
          $domainMovie = DomainMovie::findOrFail($movie->id);
          return $this->inner->triggerEnrichment($domainMovie, $this->causer());
      }

      /**
       * Resolve the acting admin as a Causer. The facade lives only inside admin
       * HTTP requests, so auth()->user() is unambiguously an AdminUser here.
       * Shared-domain services called from other contexts (backend scheduler,
       * customer HTTP) pass their own Causer — this is not auto-resolution, it
       * is facade-local ergonomics.
       */
      private function causer(): \FinalCut\Domain\Audit\Causer
      {
          return auth()->user()
              ?? throw new \LogicException(
                  'Admin MovieService facade invoked outside an authenticated admin context. '
                  . 'Shared-domain services require an explicit Causer — the facade only resolves one when '
                  . 'called from an admin HTTP request.'
              );
      }
  }
  ```

  Register in admin service container (`AppServiceProvider`) if needed so Filament can resolve it via type hint. No binding is required if `DomainMovieService` has no constructor dependencies beyond what Laravel can auto-resolve.

- **Acceptance Criteria:**
  - [ ] Facade resides at `admin/app/Services/Backend/MovieService.php` and imports `FinalCut\Domain\Services\MovieService` — no `Backend\` namespace references anywhere
  - [ ] Facade methods delegate to the domain service with an explicit `Causer` resolved from `auth()->user()`
  - [ ] Facade throws `LogicException` with a clear message if invoked outside an authenticated admin context (covered by a unit test)
  - [ ] Returned models are `App\Models\Movie`, not the domain model class
  - [ ] `triggerEnrichment` returns the boolean dispatch/skip result
  - [ ] Service resolvable from the container via type-hint injection

---

### Task 3: MovieResource (list, view, edit)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/MovieResource.php` (new)
  - `admin/app/Filament/Resources/MovieResource/Pages/ListMovies.php` (new)
  - `admin/app/Filament/Resources/MovieResource/Pages/CreateMovie.php` (new)
  - `admin/app/Filament/Resources/MovieResource/Pages/EditMovie.php` (new)
  - `admin/app/Filament/Resources/MovieResource/Pages/ViewMovie.php` (new)
- **Details:**
  Resource extends `BaseResource` with `$permissionPrefix = 'movies'`.

  ```php
  class MovieResource extends BaseResource
  {
      protected static ?string $model = Movie::class;
      protected static ?string $permissionPrefix = 'movies';
      protected static ?string $navigationIcon = 'heroicon-o-film';
      protected static ?string $navigationGroup = 'Catalog';
      protected static ?int $navigationSort = 10;

      public static function form(Form $form): Form { /* Task 4 */ }
      public static function table(Table $table): Table { /* Task 5 */ }

      public static function getRelations(): array
      {
          return [UpcomingShowtimesRelationManager::class];
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

  Override `CreateMovie::handleRecordCreation` and `EditMovie::handleRecordUpdate` to call the admin `MovieService` facade (Task 2) instead of letting Filament persist directly. This enforces the write-boundary rule.

  ```php
  // CreateMovie.php
  protected function handleRecordCreation(array $data): Model
  {
      return app(\App\Services\Backend\MovieService::class)->create($data);
  }

  // EditMovie.php
  protected function handleRecordUpdate(Model $record, array $data): Model
  {
      return app(\App\Services\Backend\MovieService::class)->update($record, $data);
  }
  ```

  **Delete must also route through the service.** The table's `DeleteAction::make()` (Task 5) and any per-row delete on the Edit / View pages default to `$record->delete()` — a direct Eloquent write that bypasses the service facade and the write-boundary rule. Every delete call site in this Resource must opt into the service explicitly via Filament's `->using()` hook:

  ```php
  DeleteAction::make()
      ->using(fn (Model $record) => app(\App\Services\Backend\MovieService::class)->delete($record));
  ```

  If a `DeleteBulkAction` is introduced later (not in v1), it must do the same:

  ```php
  DeleteBulkAction::make()
      ->using(fn (\Illuminate\Support\Collection $records) =>
          $records->each(fn ($r) => app(\App\Services\Backend\MovieService::class)->delete($r))
      );
  ```

  **Primary defense: Plan 03 Task 7 deptrac + phpstan rules.** A stock `DeleteAction::make()` without `->using()` would cause Filament to call `$record->delete()` on a `FinalCut\Domain\Models\Movie` (via the admin mirror's `Movie::find()` result chain — the mirror shares the same table, so Filament's Eloquent traversal may reach the domain model class). The phpstan `disallowedMethodCalls` rule from Plan 03 Task 7 catches any `FinalCut\Domain\Models\Movie::delete()` call outside the service layer at CI time. An acceptance test in Task 7 below is kept as a secondary regression guard — the CI-time static analysis catches the pattern even before the test runs.

- **Acceptance Criteria:**
  - [ ] Resource registers under Catalog navigation group
  - [ ] List / view / create / edit pages route correctly
  - [ ] `handleRecordCreation` and `handleRecordUpdate` call service facade
  - [ ] Every `DeleteAction` instance (table row and, if present, per-page) uses `->using()` to call `MovieService::delete`
  - [ ] Direct Eloquent writes removed from every mutation path (create, update, delete) — no `$record->save()` / `->delete()` on the Resource persistence path
  - [ ] Permission gating works per role (admin full, manager full, ops read-only)

---

### Task 4: Movie form schema

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/MovieResource.php` (modify — `form()` method)
- **Details:**
  Filament form schema:

  ```php
  public static function form(Form $form): Form
  {
      return $form->schema([
          Section::make('Identity')
              ->schema([
                  TextInput::make('title')->required()->maxLength(255)
                      ->live(onBlur: true)
                      // Auto-slug on create only. On edit, the slug is stable — a title change
                      // never rewrites an existing slug, because slugs feed public URLs and
                      // downstream references. Admins can still edit the slug by hand on the
                      // slug field itself.
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

  The genres multi-select emits an array of genre IDs, and the cast repeater emits an array of `{ name, character, profileUrl }` objects that persists to the `cast` JSON column. Both shapes must match the service input contract declared in Task 1 exactly — Filament does not transform these payloads before handing them to `handleRecordCreation` / `handleRecordUpdate`, so the service is the sole place where the shapes are consumed.

- **Acceptance Criteria:**
  - [ ] All documented fields render
  - [ ] Title auto-slugs on blur during create only; editing a title on an existing record does not modify its slug
  - [ ] Slug validates uniqueness ignoring current record
  - [ ] TMDB ID optional but numeric-validated
  - [ ] Genres multi-select wired to relationship; the submitted payload is an array of genre IDs matching `MovieService` input contract
  - [ ] Cast repeater submitted payload is an array of `{ name, character, profileUrl }` objects matching `MovieService` input contract and saves to the JSON column
  - [ ] Form submission routes through service facade

---

### Task 5: Movie table (list page)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/app/Filament/Resources/MovieResource.php` (modify — `table()` method)
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
              ...static::getTimestampColumns(),
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
                  ->visible(fn ($record) => $record->tmdb_id && auth()->user()->can('movies.trigger_enrich'))
                  ->requiresConfirmation()
                  ->action(function ($record, $livewire) {
                      $dispatched = app(MovieService::class)->triggerEnrichment($record);
                      Notification::make()
                          ->title($dispatched ? 'Enrichment queued' : 'Enrichment already in progress')
                          ->{$dispatched ? 'success' : 'warning'}()
                          ->send();
                  }),
              DeleteAction::make()
                  ->using(fn (Model $record) => app(MovieService::class)->delete($record)),
          ])
          ->bulkActions([
              BulkAction::make('mark_now_showing')
                  ->label('Mark as Now Showing')
                  ->icon('heroicon-o-play')
                  ->visible(fn () => auth()->user()->can('movies.update'))
                  ->action(fn (Collection $records) => $records->each(fn ($r) =>
                      app(MovieService::class)->update($r, ['status' => 'now_showing'])
                  ))
                  ->deselectRecordsAfterCompletion(),
              BulkAction::make('mark_coming_soon')
                  ->label('Mark as Coming Soon')
                  ->icon('heroicon-o-clock')
                  ->visible(fn () => auth()->user()->can('movies.update'))
                  ->action(fn (Collection $records) => $records->each(fn ($r) =>
                      app(MovieService::class)->update($r, ['status' => 'coming_soon'])
                  )),
          ])
          ->defaultSort('release_date', 'desc');
  }
  ```

  **Bulk-action implementation note.** The `mark_now_showing` / `mark_coming_soon` bulk actions are deliberately implemented as N sequential single-record calls to `MovieService::update` (`$records->each(...)`), not a specialized batch pathway. This keeps every status change auditable and consistent with the write-boundary rule — every record emits its own activity row and passes through the same domain code path as a single-record edit. The cost is that large selections are chatty (one service call and one activity-log write per row). For v1's expected admin usage (curating a shortlist) this is acceptable; if real usage produces 100+ row bulk changes, revisit with a dedicated batch method on `MovieService`.

  **Delete behavior.** The `DeleteAction::make()->using(...)` hook routes deletion through `MovieService::delete` per Task 3's write-boundary rule. A stock `DeleteAction::make()` without `using()` would bypass the service and is treated as a regression in tests.

- **Acceptance Criteria:**
  - [ ] Poster thumbnail renders in column
  - [ ] Title searchable, sortable
  - [ ] Status badge colored correctly
  - [ ] Genre badges display from relationship
  - [ ] Filters: status, genres, "needs enrichment"
  - [ ] Row action: View, Edit, Enrich (gated on permission + tmdb_id), Delete
  - [ ] Enrich action surfaces a distinct "already in progress" notification when the service returns `false`
  - [ ] Delete action routes through `MovieService::delete` via `->using()` — no direct Eloquent delete
  - [ ] Bulk actions: mark status
  - [ ] Bulk actions route through service facade as N sequential single-record calls

---

### Task 6: UpcomingShowtimesRelationManager (read-only)

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Resources/MovieResource/RelationManagers/UpcomingShowtimesRelationManager.php` (new)
- **Details:**
  Read-only relation manager on the movie view page showing a **preview** of up to the next 20 upcoming showtimes for this movie. The intent is a quick "where is this playing right now" glance alongside the movie's detail view; it is deliberately not a full schedule view. Staff who need the complete showtime list navigate to the Showtimes resource filtered by movie (added in Plan 06). The relation manager title and any surrounding copy must frame this as a preview, not an exhaustive listing — otherwise admins will assume they are seeing every future showtime.

  ```php
  class UpcomingShowtimesRelationManager extends RelationManager
  {
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
                      ->formatStateUsing(fn ($state) => CurrencyFormatter::format($state)),
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
  - [ ] Shows up to the next 20 future showtimes as a preview (title and any accompanying copy clearly label it as a preview, not a complete list)
  - [ ] No create/edit/delete actions available
  - [ ] Location and auditorium displayed via nested relationship

---

### Task 7: Feature tests

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `admin/tests/Feature/Resources/MovieResourceTest.php` (new)
  - `admin/tests/Feature/Resources/MovieResourcePermissionTest.php` (new)
- **Details:**
  Use Filament's Livewire test helpers. Tests split into two layers with distinct responsibilities — do not blend them.

  **Layer A — Resource tests (facade mocked).** These verify the Resource wires form / actions / permissions to the service facade. Mock `\App\Services\Backend\MovieService` via `$this->mock()` so no backend writes happen. These tests **do not** assert anything about `activity_log`, because with a mocked facade no real mutation path runs.

  **MovieResourceTest (facade mocked):**
  - admin can list movies (`Livewire::test(ListMovies::class)->assertSee(...)`)
  - admin can create a movie via form submission → asserts `MovieService::create` was called with the expected payload shape (including `genres` array and `cast` array)
  - admin can update a movie → asserts `MovieService::update` was called
  - **admin can delete a movie via the table `DeleteAction` → asserts `MovieService::delete` was called and `Model::delete()` was NOT called** (regression guard for a stock `DeleteAction::make()` slipping in without `->using()`)
  - Enrich action dispatches the job for a movie with `tmdb_id` (asserts `MovieService::triggerEnrichment` returns `true`)
  - Enrich action surfaces the "already in progress" notification when the service returns `false`
  - Enrich action hidden for movies without `tmdb_id`
  - Bulk action "mark as now_showing" calls `MovieService::update` once per selected record
  - Editing a title on an existing record does not change its slug (slug stability policy)

  **MovieResourcePermissionTest (facade mocked):**
  - ops cannot access create form (`canCreate` returns false)
  - ops cannot access edit page
  - ops cannot see enrich action
  - manager can perform all movie actions
  - nobody role cannot access list page

  **Layer B — Service persistence + audit integration tests.** A small number of tests exercise the **real** `MovieService` (not mocked) end-to-end, hitting the test database, to verify the activity-log strategy (Plan 03 Task 3) actually fires. These live in `admin/tests/Feature/Services/MovieServiceIntegrationTest.php` (or in the backend package's test suite, depending on where the service ends up per Plan 03's ADR). They are intentionally separate from the Resource tests above — mocking the service and then asserting on `activity_log` is self-contradictory.

  **MovieServiceIntegrationTest (real service, real DB):**
  - Creating a movie writes an `activity_log` row with the expected description, causer (`admin_user_id`), and diff properties
  - Updating a movie writes an update activity row with the changed attribute diff
  - Deleting a movie writes a delete activity row
  - `triggerEnrichment` writes an enrichment-triggered activity row
  - Second `triggerEnrichment` call within the cache-lock TTL does not write a second activity row (idempotency)

- **Acceptance Criteria:**
  - [ ] Layer A Resource tests cover list, create, update, delete (including the stock-DeleteAction regression guard), enrich (both dispatched and skipped outcomes), bulk, and slug stability
  - [ ] Layer A PermissionTest covers all three roles × all actions
  - [ ] Layer A service facade is mocked — no real backend writes; no `activity_log` assertions in this layer
  - [ ] Layer B integration tests run the real `MovieService` against the test DB and verify `activity_log` writes for create / update / delete / enrich, including the enrichment idempotency guard
  - [ ] `make admin-test` passes all movie tests green (both layers)

---

## Testing Requirements

Tests split into two distinct layers (see Task 7 for detail) — do not mix them:

- **Layer A — Pest Resource tests, facade mocked:** list / create / update / delete (with the stock-DeleteAction regression guard) / enrich (dispatched and skipped outcomes) / bulk / slug stability + full permission matrix across the three roles. No `activity_log` assertions at this layer.
- **Layer B — Pest service integration tests, real `MovieService` against test DB:** verifies `activity_log` rows are written for create / update / delete / enrich with the correct causer and diff, and that a second `triggerEnrichment` within the lock TTL does not write a duplicate row.
- **Backend service tests:** Task 1 ensures `MovieService` has independent test coverage (create / update / delete / enrich paths, genre sync, cast JSON round-trip, enrichment idempotency).

## Dependencies Map

```
Task 1 (backend MovieService) ← foundational (may be partial extraction)
Task 2 (admin facade) ← needs Task 1
Task 3 (Resource skeleton) ← needs Plan 03 BaseResource + Task 2
Task 4 (form schema) ← needs Task 3
Task 5 (table + actions) ← needs Tasks 3, 4
Task 6 (relation manager) ← needs Task 3
Task 7 (tests) ← needs all
```

## Risks & Open Questions

1. **TMDB enrichment job.** If `EnrichMovieJob` doesn't exist, its extraction into `packages/shared-domain/src/Jobs/` must not break the existing `movies:enrich` scheduled command. Verify the command still operates on all movies (or on a single `--movie-id=` when provided). Since the scheduled command runs in backend's context, its Causer is a system user — declare and use a `FinalCut\Domain\Audit\SystemCauser` singleton for scheduled writes.
2. **JSON column editing.** Filament's repeater on the `cast` JSON column can be finicky with large casts (50+ members). TMDB enrichment caps at 12 members, so this is not a real risk in practice — document the cap in a form helper text.
3. **Poster URL validation.** Currently just `url()` validation. Consider tightening to TMDB or a whitelist of image hosts. Deferred — staff-only tool, not a threat surface.
4. **Shared-package circular autoload.** Extracting `MovieService` and `EnrichMovieJob` into `packages/shared-domain/` while the Eloquent `Movie` model also lives there creates a package that depends on Laravel framework classes transitively. The package's `composer.json` must declare `illuminate/database` and `illuminate/queue` as dependencies at runtime, not just dev — this is covered by Plan 03 Task 1's ADR but called out here as a concrete consequence for this first extraction.
