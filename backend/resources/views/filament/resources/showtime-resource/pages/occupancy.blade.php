<x-filament-panels::page>
    @php
        $showtime = $this->getRecord();

        $byRow = [];
        foreach ($this->seatStates as $seatId => $seat) {
            $byRow[$seat['row']][$seat['number']] = $seat;
        }
        ksort($byRow);

        // Sold = primary_container (the brand fill), held = steel (info),
        // refund-pending = gold (warning, dark text), unavailable matches the
        // visual seat editor's 50% gray. Available stays recessive.
        $cellStyles = [
            'sold' => 'background-color: #550000; color: #FFB4A8;',
            'held' => 'background-color: #5A8AA0; color: rgba(255,255,255,0.9);',
            'refund_pending' => 'background-color: #DAC769; color: #131313;',
            'unavailable' => 'background-color: rgba(156, 163, 175, 0.5); color: rgba(255,255,255,0.85);',
            'available' => 'background-color: rgba(156, 163, 175, 0.15); color: rgba(107, 114, 128, 0.9);',
        ];

        $legend = [
            'sold' => 'Sold',
            'held' => 'Held',
            'refund_pending' => 'Refund pending',
            'unavailable' => 'Unavailable',
            'available' => 'Available',
        ];

        $occupied = $this->counts['sold'] + $this->counts['held'] + $this->counts['refund_pending'];
        $pct = $this->counts['capacity'] > 0 ? (int) round(($occupied / $this->counts['capacity']) * 100) : 0;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ $showtime->auditorium->location->name }} · {{ $showtime->auditorium->name }}
                · {{ $occupied }} / {{ $this->counts['capacity'] }} seats occupied ({{ $pct }}%)
                @if ($showtime->cancelled_at !== null)
                    · <span class="font-medium">Showtime cancelled — occupied seats are pending refunds.</span>
                @endif
            </div>

            <x-filament::button
                tag="a"
                href="{{ $this->bookingsUrl() }}"
                icon="heroicon-o-ticket"
                color="gray"
                size="sm"
            >
                View bookings for this showtime
            </x-filament::button>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            @foreach ($legend as $state => $label)
                <span class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span class="inline-block h-3 w-3 rounded-sm" style="{{ $cellStyles[$state] }}"></span>
                    {{ $label }} ({{ $this->counts[$state] }})
                </span>
            @endforeach
        </div>

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
                                @php($title = 'Seat '.$seat['label'].' — '.$legend[$seat['state']].($seat['confirmation_code'] ? ' ('.$seat['confirmation_code'].')' : ''))
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-sm font-mono text-[0.625rem]"
                                    style="{{ $cellStyles[$seat['state']] }}"
                                    role="img"
                                    aria-label="{{ $title }}"
                                    title="{{ $title }}"
                                >
                                    {{ $seat['number'] }}
                                </span>
                            @endif
                        @endfor
                        <span class="w-5 text-xs text-gray-600">{{ $rowLetter }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
