<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\AuditoriumResource\Pages;
use App\Models\Auditorium;
use App\Services\AuditoriumService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AuditoriumResource extends BaseResource
{
    protected static ?string $model = Auditorium::class;

    protected static ?string $permissionPrefix = 'auditoriums';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location_id')
                ->relationship('location', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('The theater this auditorium belongs to.'),
            ...self::getFormSchema(),
        ]);
    }

    /**
     * Shared auditorium form schema — consumed by both the standalone resource
     * and the `AuditoriumsRelationManager` on `LocationResource`. Do not
     * define a second form elsewhere; Task 7 asserts drift via a test that
     * compares the field set surfaced by both consumers.
     *
     * Does NOT include `location_id` — the relation manager sets the parent FK
     * automatically; the standalone resource's `form()` prepends its own
     * Location select.
     *
     * @return array<int, Component|Field>
     */
    public static function getFormSchema(): array
    {
        return [
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
                        ->maxLength(255)
                        ->helperText('Lowercase URL-friendly identifier. Unique within the location.'),
                    TextInput::make('cleanup_minutes')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(240)
                        ->default(20)
                        ->required()
                        ->suffix('min')
                        ->helperText('Turnover/cleaning buffer between showtimes. Drives scheduling conflict detection.'),
                    Textarea::make('notes')
                        ->rows(3)
                        ->helperText('Optional — operational notes visible to admin only.'),
                ])
                ->columns(2),

            Section::make('Sections')
                ->description('Pricing tiers within this auditorium. Deleting a section with seats still assigned to it is refused — reassign those seats first.')
                ->schema([
                    Repeater::make('sections')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->required()
                                ->placeholder('Standard / Premium / Accessible')
                                ->maxLength(255),
                            TextInput::make('price_multiplier')
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0)
                                ->default(1.00)
                                ->required()
                                ->helperText('1.00 = base price; 1.25 = 25% premium; 0.85 = 15% discount.'),
                            TextInput::make('display_order')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(3)
                        ->defaultItems(3)
                        ->reorderable()
                        ->orderColumn('display_order')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')->label('Location')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cleanup_minutes')->label('Cleanup')->suffix(' min')->sortable(),
                TextColumn::make('total_seats')->label('Seats')->sortable(),
                TextColumn::make('sections_count')->counts('sections')->label('Sections'),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ...self::seatManagementActions(),
            ])
            ->defaultSort('location.name');
    }

    /**
     * Seat-configuration row actions (configure / visual editor / fix /
     * service delete). Split from the standard View+Edit actions so the
     * relation manager on LocationResource can compose them without
     * depending on ordering. Reordering the list here is fine; the relation
     * manager consumes it by spread, not by index.
     *
     * @return array<int, Action|DeleteAction>
     */
    public static function seatManagementActions(): array
    {
        return [
            Action::make('configure_seats')
                ->label('Configure seats')
                ->icon('heroicon-o-wrench-screwdriver')
                ->visible(fn () => auth('admin')->user()?->can('seats.update') ?? false)
                ->url(fn (Auditorium $record) => AuditoriumResource::getUrl('configure-seats', ['record' => $record])),
            Action::make('visual_seat_editor')
                ->label('Visual seat editor')
                ->icon('heroicon-o-squares-2x2')
                ->visible(fn (Auditorium $record) => $record->seats()->exists()
                    && (auth('admin')->user()?->can('seats.update') ?? false))
                ->url(fn (Auditorium $record) => AuditoriumResource::getUrl('visual-editor', ['record' => $record])),
            self::fixSeatSectionsAction(),
            self::serviceDeleteAction(),
        ];
    }

    /**
     * Modal-based bulk seat edit action. Renders a repeater of all seats in
     * the auditorium; each row carries a section select and an unavailable
     * toggle. Submits the diff to `AuditoriumService::updateSeatBatch`.
     *
     * For the cleaner UX (click/drag/shift-click), see the visual seat
     * editor page (Task 6). This action is the no-UX-risk fallback.
     */
    public static function fixSeatSectionsAction(): Action
    {
        return Action::make('fix_seat_sections')
            ->label('Fix seat sections')
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (Auditorium $record) => $record->seats()->exists()
                && (auth('admin')->user()?->can('seats.update') ?? false))
            ->modalHeading('Fix seat sections')
            ->modalDescription('Reassign seats to different sections or flag them as unavailable. Seat IDs are preserved, so existing bookings are unaffected.')
            ->modalWidth('5xl')
            ->fillForm(fn (Auditorium $record) => [
                'seats' => $record->seats()
                    ->orderBy('row')
                    ->orderBy('number')
                    ->get()
                    ->map(fn ($seat) => [
                        'id' => $seat->id,
                        'label' => $seat->label,
                        'section_id' => $seat->section_id,
                        'unavailable' => $seat->unavailable_at !== null,
                    ])
                    ->all(),
            ])
            ->schema(fn (Auditorium $record) => [
                Repeater::make('seats')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('label')
                            ->label('Seat')
                            ->disabled()
                            ->dehydrated(),
                        Select::make('section_id')
                            ->label('Section')
                            ->options(fn () => $record->sections->pluck('name', 'id'))
                            ->required(),
                        Toggle::make('unavailable')
                            ->label('Unavailable'),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsed()
                    ->itemLabel(fn (array $state): string => ($state['label'] ?? '—').($state['unavailable'] ?? false ? ' — unavailable' : '')),
            ])
            ->action(function (array $data, Auditorium $record) {
                $patches = [];
                foreach ($data['seats'] ?? [] as $row) {
                    $patches[] = [
                        'seat_id' => $row['id'],
                        'section_id' => $row['section_id'],
                        'unavailable_at' => ($row['unavailable'] ?? false) ? now() : null,
                    ];
                }

                app(AuditoriumService::class)
                    ->updateSeatBatch($record, $patches, auth('admin')->user());

                Notification::make()
                    ->title('Seat assignments updated')
                    ->success()
                    ->send();
            });
    }

    /**
     * DeleteAction routed through the service for audit-log attribution.
     * Stock `DeleteAction::make()` without `->using()` is a test-caught
     * regression (Task 7 Layer A).
     */
    public static function serviceDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Auditorium $record) => app(AuditoriumService::class)
                ->deleteAuditorium($record, auth('admin')->user()))
            ->requiresConfirmation()
            ->modalDescription('Deleting this auditorium cascades to its showtimes and seats. Past bookings keep their historical seat references.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditoriums::route('/'),
            'create' => Pages\CreateAuditorium::route('/create'),
            'view' => Pages\ViewAuditorium::route('/{record}'),
            'edit' => Pages\EditAuditorium::route('/{record}/edit'),
            'configure-seats' => Pages\ConfigureSeats::route('/{record}/configure-seats'),
            'visual-editor' => Pages\VisualEditor::route('/{record}/visual-editor'),
        ];
    }
}
