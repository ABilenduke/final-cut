<?php

namespace App\Filament\Resources\FeaturedSlideResource\Pages;

use App\Filament\Resources\FeaturedSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeaturedSlide extends EditRecord
{
    protected static string $resource = FeaturedSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
