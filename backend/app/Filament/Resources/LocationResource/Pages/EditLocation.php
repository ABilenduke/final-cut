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

    /**
     * Explode the hours JSON column into per-day virtual form fields so the
     * Hours of Operation section hydrates correctly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return LocationResource::explodeHoursForForm($data);
    }

    /**
     * Re-assemble per-day virtual form fields back into the hours JSON shape
     * before the data reaches handleRecordUpdate.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LocationResource::implodeHoursFromForm($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            LocationResource::serviceDeleteAction(),
        ];
    }
}
