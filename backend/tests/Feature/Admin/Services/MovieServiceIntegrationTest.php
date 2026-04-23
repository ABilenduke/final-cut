<?php

use App\Jobs\EnrichMovieJob;
use App\Models\AdminUser;
use App\Models\Movie;
use App\Services\MovieService;
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
    expect($activity->causer_id)->toBe($admin->id);
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
    expect($activity->causer_id)->toBe($admin->id);
    expect(Movie::find($movie->id))->toBeNull();
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
    expect($activities->first()->causer_id)->toBe($admin->id);
});
