<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\AuditoriumResource;
use App\Models\Auditorium;
use App\Models\Location;
use App\Services\AuditoriumService;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Auditoriums listed under a Location view/edit page.
 *
 * Shares form schema AND row actions with `AuditoriumResource` — drift is
 * guarded by a Layer A test in Task 7.
 */
class AuditoriumsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditoriums';

    protected static ?string $title = 'Auditoriums';

    public function form(Schema $schema): Schema
    {
        // Reuses the exact field set used by the standalone AuditoriumResource.
        // Do not add fields here — add them to AuditoriumResource::getFormSchema().
        return $schema->components(AuditoriumResource::getFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('cleanup_minutes')->label('Cleanup')->suffix(' min'),
                TextColumn::make('total_seats')->label('Seats'),
                TextColumn::make('sections_count')->counts('sections')->label('Sections'),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire) {
                        $sections = $data['sections'] ?? null;
                        unset($data['sections']);

                        $owner = $livewire->getOwnerRecord();
                        assert($owner instanceof Location);

                        return app(AuditoriumService::class)->saveAuditoriumWithSections(
                            $owner,
                            null,
                            $data,
                            $sections,
                            auth('admin')->user(),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Auditorium $record): array {
                        $data['sections'] = $record->sections()
                            ->orderBy('display_order')
                            ->get()
                            ->map(fn ($s) => [
                                'id' => $s->id,
                                'name' => $s->name,
                                'price_multiplier' => (string) $s->price_multiplier,
                                'display_order' => $s->display_order,
                            ])
                            ->all();

                        return $data;
                    })
                    ->using(function (Auditorium $record, array $data) {
                        $sections = $data['sections'] ?? null;
                        unset($data['sections']);

                        return app(AuditoriumService::class)->saveAuditoriumWithSections(
                            $record->location,
                            $record,
                            $data,
                            $sections,
                            auth('admin')->user(),
                        );
                    }),
                ...AuditoriumResource::seatManagementActions(),
            ])
            ->defaultSort('name');
    }
}
