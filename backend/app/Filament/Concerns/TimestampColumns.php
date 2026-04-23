<?php

namespace App\Filament\Concerns;

use Filament\Tables\Columns\TextColumn;

trait TimestampColumns
{
    public static function standardTimestamps(): array
    {
        return [
            TextColumn::make('created_at')
                ->dateTime('M j, Y g:i A')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime('M j, Y g:i A')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
