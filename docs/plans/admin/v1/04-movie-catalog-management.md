# Plan 04: Movie Catalog Management

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 03 (Movie model, BaseResource, MovieService facade)
> **Unlocks:** Plan 06 (Showtime resource references Movie dropdown)

## Overview

Build the `MovieResource` — the first real Filament Resource in the admin app. CRUD for movies with all enrichment-relevant fields (title, slug, tmdb_id, synopsis, tagline, runtime, rating, release_date, poster/backdrop URLs, trailer key, genres, cast). Adds a row action to trigger TMDB enrichment for a single movie via the existing `movies:enrich` artisan command, plus bulk actions for status changes. Includes a read-only upcoming-showtimes relation manager on the movie view page so staff can see what a movie is playing without leaving the screen.

All mutations route through `App\Services\Backend\MovieService` per spec § 2.6. If the backend `MovieService` does not yet exist, the first task is extracting it from `MovieController`.

## Reference Documents

- `docs/plans/backend/v1/03-movie-api.md` — backend movie API and TMDB enrichment
- `docs/superpowers/specs/2026-04-20-admin-section-design.md` — § 2.6 write boundary, § 5 Plan 04
- `backend/app/Http/Controllers/Api/MovieController.php` — reference for extraction
- `backend/app/Services/TmdbService.php` — enrichment source

---

## Tasks

