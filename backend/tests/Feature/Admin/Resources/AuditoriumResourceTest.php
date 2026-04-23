<?php

use App\Filament\Resources\AuditoriumResource;
use App\Filament\Resources\AuditoriumResource\Pages\CreateAuditorium;
use App\Filament\Resources\AuditoriumResource\Pages\EditAuditorium;
use App\Filament\Resources\AuditoriumResource\Pages\ListAuditoriums;
use App\Filament\Resources\LocationResource\RelationManagers\AuditoriumsRelationManager;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Location;
use App\Models\Seat;
use App\Services\AuditoriumService;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admins can see the auditorium list', function (): void {
    $rows = Auditorium::factory()->count(3)->create();

    Livewire::test(ListAuditoriums::class)
        ->assertCanSeeTableRecords($rows);
});

test('creating an auditorium routes through AuditoriumService with the admin actor', function (): void {
    $location = Location::factory()->create();
    $stub = Auditorium::factory()->make(['location_id' => $location->id, 'name' => 'Stub', 'slug' => 'stub']);

    $capturedLocation = null;
    $capturedRecord = null;
    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('saveAuditoriumWithSections')
        ->once()
        ->andReturnUsing(function (Location $loc, ?Auditorium $record, array $attributes, ?array $sections, ?AdminUser $actor) use (&$capturedLocation, &$capturedRecord, &$capturedData, &$capturedActor, $stub) {
            $capturedLocation = $loc;
            $capturedRecord = $record;
            $capturedData = $attributes;
            $capturedActor = $actor;
            $stub->location_id = $loc->id;
            $stub->save();

            return $stub;
        });

    Livewire::test(CreateAuditorium::class)
        ->set('data.location_id', $location->id)
        ->set('data.name', 'New Auditorium')
        ->set('data.slug', 'new-auditorium')
        ->set('data.cleanup_minutes', 25)
        ->set('data.sections', [])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($capturedLocation?->id)->toBe($location->id);
    expect($capturedRecord)->toBeNull();
    expect($capturedData['name'])->toBe('New Auditorium');
    expect((int) $capturedData['cleanup_minutes'])->toBe(25);
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('editing an auditorium routes through AuditoriumService with the admin actor', function (): void {
    $auditorium = Auditorium::factory()->create(['name' => 'Before', 'cleanup_minutes' => 20]);

    $capturedRecord = null;
    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('saveAuditoriumWithSections')
        ->once()
        ->andReturnUsing(function (Location $loc, ?Auditorium $record, array $attributes, ?array $sections, ?AdminUser $actor) use (&$capturedRecord, &$capturedData, &$capturedActor, $auditorium) {
            $capturedRecord = $record;
            $capturedData = $attributes;
            $capturedActor = $actor;

            return $auditorium->fill(['name' => $attributes['name'] ?? $auditorium->name]);
        });

    Livewire::test(EditAuditorium::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.name', 'After')
        ->set('data.cleanup_minutes', 30)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedRecord?->id)->toBe($auditorium->id);
    expect($capturedData['name'])->toBe('After');
    expect((int) $capturedData['cleanup_minutes'])->toBe(30);
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('deleting an auditorium routes through AuditoriumService — row survives when service is mocked (DeleteAction regression guard)', function (): void {
    $auditorium = Auditorium::factory()->create();

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('deleteAuditorium')
        ->once()
        ->withArgs(function (Auditorium $record, ?AdminUser $actor) use ($auditorium) {
            expect($record->id)->toBe($auditorium->id);
            expect($actor?->id)->toBe($this->admin->id);

            return true;
        });

    Livewire::test(ListAuditoriums::class)
        ->callTableAction('delete', $auditorium);

    expect(Auditorium::find($auditorium->id))->not->toBeNull();
});

test('section repeater is forwarded to saveAuditoriumWithSections on edit', function (): void {
    $auditorium = Auditorium::factory()->create();
    $existing = AuditoriumSection::factory()
        ->for($auditorium)
        ->standard()
        ->create();

    $capturedSections = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('saveAuditoriumWithSections')
        ->once()
        ->andReturnUsing(function (Location $loc, ?Auditorium $record, array $attributes, ?array $sections, ?AdminUser $actor) use (&$capturedSections, &$capturedActor, $auditorium) {
            $capturedSections = $sections;
            $capturedActor = $actor;

            return $auditorium;
        });

    Livewire::test(EditAuditorium::class, ['record' => $auditorium->getRouteKey()])
        ->set('data.sections', [
            ['id' => $existing->id, 'name' => 'Standard', 'price_multiplier' => '1.00', 'display_order' => 10],
            ['id' => null, 'name' => 'Premium', 'price_multiplier' => '1.25', 'display_order' => 20],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedSections)->toHaveCount(2);
    expect($capturedSections[0]['name'])->toBe('Standard');
    expect($capturedSections[1]['name'])->toBe('Premium');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('Fix seat sections row action calls updateSeatBatch with actor', function (): void {
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'section_id' => $section->id,
    ]);

    $capturedAuditorium = null;
    $capturedPatches = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('updateSeatBatch')
        ->once()
        ->andReturnUsing(function (Auditorium $a, array $patches, ?AdminUser $actor) use (&$capturedAuditorium, &$capturedPatches, &$capturedActor) {
            $capturedAuditorium = $a;
            $capturedPatches = $patches;
            $capturedActor = $actor;
        });

    // Mount & submit with the form's pre-filled state (from fillForm). Verifies
    // the action's wiring to the service with the admin actor; the richer
    // toggle-unavailable round-trip is exercised in Layer B via direct service
    // calls (Repeater item keys are UUIDs that Livewire's callTableAction's
    // data: override does not reliably target).
    Livewire::test(ListAuditoriums::class)
        ->callTableAction('fix_seat_sections', $auditorium);

    expect($capturedAuditorium?->id)->toBe($auditorium->id);
    expect($capturedPatches)->toHaveCount(1);
    expect($capturedPatches[0]['seat_id'])->toBe($seat->id);
    expect($capturedPatches[0]['section_id'])->toBe($section->id);
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('shared getFormSchema is the single source of truth for both surfaces (drift guard)', function (): void {
    // Structural assertion — getFormSchema() returns a non-empty schema with
    // Identity and Sections sections at the top level. Locks in the shape
    // that both standalone and relation-manager consumers rely on.
    $shared = AuditoriumResource::getFormSchema();
    expect($shared)->not->toBeEmpty();

    $topLevelHeadings = collect($shared)
        ->filter(fn ($c) => $c instanceof Section)
        ->map(fn ($c) => $c->getHeading())
        ->all();

    expect($topLevelHeadings)->toContain('Identity');
    expect($topLevelHeadings)->toContain('Sections');

    // Source-level guarantee that both consumers call getFormSchema(). Cheapest
    // guard against a future edit inlining a divergent schema in either file.
    $standaloneSource = file_get_contents((new ReflectionClass(AuditoriumResource::class))->getFileName());
    $relationManagerSource = file_get_contents((new ReflectionClass(AuditoriumsRelationManager::class))->getFileName());

    expect($standaloneSource)->toContain('self::getFormSchema()');
    expect($relationManagerSource)->toContain('AuditoriumResource::getFormSchema()');
});
