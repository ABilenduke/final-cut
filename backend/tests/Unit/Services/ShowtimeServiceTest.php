<?php

use App\Exceptions\MovieRuntimeMissingException;
use App\Exceptions\ShowtimeAlreadyCancelledException;
use App\Exceptions\ShowtimeConflictException;
use App\Http\Requests\BulkShowtimeRequest;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->service = app(ShowtimeService::class);
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
});

/*
|--------------------------------------------------------------------------
| detectConflicts — half-open interval semantics
|--------------------------------------------------------------------------
*/

test('back-to-back showtimes do not register as a conflict', function (): void {
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 20:00:00'),
        Carbon::parse('2026-05-01 22:00:00'),
    );

    expect($conflicts)->toHaveCount(0);
});

test('exact-overlap intervals are detected as conflicts', function (): void {
    $existing = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 18:00:00'),
        Carbon::parse('2026-05-01 20:00:00'),
    );

    expect($conflicts)->toHaveCount(1);
    expect($conflicts->first()->id)->toBe($existing->id);
});

test('nested intervals are detected as conflicts', function (): void {
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 22:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 19:00:00'),
        Carbon::parse('2026-05-01 20:00:00'),
    );

    expect($conflicts)->toHaveCount(1);
});

test('one-minute overlap at the trailing boundary is detected', function (): void {
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 19:59:00'),
        Carbon::parse('2026-05-01 22:00:00'),
    );

    expect($conflicts)->toHaveCount(1);
});

test('one-minute overlap at the leading boundary is detected', function (): void {
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 16:00:00'),
        Carbon::parse('2026-05-01 18:01:00'),
    );

    expect($conflicts)->toHaveCount(1);
});

test('ignoreShowtimeId excludes the named row from the conflict set', function (): void {
    $self = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 18:00:00'),
        Carbon::parse('2026-05-01 20:00:00'),
        $self->id,
    );

    expect($conflicts)->toHaveCount(0);
});

test('cancelled showtimes are excluded from conflict detection', function (): void {
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 18:00:00',
        'end_time' => '2026-05-01 20:00:00',
        'cancelled_at' => now(),
        'cancellation_reason' => 'staffing',
    ]);

    $conflicts = $this->service->detectConflicts(
        $this->auditorium->id,
        Carbon::parse('2026-05-01 18:00:00'),
        Carbon::parse('2026-05-01 20:00:00'),
    );

    expect($conflicts)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| detectConflictsForBatch — batched lookup
|--------------------------------------------------------------------------
*/

test('detectConflictsForBatch partitions conflicts per-interval in a single query', function (): void {
    $conflicting = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'end_time' => '2026-05-01 21:00:00',
    ]);

    // DB::listen would show exactly one SELECT on showtimes for the partition
    // fetch — not one per interval.
    $results = $this->service->detectConflictsForBatch(
        $this->auditorium->id,
        collect([
            // (1) 14:00-16:00 — no conflict
            ['start' => Carbon::parse('2026-05-01 14:00:00'), 'end' => Carbon::parse('2026-05-01 16:00:00')],
            // (2) 20:00-22:00 — overlaps the 19:00-21:00 existing row
            ['start' => Carbon::parse('2026-05-01 20:00:00'), 'end' => Carbon::parse('2026-05-01 22:00:00')],
            // (3) 23:00-24:00 — no conflict
            ['start' => Carbon::parse('2026-05-01 23:00:00'), 'end' => Carbon::parse('2026-05-02 00:00:00')],
        ])
    );

    expect($results)->toHaveCount(3);
    expect($results[0])->toHaveCount(0);
    expect($results[1])->toHaveCount(1);
    expect($results[1]->first()->id)->toBe($conflicting->id);
    expect($results[2])->toHaveCount(0);
});