### Task 1: Audit/extract backend MovieService

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/MovieService.php` (new or confirm existing)
  - `backend/app/Http/Controllers/Api/MovieController.php` (modify — delegate to service)
  - `backend/tests/Feature/MovieServiceTest.php` (new)
- **Details:**
  Read `backend/app/Services/` for an existing `MovieService`. If absent, extract from `MovieController`.

  Required methods:
  ```php
  class MovieService
  {
      public function create(array $attributes): Movie;
      public function update(Movie $movie, array $attributes): Movie;
      public function delete(Movie $movie): void;
      public function triggerEnrichment(Movie $movie): void; // dispatches movies:enrich job for this single movie
  }
  ```

  `triggerEnrichment` calls the existing `TmdbService::enrichMovie()` via a queued job (`EnrichMovieJob`) so the admin request returns immediately. If the job class does not exist, create it — single-movie enrichment.

  Extraction principles:
  - Move validation and mutation logic from `MovieController@store` / `update` / `destroy` into the service
  - Keep HTTP concerns (request parsing, response formatting) in the controller
  - Preserve existing backend test coverage; add service-level tests for the extracted logic

  Update the progress journal's service extraction checklist.

- **Acceptance Criteria:**
  - [ ] `MovieService` exists in backend with documented methods
  - [ ] `MovieController` delegates write operations to the service
  - [ ] Backend Pest tests for `MovieService` cover create/update/delete/enrich paths
  - [ ] `EnrichMovieJob` dispatches single-movie enrichment
  - [ ] Existing backend test suite still green
  - [ ] Progress journal updated

---

### Task 2: Admin MovieService facade

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `admin/app/Services/Backend/MovieService.php` (modify — remove `@todo`)
- **Details:**
  Now that the backend service exists, finalize the facade created in Plan 03 Task 1. The facade calls the backend service and re-fetches through the admin `Movie` model.

  ```php
  namespace App\Services\Backend;

  use Backend\App\Services\MovieService as BackendMovieService;
  use App\Models\Movie;

  class MovieService
  {
      public function __construct(private BackendMovieService $inner) {}

      public function create(array $attributes): Movie
      {
          $backendMovie = $this->inner->create($attributes);
          return Movie::findOrFail($backendMovie->id);
      }

      public function update(Movie $movie, array $attributes): Movie
      {
          $backendMovie = \Backend\App\Models\Movie::findOrFail($movie->id);
          $this->inner->update($backendMovie, $attributes);
          return $movie->fresh();
      }

      public function delete(Movie $movie): void
      {
          $backendMovie = \Backend\App\Models\Movie::findOrFail($movie->id);
          $this->inner->delete($backendMovie);
      }

      public function triggerEnrichment(Movie $movie): void
      {
          $backendMovie = \Backend\App\Models\Movie::findOrFail($movie->id);
          $this->inner->triggerEnrichment($backendMovie);
      }
  }
  ```

  Register in admin service container (`AppServiceProvider`) so Filament can resolve it via type hint.

- **Acceptance Criteria:**
  - [ ] Facade methods delegate to backend service
  - [ ] Returned models are `App\Models\Movie`, not `Backend\App\Models\Movie`
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

- **Acceptance Criteria:**
  - [ ] Resource registers under Catalog navigation group
  - [ ] List / view / create / edit pages route correctly
  - [ ] `handleRecordCreation` and `handleRecordUpdate` call service facade
  - [ ] Direct Eloquent writes removed from form submission path
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
                      ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                  TextInput::make('slug')->required()->unique(ignoreRecord: true)
                      ->helperText('Auto-derived from title; edit only if needed'),
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

  The `cast` repeater persists to the JSON column on the movies table.

- **Acceptance Criteria:**
  - [ ] All documented fields render
  - [ ] Title auto-slugs on blur
  - [ ] Slug validates uniqueness ignoring current record
  - [ ] TMDB ID optional but numeric-validated
  - [ ] Genres multi-select wired to relationship
  - [ ] Cast repeater saves to JSON column
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
                  ->action(fn ($record) => app(MovieService::class)->triggerEnrichment($record))
                  ->successNotificationTitle('Enrichment queued'),
              DeleteAction::make(),
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

- **Acceptance Criteria:**
  - [ ] Poster thumbnail renders in column
  - [ ] Title searchable, sortable
  - [ ] Status badge colored correctly
  - [ ] Genre badges display from relationship
  - [ ] Filters: status, genres, "needs enrichment"
  - [ ] Row action: View, Edit, Enrich (gated on permission + tmdb_id), Delete
  - [ ] Bulk actions: mark status
  - [ ] Bulk actions route through service facade

---

### Task 6: UpcomingShowtimesRelationManager (read-only)

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `admin/app/Filament/Resources/MovieResource/RelationManagers/UpcomingShowtimesRelationManager.php` (new)
- **Details:**
  Read-only relation manager on the movie view page showing the next 20 upcoming showtimes for this movie. Helps staff see "where is this movie playing" without leaving the resource.

  ```php
  class UpcomingShowtimesRelationManager extends RelationManager
  {
      protected static string $relationship = 'showtimes';
      protected static ?string $title = 'Upcoming Showtimes';

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
  - [ ] Shows next 20 future showtimes
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
  Use Filament's Livewire test helpers.

  **MovieResourceTest:**
  - Test: admin can list movies (Livewire::test(ListMovies::class)->assertSee(...))
  - Test: admin can create a movie via form submission → asserts `MovieService::create` was called (mock the facade)
  - Test: admin can update a movie → asserts `MovieService::update` was called
  - Test: admin can delete a movie → asserts `MovieService::delete` was called
  - Test: Enrich action dispatches the job for a movie with tmdb_id
  - Test: Enrich action hidden for movies without tmdb_id
  - Test: Bulk action "mark as now_showing" calls service for each record

  **MovieResourcePermissionTest:**
  - Test: ops cannot access create form (canCreate returns false)
  - Test: ops cannot access edit page
  - Test: ops cannot see enrich action
  - Test: manager can perform all movie actions
  - Test: nobody role cannot access list page

  Mock `\App\Services\Backend\MovieService` in tests via `$this->mock()` so we don't hit backend.

- **Acceptance Criteria:**
  - [ ] MovieResourceTest covers list, create, update, delete, enrich, bulk
  - [ ] PermissionTest covers all three roles × all actions
  - [ ] Service facade mocked — no backend writes during tests
  - [ ] `make admin-test` passes all movie tests green

---

## Testing Requirements

- **Pest Feature Tests:** list/create/update/delete/enrich/bulk + permission matrix (13+ tests)
- **Activity log assertions:** mutations write to `activity_log` with correct causer and diff
- **Backend service tests:** Task 1 ensures `MovieService` has independent test coverage

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

1. **TMDB enrichment job.** If `EnrichMovieJob` doesn't exist, its extraction must not break the existing `movies:enrich` scheduled command. Verify the command still operates on all movies (or on a single `--movie-id=` when provided).
2. **JSON column editing.** Filament's repeater on the `cast` JSON column can be finicky with large casts (50+ members). TMDB enrichment caps at 12 members, so this is not a real risk in practice — document the cap in a form helper text.
3. **Poster URL validation.** Currently just `url()` validation. Consider tightening to TMDB or a whitelist of image hosts. Deferred — staff-only tool, not a threat surface.
