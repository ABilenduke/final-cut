<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PromoCode $record */
        return app(PromoCodeService::class)
            ->update($record, $data, auth('admin')->user());
    }
}