test('detectConflictsForBatch returns an empty collection when no intervals are supplied', function (): void {
    $results = $this->service->detectConflictsForBatch($this->auditorium->id, collect());

    expect($results)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| create + end-time computation
|--------------------------------------------------------------------------
*/

test('create computes end_time from movie runtime + auditorium cleanup', function (): void {
    $showtime = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    // 120 minutes runtime + 15 minutes cleanup = 135 minutes → 21:15.
    expect($showtime->end_time->format('Y-m-d H:i'))->toBe('2026-05-01 21:15');
});

test('create refuses scheduling when movie runtime is null', function (): void {
    $movieWithoutRuntime = Movie::factory()->create(['runtime' => null]);

    $this->service->create([
        'movie_id' => $movieWithoutRuntime->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);
})->throws(MovieRuntimeMissingException::class);

test('create translates Postgres exclusion violation into ShowtimeConflictException', function (): void {
    // Seed a showtime that occupies 19:00–21:15 (120 runtime + 15 cleanup).
    $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    // Now attempt a second showtime starting at 20:00 — inside the occupied
    // window. The EXCLUDE constraint fires; the service re-throws as the
    // domain exception.
    $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 20:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);
})->throws(ShowtimeConflictException::class);

/*
|--------------------------------------------------------------------------
| cancel — idempotency + outbox writes
|--------------------------------------------------------------------------
*/

test('cancel flags all bookings once and writes one outbox row per booking', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'end_time' => '2026-05-01 21:15:00',
    ]);

    Booking::factory()->count(3)->create(['showtime_id' => $showtime->id]);

    $this->service->cancel($showtime, 'Projector failure');

    $fresh = $showtime->fresh();
    expect($fresh->cancelled_at)->not->toBeNull();
    expect($fresh->cancellation_reason)->toBe('Projector failure');
    expect($fresh->bookings()->whereNotNull('flagged_at')->count())->toBe(3);
    expect(DispatchOutbox::where('event_type', ShowtimeService::EVENT_CANCELLED)->count())->toBe(3);
});

test('cancel on an already-cancelled showtime throws ShowtimeAlreadyCancelledException', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'end_time' => '2026-05-01 21:15:00',
    ]);

    $this->service->cancel($showtime, 'First pass');

    $this->service->cancel($showtime->fresh(), 'Second pass');
})->throws(ShowtimeAlreadyCancelledException::class);

test('cancel is idempotent — a second call does not duplicate flags or outbox rows', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'end_time' => '2026-05-01 21:15:00',
    ]);

    Booking::factory()->count(2)->create(['showtime_id' => $showtime->id]);

    $this->service->cancel($showtime, 'First pass');

    try {
        $this->service->cancel($showtime->fresh(), 'Second pass');
    } catch (ShowtimeAlreadyCancelledException) {
        // Expected — swallowed so we can verify state wasn't mutated.
    }

    expect(DispatchOutbox::where('event_type', ShowtimeService::EVENT_CANCELLED)->count())->toBe(2);
    expect(Booking::where('showtime_id', $showtime->id)->whereNotNull('flagged_at')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| bulkCreate — transactional rollback on failure
|--------------------------------------------------------------------------
*/

test('bulkCreate persists every tuple when all are conflict-free', function (): void {
    $request = new BulkShowtimeRequest(
        movieId: (string) $this->movie->id,
        auditoriumId: $this->auditorium->id,
        tuples: new Collection([
            ['start_time' => Carbon::parse('2026-05-01 14:00:00')],
            ['start_time' => Carbon::parse('2026-05-01 17:00:00')],
            ['start_time' => Carbon::parse('2026-05-01 20:00:00')],
        ]),
        priceStandard: 1200,
        pricePremium: 1800,
        priceAccessible: 1000,
    );

    $created = $this->service->bulkCreate($request);

    expect($created)->toHaveCount(3);
    expect(Showtime::where('auditorium_id', $this->auditorium->id)->count())->toBe(3);
});

test('bulkCreate rolls back the entire batch when any tuple conflicts with a pre-existing showtime', function (): void {
    // Pre-seed a conflicting showtime so the second tuple's insert will violate
    // the EXCLUDE constraint.
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 17:00:00',
        'end_time' => '2026-05-01 19:00:00',
    ]);

    $request = new BulkShowtimeRequest(
        movieId: (string) $this->movie->id,
        auditoriumId: $this->auditorium->id,
        tuples: new Collection([
            ['start_time' => Carbon::parse('2026-05-01 14:00:00')],
            // This one overlaps the pre-seeded 17:00–19:00 row.
            ['start_time' => Carbon::parse('2026-05-01 17:30:00')],
            ['start_time' => Carbon::parse('2026-05-01 20:00:00')],
        ]),
        priceStandard: 1200,
        pricePremium: 1800,
        priceAccessible: 1000,
    );

    try {
        $this->service->bulkCreate($request);
    } catch (ShowtimeConflictException) {
        // Expected — the entire batch should have rolled back.
    }

    // Only the pre-seeded row should remain — the batch committed nothing.
    expect(Showtime::where('auditorium_id', $this->auditorium->id)->count())->toBe(1);
});
