<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Models\Location;
use App\Services\AuditoriumService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Location);

        return app(AuditoriumService::class)
            ->updateLocation($record, $data, auth('admin')->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            LocationResource::serviceDeleteAction(),
        ];
    }
}
