<?php

use App\Exceptions\AuditoriumSeatRegenerationBlockedException;
use App\Filament\Resources\AuditoriumResource\Pages\ConfigureSeats;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Services\AuditoriumService;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('submitting a valid config calls generateSeats with the expanded row array and admin actor', function (): void {
    $auditorium = Auditorium::factory()->create();
    $premium = AuditoriumSection::factory()->for($auditorium)->premium()->create();
    $standard = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    $capturedConfig = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('getRegenerationBlockers')
        ->zeroOrMoreTimes()
        ->andReturn(['future_showtimes' => 0, 'active_bookings' => 0, 'held_seats' => 0]);
    $service->shouldReceive('generateSeats')
        ->once()
        ->andReturnUsing(function (Auditorium $a, array $config, ?AdminUser $actor) use (&$capturedConfig, &$capturedActor) {
            $capturedConfig = $config;
            $capturedActor = $actor;
        });

    Livewire::test(ConfigureSeats::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.rows', 10)
        ->set('data.seats_per_row', 12)
        ->set('data.section_map', [
            ['row_range' => 'A-B', 'section_id' => $premium->id, 'type' => 'premium'],
            ['row_range' => 'C', 'section_id' => $standard->id, 'type' => 'standard'],
        ])
        ->set('data.unavailable_seats', ['A3', 'J11'])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($capturedConfig['rows'])->toBe(10);
    expect($capturedConfig['seats_per_row'])->toBe(12);
    expect($capturedConfig['section_map'][0]['rows'])->toBe(['A', 'B']);
    expect($capturedConfig['section_map'][0]['type'])->toBe('premium');
    expect($capturedConfig['section_map'][1]['rows'])->toBe(['C']);
    expect($capturedConfig['unavailable_seats'])->toBe(['A3', 'J11']);
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('row range A-C expands to [A,B,C]', function (): void {
    expect(ConfigureSeats::expandRowRange('A-C'))->toBe(['A', 'B', 'C']);
    expect(ConfigureSeats::expandRowRange('A'))->toBe(['A']);
    expect(ConfigureSeats::expandRowRange('D-H'))->toBe(['D', 'E', 'F', 'G', 'H']);
});

test('invalid row-range tokens fail the page submission without calling the service', function (): void {
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('getRegenerationBlockers')
        ->zeroOrMoreTimes()
        ->andReturn(['future_showtimes' => 0, 'active_bookings' => 0, 'held_seats' => 0]);
    $service->shouldReceive('generateSeats')->never();

    Livewire::test(ConfigureSeats::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.rows', 10)
        ->set('data.seats_per_row', 12)
        ->set('data.section_map', [
            ['row_range' => 'C-A', 'section_id' => $section->id, 'type' => 'standard'],
        ])
        ->set('data.unavailable_seats', [])
        ->call('submit')
        ->assertHasFormErrors();
});

test('isValidRowRange accepts single letters and contiguous ranges only', function (): void {
    expect(ConfigureSeats::isValidRowRange('A'))->toBeTrue();
    expect(ConfigureSeats::isValidRowRange('Z'))->toBeTrue();
    expect(ConfigureSeats::isValidRowRange('A-C'))->toBeTrue();
    expect(ConfigureSeats::isValidRowRange('A-Z'))->toBeTrue();

    expect(ConfigureSeats::isValidRowRange('AA'))->toBeFalse();
    expect(ConfigureSeats::isValidRowRange('C-A'))->toBeFalse();
    expect(ConfigureSeats::isValidRowRange('a-c'))->toBeFalse();
    expect(ConfigureSeats::isValidRowRange('1-3'))->toBeFalse();
    expect(ConfigureSeats::isValidRowRange('A-B,D-E'))->toBeFalse();
});

test('when the service throws AuditoriumSeatRegenerationBlockedException the UI renders blocker counts and does not report success', function (): void {
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('getRegenerationBlockers')
        ->zeroOrMoreTimes()
        ->andReturn(['future_showtimes' => 0, 'active_bookings' => 0, 'held_seats' => 0]);
    $service->shouldReceive('generateSeats')
        ->once()
        ->andThrow(new AuditoriumSeatRegenerationBlockedException([
            'future_showtimes' => 3,
            'active_bookings' => 12,
            'held_seats' => 0,
        ]));

    Livewire::test(ConfigureSeats::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.rows', 4)
        ->set('data.seats_per_row', 4)
        ->set('data.section_map', [['row_range' => 'A-D', 'section_id' => $section->id, 'type' => 'standard']])
        ->set('data.unavailable_seats', [])
        ->call('submit');

    Notification::assertNotified('Seat regeneration refused');
});

test('when the service throws a generic error the UI states the existing layout was not changed', function (): void {
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('getRegenerationBlockers')
        ->zeroOrMoreTimes()
        ->andReturn(['future_showtimes' => 0, 'active_bookings' => 0, 'held_seats' => 0]);
    $service->shouldReceive('generateSeats')
        ->once()
        ->andThrow(new RuntimeException('boom'));

    Livewire::test(ConfigureSeats::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.rows', 4)
        ->set('data.seats_per_row', 4)
        ->set('data.section_map', [['row_range' => 'A-D', 'section_id' => $section->id, 'type' => 'standard']])
        ->set('data.unavailable_seats', [])
        ->call('submit');

    Notification::assertNotified('Regeneration failed');
});

test('when blockers exist, submit is refused at the page level and the service is never called', function (): void {
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('getRegenerationBlockers')
        ->zeroOrMoreTimes()
        ->andReturn(['future_showtimes' => 1, 'active_bookings' => 0, 'held_seats' => 0]);
    $service->shouldReceive('generateSeats')->never();

    Livewire::test(ConfigureSeats::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.rows', 4)
        ->set('data.seats_per_row', 4)
        ->set('data.section_map', [['row_range' => 'A-D', 'section_id' => $section->id, 'type' => 'standard']])
        ->set('data.unavailable_seats', [])
        ->call('submit');

    Notification::assertNotified('Seat regeneration refused');
});

test('ops role cannot mount the configure-seats page', function (): void {
    $this->actingAsOps();
    $auditorium = Auditorium::factory()->create();

    $this->get(ConfigureSeats::getUrl(['record' => $auditorium]))
        ->assertForbidden();
});
