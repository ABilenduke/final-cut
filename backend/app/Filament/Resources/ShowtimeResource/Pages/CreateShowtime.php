<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Filament\Resources\ShowtimeResource;
use App\Services\ShowtimeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShowtime extends CreateRecord
{
    protected static string $resource = ShowtimeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Pre-submit conflict check surfaces a friendly validation error rather
        // than relying on the raw Postgres exclusion violation.
        ShowtimeResource::validateAgainstConflicts($data);

        return app(ShowtimeService::class)
            ->create($data, auth('admin')->user());
    }
}
