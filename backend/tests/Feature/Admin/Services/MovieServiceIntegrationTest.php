<?php

use App\Exceptions\MovieHasBookingsException;
use App\Jobs\EnrichMovieJob;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\MovieService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(MovieService::class);
});

test('create with actor writes a movie.created activity row attributed to the admin', function (): void {
    $admin = $this->actingAsAdmin();
    Activity::query()->delete();

    $movie = $this->service->create([
        'slug' => 'logged-create',
        'title' => 'Logged Create',
        'status' => 'now_showing',
    ], $admin);

    $activity = Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('movie.created');
    expect($activity->causer_type)->toBe(AdminUser::class);
    expect((int) $activity->causer_id)->toBe($admin->id);
    expect($activity->log_name)->toBe('admin');
});

test('create with null actor writes no activity row', function (): void {
    $this->actingAsAdmin();
    Activity::query()->delete();

    $movie = $this->service->create([
        'slug' => 'quiet-create',
        'title' => 'Quiet Create',
        'status' => 'coming_soon',
    ], null);

    expect(Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->count())->toBe(0);
});

test('update with actor writes a movie.updated activity row with before and after properties', function (): void {
    $admin = $this->actingAsAdmin();
    $movie = Movie::factory()->create(['title' => 'Original']);
    Activity::query()->delete();

    $this->service->update($movie, ['title' => 'Revised'], $admin);

    $activity = Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('movie.updated');

    $props = $activity->properties->toArray();
    expect($props)->toHaveKeys(['before', 'after']);
    expect($props['before']['title'])->toBe('Original');
    expect($props['after']['title'])->toBe('Revised');
});

test('update with no dirty attributes writes no activity row', function (): void {
    $admin = $this->actingAsAdmin();
    $movie = Movie::factory()->create(['title' => 'Unchanged']);
    Activity::query()->delete();

    $this->service->update($movie, ['title' => 'Unchanged'], $admin);

    expect(Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->where('description', 'movie.updated')
        ->count())->toBe(0);
});

test('delete with actor writes a movie.deleted activity row before the row is removed', function (): void {
    $admin = $this->actingAsAdmin();
    $movie = Movie::factory()->create();
    Activity::query()->delete();

    $this->service->delete($movie, $admin);

    $activity = Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('movie.deleted');
    expect((int) $activity->causer_id)->toBe($admin->id);
    expect(Movie::find($movie->id))->toBeNull();
});

test('delete throws MovieHasBookingsException when any showtime has a booking and leaves the movie intact', function (): void {
    $admin = $this->actingAsAdmin();
    $movie = Movie::factory()->create();
    $showtime = Showtime::factory()->for($movie)->create();
    Booking::factory()->for($showtime)->create();
    Activity::query()->delete();

    expect(fn () => $this->service->delete($movie, $admin))
        ->toThrow(MovieHasBookingsException::class);

    expect(Movie::find($movie->id))->not->toBeNull();
    expect(Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->where('description', 'movie.deleted')
        ->count())->toBe(0);
});

test('triggerEnrichment is idempotent — second call inside the lock TTL dispatches no second job and logs nothing', function (): void {
    $admin = $this->actingAsAdmin();
    Cache::clear();
    Bus::fake();
    $movie = Movie::factory()->create();
    Activity::query()->delete();

    $first = $this->service->triggerEnrichment($movie, $admin);
    $second = $this->service->triggerEnrichment($movie, $admin);

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();

    Bus::assertDispatchedTimes(EnrichMovieJob::class, 1);

    $activities = Activity::where('subject_type', Movie::class)
        ->where('subject_id', $movie->id)
        ->where('description', 'movie.enrichment_triggered')
        ->get();

    expect($activities)->toHaveCount(1);
    expect((int) $activities->first()->causer_id)->toBe($admin->id);
});

test('triggerEnrichment passes the acquired lock owner token to the dispatched job', function (): void {
    $admin = $this->actingAsAdmin();
    Cache::clear();
    Bus::fake();
    $movie = Movie::factory()->create();

    $dispatched = $this->service->triggerEnrichment($movie, $admin);

    expect($dispatched)->toBeTrue();

    Bus::assertDispatched(
        EnrichMovieJob::class,
        fn (EnrichMovieJob $job) => $job->movieId === $movie->id
            && is_string($job->lockOwner)
            && $job->lockOwner !== ''
    );
});

test('triggerEnrichment releases the lock when the dispatcher throws so the next click is not lied to', function (): void {
    $admin = $this->actingAsAdmin();
    Cache::clear();
    $movie = Movie::factory()->create();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue down'));
    $this->app->instance(Dispatcher::class, $dispatcher);

    expect(fn () => $this->service->triggerEnrichment($movie, $admin))
        ->toThrow(RuntimeException::class);

    // Lock was released — acquiring it fresh must succeed.
    expect(Cache::lock(MovieService::enrichmentLockKey($movie->id), 300)->get())->toBeTrue();
});

test('triggerEnrichment returns false without acquiring a lock when the movie has no tmdb_id', function (): void {
    $admin = $this->actingAsAdmin();
    Cache::clear();
    Bus::fake();
    $movie = Movie::factory()->create(['tmdb_id' => null]);

    expect($this->service->triggerEnrichment($movie, $admin))->toBeFalse();

    Bus::assertNothingDispatched();

    // Lock was never acquired — a new acquire should succeed.
    expect(Cache::lock(MovieService::enrichmentLockKey($movie->id), 300)->get())->toBeTrue();
});

test('write operations forget the genre filter options cache', function (): void {
    $this->actingAsAdmin();

    Cache::put(MovieService::GENRE_FILTER_CACHE_KEY, ['stale'], 60);
    $this->service->create([
        'slug' => 'cache-bust-create',
        'title' => 'Cache Bust Create',
        'status' => 'coming_soon',
    ]);
    expect(Cache::has(MovieService::GENRE_FILTER_CACHE_KEY))->toBeFalse();

    $movie = Movie::factory()->create();
    Cache::put(MovieService::GENRE_FILTER_CACHE_KEY, ['stale'], 60);
    $this->service->delete($movie);
    expect(Cache::has(MovieService::GENRE_FILTER_CACHE_KEY))->toBeFalse();
});
