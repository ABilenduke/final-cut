<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Exceptions\ShowtimeHasBookingsException;
use App\Filament\Resources\ShowtimeResource;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditShowtime extends EditRecord
{
    protected static string $resource = ShowtimeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Showtime);

        ShowtimeResource::validateAgainstConflicts($data, $record->id);

        try {
            return app(ShowtimeService::class)
                ->update($record, $data, auth('admin')->user());
        } catch (ShowtimeHasBookingsException $e) {
            Notification::make()
                ->title('Showtime has bookings')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ShowtimeResource::cancelAction(),
        ];
    }
}
