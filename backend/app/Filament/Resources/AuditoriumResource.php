<?php

namespace App\Filament\Resources;

use App\Exceptions\AuditoriumHasBookingsException;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
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
                // Re-parenting an auditorium is out of scope for v1. The edit
                // form previously accepted a new value silently but the service
                // discarded it, so lock the field on edit to match behaviour.
                ->disabledOn('edit')
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
                        ->unique(ignoreRecord: true, modifyRuleUsing: self::scopeUniqueToLocation())
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, $record) {
                            if ($record === null) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: self::scopeUniqueToLocation())
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
                // Snapshot current unavailable timestamps so we only include
                // `unavailable_at` in a patch when the toggle actually changed.
                // Without this guard, resaving the modal rewrites every
                // already-unavailable seat's timestamp to `now()` and fires a
                // spurious activity_log row per seat.
                $currentUnavailable = $record->seats()
                    ->pluck('unavailable_at', 'id');

                $patches = [];
                foreach ($data['seats'] ?? [] as $row) {
                    $seatId = $row['id'];
                    $wasUnavailable = $currentUnavailable->get($seatId) !== null;
                    $isUnavailable = (bool) ($row['unavailable'] ?? false);

                    $patch = [
                        'seat_id' => $seatId,
                        'section_id' => $row['section_id'],
                    ];
                    if ($isUnavailable !== $wasUnavailable) {
                        $patch['unavailable_at'] = $isUnavailable ? now() : null;
                    }
                    $patches[] = $patch;
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
     * Scope the `unique` validator on `name`/`slug` to the auditorium's
     * location. Works for both the standalone resource (reads `location_id`
     * from the form state via `Get`) and the `AuditoriumsRelationManager`
     * (falls back to the owner record when the form has no location_id field).
     */
    private static function scopeUniqueToLocation(): \Closure
    {
        return function (Unique $rule, Get $get, \Livewire\Component $livewire) {
            $locationId = $get('location_id');
            if ($locationId === null && method_exists($livewire, 'getOwnerRecord')) {
                $owner = $livewire->getOwnerRecord();
                $locationId = $owner?->getKey();
            }

            return $locationId !== null
                ? $rule->where('location_id', $locationId)
                : $rule;
        };
    }

    /**
     * DeleteAction routed through the service for audit-log attribution.
     * Stock `DeleteAction::make()` without `->using()` is a test-caught
     * regression (Task 7 Layer A).
     */
    public static function serviceDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (Auditorium $record) {
                try {
                    app(AuditoriumService::class)->deleteAuditorium($record, auth('admin')->user());
                } catch (AuditoriumHasBookingsException $e) {
                    Notification::make()
                        ->title('Cannot delete auditorium')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }
            })
            ->requiresConfirmation()
            ->modalDescription('Deleting this auditorium cascades to its showtimes, seats, and sections. Deletion is refused while any showtime still has bookings — cancel or refund affected bookings first.');
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
