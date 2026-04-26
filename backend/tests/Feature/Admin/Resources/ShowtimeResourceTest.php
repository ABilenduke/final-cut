<?php

use App\Filament\Resources\ShowtimeResource\Pages\CreateShowtime;
use App\Filament\Resources\ShowtimeResource\Pages\EditShowtime;
use App\Filament\Resources\ShowtimeResource\Pages\ListShowtimes;
use App\Filament\Resources\ShowtimeResource\Pages\ViewShowtime;
use App\Models\User;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
});

test('admins can see the showtime list', function (): void {
    // Pin start_time to non-overlapping slots so the random factory date
    // can't trip the showtimes_no_overlap exclusion constraint when three
    // rows land on the same auditorium.
    $base = now()->addDays(7)->setTime(12, 0);
    $showtimes = collect(range(0, 2))->map(fn (int $i) => Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => $base->copy()->addHours($i * 4),
    ]));

    Livewire::test(ListShowtimes::class)
        ->assertCanSeeTableRecords($showtimes);
});

test('creating a showtime routes through ShowtimeService with the admin actor', function (): void {
    $expected = Showtime::factory()->make([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
    ]);
    $expected->id = (string) Str::uuid();

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(ShowtimeService::class);
    // detectConflicts runs during validateAgainstConflicts; mock it to return empty.
    $service->shouldReceive('detectConflicts')
        ->andReturn(collect());
    $service->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data, ?User $actor) use (&$capturedData, &$capturedActor, $expected) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $expected;
        });

    Livewire::test(CreateShowtime::class)
        ->set('data.movie_id', $this->movie->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_time', '2026-06-01 19:00:00')
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('create')
        ->assertHasNoFormErrors();

    expect($capturedData['movie_id'])->toBe($this->movie->id);
    expect($capturedData['auditorium_id'])->toBe($this->auditorium->id);
    // Filament's numeric TextInput dehydrates as string; service casts to int on write.
    expect((int) $capturedData['price_standard'])->toBe(1200);
    expect($capturedActor?->id)->toBe($this->admin->id);

    // location_id is form-only and must not leak into persistence.
    expect($capturedData)->not->toHaveKey('location_id');
});

test('editing a showtime routes through ShowtimeService with the admin actor', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    $capturedActor = null;

    $service = $this->mock(ShowtimeService::class);
    $service->shouldReceive('detectConflicts')
        ->andReturn(collect());
    $service->shouldReceive('update')
        ->once()
        ->andReturnUsing(function ($record, array $data, ?User $actor) use (&$capturedActor) {
            $capturedActor = $actor;

            return $record;
        });

    Livewire::test(EditShowtime::class, ['record' => $showtime->id])
        ->set('data.price_standard', 1500)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('create form blocks submission when the chosen movie has no runtime', function (): void {
    $movieWithoutRuntime = Movie::factory()->create(['runtime' => null]);

    // ShowtimeService should never be called — validation trips first.
    $service = $this->mock(ShowtimeService::class);
    $service->shouldNotReceive('create');

    Livewire::test(CreateShowtime::class)
        ->set('data.movie_id', $movieWithoutRuntime->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_time', '2026-06-01 19:00:00')
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('create')
        ->assertHasFormErrors(['movie_id']);
});

test('create form blocks submission when detectConflicts returns a collision', function (): void {
    $conflicting = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => '2026-06-01 18:00:00',
        'end_time' => '2026-06-01 20:00:00',
    ]);

    $service = $this->mock(ShowtimeService::class);
    $service->shouldReceive('detectConflicts')
        ->once()
        ->andReturn(collect([$conflicting->load('movie')]));
    $service->shouldNotReceive('create');

    Livewire::test(CreateShowtime::class)
        ->set('data.movie_id', $this->movie->id)
        ->set('data.location_id', $this->location->id)
        ->set('data.auditorium_id', $this->auditorium->id)
        ->set('data.start_time', '2026-06-01 19:00:00')
        ->set('data.price_standard', 1200)
        ->set('data.price_premium', 1800)
        ->set('data.price_accessible', 1000)
        ->call('create')
        ->assertHasFormErrors(['start_time']);
});

test('cancel action is hidden for cancelled showtimes', function (): void {
    $cancelled = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
        'cancelled_at' => now(),
        'cancellation_reason' => 'earlier pass',
    ]);

    Livewire::test(ViewShowtime::class, ['record' => $cancelled->id])
        ->assertActionHidden('cancel');
});

test('cancel action is hidden for past showtimes', function (): void {
    $past = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subDay(),
        'end_time' => now()->subDay()->addMinutes(120),
    ]);

    Livewire::test(ViewShowtime::class, ['record' => $past->id])
        ->assertActionHidden('cancel');
});

test('cancel action calls ShowtimeService::cancel with the actor', function (): void {
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    $capturedReason = null;
    $capturedActor = null;

    $service = $this->mock(ShowtimeService::class);
    $service->shouldReceive('cancel')
        ->once()
        ->andReturnUsing(function ($record, $reason, ?User $actor) use (&$capturedReason, &$capturedActor) {
            $capturedReason = $reason;
            $capturedActor = $actor;
        });

    Livewire::test(ViewShowtime::class, ['record' => $showtime->id])
        ->assertActionVisible('cancel')
        ->mountAction('cancel')
        ->assertActionMounted('cancel')
        ->set('mountedActions.0.data.reason', 'Projector failure')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($capturedReason)->toBe('Projector failure');
    expect($capturedActor?->id)->toBe($this->admin->id);
});
