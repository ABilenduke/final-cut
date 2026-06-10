<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Filament\Resources\ShowtimeResource;
use App\Models\Showtime;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShowtime extends ViewRecord
{
    protected static string $resource = ShowtimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('occupancy_map')
                ->label('Occupancy map')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(fn (Showtime $record): string => ShowtimeResource::getUrl('occupancy', ['record' => $record])),
            EditAction::make()
                ->visible(fn (Showtime $record) => $record->cancelled_at === null && $record->start_time->isFuture()),
            ShowtimeResource::cancelAction(),
        ];
    }
}
