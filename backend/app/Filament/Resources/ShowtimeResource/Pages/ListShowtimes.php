<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Filament\Resources\ShowtimeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShowtimes extends ListRecords
{
    protected static string $resource = ShowtimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('bulk_create')
                ->label('Bulk create')
                ->icon('heroicon-o-rectangle-stack')
                ->visible(fn () => auth('admin')->user()?->can('showtimes.create') ?? false)
                ->url(fn () => ShowtimeResource::getUrl('bulk_create')),
            Action::make('copy_week')
                ->label('Copy week')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn () => auth('admin')->user()?->can('showtimes.create') ?? false)
                ->url(fn () => ShowtimeResource::getUrl('copy_week')),
        ];
    }
}
