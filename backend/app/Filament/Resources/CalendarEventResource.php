<?php

namespace App\Filament\Resources;

use App\Enums\CalendarEventType;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\CalendarEventResource\Pages;
use App\Models\CalendarEvent;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Calendar events are admin-authored editorial content (special events,
 * loyalty exclusives, and private-screening blackouts). They have no
 * customer-facing state transitions and no downstream invariants the
 * customer API enforces, so writes use Filament's default Eloquent
 * persistence with no dedicated service. Showtime-type calendar entries
 * are produced by the showtimes domain (Plan 06) — this resource hides
 * `showtime` from the create/edit Select but keeps it visible in filters
 * and table rendering so legacy/imported showtime rows remain inspectable.
 */
class CalendarEventResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = CalendarEvent::class;

    protected static ?string $permissionPrefix = 'events';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Calendar Events';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('URL-safe slug. Auto-derived from title; edit if you need a custom value.'),
                    Select::make('type')
                        ->options(self::editableTypeOptions())
                        ->required()
                        ->live()
                        ->helperText('Showtime-type events are produced by the showtimes domain and are not authorable here.'),
                    Toggle::make('loyalty_only')
                        ->label('Members only')
                        ->visible(fn ($get) => $get('type') === CalendarEventType::LoyaltyExclusive->value)
                        ->default(false),
                ])
                ->columns(2),

            Section::make('Schedule')
                ->schema([
                    DatePicker::make('date')->required(),
                    TimePicker::make('start_time')->seconds(false),
                    TimePicker::make('end_time')
                        ->seconds(false)
                        ->helperText('Leave blank for all-day events.'),
                ])
                ->columns(3),

            Section::make('Content')
                ->schema([
                    Textarea::make('description')->rows(5),
                    FileUpload::make('image_path')
                        ->image()
                        ->directory('calendar-events')
                        ->disk('public')
                        ->visibility('public')
                        ->imageEditor(),
                    TextInput::make('ticket_url')
                        ->url()
                        ->maxLength(2048)
                        ->helperText('Optional external ticket / RSVP link.'),
                    TextInput::make('movie_slug')
                        ->maxLength(255)
                        ->helperText('Optional reference to a related movie slug — used by the customer calendar to deep-link into movie detail.'),
                ]),

            Section::make('Accessibility')
                ->schema([
                    CheckboxList::make('accessibility_tags')
                        ->options([
                            'sensory_friendly' => 'Sensory Friendly',
                            'open_caption' => 'Open Caption',
                            'audio_described' => 'Audio Described',
                        ])
                        ->columns(3)
                        ->helperText('Stored as JSON array on calendar_events.accessibility_tags.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (CalendarEventType $state): string => self::allTypeOptions()[$state->value] ?? $state->value)
                    ->color(fn (CalendarEventType $state): string => match ($state) {
                        CalendarEventType::SpecialEvent => 'warning',
                        CalendarEventType::LoyaltyExclusive => 'success',
                        CalendarEventType::PrivateScreeningBlackout => 'gray',
                        CalendarEventType::Showtime => 'info',
                    }),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('start_time')->time('H:i'),
                IconColumn::make('loyalty_only')
                    ->boolean()
                    ->label('Members only'),
                TextColumn::make('accessibility_tags')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => is_array($state) ? array_map(
                        fn (string $tag) => self::accessibilityLabel($tag),
                        $state,
                    ) : []),
                ...self::standardTimestamps(),
            ])
            ->defaultSort('date')
            ->filters([
                SelectFilter::make('type')->options(self::allTypeOptions()),
                SelectFilter::make('accessibility_tag')
                    ->label('Accessibility tag')
                    ->options([
                        'sensory_friendly' => 'Sensory Friendly',
                        'open_caption' => 'Open Caption',
                        'audio_described' => 'Audio Described',
                    ])
                    ->query(fn (Builder $q, array $data) => $data['value']
                        ? $q->whereJsonContains('accessibility_tags', $data['value'])
                        : $q),
                TernaryFilter::make('loyalty_only')
                    ->label('Members only')
                    ->placeholder('Any'),
                Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn (Builder $q) => $q->whereDate('date', '>=', now()->toDateString()))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarEvents::route('/'),
            'create' => Pages\CreateCalendarEvent::route('/create'),
            'edit' => Pages\EditCalendarEvent::route('/{record}/edit'),
        ];
    }

    /**
     * Editable type options exposed in the create/edit Select.
     *
     * `Showtime` is intentionally hidden — showtime-type calendar rows are
     * produced by the showtimes domain and would diverge from the showtimes
     * table if hand-authored.
     *
     * @return array<string, string>
     */
    private static function editableTypeOptions(): array
    {
        return [
            CalendarEventType::SpecialEvent->value => 'Special Event',
            CalendarEventType::LoyaltyExclusive->value => 'Loyalty Exclusive',
            CalendarEventType::PrivateScreeningBlackout->value => 'Private Screening Blackout',
        ];
    }

    /**
     * All type options (including Showtime) for table rendering and filters.
     *
     * @return array<string, string>
     */
    private static function allTypeOptions(): array
    {
        return [
            CalendarEventType::Showtime->value => 'Showtime',
            ...self::editableTypeOptions(),
        ];
    }

    private static function accessibilityLabel(string $tag): string
    {
        return match ($tag) {
            'sensory_friendly' => 'Sensory Friendly',
            'open_caption' => 'Open Caption',
            'audio_described' => 'Audio Described',
            default => $tag,
        };
    }
}
