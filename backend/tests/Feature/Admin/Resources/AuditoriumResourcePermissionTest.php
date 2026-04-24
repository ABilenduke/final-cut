<?php

use App\Filament\Resources\AuditoriumResource;
use App\Filament\Resources\AuditoriumResource\Pages\CreateAuditorium;
use App\Filament\Resources\AuditoriumResource\Pages\ListAuditoriums;
use App\Models\Auditorium;
use Livewire\Livewire;

test('ops can view the auditorium list but cannot create, edit, delete, or see destructive row actions', function (): void {
    $this->actingAsOps();
    $auditorium = Auditorium::factory()->create();

    expect(AuditoriumResource::canViewAny())->toBeTrue();
    expect(AuditoriumResource::canCreate())->toBeFalse();
    expect(AuditoriumResource::canEdit($auditorium))->toBeFalse();
    expect(AuditoriumResource::canDelete($auditorium))->toBeFalse();

    $this->get(CreateAuditorium::getUrl())->assertForbidden();

    // Seat-config actions gated by `seats.update` — ops doesn't have it.
    Livewire::test(ListAuditoriums::class)
        ->assertTableActionHidden('configure_seats', $auditorium)
        ->assertTableActionHidden('fix_seat_sections', $auditorium)
        ->assertTableActionHidden('visual_seat_editor', $auditorium);
});

test('managers can create, edit, delete auditoriums and see seat-configuration actions', function (): void {
    $this->actingAsManager();
    $auditorium = Auditorium::factory()->create();

    expect(AuditoriumResource::canViewAny())->toBeTrue();
    expect(AuditoriumResource::canCreate())->toBeTrue();
    expect(AuditoriumResource::canEdit($auditorium))->toBeTrue();
    expect(AuditoriumResource::canDelete($auditorium))->toBeTrue();

    Livewire::test(ListAuditoriums::class)
        ->assertTableActionVisible('configure_seats', $auditorium);
});

test('admins have full CRUD on auditoriums', function (): void {
    $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();

    expect(AuditoriumResource::canViewAny())->toBeTrue();
    expect(AuditoriumResource::canCreate())->toBeTrue();
    expect(AuditoriumResource::canEdit($auditorium))->toBeTrue();
    expect(AuditoriumResource::canDelete($auditorium))->toBeTrue();
});

test('an admin user with no role cannot view the auditorium list', function (): void {
    $this->actingAsNobody();

    expect(AuditoriumResource::canViewAny())->toBeFalse();

    $this->get(ListAuditoriums::getUrl())->assertForbidden();
});
