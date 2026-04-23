<x-filament-panels::page>
    <form wire:submit.prevent="submit" class="fi-form grid gap-y-6">
        {{ $this->form }}

        <div class="fi-form-actions flex flex-wrap items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
