<?php

namespace App\Filament\Resources\GiftCardResource\RelationManagers;

use App\Filament\Concerns\FormatsCurrency;
use App\Models\GiftCardLedgerEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only ledger of gift card balance events.
 *
 * The relation `GiftCard::ledgerEntries()` orders entries reverse-chronological
 * by default; tests assert on that ordering.
 */
class BalanceHistoryRelationManager extends RelationManager
{
    use FormatsCurrency;

    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Balance History';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['booking', 'adminUser']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->getStateUsing(fn (GiftCardLedgerEntry $r): string => $r->type->value)
                    ->color(fn ($state) => match ($state) {
                        'purchase' => 'success',
                        'redemption' => 'gray',
                        'void' => 'danger',
                        'adjustment' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(function (int $state) {
                        $sign = $state < 0 ? '−' : '+';

                        return $sign.self::centsToDisplay(abs($state));
                    }),
                TextColumn::make('balance_after_cents')
                    ->label('Balance after')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state)),
                TextColumn::make('booking_id')
                    ->label('Booking')
                    ->placeholder('—')
                    ->limit(8),
                TextColumn::make('adminUser.email')
                    ->label('By')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
