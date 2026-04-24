<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Filament\Resources\AuditoriumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuditoriums extends ListRecords
{
    protected static string $resource = AuditoriumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
