<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\LoyaltyTier;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\LoyaltyService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Route every loyalty change through LoyaltyService so we get:
     *  - lockForUpdate on the user row,
     *  - a `loyalty_adjustments` audit row,
     *  - an `activity_log` row with the admin as causer.
     *
     * Non-loyalty fields are NOT rendered in the form (see UserResource::form),
     * so `$data` only contains loyalty_points, loyalty_tier, and (conditionally)
     * premier_expiry.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof User);

        $service = app(LoyaltyService::class);
        $actor = auth('admin')->user();

        $currentPoints = (int) $record->loyalty_points;
        $newPoints = (int) ($data['loyalty_points'] ?? $currentPoints);
        if ($newPoints !== $currentPoints) {
            $service->adjustPoints(
                $record,
                $newPoints - $currentPoints,
                'Edited via profile form',
                $actor,
            );
        }

        $currentTier = $record->loyalty_tier->value;
        $newTier = $data['loyalty_tier'] ?? $currentTier;
        if ($newTier !== $currentTier) {
            if ($newTier === LoyaltyTier::Premier->value) {
                $expiry = $data['premier_expiry'] ?? null;
                $service->grantPremier(
                    $record,
                    $expiry ? Carbon::parse($expiry) : Carbon::now()->addYear(),
                    'Edited via profile form',
                    $actor,
                );
            } else {
                $service->revokePremier(
                    $record,
                    'Edited via profile form',
                    $actor,
                );
            }
        } elseif ($newTier === LoyaltyTier::Premier->value
            && isset($data['premier_expiry'])
            && Carbon::parse($data['premier_expiry'])->toDateString() !== $record->premier_expiry?->toDateString()
        ) {
            // Tier unchanged but expiry moved — re-grant to roll the expiry
            // under the same lockForUpdate + audit contract.
            $service->grantPremier(
                $record,
                Carbon::parse($data['premier_expiry']),
                'Edited via profile form',
                $actor,
            );
        }

        return $record->fresh();
    }
}
