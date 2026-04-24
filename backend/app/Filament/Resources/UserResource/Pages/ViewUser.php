<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Override the default form-derived view to render the dedicated read-only
     * schema. Keeps the edit form narrowly scoped to loyalty fields while the
     * view page shows the full profile.
     */
    public function form(Schema $schema): Schema
    {
        return UserResource::viewSchema($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            UserResource::adjustPointsAction(),
            UserResource::upgradePremierAction(),
            UserResource::revokePremierAction(),
        ];
    }
}
