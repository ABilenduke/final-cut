<?php

namespace App\Filament\Resources;

use App\Enums\MovieStatus;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\MovieResource\Pages;
use App\Filament\Resources\MovieResource\RelationManagers;
use App\Models\Movie;
use App\Services\MovieService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class MovieResource extends BaseResource
{
    protected static ?string $model = Movie::class;

    protected static ?string $permissionPrefix = 'movies';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-film';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, ?Model $record) {
                            if ($record === null) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Auto-derived from title on create. On edit the slug is stable — change it manually only if needed (public URLs depend on it).'),
                    Select::make('status')
                        ->options(self::statusOptions())
                        ->required(),
                    TextInput::make('tmdb_id')
                        ->numeric()
                        ->helperText('TMDB movie ID for metadata enrichment'),
                ])
                ->columns(2),

            Section::make('Content')
                ->schema([
                    TextInput::make('tagline')->maxLength(500),
                    Textarea::make('synopsis')->rows(5),
                    TextInput::make('runtime')->numeric()->suffix('minutes'),
                    DatePicker::make('release_date'),
                    TextInput::make('rating')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->step(0.1),
                ]),

            Section::make('Media')
                ->schema([
                    TextInput::make('poster_url')->url()->label('Poster URL'),
                    TextInput::make('backdrop_url')->url()->label('Backdrop URL'),
                    TextInput::make('trailer_key')
                        ->label('YouTube Trailer Key')
                        ->helperText('e.g., "dQw4w9WgXcQ" from youtube.com/watch?v=dQw4w9WgXcQ'),
                ])
                ->columns(2),

            Section::make('Taxonomy')
                ->schema([
                    Repeater::make('genres')
                        ->schema([
                            TextInput::make('id')
                                ->numeric()
                                ->required()
                                ->helperText('TMDB genre ID'),
                            TextInput::make('name')->required(),
                        ])
                        ->columns(2)
                        ->collapsed()
                        ->defaultItems(0)
                        ->reorderable()
                        ->helperText('Usually populated by TMDB enrichment — edit manually only for custom entries.'),
                ]),

            Section::make('Cast')
                ->schema([
                    Repeater::make('cast')
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('character')->required(),
                            TextInput::make('profileUrl')->url()->label('Profile URL'),
                        ])
                        ->columns(3)
                        ->reorderable()
                        ->collapsed(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('poster_url')
                    ->label('Poster')
                    ->defaultImageUrl('/images/movie-placeholder.png')
                    ->extraImgAttributes(['style' => 'max-height:3rem']),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::statusValue($state))
                    ->color(fn ($state): string => match (self::statusValue($state)) {
                        MovieStatus::NowShowing->value => 'success',
                        MovieStatus::ComingSoon->value => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('genres')
                    ->badge()
                    ->formatStateUsing(fn ($state) => collect($state ?? [])->pluck('name')->all())
                    ->separator(','),
                TextColumn::make('runtime')->suffix(' min')->sortable(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : '—')
                    ->sortable(),
                TextColumn::make('tmdb_enriched_at')->label('Enriched')->since()->sortable(),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                SelectFilter::make('genre_name')
                    ->label('Genre')
                    ->options(fn () => Movie::query()
                        ->whereNotNull('genres')
                        ->pluck('genres')
                        ->flatMap(fn ($genres) => collect($genres)->pluck('name'))
                        ->unique()
                        ->sort()
                        ->mapWithKeys(fn ($name) => [$name => $name])
                        ->all())
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['value'] ?? null,
                        fn ($q, $name) => $q->whereJsonContains('genres', [['name' => $name]])
                    )),
                Filter::make('needs_enrichment')
                    ->label('Never enriched')
                    ->query(fn (Builder $q) => $q->whereNull('tmdb_enriched_at')->whereNotNull('tmdb_id')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('enrich')
                    ->label('Enrich from TMDB')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => $record->tmdb_id && auth('admin')->user()?->can('movies.trigger_enrich'))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $dispatched = app(MovieService::class)
                            ->triggerEnrichment($record, auth('admin')->user());

                        Notification::make()
                            ->title($dispatched ? 'Enrichment queued' : 'Enrichment already in progress')
                            ->{$dispatched ? 'success' : 'warning'}()
                            ->send();
                    }),
                self::serviceDeleteAction(),
            ])
            ->toolbarActions([
                self::statusBulkAction('mark_now_showing', 'Mark as Now Showing', 'heroicon-o-play', MovieStatus::NowShowing),
                self::statusBulkAction('mark_coming_soon', 'Mark as Coming Soon', 'heroicon-o-clock', MovieStatus::ComingSoon),
            ])
            ->defaultSort('release_date', 'desc');
    }

    /**
     * DeleteAction that routes deletion through MovieService so activity_log
     * attribution fires. Stock DeleteAction bypasses the service — guarded by test.
     */
    public static function serviceDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Movie $record) => app(MovieService::class)
                ->delete($record, auth('admin')->user()));
    }

    private static function statusBulkAction(string $name, string $label, string $icon, MovieStatus $status): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn () => auth('admin')->user()?->can('movies.update') ?? false)
            ->action(fn (Collection $records) => $records->each(
                fn (Movie $r) => app(MovieService::class)
                    ->update($r, ['status' => $status->value], auth('admin')->user())
            ))
            ->deselectRecordsAfterCompletion();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return [
            MovieStatus::NowShowing->value => 'Now Showing',
            MovieStatus::ComingSoon->value => 'Coming Soon',
        ];
    }

    private static function statusValue(mixed $state): string
    {
        return $state instanceof MovieStatus ? $state->value : (string) $state;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UpcomingShowtimesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovies::route('/'),
            'create' => Pages\CreateMovie::route('/create'),
            'view' => Pages\ViewMovie::route('/{record}'),
            'edit' => Pages\EditMovie::route('/{record}/edit'),
        ];
    }
}
