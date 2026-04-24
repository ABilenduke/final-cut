<?php

use App\Filament\Pages\SchedulePlanner;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->location = Location::factory()->create(['name' => 'Downtown', 'slug' => 'downtown']);
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'name' => 'Screen 1',
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120, 'title' => 'Interstellar']);
});

test('planner renders the current week by default for admin users', function (): void {
    $this->actingAsAdmin();

    Livewire::test(SchedulePlanner::class)
        ->assertSet('weekStart', now()->startOfWeek(Carbon::MONDAY)->toDateString())
        ->assertSee('Screen 1')
        ->assertOk();
});

test('planner renders showtimes in the week they fall in', function (): void {
    $this->actingAsAdmin();

    // Anchor to a specific Monday so the test is deterministic regardless of `now()`.
    $mondayIso = '2026-05-04';
    $weds = Carbon::parse($mondayIso)->addDays(2)->setTime(19, 0);

    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => $weds,
    ]);

    Livewire::test(SchedulePlanner::class, ['weekStart' => $mondayIso, 'locationSlug' => $this->location->slug])
        ->assertSee('Interstellar')
        ->assertSee('7:00 PM');
});

test('cancelled showtimes are hidden from the planner', function (): void {
    $this->actingAsAdmin();

    $mondayIso = '2026-05-04';
    $weds = Carbon::parse($mondayIso)->addDays(2)->setTime(19, 0);

    Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => $weds,
        'cancelled_at' => now(),
        'cancellation_reason' => 'cancelled',
    ]);

    Livewire::test(SchedulePlanner::class, ['weekStart' => $mondayIso, 'locationSlug' => $this->location->slug])
        ->assertDontSee('Interstellar');
});

test('a malformed ?week= URL param falls back to the current week instead of 500ing', function (): void {
    $this->actingAsAdmin();

    $thisMonday = now()->startOfWeek(Carbon::MONDAY)->toDateString();

    Livewire::test(SchedulePlanner::class, [
        'weekStart' => 'not-a-date',
        'locationSlug' => $this->location->slug,
    ])
        ->assertSet('weekStart', $thisMonday)
        ->assertOk();
});

test('a mid-week ?week= param is normalised to that week\'s Monday', function (): void {
    $this->actingAsAdmin();

    // 2026-05-06 is a Wednesday; the planner should render the Monday of that week.
    Livewire::test(SchedulePlanner::class, [
        'weekStart' => '2026-05-06',
        'locationSlug' => $this->location->slug,
    ])
        ->assertSet('weekStart', '2026-05-04');
});

test('week navigation shifts the visible week', function (): void {
    $this->actingAsAdmin();

    $mondayIso = '2026-05-04';

    Livewire::test(SchedulePlanner::class, ['weekStart' => $mondayIso, 'locationSlug' => $this->location->slug])
        ->call('nextWeek')
        ->assertSet('weekStart', '2026-05-11')
        ->call('previousWeek')
        ->assertSet('weekStart', '2026-05-04')
        ->call('thisWeek')
        ->assertSet('weekStart', now()->startOfWeek(Carbon::MONDAY)->toDateString());
});

test('location tab switching filters auditoriums to the chosen location', function (): void {
    $this->actingAsAdmin();

    $otherLocation = Location::factory()->create(['name' => 'Uptown', 'slug' => 'uptown']);
    $otherAuditorium = Auditorium::factory()->create([
        'location_id' => $otherLocation->id,
        'name' => 'Big Screen',
    ]);

    Livewire::test(SchedulePlanner::class, ['locationSlug' => $this->location->slug])
        ->assertSee('Screen 1')
        ->assertDontSee('Big Screen')
        ->call('setLocation', $otherLocation->slug)
        ->assertSee('Big Screen')
        ->assertDontSee('Screen 1');
});

test('ops can view the planner', function (): void {
    $this->actingAsOps();

    expect(SchedulePlanner::canAccess())->toBeTrue();

    Livewire::test(SchedulePlanner::class, ['locationSlug' => $this->location->slug])
        ->assertOk();
});

test('ops do not see the Add button on empty cells', function (): void {
    $this->actingAsOps();

    Livewire::test(SchedulePlanner::class, ['locationSlug' => $this->location->slug])
        ->assertDontSee('+ Add');
});

test('admins do see the Add button on empty cells', function (): void {
    $this->actingAsAdmin();

    Livewire::test(SchedulePlanner::class, ['locationSlug' => $this->location->slug])
        ->assertSee('+ Add');
});

test('an admin user with no role cannot access the planner', function (): void {
    $this->actingAsNobody();

    expect(SchedulePlanner::canAccess())->toBeFalse();

    $this->get(SchedulePlanner::getUrl())->assertForbidden();
});
