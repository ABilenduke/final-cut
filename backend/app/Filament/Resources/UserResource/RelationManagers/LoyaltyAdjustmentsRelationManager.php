<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\LoyaltyAdjustment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyAdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyAdjustments';

    protected static ?string $title = 'Loyalty Adjustments';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('adminUser')->latest())
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextColumn::make('adminUser.email')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('change_type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (LoyaltyAdjustment $r): string => $r->change_type->value),
                TextColumn::make('points_delta')
                    ->label('Delta')
                    ->numeric(),
                TextColumn::make('reason')
                    ->wrap()
                    ->limit(80),
            ])
            ->headerActions([])
            ->recordActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
