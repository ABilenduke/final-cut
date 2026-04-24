<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Filament\Resources\AuditoriumResource;
use App\Models\Auditorium;
use App\Services\AuditoriumService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAuditorium extends EditRecord
{
    protected static string $resource = AuditoriumResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Auditorium);

        $sections = $data['sections'] ?? null;
        unset($data['sections'], $data['location_id']);

        return app(AuditoriumService::class)
            ->saveAuditoriumWithSections($record->location, $record, $data, $sections, auth('admin')->user());
    }

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
            ViewAction::make(),
            AuditoriumResource::serviceDeleteAction(),
        ];
    }
}
