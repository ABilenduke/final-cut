<x-filament-panels::page>
    @php
        $byRow = [];
        foreach ($this->seatStates as $seatId => $seat) {
            $byRow[$seat['row']][$seat['number']] = array_merge($seat, ['id' => $seatId]);
        }
        ksort($byRow);

        $cellStyles = [
            'sold' => 'background-color: #550000; color: #FFB4A8;',
            'held' => 'background-color: #5A8AA0; color: rgba(255,255,255,0.9);',
            'refund_pending' => 'background-color: #DAC769; color: #131313;',
            'unavailable' => 'background-color: rgba(156, 163, 175, 0.5); color: rgba(255,255,255,0.85);',
            'available' => 'background-color: rgba(156, 163, 175, 0.15); color: rgba(107, 114, 128, 0.9);',
        ];
        $selectedStyle = 'background-color: #550000; color: #DAC769; box-shadow: 0 0 0 0.125rem #DAC769;';
    @endphp

    <form wire:submit.prevent="create" class="fi-form grid gap-y-6">
        {{ $this->form }}

        @if (! empty($this->seatStates))
            <div class="fi-section rounded-lg border border-gray-200 p-6 dark:border-white/10">
                <h3 class="mb-4 text-base font-semibold">Pick seats</h3>

                <div class="overflow-x-auto">
                    <div class="inline-flex select-none flex-col gap-1">
                        @foreach ($byRow as $rowLetter => $rowSeats)
                            <div class="flex items-center gap-1">
                                <span class="w-5 text-end text-xs font-medium text-gray-600">{{ $rowLetter }}</span>
                                @for ($n = 1; $n <= $this->seatsPerRow; $n++)
                                    @php($seat = $rowSeats[$n] ?? null)
                                    @if ($seat === null)
                                        <span class="h-8 w-8"></span>
                                    @else
                                        @php($isSelected = in_array($seat['id'], $this->selectedSeatIds, true))
                                        @php($isAvailable = $seat['state'] === 'available')
                                        <button
                                            type="button"
                                            wire:click="toggleSeat('{{ $seat['id'] }}')"
                                            @disabled(! $isAvailable)
                                            class="flex h-8 w-8 items-center justify-center rounded-sm font-mono text-[0.625rem] {{ $isAvailable ? 'cursor-pointer transition-transform hover:scale-110' : 'cursor-not-allowed' }}"
                                            style="{{ $isSelected ? $selectedStyle : $cellStyles[$seat['state']] }}"
                                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                            aria-label="Seat {{ $seat['label'] }}{{ $isAvailable ? '' : ', unavailable' }}"
                                            title="Seat {{ $seat['label'] }}"
                                        >
                                            {{ $seat['number'] }}
                                        </button>
                                    @endif
                                @endfor
                                <span class="w-5 text-xs text-gray-600">{{ $rowLetter }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ count($this->selectedSeatIds) }} seat(s) selected ·
                    total <span class="font-semibold">{{ \App\Filament\Resources\BookingResource::centsToDisplay($this->totalCents()) }}</span>
                </p>
            </div>
        @endif

        <div class="fi-form-actions flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" :disabled="empty($this->selectedSeatIds)">
                Create booking
            </x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ \App\Filament\Resources\BookingResource::getUrl('index') }}"
            >
                Cancel
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
