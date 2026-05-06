<?php

use App\Exceptions\LocationHasBookingsException;
use App\Filament\Resources\LocationResource;
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

// ---------------------------------------------------------------------------
// Hours of Operation — form section tests
// ---------------------------------------------------------------------------

test('the hours section renders per-day toggle and time fields in the form schema', function (): void {
    $component = Livewire::test(CreateLocation::class);

    // Each day should have its closed toggle present in the form
    foreach (array_keys(LocationResource::DAYS) as $day) {
        $component->assertFormFieldExists("hours_{$day}_closed");
    }
});

test('explodeHoursForForm populates per-day virtual fields from the hours JSON', function (): void {
    $data = [
        'hours' => [
            'monday' => ['open' => '12:00', 'close' => '23:00'],
            'tuesday' => null,
            'wednesday' => ['open' => '10:00', 'close' => '22:00'],
            'thursday' => ['open' => '12:00', 'close' => '23:30'],
            'friday' => ['open' => '12:00', 'close' => '00:30'],
            'saturday' => null,
            'sunday' => ['open' => '10:00', 'close' => '23:00'],
        ],
    ];

    $result = LocationResource::explodeHoursForForm($data);

    // Open day — closed toggle off, times populated
    expect($result['hours_monday_closed'])->toBeFalse();
    expect($result['hours_monday_open'])->toBe('12:00');
    expect($result['hours_monday_close'])->toBe('23:00');

    // Closed day — closed toggle on, times null
    expect($result['hours_tuesday_closed'])->toBeTrue();
    expect($result['hours_tuesday_open'])->toBeNull();
    expect($result['hours_tuesday_close'])->toBeNull();

    // Saturday also closed
    expect($result['hours_saturday_closed'])->toBeTrue();

    // Original hours key survives (so the model field is still filled)
    expect($result)->toHaveKey('hours');
});

test('implodeHoursFromForm assembles hours JSON with null for closed days', function (): void {
    $data = [
        'name' => 'Test Cinema',
        'hours_monday_closed' => false,
        'hours_monday_open' => '12:00',
        'hours_monday_close' => '23:00',
        'hours_tuesday_closed' => true,
        'hours_tuesday_open' => null,
        'hours_tuesday_close' => null,
        'hours_wednesday_closed' => false,
        'hours_wednesday_open' => '10:00',
        'hours_wednesday_close' => '22:00',
        'hours_thursday_closed' => false,
        'hours_thursday_open' => '12:00',
        'hours_thursday_close' => '23:30',
        'hours_friday_closed' => false,
        'hours_friday_open' => '12:00',
        'hours_friday_close' => '00:30',
        'hours_saturday_closed' => true,
        'hours_saturday_open' => null,
        'hours_saturday_close' => null,
        'hours_sunday_closed' => false,
        'hours_sunday_open' => '10:00',
        'hours_sunday_close' => '23:00',
    ];

    $result = LocationResource::implodeHoursFromForm($data);

    // Virtual keys are removed
    expect($result)->not->toHaveKey('hours_monday_closed');
    expect($result)->not->toHaveKey('hours_tuesday_open');

    // hours key contains assembled JSON
    expect($result['hours']['monday'])->toBe(['open' => '12:00', 'close' => '23:00']);
    expect($result['hours']['tuesday'])->toBeNull();
    expect($result['hours']['wednesday'])->toBe(['open' => '10:00', 'close' => '22:00']);
    expect($result['hours']['saturday'])->toBeNull();
    expect($result['hours']['sunday'])->toBe(['open' => '10:00', 'close' => '23:00']);

    // Non-hours keys survive untouched
    expect($result['name'])->toBe('Test Cinema');
});

test('implodeHoursFromForm treats a day with both times blank as closed (null)', function (): void {
    $data = array_merge(
        // All days closed by default
        collect(array_keys(LocationResource::DAYS))
            ->flatMap(fn ($day) => [
                "hours_{$day}_closed" => true,
                "hours_{$day}_open" => null,
                "hours_{$day}_close" => null,
            ])->all(),
        // Monday: toggle off but no times entered — should resolve to null
        [
            'hours_monday_closed' => false,
            'hours_monday_open' => null,
            'hours_monday_close' => null,
        ]
    );

    $result = LocationResource::implodeHoursFromForm($data);

    expect($result['hours']['monday'])->toBeNull();
});

test('saving a location via the edit form persists hours JSON with correct shape', function (): void {
    $location = Location::factory()->create([
        'hours' => [
            'monday' => ['open' => '09:00', 'close' => '21:00'],
            'tuesday' => ['open' => '09:00', 'close' => '21:00'],
            'wednesday' => ['open' => '09:00', 'close' => '21:00'],
            'thursday' => ['open' => '09:00', 'close' => '21:00'],
            'friday' => ['open' => '09:00', 'close' => '22:00'],
            'saturday' => ['open' => '10:00', 'close' => '22:00'],
            'sunday' => ['open' => '10:00', 'close' => '20:00'],
        ],
    ]);

    Livewire::test(EditLocation::class, ['record' => $location->getRouteKey()])
        // Mark Sunday as closed, change Monday opening time
        ->set('data.hours_sunday_closed', true)
        ->set('data.hours_monday_open', '12:00')
        ->set('data.hours_monday_close', '23:00')
        ->call('save')
        ->assertHasNoFormErrors();

    $location->refresh();
    $hours = $location->hours;

    expect($hours['sunday'])->toBeNull();
    expect($hours['monday']['open'])->toBe('12:00');
    expect($hours['monday']['close'])->toBe('23:00');
    // Other days should still be saved (they were pre-populated by mutateFormDataBeforeFill)
    expect($hours['friday'])->toBeArray();
});

test('saving with all days null (all closed) does not break the form', function (): void {
    $location = Location::factory()->create(['hours' => null]);

    Livewire::test(EditLocation::class, ['record' => $location->getRouteKey()])
        // All days will default to closed (closed toggle = true) because hours is null
        ->call('save')
        ->assertHasNoFormErrors();

    $location->refresh();
    $hours = $location->hours;

    // Every day should be null
    foreach (array_keys(LocationResource::DAYS) as $day) {
        expect($hours[$day] ?? null)->toBeNull();
    }
});

test('form schema exposes all public API fields for editing', function (): void {
    $auditFields = [
        'name', 'slug', 'phone', 'email',
        'street', 'city', 'state', 'postal_code', 'country',
        'timezone', 'latitude', 'longitude',
    ];

    $component = Livewire::test(CreateLocation::class);

    foreach ($auditFields as $field) {
        $component->assertFormFieldExists($field);
    }

    // Hours section: each day's closed toggle must exist
    foreach (array_keys(LocationResource::DAYS) as $day) {
        $component->assertFormFieldExists("hours_{$day}_closed");
    }
});
