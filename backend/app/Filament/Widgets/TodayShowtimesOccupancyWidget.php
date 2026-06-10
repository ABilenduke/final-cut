<?php

namespace App\Filament\Widgets;

use App\Models\BookingSeat;
use App\Models\Showtime;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Today's showtimes with live occupancy (admin-v2 Plan 07). Occupancy reads
 * the occupancy guard's own flag (`booking_seats.occupies_seat`) via a
 * correlated subquery, so the numbers always agree with checkout/refund
 * logic. Day boundaries follow TodayKpisWidget::venueDayBounds().
 */
class TodayShowtimesOccupancyWidget extends TableWidget
{
    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('showtimes.view') ?? false;
    }

    public function table(Table $table): Table
    {
        [$dayStart, $dayEnd] = TodayKpisWidget::venueDayBounds();

        return $table
            ->heading('Today\'s showtimes')
            ->query(
                Showtime::query()
                    ->whereNull('cancelled_at')
                    ->whereBetween('start_time', [$dayStart, $dayEnd])
                    ->with('movie', 'auditorium.location')
                    ->addSelect([
                        'occupied_seats_count' => BookingSeat::query()
                            ->selectRaw('count(*)')
                            ->whereColumn('booking_seats.showtime_id', 'showtimes.id')
                            ->where('occupies_seat', true),
                    ])
                    ->orderBy('start_time'),
            )
            ->columns([
                TextColumn::make('start_time')
                    ->label('Start')
                    ->time('G:i'),
                TextColumn::make('movie.title')
                    ->label('Movie'),
                TextColumn::make('auditorium.location.name')
                    ->label('Location'),
                TextColumn::make('auditorium.name')
                    ->label('Auditorium'),
                TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->getStateUsing(fn (Showtime $record): string => sprintf(
                        '%d / %d',
                        $record->occupied_seats_count ?? 0,
                        $record->auditorium->total_seats,
                    )),
            ])
            ->paginated(false);
    }
}
