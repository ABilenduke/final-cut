<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Filament\Resources\AuditoriumResource;
use App\Models\Location;
use App\Services\AuditoriumService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAuditorium extends CreateRecord
{
    protected static string $resource = AuditoriumResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $location = Location::findOrFail($data['location_id']);
        $sections = $data['sections'] ?? null;
        unset($data['location_id'], $data['sections']);

        return app(AuditoriumService::class)
            ->saveAuditoriumWithSections($location, null, $data, $sections, auth('admin')->user());
    }
}
