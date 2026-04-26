<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use App\Services\PromoCodeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PromoCodeService::class)
            ->create($data, auth('admin')->user());
    }
}
