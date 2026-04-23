@php
    $byRow = [];
    foreach ($seats as $seat) {
        $byRow[$seat['row']][$seat['number']] = $seat;
    }
    ksort($byRow);

    $cellColor = fn (array $seat): string => $seat['unavailable']
        ? 'rgba(156, 163, 175, 0.5)' // gray-400 / 50% — reserved for unavailable seats
        : \App\Support\SeatSectionPalette::colorFor($seat['section_id']);
@endphp

<div
    class="fi-seat-grid inline-flex select-none flex-col gap-1"
    @mouseleave="if (dragging) { dragging = false; selection.clear(); }"
>
    @foreach ($byRow as $rowLetter => $rowSeats)
        <div class="flex items-center gap-1">
            <span class="w-5 text-end text-xs font-medium text-gray-600">{{ $rowLetter }}</span>
            @for ($n = 1; $n <= $seatsPerRow; $n++)
                @php($seat = $rowSeats[$n] ?? null)
                @if ($seat === null)
                    <span class="h-8 w-8"></span>
                @else
                    <button
                        type="button"
                        wire:key="seat-{{ $seat['id'] }}-{{ $seat['section_id'] ?? 'none' }}-{{ $seat['unavailable'] ? 'u' : 'a' }}"
                        class="fi-seat relative h-8 w-8 rounded-sm text-[10px] font-mono transition-transform hover:scale-110"
                        style="background-color: {{ $cellColor($seat) }}; color: rgba(255,255,255,0.85);"
                        x-on:mousedown.prevent="onMouseDown($event, '{{ $seat['id'] }}')"
                        x-on:mouseenter="onMouseEnter('{{ $seat['id'] }}')"
                        x-on:mouseup="onMouseUp('{{ $seat['id'] }}')"
                        :class="{ 'ring-2 ring-primary-500': selection.has('{{ $seat['id'] }}') }"
                        title="Seat {{ $seat['label'] }}{{ $seat['unavailable'] ? ' (unavailable)' : '' }}"
                    >
                        {{ $seat['number'] }}
                    </button>
                @endif
            @endfor
            <span class="w-5 text-xs text-gray-600">{{ $rowLetter }}</span>
        </div>
    @endforeach
</div>
