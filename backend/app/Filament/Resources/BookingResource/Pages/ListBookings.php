<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('walk_up')
                ->label('Walk-up sale')
                ->icon('heroicon-o-banknotes')
                ->visible(fn () => auth('admin')->user()?->can('bookings.create_walkup') ?? false)
                ->url(fn () => BookingResource::getUrl('walkup')),
        ];
    }
}
