<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\LoyaltyTier;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\LoyaltyService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Permission split is enforced server-side here as defence-in-depth on top
     * of the field-level `disabled()`/`dehydrated()` guards in `UserResource::form()`:
     * a payload that tries to sneak a tier change past a points-only admin
     * (or vice versa) is rejected before it reaches the service.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof User);

        $service = app(LoyaltyService::class);
        $actor = auth('admin')->user();

        $currentPoints = (int) $record->loyalty_points;
        $newPoints = array_key_exists('loyalty_points', $data)
            ? (int) $data['loyalty_points']
            : $currentPoints;

        if ($newPoints !== $currentPoints) {
            if (! UserResource::actorCanAdjustPoints()) {
                throw new AuthorizationException('Missing loyalty.adjust_points permission.');
            }

            $service->adjustPoints(
                $record,
                $newPoints - $currentPoints,
                'Edited via profile form',
                $actor,
            );
        }

        $currentTier = $record->loyalty_tier->value;
        $newTier = $data['loyalty_tier'] ?? $currentTier;
        $submittedExpiry = $data['premier_expiry'] ?? null;

        $tierChanged = $newTier !== $currentTier;
        $expiryRolled = ! $tierChanged
            && $newTier === LoyaltyTier::Premier->value
            && $submittedExpiry !== null
            && Carbon::parse($submittedExpiry)->toDateString() !== $record->premier_expiry?->toDateString();

        if ($tierChanged || $expiryRolled) {
            if (! UserResource::actorCanAdjustTier()) {
                throw new AuthorizationException('Missing loyalty.adjust_tier permission.');
            }

            if ($newTier === LoyaltyTier::Premier->value) {
                $service->grantPremier(
                    $record,
                    $submittedExpiry ? Carbon::parse($submittedExpiry) : Carbon::now()->addYear(),
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
        }

        return $record->fresh();
    }
}
