<?php

use App\Filament\Resources\ShowtimeResource;
use App\Filament\Resources\ShowtimeResource\Pages\CreateShowtime;
use App\Filament\Resources\ShowtimeResource\Pages\ListShowtimes;
use App\Filament\Resources\ShowtimeResource\Pages\ViewShowtime;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->location = Location::factory()->create();
    $this->auditorium = Auditorium::factory()->create([
        'location_id' => $this->location->id,
        'cleanup_minutes' => 15,
    ]);
    $this->movie = Movie::factory()->create(['runtime' => 120]);
});

test('ops can view the showtime list but cannot create, edit, delete, or cancel', function (): void {
    $this->actingAsOps();
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    expect(ShowtimeResource::canViewAny())->toBeTrue();
    expect(ShowtimeResource::canCreate())->toBeFalse();
    expect(ShowtimeResource::canEdit($showtime))->toBeFalse();
    expect(ShowtimeResource::canDelete($showtime))->toBeFalse();

    $this->get(CreateShowtime::getUrl())->assertForbidden();

    Livewire::test(ViewShowtime::class, ['record' => $showtime->id])
        ->assertActionHidden('cancel');
});

test('managers can create, edit, and cancel showtimes', function (): void {
    $this->actingAsManager();
    $showtime = Showtime::factory()->create([
        'movie_id' => $this->movie->id,
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDays(7),
    ]);

    expect(ShowtimeResource::canViewAny())->toBeTrue();
    expect(ShowtimeResource::canCreate())->toBeTrue();
    expect(ShowtimeResource::canEdit($showtime))->toBeTrue();

    Livewire::test(ViewShowtime::class, ['record' => $showtime->id])
        ->assertActionVisible('cancel');
});

test('an admin user with no role cannot view the showtime list', function (): void {
    $this->actingAsNobody();

    expect(ShowtimeResource::canViewAny())->toBeFalse();

    $this->get(ListShowtimes::getUrl())->assertForbidden();
});
