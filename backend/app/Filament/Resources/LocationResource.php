<?php

namespace App\Filament\Resources;

use App\Exceptions\LocationHasBookingsException;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers;
use App\Models\Location;
use App\Services\AuditoriumService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class LocationResource extends BaseResource
{
    protected static ?string $model = Location::class;

    protected static ?string $permissionPrefix = 'locations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, $record) {
                            if ($record === null) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Auto-derived from name on create. Stable after that — public URLs depend on it.'),
                ])
                ->columns(2),

            Section::make('Contact')
                ->schema([
                    TextInput::make('phone')->tel()->maxLength(255),
                    TextInput::make('email')->email()->maxLength(255),
                ])
                ->columns(2),

            Section::make('Address')
                ->schema([
                    TextInput::make('street')->maxLength(255),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('postal_code')->maxLength(20),
                    TextInput::make('country')
                        ->default('US')
                        ->maxLength(2)
                        ->helperText('ISO 3166-1 alpha-2 country code (e.g. US, CA, GB)'),
                ])
                ->columns(2),

            Section::make('Geography')
                ->schema([
                    Select::make('timezone')
                        ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->default(fn () => config('app.default_location_timezone') ?? config('app.timezone'))
                        ->required()
                        ->searchable()
                        ->helperText('Timezone for this theater. Drives showtime display and scheduling math.'),
                    TextInput::make('latitude')
                        ->numeric()
                        ->step(0.000001)
                        ->minValue(-90)
                        ->maxValue(90),
                    TextInput::make('longitude')
                        ->numeric()
                        ->step(0.000001)
                        ->minValue(-180)
                        ->maxValue(180),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('state')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')->toggleable(),
                TextColumn::make('auditoriums_count')
                    ->counts('auditoriums')
                    ->label('Auditoriums')
                    ->sortable(),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::serviceDeleteAction(),
            ])
            ->defaultSort('name');
    }

    /**
     * DeleteAction that routes deletion through AuditoriumService so the
     * activity_log row fires with the admin actor. A stock
     * `DeleteAction::make()` without `->using()` would bypass the service —
     * guarded by the Layer A regression test in Task 7.
     */
    public static function serviceDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (Location $record) {
                try {
                    app(AuditoriumService::class)->deleteLocation($record, auth('admin')->user());
                } catch (LocationHasBookingsException $e) {
                    Notification::make()
                        ->title('Cannot delete location')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }
            })
            ->requiresConfirmation()
            ->modalDescription('Deleting this location cascades to its auditoriums, sections, and seats. Deletion is refused while any showtime still has bookings — cancel or refund affected bookings first.');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AuditoriumsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
