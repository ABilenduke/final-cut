<?php

use App\Filament\Resources\AuditoriumResource\Pages\VisualEditor;
use App\Models\User;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Seat;
use App\Services\AuditoriumService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();

    $this->auditorium = Auditorium::factory()->create();
    $this->standard = AuditoriumSection::factory()->for($this->auditorium)->standard()->create();
    $this->premium = AuditoriumSection::factory()->for($this->auditorium)->premium()->create();

    $this->seat = Seat::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'section_id' => $this->standard->id,
    ]);
});

test('cycleSection advances a seat through the ordered section list and records it as dirty', function (): void {
    Livewire::test(VisualEditor::class, ['record' => $this->auditorium->getRouteKey()])
        ->call('cycleSection', $this->seat->id)
        ->assertSet("seats.{$this->seat->id}.section_id", $this->premium->id)
        ->assertSet("dirty.{$this->seat->id}.section_id", $this->premium->id);
});

test('toggleUnavailable flips the unavailable flag and records it as dirty', function (): void {
    Livewire::test(VisualEditor::class, ['record' => $this->auditorium->getRouteKey()])
        ->call('toggleUnavailable', $this->seat->id)
        ->assertSet("seats.{$this->seat->id}.unavailable", true)
        ->assertSet("dirty.{$this->seat->id}.unavailable", true);
});

test('bulkApplyActiveSection applies the toolbar section to every seat in the selection', function (): void {
    $seat2 = Seat::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'section_id' => $this->standard->id,
    ]);

    Livewire::test(VisualEditor::class, ['record' => $this->auditorium->getRouteKey()])
        ->set('activeSectionId', $this->premium->id)
        ->call('bulkApplyActiveSection', [$this->seat->id, $seat2->id])
        ->assertSet("seats.{$this->seat->id}.section_id", $this->premium->id)
        ->assertSet("seats.{$seat2->id}.section_id", $this->premium->id)
        ->assertSet("dirty.{$this->seat->id}.section_id", $this->premium->id)
        ->assertSet("dirty.{$seat2->id}.section_id", $this->premium->id);
});

test('save dispatches the accumulated dirty patch to updateSeatBatch with the admin actor', function (): void {
    $capturedAuditorium = null;
    $capturedPatches = null;
    $capturedActor = null;

    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('updateSeatBatch')
        ->once()
        ->andReturnUsing(function (Auditorium $a, array $patches, ?User $actor) use (&$capturedAuditorium, &$capturedPatches, &$capturedActor) {
            $capturedAuditorium = $a;
            $capturedPatches = $patches;
            $capturedActor = $actor;
        });

    Livewire::test(VisualEditor::class, ['record' => $this->auditorium->getRouteKey()])
        ->call('cycleSection', $this->seat->id)
        ->call('toggleUnavailable', $this->seat->id)
        ->call('save');

    expect($capturedAuditorium?->id)->toBe($this->auditorium->id);
    expect($capturedPatches)->toHaveCount(1);
    expect($capturedPatches[0]['seat_id'])->toBe($this->seat->id);
    expect($capturedPatches[0]['section_id'])->toBe($this->premium->id);
    expect($capturedPatches[0]['unavailable_at'])->not->toBeNull();
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('save with no dirty patch does not call updateSeatBatch', function (): void {
    $service = $this->mock(AuditoriumService::class);
    $service->shouldReceive('updateSeatBatch')->never();

    Livewire::test(VisualEditor::class, ['record' => $this->auditorium->getRouteKey()])
        ->call('save');
});

test('ops role cannot mount the visual editor page', function (): void {
    $this->actingAsOps();

    $this->get(VisualEditor::getUrl(['record' => $this->auditorium]))
        ->assertForbidden();
});
