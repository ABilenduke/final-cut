<x-filament-panels::page>
    <form wire:submit="search" class="space-y-4">
        <x-filament::input.wrapper
            :valid="! $errors->has('query')"
            prefix-icon="heroicon-m-magnifying-glass"
        >
            <x-filament::input
                type="text"
                wire:model="query"
                placeholder="CVF-A3X9K2 or customer@example.com"
                autofocus
                autocomplete="off"
            />
        </x-filament::input.wrapper>

        @error('query')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
            Look up
        </x-filament::button>
    </form>
</x-filament-panels::page>
