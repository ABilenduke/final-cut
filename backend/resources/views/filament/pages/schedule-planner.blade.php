<x-filament-panels::page>
    @php
        $locations = $this->getLocations();
        $activeLocation = $this->getActiveLocation();
        $auditoriums = $this->getAuditoriumsForWeek();
        $weekDays = $this->getWeekDays();
        $canCreate = $this->canCreate();
    @endphp

    {{-- Controls --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <x-filament::button wire:click="previousWeek" icon="heroicon-m-chevron-left" color="gray" size="sm">
                Previous
            </x-filament::button>
            <x-filament::button wire:click="thisWeek" color="gray" size="sm">
                Today
            </x-filament::button>
            <x-filament::button wire:click="nextWeek" icon="heroicon-m-chevron-right" icon-position="after" color="gray" size="sm">
                Next
            </x-filament::button>
            <span class="text-sm font-medium px-3">
                Week of {{ \Carbon\Carbon::parse($this->weekStart)->format('F j, Y') }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">Location:</span>
            @foreach ($locations as $loc)
                <x-filament::button
                    wire:click="setLocation('{{ $loc->slug }}')"
                    size="sm"
                    :color="$activeLocation && $activeLocation->id === $loc->id ? 'primary' : 'gray'">
                    {{ $loc->name }}
                </x-filament::button>
            @endforeach
        </div>
    </div>

    {{-- Grid --}}
    @if ($activeLocation === null)
        <div class="text-center py-12 text-gray-500">
            No locations configured yet.
        </div>
    @elseif ($auditoriums->isEmpty())
        <div class="text-center py-12 text-gray-500">
            {{ $activeLocation->name }} has no auditoriums yet.
            <a href="{{ \App\Filament\Resources\AuditoriumResource::getUrl('create') }}" class="text-primary-600 underline">
                Create one
            </a>.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="fi-ta-header-row">
                        <th class="fi-ta-header-cell px-3 py-2 text-left sticky left-0 bg-white dark:bg-gray-900 z-10 min-w-[8rem]">
                            Day
                        </th>
                        @foreach ($auditoriums as $auditorium)
                            <th class="fi-ta-header-cell px-3 py-2 text-left min-w-[14rem]">
                                {{ $auditorium->name }}
                                @if ($auditorium->cleanup_minutes)
                                    <span class="block text-xs font-normal text-gray-500">
                                        {{ $auditorium->cleanup_minutes }} min cleanup
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekDays as $day)
                        <tr class="fi-ta-row border-t border-gray-100 dark:border-white/5">
                            <th class="fi-ta-cell px-3 py-2 text-left align-top sticky left-0 bg-white dark:bg-gray-900 z-10 font-medium">
                                <span class="block">{{ $day['label'] }}</span>
                                <span class="block text-xs font-normal text-gray-500">{{ $day['dayOfWeek'] }}</span>
                            </th>
                            @foreach ($auditoriums as $auditorium)
                                @php
                                    $dayShowtimes = $this->showtimesForDay(collect($auditorium->showtimes), $day['iso']);
                                @endphp
                                <td class="fi-ta-cell px-2 py-2 align-top border-l border-gray-100 dark:border-white/5">
                                    <div class="flex flex-col gap-1">
                                        @foreach ($dayShowtimes as $showtime)
                                            <a href="{{ $this->viewShowtimeUrl($showtime) }}"
                                               class="block px-2 py-1 rounded bg-primary-50 dark:bg-primary-500/20 text-primary-900 dark:text-primary-200 hover:bg-primary-100 dark:hover:bg-primary-500/30 text-xs">
                                                <span class="font-semibold">{{ $showtime->start_time->format('g:i A') }}</span>
                                                <span class="block truncate">{{ $showtime->movie?->title ?? '(no movie)' }}</span>
                                            </a>
                                        @endforeach

                                        @if ($canCreate)
                                            <a href="{{ $this->createShowtimeUrl($auditorium->id, $day['iso']) }}"
                                               class="block text-center px-2 py-1 rounded border border-dashed border-gray-300 dark:border-white/10 text-xs text-gray-500 hover:text-primary-600 hover:border-primary-500">
                                                + Add
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
