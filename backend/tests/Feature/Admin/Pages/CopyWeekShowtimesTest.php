<?php

use App\Filament\Resources\ShowtimeResource\Pages\CopyWeekShowtimes;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(ShowtimeService::class);

    $this->location = Location::factory()->create(['timezone' => 'America/New_York']);
    $this->auditorium = Auditorium::factory()->create(['location_id' => $this->location->id]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
});

/** Create a showtime at a venue-local wall-clock time. */
function showtimeAtLocal(string $localDateTime, array $overrides = []): Showtime
{
    $test = test();

    return Showtime::factory()->create(array_merge([
        'movie_id' => $test->movie->id,
        'auditorium_id' => $test->auditorium->id,
        'start_time' => Carbon::parse($localDateTime, 'America/New_York')->utc(),
        'end_time' => Carbon::parse($localDateTime, 'America/New_York')->utc()->addMinutes(135),
        'price_standard' => 1500,
        'price_premium' => 2100,
        'price_accessible' => 1200,
    ], $overrides));
}

// ── buildWeekCopyPlan ───────────────────────────────────────────────────────

test('the plan shifts showtimes a week forward preserving venue wall-clock across the DST boundary', function (): void {
    // US spring-forward 2027: Sun Mar 14. Source week is EST (UTC-5), target is EDT (UTC-4).
    showtimeAtLocal('2027-03-08 19:00');

    $plan = $this->service->buildWeekCopyPlan(
        Carbon::parse('2027-03-08'),
        Carbon::parse('2027-03-15'),
    );

    expect($plan['rows'])->toHaveCount(1);
    $row = $plan['rows'][0];

    // Venue wall-clock preserved…
    expect($row['start']->copy()->setTimezone('America/New_York')->format('Y-m-d H:i'))
        ->toBe('2027-03-15 19:00');
    // …which means the UTC instant moved by 7 days MINUS one hour.
    expect($row['start']->copy()->setTimezone('UTC')->format('Y-m-d H:i'))
        ->toBe('2027-03-15 23:00');
    expect($plan['skipped_missing_runtime'])->toHaveCount(0);
});

test('cancelled and out-of-window showtimes are excluded and the location filter applies', function (): void {
    $inWindow = showtimeAtLocal('2027-06-08 19:00'); // Tue of source week
    showtimeAtLocal('2027-06-01 19:00'); // previous week
    showtimeAtLocal('2027-06-09 19:00', ['cancelled_at' => now()]);

    $otherLocation = Location::factory()->create(['timezone' => 'America/New_York']);
    $otherAuditorium = Auditorium::factory()->create(['location_id' => $otherLocation->id]);
    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $otherAuditorium->id,
        'start_time' => Carbon::parse('2027-06-08 20:00', 'America/New_York')->utc(),
        'end_time' => Carbon::parse('2027-06-08 22:15', 'America/New_York')->utc(),
    ]);

    $plan = $this->service->buildWeekCopyPlan(
        Carbon::parse('2027-06-07'),
        Carbon::parse('2027-06-14'),
        $this->location->id,
    );

    expect($plan['rows'])->toHaveCount(1)
        ->and($plan['rows'][0]['source']->id)->toBe($inWindow->id);
});

test('rows whose movie lost its runtime are skipped and reported', function (): void {
    $runtimeless = Movie::factory()->create(['runtime' => null]);
    showtimeAtLocal('2027-06-08 19:00', ['movie_id' => $runtimeless->id]);
    showtimeAtLocal('2027-06-08 22:00'); // clear of the 19:00 row's 21:15 end

    $plan = $this->service->buildWeekCopyPlan(
        Carbon::parse('2027-06-07'),
        Carbon::parse('2027-06-14'),
    );

    expect($plan['rows'])->toHaveCount(1);
    expect($plan['skipped_missing_runtime'])->toHaveCount(1);
});

test('the plan flags rows that conflict with the target week', function (): void {
    showtimeAtLocal('2027-06-08 19:00');
    // Pre-existing showtime occupying the same target-week slot.
    showtimeAtLocal('2027-06-15 19:30');

    $plan = $this->service->buildWeekCopyPlan(
        Carbon::parse('2027-06-07'),
        Carbon::parse('2027-06-14'),
    );

    expect($plan['rows'])->toHaveCount(1)
        ->and($plan['rows'][0]['conflicts'])->not->toBeEmpty();
});

// ── copyWeek (write path) ───────────────────────────────────────────────────

test('copyWeek creates the rows with copy_week attribution', function (): void {
    $admin = $this->actingAsAdmin();
    showtimeAtLocal('2027-06-08 19:00');

    $plan = $this->service->buildWeekCopyPlan(
        Carbon::parse('2027-06-07'),
        Carbon::parse('2027-06-14'),
    );

    $rows = collect($plan['rows'])->map(fn (array $row): array => [
        'movie_id' => $row['source']->movie_id,
        'auditorium_id' => $row['source']->auditorium_id,
        'start_time' => $row['start'],
        'price_standard' => $row['source']->price_standard,
        'price_premium' => $row['source']->price_premium,
        'price_accessible' => $row['source']->price_accessible,
    ]);

    $created = $this->service->copyWeek($rows, $admin);

    expect($created)->toHaveCount(1);
    $new = $created->first();
    expect($new->start_time->copy()->setTimezone('America/New_York')->format('Y-m-d H:i'))
        ->toBe('2027-06-15 19:00')
        ->and($new->price_standard)->toBe(1500);

    $activity = Activity::where('log_name', 'admin')
        ->where('description', ShowtimeService::EVENT_CREATED)
        ->latest('id')
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['via'])->toBe('copy_week');
});

// ── Page ────────────────────────────────────────────────────────────────────

test('the page requires showtimes.create', function (): void {
    $this->actingAsOps();

    Livewire::test(CopyWeekShowtimes::class)->assertForbidden();
});

test('a clean week copies end to end from the page', function (): void {
    $this->actingAsAdmin();
    showtimeAtLocal('2027-06-08 19:00');
    showtimeAtLocal('2027-06-09 21:30');

    Livewire::test(CopyWeekShowtimes::class)
        ->set('data.source_week', '2027-06-07')
        ->set('data.target_week', '2027-06-14')
        ->call('submit')
        ->assertHasNoFormErrors();

    $targetWeek = Showtime::query()
        ->where('start_time', '>=', Carbon::parse('2027-06-14'))
        ->where('start_time', '<', Carbon::parse('2027-06-21'))
        ->get();

    expect($targetWeek)->toHaveCount(2);
});

test('conflicting rows are skipped on commit while clean rows are created', function (): void {
    $this->actingAsAdmin();
    showtimeAtLocal('2027-06-08 19:00'); // will conflict in target week
    showtimeAtLocal('2027-06-09 21:30'); // clean
    $blocker = showtimeAtLocal('2027-06-15 19:30'); // occupies the first row's target slot

    Livewire::test(CopyWeekShowtimes::class)
        ->set('data.source_week', '2027-06-07')
        ->set('data.target_week', '2027-06-14')
        ->call('submit')
        ->call('commit');

    $targetWeek = Showtime::query()
        ->where('start_time', '>=', Carbon::parse('2027-06-14'))
        ->where('start_time', '<', Carbon::parse('2027-06-21'))
        ->whereKeyNot($blocker->id)
        ->get();

    expect($targetWeek)->toHaveCount(1);
    expect($targetWeek->first()->start_time->copy()->setTimezone('America/New_York')->format('Y-m-d H:i'))
        ->toBe('2027-06-16 21:30');
});
