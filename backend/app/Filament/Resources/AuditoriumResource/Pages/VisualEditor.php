<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Filament\Resources\AuditoriumResource;
use App\Models\Auditorium;
use App\Services\AuditoriumService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Livewire-driven visual seat editor.
 *
 * Renders the auditorium's full seat grid. Click cycles a seat through the
 * sections; shift-click toggles `unavailable_at`; drag-select (on the
 * client, Alpine) applies the toolbar's active section to the selection.
 * Save dispatches the accumulated patch set to
 * `AuditoriumService::updateSeatBatch($auditorium, $patches, $actor)`.
 *
 * This page is a better UX on top of the already-present service method;
 * it does not add any new service contract. If disabled (feature-flag or
 * budget), the "Fix seat sections" row action on `AuditoriumResource`
 * covers the same capability via a Repeater modal.
 */
class VisualEditor extends Page
{
    protected static string $resource = AuditoriumResource::class;

    protected string $view = 'filament.resources.auditorium-resource.pages.visual-editor';

    /** Public route-binding key. Stays a string so Livewire hydration never
     *  conflicts with a model-typed property. Access the resolved Auditorium
     *  via {@see getRecord()}. */
    public string $record;

    protected ?Auditorium $resolvedRecord = null;

    /**
     * Authoritative seat state, keyed by seat id.
     *
     * Shape:
     *   [
     *     '<seatId>' => [
     *       'id'         => string,
     *       'label'      => string,
     *       'row'        => string,  // 'A'
     *       'number'     => int,     // 1
     *       'section_id' => ?string,
     *       'unavailable' => bool,
     *     ],
     *     ...
     *   ]
     *
     * @var array<string, array<string, mixed>>
     */
    public array $seats = [];

    /**
     * Accumulated per-seat diffs since last save. Keyed by seat id; each
     * value is a subset of {'section_id' => ..., 'unavailable' => bool}
     * representing the CURRENT desired state. Multiple edits to the same
     * seat collapse into a single entry.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $dirty = [];

    /** @var array<string, array{id: string, name: string}> */
    public array $sections = [];

    public int $rows = 0;

    public int $seatsPerRow = 0;

    /** Toolbar selection — section applied by drag-select. */
    public ?string $activeSectionId = null;

    public function mount(int|string $record): void
    {
        abort_unless(auth('admin')->user()?->can('seats.update') ?? false, 403);

        $this->record = (string) $record;
        $this->getRecord(); // 404 on bad id early.
        $this->loadState();
    }

    public function getRecord(): Auditorium
    {
        return $this->resolvedRecord ??= Auditorium::findOrFail($this->record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Visual seat editor — '.$this->getRecord()->name;
    }

    protected function loadState(): void
    {
        $rawSeats = $this->getRecord()->seats()
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        $this->seats = $rawSeats
            ->mapWithKeys(fn ($s) => [$s->id => [
                'id' => $s->id,
                'label' => $s->label,
                'row' => $s->row,
                'number' => $s->number,
                'section_id' => $s->section_id,
                'unavailable' => $s->unavailable_at !== null,
            ]])
            ->all();

        $this->rows = $rawSeats->pluck('row')->unique()->count();
        $this->seatsPerRow = (int) ($rawSeats->max('number') ?? 0);

        $this->sections = $this->getRecord()->sections
            ->sortBy('display_order')
            ->values()
            ->mapWithKeys(fn ($s) => [$s->id => ['id' => $s->id, 'name' => $s->name]])
            ->all();

        $this->activeSectionId = array_key_first($this->sections) ?: null;
        $this->dirty = [];
    }

    /**
     * Called by a click on a seat cell — cycles the seat's section through
     * the ordered list of sections for the auditorium.
     */
    public function cycleSection(string $seatId): void
    {
        if (! isset($this->seats[$seatId])) {
            return;
        }

        $sectionIds = array_keys($this->sections);
        if ($sectionIds === []) {
            return;
        }

        $current = $this->seats[$seatId]['section_id'];
        $idx = array_search($current, $sectionIds, true);
        $next = ($idx === false) ? $sectionIds[0] : $sectionIds[($idx + 1) % count($sectionIds)];

        $this->applySectionToSeat($seatId, $next);
    }

    /** Toggle a seat between available and unavailable. */
    public function toggleUnavailable(string $seatId): void
    {
        if (! isset($this->seats[$seatId])) {
            return;
        }

        $this->seats[$seatId]['unavailable'] = ! $this->seats[$seatId]['unavailable'];
        $this->markDirty($seatId, 'unavailable', $this->seats[$seatId]['unavailable']);
    }

    /**
     * Apply the active section to a batch of seats (drag-select).
     *
     * @param  array<int, string>  $seatIds
     */
    public function bulkApplyActiveSection(array $seatIds): void
    {
        if ($this->activeSectionId === null) {
            return;
        }

        foreach ($seatIds as $seatId) {
            if (isset($this->seats[$seatId])) {
                $this->applySectionToSeat($seatId, $this->activeSectionId);
            }
        }
    }

    protected function applySectionToSeat(string $seatId, ?string $sectionId): void
    {
        $this->seats[$seatId]['section_id'] = $sectionId;
        $this->markDirty($seatId, 'section_id', $sectionId);
    }

    protected function markDirty(string $seatId, string $field, mixed $value): void
    {
        $this->dirty[$seatId] = array_merge($this->dirty[$seatId] ?? [], [$field => $value]);
    }

    public function save(): void
    {
        if ($this->dirty === []) {
            Notification::make()
                ->title('No changes to save')
                ->info()
                ->send();

            return;
        }

        $patches = [];
        foreach ($this->dirty as $seatId => $changes) {
            $patch = ['seat_id' => $seatId];
            if (array_key_exists('section_id', $changes)) {
                $patch['section_id'] = $changes['section_id'];
            }
            if (array_key_exists('unavailable', $changes)) {
                $patch['unavailable_at'] = $changes['unavailable'] ? now() : null;
            }
            $patches[] = $patch;
        }

        try {
            app(AuditoriumService::class)
                ->updateSeatBatch($this->getRecord(), $patches, auth('admin')->user());
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Save failed')
                ->body('No changes were applied.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(count($patches).' seat'.(count($patches) === 1 ? '' : 's').' updated')
            ->success()
            ->send();

        $this->getRecord()->refresh();
        $this->resolvedRecord = null; // force reload on next access
        $this->loadState();
    }

    public function hasUnsavedChanges(): bool
    {
        return $this->dirty !== [];
    }
}
