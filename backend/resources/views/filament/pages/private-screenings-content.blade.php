<x-filament-panels::page>
    <form wire:submit.prevent="save" class="fi-form grid gap-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
