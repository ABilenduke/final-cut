<?php

use App\Filament\Resources\MovieResource\Pages\CreateMovie;
use App\Filament\Resources\MovieResource\Pages\EditMovie;
use App\Filament\Resources\MovieResource\Pages\ListMovies;
use App\Models\AdminUser;
use App\Models\Movie;
use App\Services\MovieService;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admins can see the movie list', function (): void {
    $movies = Movie::factory()->count(3)->create();

    Livewire::test(ListMovies::class)
        ->assertCanSeeTableRecords($movies);
});

test('creating a movie routes through MovieService with the admin actor', function (): void {
    $expected = Movie::factory()->create([
        'slug' => 'persisted-stub',
        'title' => 'Persisted Stub',
    ]);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data, ?AdminUser $actor) use (&$capturedData, &$capturedActor, $expected) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $expected;
        });

    Livewire::test(CreateMovie::class)
        ->set('data.title', 'New Movie')
        ->set('data.slug', 'new-movie')
        ->set('data.status', 'now_showing')
        ->set('data.genres', [
            ['id' => 28, 'name' => 'Action'],
        ])
        ->set('data.cast', [
            ['name' => 'Lead Actor', 'character' => 'Hero', 'profileUrl' => null],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($capturedData['title'])->toBe('New Movie');
    expect($capturedData['slug'])->toBe('new-movie');
    expect($capturedData['status'])->toBe('now_showing');

    $genres = array_values($capturedData['genres'] ?? []);
    expect($genres)->toHaveCount(1);
    expect((int) $genres[0]['id'])->toBe(28);
    expect($genres[0]['name'])->toBe('Action');

    $cast = array_values($capturedData['cast'] ?? []);
    expect($cast)->toHaveCount(1);
    expect($cast[0]['name'])->toBe('Lead Actor');
    expect($cast[0]['character'])->toBe('Hero');

    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('editing a movie routes through MovieService with the admin actor', function (): void {
    $movie = Movie::factory()->create(['title' => 'Before']);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('update')
        ->once()
        ->andReturnUsing(function (Movie $record, array $data, ?AdminUser $actor) use (&$capturedData, &$capturedActor, $movie) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $movie->fill(['title' => $data['title'] ?? $movie->title]);
        });

    Livewire::test(EditMovie::class, ['record' => $movie->getRouteKey()])
        ->set('data.title', 'After')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedData['title'])->toBe('After');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('deleting a movie routes through MovieService — the row survives if the service is mocked', function (): void {
    $movie = Movie::factory()->create();

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('delete')
        ->once()
        ->withArgs(function (Movie $record, ?AdminUser $actor) use ($movie) {
            expect($record->id)->toBe($movie->id);
            expect($actor?->id)->toBe($this->admin->id);

            return true;
        });

    Livewire::test(ListMovies::class)
        ->callTableAction('delete', $movie);

    // Mock swallowed the delete call, so the row must still exist. This is the
    // regression guard that DeleteAction::make()->using(...) is wired correctly.
    expect(Movie::find($movie->id))->not->toBeNull();
});

test('enrich action surfaces a success notification when the service dispatches the job', function (): void {
    $movie = Movie::factory()->create(['tmdb_id' => 12345]);

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('triggerEnrichment')
        ->once()
        ->andReturn(true);

    Livewire::test(ListMovies::class)
        ->callTableAction('enrich', $movie);

    Notification::assertNotified('Enrichment queued');
});

test('enrich action surfaces a warning notification when the service returns false', function (): void {
    $movie = Movie::factory()->create(['tmdb_id' => 12345]);

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('triggerEnrichment')
        ->once()
        ->andReturn(false);

    Livewire::test(ListMovies::class)
        ->callTableAction('enrich', $movie);

    Notification::assertNotified('Enrichment already in progress');
});

test('enrich action is hidden for movies without a tmdb_id', function (): void {
    $movie = Movie::factory()->create(['tmdb_id' => null]);

    Livewire::test(ListMovies::class)
        ->assertTableActionHidden('enrich', $movie);
});

test('bulk mark_now_showing calls MovieService::update once per selected record', function (): void {
    $movies = Movie::factory()->count(3)->create(['status' => 'coming_soon']);

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('update')
        ->times(3)
        ->withArgs(function (Movie $record, array $data, ?AdminUser $actor) {
            expect($data)->toBe(['status' => 'now_showing']);
            expect($actor?->id)->toBe($this->admin->id);

            return true;
        })
        ->andReturnUsing(fn (Movie $m) => $m);

    Livewire::test(ListMovies::class)
        ->callTableBulkAction('mark_now_showing', $movies);
});

test('editing a movie title does not mutate the slug on an existing record', function (): void {
    $movie = Movie::factory()->create([
        'title' => 'Stable',
        'slug' => 'stable',
    ]);

    $capturedSlug = null;

    $service = $this->mock(MovieService::class);
    $service->shouldReceive('update')
        ->once()
        ->andReturnUsing(function (Movie $record, array $data) use (&$capturedSlug, $movie) {
            $capturedSlug = $data['slug'] ?? null;

            return $movie;
        });

    Livewire::test(EditMovie::class, ['record' => $movie->getRouteKey()])
        ->set('data.title', 'Changed Title')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedSlug)->toBe('stable');
});
