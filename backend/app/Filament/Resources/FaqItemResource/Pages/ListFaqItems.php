<?php

namespace App\Filament\Resources\FaqItemResource\Pages;

use App\Filament\Resources\FaqItemResource;
use Filament\Resources\Pages\ListRecords;

class ListFaqItems extends ListRecords
{
    protected static string $resource = FaqItemResource::class;
}
