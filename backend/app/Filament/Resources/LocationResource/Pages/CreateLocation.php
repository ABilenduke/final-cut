<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Services\AuditoriumService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(AuditoriumService::class)
            ->createLocation($data, auth('admin')->user());
    }

    /**
     * Explode the hours JSON into per-day virtual fields so the Hours of
     * Operation section can pre-fill correctly when the form is opened.
     * On a new record there is no stored hours value, so all days default
     * to "closed" (which renders as the toggle being on).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return LocationResource::explodeHoursForForm($data);
    }

    /**
     * Re-assemble the per-day virtual fields back into the hours JSON shape
     * before the data reaches handleRecordCreation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LocationResource::implodeHoursFromForm($data);
    }
}
