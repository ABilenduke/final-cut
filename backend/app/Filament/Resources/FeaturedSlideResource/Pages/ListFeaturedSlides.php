<?php

namespace App\Filament\Resources\FeaturedSlideResource\Pages;

use App\Filament\Resources\FeaturedSlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedSlides extends ListRecords
{
    protected static string $resource = FeaturedSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
