<x-filament-panels::page>
    <div
        x-data="{
            selection: new Set(),
            dragging: false,
            dragStart: null,
            dragEnd: null,
            // Hoisted ref so add/removeEventListener see the same Function
            // instance — bind() returns a fresh function each call.
            _beforeUnloadRef: null,
            beforeUnload(e) {
                // `$wire.dirty` is a Livewire PROPERTY access (synchronous).
                // Calling `$wire.hasUnsavedChanges()` would return a Promise —
                // always truthy — causing the confirm dialog to fire on every
                // navigation.
                const dirty = this.$wire.dirty || {};
                if (Object.keys(dirty).length > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            },
            init() {
                this._beforeUnloadRef = this.beforeUnload.bind(this);
                window.addEventListener('beforeunload', this._beforeUnloadRef);
            },
            destroy() {
                if (this._beforeUnloadRef) {
                    window.removeEventListener('beforeunload', this._beforeUnloadRef);
                    this._beforeUnloadRef = null;
                }
            },
            onMouseDown(event, seatId) {
                if (event.shiftKey) {
                    this.$wire.toggleUnavailable(seatId);
                    return;
                }
                this.dragging = true;
                this.dragStart = seatId;
                this.dragEnd = seatId;
                this.selection.clear();
                this.selection.add(seatId);
            },
            onMouseEnter(seatId) {
                if (!this.dragging) return;
                this.dragEnd = seatId;
                this.selection.add(seatId);
            },
            onMouseUp(seatId) {
                if (!this.dragging) return;
                this.dragging = false;
                if (this.selection.size === 1) {
                    this.$wire.cycleSection(seatId);
                } else {
                    this.$wire.bulkApplyActiveSection(Array.from(this.selection));
                }
                this.selection.clear();
            }
        }"
        class="fi-in gap-y-4"
    >
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 pb-3 dark:border-gray-700">
            <label class="text-sm font-medium">Drag-select applies:</label>
            <select
                wire:model.live="activeSectionId"
                class="fi-input rounded-md border-gray-300 text-sm"
            >
                @foreach ($sections as $section)
                    <option value="{{ $section['id'] }}">{{ $section['name'] }}</option>
                @endforeach
            </select>

            <div class="ms-auto flex items-center gap-2">
                <x-filament::button
                    wire:click="save"
                    color="primary"
                    :disabled="! $this->hasUnsavedChanges()"
                >
                    Save changes
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    color="gray"
                    href="{{ \App\Filament\Resources\AuditoriumResource::getUrl('view', ['record' => $this->getRecord()]) }}"
                >
                    Done
                </x-filament::button>
            </div>
        </div>

        <div class="text-xs text-gray-600 dark:text-gray-400">
            Click a seat to cycle its section. Shift-click to toggle unavailable. Drag across seats to apply the active section in bulk.
            @if ($this->hasUnsavedChanges())
                <span class="ms-2 inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-200">Unsaved changes</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <div class="mx-auto my-4 h-1 w-3/5 rounded bg-primary-600 dark:bg-primary-500"></div>
            <div class="mx-auto mb-1 text-center text-xs uppercase tracking-wide text-gray-500">Screen</div>

            @include('filament.resources.auditorium-resource.pages.partials.seat-grid', [
                'rows' => $rows,
                'seatsPerRow' => $seatsPerRow,
                'seats' => $seats,
                'sections' => $sections,
            ])
        </div>

        {{-- Section legend --}}
        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
            @foreach ($sections as $section)
                <div class="flex items-center gap-1">
                    <span class="inline-block h-3 w-3 rounded-sm" style="background-color: {{ \App\Support\SeatSectionPalette::colorFor($section['id']) }}"></span>
                    {{ $section['name'] }}
                </div>
            @endforeach
            <div class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-gray-400 opacity-50"></span>
                Unavailable
            </div>
        </div>
    </div>
</x-filament-panels::page>
