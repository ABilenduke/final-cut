<?php

use App\Filament\Resources\AuditoriumResource\Pages\ListAuditoriums;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use Livewire\Livewire;

test('admin can close a section from the manage-closures action', function (): void {
    $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->create(['auditorium_id' => $auditorium->id, 'closed_at' => null]);

    Livewire::test(ListAuditoriums::class)
        ->mountTableAction('manage_section_closures', $auditorium)
        ->set('mountedActions.0.data.sections', [
            'item-0' => ['id' => $section->id, 'name' => $section->name, 'closed' => true],
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($section->refresh()->closed_at)->not->toBeNull();
});

test('admin can reopen a closed section', function (): void {
    $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->create(['auditorium_id' => $auditorium->id, 'closed_at' => now()]);

    Livewire::test(ListAuditoriums::class)
        ->mountTableAction('manage_section_closures', $auditorium)
        ->set('mountedActions.0.data.sections', [
            'item-0' => ['id' => $section->id, 'name' => $section->name, 'closed' => false],
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($section->refresh()->closed_at)->toBeNull();
});

test('the manage-closures action is hidden for ops and for auditoriums with no sections', function (): void {
    $auditoriumWithSections = Auditorium::factory()->create();
    AuditoriumSection::factory()->create(['auditorium_id' => $auditoriumWithSections->id]);
    $bareAuditorium = Auditorium::factory()->create();

    $this->actingAsOps();
    Livewire::test(ListAuditoriums::class)
        ->assertTableActionHidden('manage_section_closures', $auditoriumWithSections);

    $this->actingAsAdmin();
    Livewire::test(ListAuditoriums::class)
        ->assertTableActionVisible('manage_section_closures', $auditoriumWithSections)
        ->assertTableActionHidden('manage_section_closures', $bareAuditorium);
});
