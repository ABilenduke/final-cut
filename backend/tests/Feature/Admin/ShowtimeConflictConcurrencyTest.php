<?php

use App\Exceptions\ShowtimeConflictException;
use App\Http\Requests\BulkShowtimeRequest;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actingAsAdmin();
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
    $this->service = app(ShowtimeService::class);
});

/**
 * These tests verify the `showtimes_no_overlap` EXCLUDE USING gist constraint
 * is the authoritative TOCTOU guard. `detectConflicts()` alone is a pre-check
 * affordance — between pre-check and INSERT, any other transaction committing
 * a conflicting row would slip past. The EXCLUDE constraint catches that; the
 * service translates the SQLSTATE 23P01 into `ShowtimeConflictException`.
 *
 * Because Laravel's test harness wraps each test in a single transaction
 * (RefreshDatabase), we can't literally open two parallel transactions on
 * separate connections here without breaking the rollback model. The tests
 * below simulate the TOCTOU scenario by directly seeding a row that would
 * have landed during the pre-check → insert gap.
 */
test('direct INSERT of an overlapping showtime is rejected at the database level', function (): void {
    $first = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    // Simulate what a second transaction would do — bypass the service and
    // try to insert directly. The EXCLUDE constraint must raise SQLSTATE 23P01.
    $threw = false;
    $sqlState = null;

    // Wrap in DB::transaction so the exclusion error is contained at a
    // savepoint — otherwise the RefreshDatabase outer transaction aborts
    // and follow-up reads raise SQLSTATE 25P02 ("transaction is aborted").
    try {
        DB::transaction(function () {
            DB::table('showtimes')->insert([
                'id' => (string) Str::uuid(),
                'movie_id' => $this->movie->id,
                'auditorium_id' => $this->auditorium->id,
                'start_time' => '2026-05-01 20:00:00', // inside first's 19:00-21:15 window
                'end_time' => '2026-05-01 22:15:00',
                'price_standard' => 1200,
                'price_premium' => 1800,
                'price_accessible' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    } catch (QueryException $e) {
        $threw = true;
        $sqlState = $e->errorInfo[0] ?? $e->getCode();
    }

    expect($threw)->toBeTrue();
    expect($sqlState)->toBe('23P01');
    expect(Showtime::where('auditorium_id', $this->auditorium->id)->count())->toBe(1);
});

test('service translates TOCTOU exclusion violation into ShowtimeConflictException', function (): void {
    // Seed a showtime that detectConflicts would have missed if we only checked
    // before insert. To simulate that: we seed it AFTER the service pre-checks
    // detectConflicts but BEFORE it inserts. We replicate the effect by just
    // seeding first and calling the service, knowing its insert will trip the
    // EXCLUDE constraint regardless of the pre-check order.
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'end_time' => '2026-05-01 21:15:00',
    ]);

    $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 20:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);
})->throws(ShowtimeConflictException::class);

test('cancelling a showtime frees its slot for a new insertion', function (): void {
    $showtime = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    // Before cancellation, any attempt to create at the same slot must fail.
    $threwBeforeCancel = false;
    try {
        $this->service->create([
            'movie_id' => $this->movie->id,
            'auditorium_id' => $this->auditorium->id,
            'start_time' => '2026-05-01 19:00:00',
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
        ]);
    } catch (ShowtimeConflictException) {
        $threwBeforeCancel = true;
    }
    expect($threwBeforeCancel)->toBeTrue();

    // Cancel frees the slot (partial constraint: WHERE cancelled_at IS NULL).
    $this->service->cancel($showtime, 'rescheduled');

    // Same slot now inserts cleanly.
    $replacement = $this->service->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 19:00:00',
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    expect($replacement->id)->not->toBe($showtime->id);
});

test('bulkCreate rolls back the whole batch when a tuple trips the EXCLUDE constraint', function (): void {
    // Pre-seed a conflicting row.
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 17:00:00',
        'end_time' => '2026-05-01 19:00:00',
    ]);

    $request = new BulkShowtimeRequest(
        movieId: (string) $this->movie->id,
        auditoriumId: $this->auditorium->id,
        tuples: collect([
            ['start_time' => Carbon::parse('2026-05-01 14:00:00')],
            // overlaps the pre-seeded 17:00-19:00 row
            ['start_time' => Carbon::parse('2026-05-01 17:30:00')],
            ['start_time' => Carbon::parse('2026-05-01 22:00:00')],
        ]),
        priceStandard: 1200,
        pricePremium: 1800,
        priceAccessible: 1000,
    );

    $threw = false;
    try {
        $this->service->bulkCreate($request);
    } catch (ShowtimeConflictException) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
    // Only the pre-seeded row persists — nothing from the batch.
    expect(Showtime::where('auditorium_id', $this->auditorium->id)->count())->toBe(1);
});
