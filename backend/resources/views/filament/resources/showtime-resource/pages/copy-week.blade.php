<x-filament-panels::page>
    <form wire:submit.prevent="submit" class="fi-form grid gap-y-6">
        {{ $this->form }}

        @if (! empty($this->preview))
            <div class="fi-section rounded-lg border border-gray-200 dark:border-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <h3 class="fi-section-header-heading text-base font-semibold">Preview</h3>
                </div>
                {{ $this->renderPreviewSummary() }}
            </div>
        @endif

        <div class="fi-form-actions flex flex-wrap items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
