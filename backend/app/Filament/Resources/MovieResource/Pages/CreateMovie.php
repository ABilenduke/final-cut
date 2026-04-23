<?php

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use App\Services\MovieService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMovie extends CreateRecord
{
    protected static string $resource = MovieResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(MovieService::class)
            ->create($data, auth('admin')->user());
    }
}
