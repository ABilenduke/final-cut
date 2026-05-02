<?php

use App\Filament\Resources\ShowtimeResource\Pages\BulkCreateShowtimes;
use App\Filament\Resources\ShowtimeResource\Pages\ListShowtimes;
use App\Http\Requests\BulkShowtimeRequest;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ShowtimeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function (): void {
    // The form gates `start_date` with `minDate(today())`. The cases below pin
    // dates to 2026-05-01 (a Friday) so day-of-week assertions stay meaningful;
    // freeze "now" before that anchor so the validation rule keeps passing.
    Carbon::setTestNow('2026-04-30 12:00:00');

    $this->admin = $this->actingAsAdmin();
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
});

test('bulk create preview counts upcoming showtimes across date range × days × times', function (): void {
    // detectConflictsForBatch mocked to return an empty Collection per interval
    // so submit() auto-commits without hitting the DB.
    $service = $this->mock(ShowtimeService::class);
    $service->shouldReceive('detectConflictsForBatch')
        ->once()
        ->andReturnUsing(fn (string $_id, Collection $intervals) => $intervals->map(fn () => collect()));

    $capturedRequest = null;
    $capturedActor = null;
    $service->shouldReceive('bulkCreate')
        ->once()
        ->andReturnUsing(function (BulkShowtimeRequest $r, ?User $actor) use (&$capturedRequest, &$capturedActor) {
            $capturedRequest = $r;
            $capturedActor = $actor;

            return new Collection;
        });

    Livewire::test(BulkCreateShowtimes::class)
        ->set('data.movie_id', $this->movie->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        // 2026-05-01 is a Friday. Through 2026-05-14 (Thursday) is 2 weeks.
        ->set('data.start_date', '2026-05-01')
        ->set('data.end_date', '2026-05-14')
        // Weekdays only — 5 days/wk × 2 weeks = 10 days, × 2 times = 20 tuples.
        ->set('data.days_of_week', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])
        ->set('data.times', ['19:00', '21:30'])
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('submit');

    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->tuples)->toHaveCount(20);
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('bulk create splits into creatable and conflicting subsets when some tuples overlap', function (): void {
    $service = $this->mock(ShowtimeService::class);

    // Simulate: first tuple has no conflict, second does, third does not.
    $conflicting = Showtime::factory()->make([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-05-01 21:30:00',
        'end_time' => '2026-05-01 23:45:00',
    ]);
    $conflicting->setRelation('movie', $this->movie);

    $service->shouldReceive('detectConflictsForBatch')
        ->once()
        ->andReturnUsing(
            fn (string $_id, Collection $intervals) => $intervals->map(
                fn (array $interval, int $idx) => $idx === 1 ? collect([$conflicting]) : collect()
            )->values()
        );

    // bulkCreate should NOT be called when conflicts exist — Phase 2 is separate.
    $service->shouldNotReceive('bulkCreate');

    $component = Livewire::test(BulkCreateShowtimes::class)
        ->set('data.movie_id', $this->movie->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_date', '2026-05-01')
        ->set('data.end_date', '2026-05-01')
        ->set('data.days_of_week', ['Fri'])
        ->set('data.times', ['19:00', '21:30', '23:45'])
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('submit');

    $preview = $component->get('preview');

    expect($preview['creatable'])->toHaveCount(2);
    expect($preview['conflicting'])->toHaveCount(1);
    expect($preview['conflicting'][0]['time'])->toBe('21:30');
});

test('bulk create detects intra-batch overlaps without a DB round-trip', function (): void {
    $service = $this->mock(ShowtimeService::class);

    // No DB conflicts — the only overlaps come from within the request.
    $service->shouldReceive('detectConflictsForBatch')
        ->once()
        ->andReturnUsing(fn (string $_id, Collection $intervals) => $intervals->map(fn () => collect()));
    $service->shouldNotReceive('bulkCreate');

    // Movie runtime 120 min + auditorium cleanup 15 min = 135 min. Times
    // "19:00" and "19:30" both occupy the 19:00-21:15 and 19:30-21:45 windows
    // — they overlap each other even though neither hits an existing DB row.
    $component = Livewire::test(BulkCreateShowtimes::class)
        ->set('data.movie_id', $this->movie->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_date', '2026-05-01')
        ->set('data.end_date', '2026-05-01')
        ->set('data.days_of_week', ['Fri'])
        ->set('data.times', ['19:00', '19:30'])
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('submit');

    $preview = $component->get('preview');

    expect($preview['creatable'])->toHaveCount(0);
    expect($preview['conflicting'])->toHaveCount(2);
    expect($preview['conflicting'][0]['conflicts'][0])->toContain('another entry in this batch');
});

test('phase-two commit calls bulkCreate with only the creatable subset', function (): void {
    $service = $this->mock(ShowtimeService::class);
    $service->shouldReceive('detectConflictsForBatch')
        ->andReturnUsing(fn (string $_id, Collection $intervals) => $intervals->map(fn () => collect()));

    $capturedRequest = null;
    $service->shouldReceive('bulkCreate')
        ->once()
        ->andReturnUsing(function (BulkShowtimeRequest $r) use (&$capturedRequest) {
            $capturedRequest = $r;

            return new Collection;
        });

    // Simulate: admin has already run submit() and we're now invoking commit()
    // with a preview state containing 2 creatable tuples and 1 conflict.
    Livewire::test(BulkCreateShowtimes::class)
        ->set('preview', [
            'creatable' => [
                ['date' => '2026-05-01', 'time' => '19:00', 'start_iso' => '2026-05-01T19:00:00+00:00'],
                ['date' => '2026-05-01', 'time' => '23:45', 'start_iso' => '2026-05-01T23:45:00+00:00'],
            ],
            'conflicting' => [
                ['date' => '2026-05-01', 'time' => '21:30', 'conflicts' => ['Movie @ May 1 9:30 PM']],
            ],
            'movie_id' => (int) $this->movie->id,
            'auditorium_id' => $this->auditorium->id,
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
        ])
        ->call('commit');

    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->tuples)->toHaveCount(2);
    expect($capturedRequest->auditoriumId)->toBe($this->auditorium->id);
    expect($capturedRequest->priceStandard)->toBe(1200);
});

test('bulk create blocks submission when the chosen movie has no runtime', function (): void {
    $movieWithoutRuntime = Movie::factory()->create(['runtime' => null]);

    $service = $this->mock(ShowtimeService::class);
    $service->shouldNotReceive('detectConflictsForBatch');
    $service->shouldNotReceive('bulkCreate');

    Livewire::test(BulkCreateShowtimes::class)
        ->set('data.movie_id', $movieWithoutRuntime->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_date', '2026-05-01')
        ->set('data.end_date', '2026-05-02')
        ->set('data.days_of_week', ['Fri'])
        ->set('data.times', ['19:00'])
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('submit')
        ->assertNotified();
});

test('bulk create is gated by showtimes.create permission', function (): void {
    $this->actingAsOps();

    // ops does not hold showtimes.create — the page must 403 on mount.
    $this->get(BulkCreateShowtimes::getUrl())->assertForbidden();
});

test('bulk create action is visible on list page for permitted users', function (): void {
    Livewire::test(ListShowtimes::class)
        ->assertActionVisible('bulk_create');
});
