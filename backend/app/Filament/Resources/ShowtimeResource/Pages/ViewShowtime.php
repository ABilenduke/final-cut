<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Filament\Resources\ShowtimeResource;
use App\Models\Showtime;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShowtime extends ViewRecord
{
    protected static string $resource = ShowtimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Showtime $record) => $record->cancelled_at === null && $record->start_time->isFuture()),
            ShowtimeResource::cancelAction(),
        ];
    }
}
