<?php

use App\Exceptions\LocationHasBookingsException;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\LocationResource\Pages\EditLocation;
use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditoriumService;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admins can see the location list', function (): void {
    $locations = Location::factory()->count(3)->create();

    Livewire::test(ListLocations::class)
        ->assertCanSeeTableRecords($locations);
});

test('creating a location routes through AuditoriumService with the admin actor', function (): void {
    $stub = Location::factory()->make(['name' => 'Persisted Stub', 'slug' => 'persisted-stub']);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('createLocation')
        ->once()
        ->andReturnUsing(function (array $data, ?User $actor) use (&$capturedData, &$capturedActor, $stub) {
            $capturedData = $data;
            $capturedActor = $actor;
            $stub->save();

            return $stub;
        });

    Livewire::test(CreateLocation::class)
        ->set('data.name', 'New Cinema')
        ->set('data.slug', 'new-cinema')
        ->set('data.timezone', 'America/New_York')
        ->set('data.country', 'US')
        ->call('create')
        ->assertHasNoFormErrors();

    expect($capturedData['name'])->toBe('New Cinema');
    expect($capturedData['slug'])->toBe('new-cinema');
    expect($capturedData['timezone'])->toBe('America/New_York');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('editing a location routes through AuditoriumService with the admin actor', function (): void {
    $location = Location::factory()->create(['name' => 'Before']);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('updateLocation')
        ->once()
        ->andReturnUsing(function (Location $record, array $data, ?User $actor) use (&$capturedData, &$capturedActor, $location) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $location->fill(['name' => $data['name'] ?? $location->name]);
        });

    Livewire::test(EditLocation::class, ['record' => $location->getRouteKey()])
        ->set('data.name', 'After')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedData['name'])->toBe('After');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('deleting a location routes through AuditoriumService — the row survives if the service is mocked (DeleteAction regression guard)', function (): void {
    $location = Location::factory()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('deleteLocation')
        ->once()
        ->withArgs(function (Location $record, ?User $actor) use ($location) {
            expect($record->id)->toBe($location->id);
            expect($actor?->id)->toBe($this->admin->id);

            return true;
        });

    Livewire::test(ListLocations::class)
        ->callTableAction('delete', $location);

    // Mock swallowed the delete — row must still exist. This proves
    // DeleteAction::make()->using(...) is wired; a stock DeleteAction would
    // have called $record->delete() directly and removed the row.
    expect(Location::find($location->id))->not->toBeNull();
});

test('timezone default falls back to app.timezone when no config override is set (no hardcoded geographic bias)', function (): void {
    config()->set('app.default_location_timezone', null);
    config()->set('app.timezone', 'UTC');

    Livewire::test(CreateLocation::class)
        ->assertSet('data.timezone', 'UTC');
});

test('timezone default honours app.default_location_timezone when configured', function (): void {
    config()->set('app.default_location_timezone', 'America/Chicago');

    Livewire::test(CreateLocation::class)
        ->assertSet('data.timezone', 'America/Chicago');
});

test('creating a location without a timezone produces a validation error', function (): void {
    $this->mock(AuditoriumService::class)
        ->shouldReceive('createLocation')
        ->never();

    Livewire::test(CreateLocation::class)
        ->set('data.name', 'No TZ')
        ->set('data.slug', 'no-tz')
        ->set('data.timezone', null)
        ->set('data.country', 'US')
        ->call('create')
        ->assertHasFormErrors(['timezone']);
});

test('delete action surfaces a danger notification when LocationHasBookingsException fires', function (): void {
    $location = Location::factory()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('deleteLocation')
        ->once()
        ->andThrow(new LocationHasBookingsException);

    Livewire::test(ListLocations::class)
        ->callTableAction('delete', $location);

    Notification::assertNotified('Cannot delete location');
    expect(Location::find($location->id))->not->toBeNull();
});
