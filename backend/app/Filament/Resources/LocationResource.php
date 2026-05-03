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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
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

    /** Days of the week in order. Used to build the hours section. */
    public const DAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

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

            self::hoursSection(),
        ]);
    }

    /**
     * Builds the "Hours of Operation" form section.
     *
     * The `hours` JSON column is exploded into virtual per-day sub-fields
     * by `mutateFormDataBeforeFill` on the Edit/Create page classes, then
     * re-assembled by `mutateFormDataBeforeSave` / `mutateFormDataBeforeCreate`
     * back into the JSON shape before reaching the service layer.
     *
     * The virtual fields (hours_{day}_closed, hours_{day}_open, hours_{day}_close)
     * ARE included in the dehydrated form state (no ->dehydrated(false)) so that
     * mutateFormDataBeforeSave can read them. implodeHoursFromForm strips them
     * out and writes the assembled `hours` key before the data reaches the model.
     *
     * Shape saved to DB:
     *   "monday": { "open": "HH:MM", "close": "HH:MM" }  — open day
     *   "monday": null                                     — closed day
     */
    public static function hoursSection(): Section
    {
        $dayFields = [];

        foreach (self::DAYS as $key => $label) {
            $dayFields[] = Grid::make(3)
                ->schema([
                    Toggle::make("hours_{$key}_closed")
                        ->label($label)
                        ->live()
                        ->columnSpan(1),

                    TextInput::make("hours_{$key}_open")
                        ->label('Opens')
                        ->placeholder('HH:MM')
                        ->regex('/^\d{1,2}:\d{2}$/')
                        ->helperText('24-hour format, e.g. 12:00')
                        ->hidden(fn ($get) => (bool) $get("hours_{$key}_closed"))
                        ->columnSpan(1),

                    TextInput::make("hours_{$key}_close")
                        ->label('Closes')
                        ->placeholder('HH:MM')
                        ->regex('/^\d{1,2}:\d{2}$/')
                        ->helperText('24-hour format, e.g. 23:00')
                        ->hidden(fn ($get) => (bool) $get("hours_{$key}_closed"))
                        ->columnSpan(1),
                ]);
        }

        return Section::make('Hours of Operation')
            ->description('All times are local to the venue\'s timezone. Leave a day marked "closed" for days the theater is not open.')
            ->schema($dayFields)
            ->collapsible();
    }

    /**
     * Explodes the hours JSON column into per-day virtual form fields.
     * Called from mutateFormDataBeforeFill on the Edit/Create pages.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function explodeHoursForForm(array $data): array
    {
        $hours = $data['hours'] ?? [];
        if (! is_array($hours)) {
            $hours = [];
        }

        foreach (array_keys(self::DAYS) as $day) {
            $dayData = $hours[$day] ?? null;

            if ($dayData === null) {
                // Closed — toggle on, no times
                $data["hours_{$day}_closed"] = true;
                $data["hours_{$day}_open"] = null;
                $data["hours_{$day}_close"] = null;
            } else {
                $data["hours_{$day}_closed"] = false;
                $data["hours_{$day}_open"] = $dayData['open'] ?? null;
                $data["hours_{$day}_close"] = $dayData['close'] ?? null;
            }
        }

        // Leave hours key in place — it is a real DB column that Filament
        // will attempt to fill. mutateFormDataBeforeSave will overwrite it
        // with the re-assembled value before the model is saved.
        return $data;
    }

    /**
     * Re-assembles the per-day virtual form fields back into the hours JSON
     * shape expected by the DB column. Called from mutateFormDataBeforeSave.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function implodeHoursFromForm(array $data): array
    {
        $hours = [];

        foreach (array_keys(self::DAYS) as $day) {
            $closed = (bool) ($data["hours_{$day}_closed"] ?? false);

            if ($closed) {
                $hours[$day] = null;
            } else {
                $open = $data["hours_{$day}_open"] ?? null;
                $close = $data["hours_{$day}_close"] ?? null;

                // If both times are blank treat as closed rather than storing
                // an incomplete record.
                if ($open === null && $close === null) {
                    $hours[$day] = null;
                } else {
                    $hours[$day] = ['open' => $open, 'close' => $close];
                }
            }

            // Remove the virtual keys — they must not reach the model.
            unset($data["hours_{$day}_closed"]);
            unset($data["hours_{$day}_open"]);
            unset($data["hours_{$day}_close"]);
        }

        $data['hours'] = $hours;

        return $data;
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
