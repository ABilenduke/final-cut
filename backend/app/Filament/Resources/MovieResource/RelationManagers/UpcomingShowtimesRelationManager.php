<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use App\Filament\Concerns\FormatsCurrency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UpcomingShowtimesRelationManager extends RelationManager
{
    use FormatsCurrency;

    protected static string $relationship = 'showtimes';

    protected static ?string $title = 'Upcoming Showtimes (next 20)';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['auditorium.location'])
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->limit(20))
            ->paginated(false)
            ->columns([
                TextColumn::make('start_time')->dateTime()->sortable(),
                TextColumn::make('auditorium.location.name')->label('Location'),
                TextColumn::make('auditorium.name')->label('Auditorium'),
                TextColumn::make('price_standard')
                    ->label('Standard')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
            ])
            ->headerActions([])
            ->recordActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
