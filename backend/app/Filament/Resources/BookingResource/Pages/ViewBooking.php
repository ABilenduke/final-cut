<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Add seats + foodItems to the list-page eager loads. Those two relations
     * only render in the Placeholder sections on this page, so we don't pay
     * for them on the list query.
     */
    protected function resolveRecord(int|string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['seats', 'foodItems'])
            ->whereKey($key)
            ->firstOrFail();
    }
}
