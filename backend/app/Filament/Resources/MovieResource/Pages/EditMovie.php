<?php

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use App\Models\Movie;
use App\Services\MovieService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMovie extends EditRecord
{
    protected static string $resource = MovieResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Movie);

        return app(MovieService::class)
            ->update($record, $data, auth('admin')->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            MovieResource::serviceDeleteAction(),
        ];
    }
}
