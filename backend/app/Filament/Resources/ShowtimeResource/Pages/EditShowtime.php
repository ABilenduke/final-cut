<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Filament\Resources\ShowtimeResource;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditShowtime extends EditRecord
{
    protected static string $resource = ShowtimeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Showtime);

        ShowtimeResource::validateAgainstConflicts($data, $record->id);

        return app(ShowtimeService::class)
            ->update($record, $data, auth('admin')->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ShowtimeResource::cancelAction(),
        ];
    }
}
