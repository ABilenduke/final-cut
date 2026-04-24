<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsRelationManager extends RelationManager
{
    use FormatsCurrency;

    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('showtime.movie')->latest())
            ->columns([
                TextColumn::make('confirmation_code')
                    ->copyable()
                    ->url(fn (Booking $record) => BookingResource::getUrl('view', ['record' => $record])),
                TextColumn::make('showtime.movie.title')
                    ->label('Movie'),
                TextColumn::make('showtime.start_time')
                    ->label('Showtime')
                    ->dateTime(),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Booking $r): string => $r->status->value),
            ])
            ->headerActions([])
            ->recordActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
