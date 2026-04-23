<?php

use App\Enums\MovieStatus;
use App\Jobs\EnrichMovieJob;
use App\Models\Movie;
use App\Services\MovieService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(MovieService::class);
});

test('create persists a movie with genres and cast JSON payloads', function (): void {
    $movie = $this->service->create([
        'slug' => 'test-film',
        'title' => 'Test Film',
        'status' => 'now_showing',
        'genres' => [
            ['id' => 28, 'name' => 'Action'],
            ['id' => 12, 'name' => 'Adventure'],
        ],
        'cast' => [
            ['name' => 'Jane Doe', 'character' => 'Lead', 'profileUrl' => 'https://example.com/j.jpg'],
        ],
    ]);

    expect($movie)->toBeInstanceOf(Movie::class);
    expect($movie->slug)->toBe('test-film');
    expect($movie->title)->toBe('Test Film');
    expect($movie->status)->toBe(MovieStatus::NowShowing);
    expect($movie->genres)->toBe([
        ['id' => 28, 'name' => 'Action'],
        ['id' => 12, 'name' => 'Adventure'],
    ]);
    expect($movie->cast)->toBe([
        ['name' => 'Jane Doe', 'character' => 'Lead', 'profileUrl' => 'https://example.com/j.jpg'],
    ]);
});

test('update persists changes and returns a fresh model', function (): void {
    $movie = Movie::factory()->create(['title' => 'Old']);

    $updated = $this->service->update($movie, ['title' => 'New']);

    expect($updated->title)->toBe('New');
    expect(Movie::find($movie->id)->title)->toBe('New');
});

test('delete removes the row', function (): void {
    $movie = Movie::factory()->create();

    $this->service->delete($movie);

    expect(Movie::find($movie->id))->toBeNull();
});

test('triggerEnrichment returns true on first call and false while the lock is held', function (): void {
    Cache::clear();
    Bus::fake();
    $movie = Movie::factory()->create();

    $first = $this->service->triggerEnrichment($movie);
    $second = $this->service->triggerEnrichment($movie);

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
    Bus::assertDispatchedTimes(EnrichMovieJob::class, 1);
});

test('writes no activity row when actor is null', function (): void {
    Activity::query()->delete();

    $movie = $this->service->create([
        'slug' => 'no-actor',
        'title' => 'No Actor',
        'status' => 'coming_soon',
    ]);

    $this->service->update($movie, ['title' => 'Still No Actor']);
    $this->service->delete($movie);

    expect(Activity::where('subject_type', Movie::class)->count())->toBe(0);
});
