<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Filament\Resources\AuditoriumResource;
use App\Models\Auditorium;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditorium extends ViewRecord
{
    protected static string $resource = AuditoriumResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        assert($this->record instanceof Auditorium);
        $data['sections'] = $this->record->sections()
            ->orderBy('display_order')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'price_multiplier' => (string) $s->price_multiplier,
                'display_order' => $s->display_order,
            ])
            ->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            AuditoriumResource::serviceDeleteAction(),
        ];
    }
}
