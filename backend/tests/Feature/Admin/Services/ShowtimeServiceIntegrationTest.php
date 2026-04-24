<?php

use App\Http\Requests\BulkShowtimeRequest;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
    $this->service = app(ShowtimeService::class);
});

test('creating a showtime with an actor writes an admin activity log row', function (): void {
    $showtime = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ], $this->admin);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_CREATED)
        ->where('subject_id', $showtime->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect((int) $activity->causer_id)->toBe((int) $this->admin->id);
    expect($activity->causer_type)->toBe(AdminUser::class);
    expect($activity->properties->get('movie_id'))->toBe($this->movie->id);
});

test('creating a showtime without an actor writes no admin activity log row', function (): void {
    $initialCount = Activity::where('log_name', 'admin')->count();

    $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ], actor: null);

    expect(Activity::where('log_name', 'admin')->count())->toBe($initialCount);
});

test('updating a showtime with an actor writes an activity log row with before/after diff', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
    ]);

    $this->service->update($showtime, ['price_standard' => 1500], $this->admin);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_UPDATED)
        ->where('subject_id', $showtime->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect((int) $activity->causer_id)->toBe((int) $this->admin->id);

    $before = $activity->properties->get('before');
    $after = $activity->properties->get('after');

    expect($before['price_standard'])->toBe(1200);
    expect($after['price_standard'])->toBe(1500);
});

test('updating a showtime with the same attributes does not write a no-op activity log row', function (): void {
    // Create through the service so end_time = start + movie.runtime + cleanup — the
    // same formula update() uses. Otherwise the factory's random runtime would
    // cause end_time to recompute dirty on every update call.
    $showtime = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ], $this->admin);

    $initialCount = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_UPDATED)
        ->where('subject_id', $showtime->id)
        ->count();

    // Same values → the service should not emit an update log row.
    $this->service->update($showtime->fresh(), ['price_standard' => 1200], $this->admin);

    $finalCount = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_UPDATED)
        ->where('subject_id', $showtime->id)
        ->count();

    expect($finalCount)->toBe($initialCount);
});

test('bulk create with an actor emits one activity_log row per showtime', function (): void {
    $request = new BulkShowtimeRequest(
        movieId: (string) $this->movie->id,
        auditoriumId: $this->auditorium->id,
        tuples: collect([
            ['start_time' => Carbon::parse('2026-05-01 14:00:00')],
            ['start_time' => Carbon::parse('2026-05-01 17:00:00')],
            ['start_time' => Carbon::parse('2026-05-01 20:00:00')],
        ]),
        priceStandard: 1200,
        pricePremium: 1800,
        priceAccessible: 1000,
    );

    $created = $this->service->bulkCreate($request, $this->admin);

    expect($created)->toHaveCount(3);

    $activityRows = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_CREATED)
        ->whereIn('subject_id', $created->pluck('id'))
        ->get();

    expect($activityRows)->toHaveCount(3);
    expect($activityRows->every(fn (Activity $a) => $a->properties->get('via') === 'bulk'))->toBeTrue();
});
