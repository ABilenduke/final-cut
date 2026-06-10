<?php

namespace App\Filament\Resources;

use App\Exceptions\MovieRuntimeMissingException;
use App\Exceptions\ShowtimeAlreadyCancelledException;
use App\Exceptions\ShowtimeConflictException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\ShowtimeResource\Pages;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ShowtimeResource extends BaseResource
{
    use FormatsCurrency;

    /**
     * Computed status badge values used by the status column + filter. Not a
     * persisted column — derived from `cancelled_at` and `start_time` at read
     * time, so it lives here as a const rather than on the model.
     */
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PAST = 'past';

    public const STATUS_CANCELLED = 'cancelled';

    protected static ?string $model = Showtime::class;

    protected static ?string $permissionPrefix = 'showtimes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    Select::make('movie_id')
                        ->label('Movie')
                        ->relationship('movie', 'title')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    // Form-only cascade: location_id is not persisted on showtimes
                    // (auditorium_id already resolves to a location via its FK). The
                    // resource adds edit-mode hydration so editing an existing row
                    // opens with the cascade already pointing at the right location.
                    self::locationCascadeSelect()
                        ->default(fn (?Showtime $record) => $record?->auditorium->location_id)
                        ->afterStateHydrated(function (Select $component, ?Showtime $record) {
                            if ($record !== null && $component->getState() === null) {
                                $component->state($record->auditorium->location_id);
                            }
                        }),

                    self::auditoriumCascadeSelect(),

                    DateTimePicker::make('start_time')
                        ->required()
                        ->seconds(false)
                        ->minutesStep(5)
                        ->live(),

                    Placeholder::make('computed_end_time')
                        ->label('End Time')
                        ->content(fn (Get $get) => static::computeEndTimePreview($get)),
                ])
                ->columns(2),

            Section::make('Pricing (cents)')
                ->schema([
                    TextInput::make('price_standard')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('¢')
                        ->required()
                        ->helperText('Store as cents: $12.99 → 1299'),
                    TextInput::make('price_premium')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('¢')
                        ->required(),
                    TextInput::make('price_accessible')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('¢')
                        ->required(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movie.title')->searchable()->sortable(),
                TextColumn::make('auditorium.location.name')
                    ->label('Location')
                    ->sortable(),
                TextColumn::make('auditorium.name')
                    ->label('Auditorium')
                    ->sortable(),
                TextColumn::make('start_time')->dateTime()->sortable(),
                TextColumn::make('end_time')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price_standard')
                    ->label('Std')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
                TextColumn::make('price_premium')
                    ->label('Prem')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price_accessible')
                    ->label('Access')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occupancy')
                    ->label('Occupancy')
                    // `occupied_seats_count` + the eager-loaded auditorium come
                    // from getEloquentQuery() — no per-row queries.
                    ->getStateUsing(fn (Showtime $record): string => sprintf(
                        '%d / %d',
                        $record->occupied_seats_count ?? 0,
                        $record->auditorium->total_seats,
                    )),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Showtime $record): string => self::deriveStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        self::STATUS_SCHEDULED => 'success',
                        self::STATUS_PAST => 'gray',
                        self::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        self::STATUS_SCHEDULED => 'Scheduled',
                        self::STATUS_PAST => 'Past',
                        self::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->query(function (Builder $q, array $data) {
                        return match ($data['value'] ?? null) {
                            self::STATUS_SCHEDULED => $q->whereNull('cancelled_at')->where('start_time', '>', now()),
                            self::STATUS_PAST => $q->whereNull('cancelled_at')->where('start_time', '<=', now()),
                            self::STATUS_CANCELLED => $q->whereNotNull('cancelled_at'),
                            default => $q,
                        };
                    }),
                SelectFilter::make('movie_id')
                    ->label('Movie')
                    ->relationship('movie', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->options(fn () => Location::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['value'] ?? null,
                        fn (Builder $qq, $id) => $qq->whereHas('auditorium', fn (Builder $qqq) => $qqq->where('location_id', $id))
                    )),
                SelectFilter::make('auditorium_id')
                    ->label('Auditorium')
                    ->options(fn () => Auditorium::with('location')
                        ->get()
                        ->sortBy(fn (Auditorium $a) => $a->location->name.'/'.$a->name)
                        ->mapWithKeys(fn (Auditorium $a) => [
                            $a->id => "{$a->location->name} — {$a->name}",
                        ])
                        ->all()),
                Filter::make('upcoming_7_days')
                    ->label('Next 7 days')
                    ->query(fn (Builder $q) => $q->whereBetween('start_time', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Showtime $record) => $record->cancelled_at === null && $record->start_time->isFuture()),
                self::cancelAction(),
            ])
            ->defaultSort('start_time', 'asc');
    }

    /**
     * Cancel row action — writes cancellation + flags bookings + queues
     * dispatch_outbox rows via ShowtimeService. Visible only for future,
     * non-cancelled showtimes, and only to admins with the cancel permission.
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel Showtime')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Showtime $record) => auth('admin')->user()?->can('showtimes.cancel')
                && $record->cancelled_at === null
                && $record->start_time->isFuture())
            ->schema([
                Textarea::make('reason')
                    ->label('Cancellation reason')
                    ->helperText('Customers do not see this; it is logged for staff reference.')
                    ->required()
                    ->minLength(3)
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalDescription(
                fn (Showtime $record) => 'Cancelling this showtime will flag '
                    // `bookings_count` is populated by getEloquentQuery()'s withCount.
                    // Fall back to a live count if this was reached via a fresh() or
                    // fetch that skipped the aggregate.
                    .($record->bookings_count ?? $record->bookings()->count())
                    .' booking(s) for manual refund.'
            )
            ->action(function (Showtime $record, array $data): void {
                try {
                    app(ShowtimeService::class)->cancel($record, $data['reason'], auth('admin')->user());
                } catch (ShowtimeAlreadyCancelledException $e) {
                    Notification::make()
                        ->title('Already cancelled')
                        ->body($e->getMessage())
                        ->warning()
                        ->send();

                    throw new Halt;
                }

                Notification::make()
                    ->title('Showtime cancelled')
                    ->body('Follow-up queue updated.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Reactive end-time preview shown under the start-time picker. States:
     * - Missing input → nudge to pick the required fields.
     * - Movie with runtime=null → block with actionable link to edit the movie.
     * - Valid inputs → render "HH:MM (includes N min cleanup)".
     * - Any unexpected error → surface it rather than silently rendering "—".
     *
     * Returns HtmlString when an anchor is needed so Filament's Placeholder
     * renders the link rather than the raw markdown-style brackets.
     */
    protected static function computeEndTimePreview(Get $get): string|Htmlable
    {
        $movieId = $get('movie_id');
        $auditoriumId = $get('auditorium_id');
        $startTime = $get('start_time');

        if (! $movieId || ! $auditoriumId || ! $startTime) {
            return 'Pick a movie, auditorium, and start time to preview.';
        }

        try {
            /** @var ?Movie $movie */
            $movie = Movie::find($movieId);
            /** @var ?Auditorium $auditorium */
            $auditorium = Auditorium::find($auditoriumId);

            if (! $movie || ! $auditorium) {
                return 'Selected movie or auditorium not found.';
            }

            if ($movie->runtime === null) {
                $editUrl = self::safeMovieEditUrl($movie);
                if ($editUrl) {
                    return new HtmlString(sprintf(
                        'This movie has no runtime set. <a href="%s" class="fi-link underline">Edit the movie</a> to add one before scheduling.',
                        e($editUrl),
                    ));
                }

                return 'This movie has no runtime set — edit the movie to add one before scheduling.';
            }

            $start = $startTime instanceof CarbonInterface
                ? $startTime->copy()
                : Carbon::parse($startTime);

            $end = ShowtimeService::computeEndTime($movie, $auditorium, $start);
            $cleanup = (int) ($auditorium->cleanup_minutes ?? 0);
            $suffix = $cleanup > 0
                ? " (includes {$cleanup} min cleanup)"
                : '';

            return $end->format('g:i A').' · '.$end->format('l F j, Y').$suffix;
        } catch (\Throwable $e) {
            return 'Unable to compute end time: '.$e->getMessage();
        }
    }

    /** Best-effort MovieResource edit URL — avoids leaking route-generation errors into the form. */
    private static function safeMovieEditUrl(Movie $movie): ?string
    {
        try {
            return MovieResource::getUrl('edit', ['record' => $movie]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Shared pre-submit validation invoked by Create/Edit pages before the
     * service write. Raises a validation error summarising conflicting rows
     * so the form displays a readable message instead of a raw SQL error.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validateAgainstConflicts(array $data, ?string $ignoreShowtimeId = null): void
    {
        $movieId = $data['movie_id'] ?? null;
        $auditoriumId = $data['auditorium_id'] ?? null;
        $startRaw = $data['start_time'] ?? null;

        if (! $movieId || ! $auditoriumId || ! $startRaw) {
            return;
        }

        $movie = Movie::find($movieId);

        if ($movie === null) {
            return;
        }

        if ($movie->runtime === null) {
            // `data.*` prefix pairs the error with the Livewire-bound form state
            // (`wire:model="data.movie_id"`), so Filament surfaces the message
            // under the Movie select instead of a generic 422.
            throw ValidationException::withMessages([
                'data.movie_id' => (new MovieRuntimeMissingException($movie))->getMessage(),
            ]);
        }

        $auditorium = Auditorium::find($auditoriumId);

        if ($auditorium === null) {
            return;
        }

        $start = $startRaw instanceof CarbonInterface ? $startRaw->copy() : Carbon::parse($startRaw);
        $end = ShowtimeService::computeEndTime($movie, $auditorium, $start);

        $conflicts = app(ShowtimeService::class)
            ->detectConflicts($auditorium->id, $start, $end, $ignoreShowtimeId);

        if ($conflicts->isEmpty()) {
            return;
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $conflicts->map(fn (Showtime $s) => [
            'id' => $s->id,
            'movie_title' => $s->movie->title,
            'start_time' => $s->start_time->format('M j, Y g:i A'),
            'end_time' => $s->end_time->format('g:i A'),
        ])->values();

        throw ValidationException::withMessages([
            'data.start_time' => 'Showtime overlaps with: '
                .ShowtimeConflictException::formatConflicts($rows),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShowtimes::route('/'),
            'create' => Pages\CreateShowtime::route('/create'),
            'bulk_create' => Pages\BulkCreateShowtimes::route('/bulk-create'),
            'view' => Pages\ViewShowtime::route('/{record}'),
            'occupancy' => Pages\ShowtimeOccupancy::route('/{record}/occupancy'),
            'edit' => Pages\EditShowtime::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // `withCount('bookings')` feeds the cancel action's modal description
        // without one SELECT COUNT per rendered row — reads `bookings_count`
        // off the eager-loaded aggregate instead.
        return parent::getEloquentQuery()
            ->with(['movie', 'auditorium.location'])
            ->withCount('bookings')
            // Feeds the list page's occupancy column off the occupancy
            // guard's own flag, one aggregate per row. seat_id IS NOT NULL
            // mirrors the guard's partial-index predicate — orphaned rows
            // (seat deleted after booking) must not inflate the count.
            ->withCount(['bookingSeats as occupied_seats_count' => fn (Builder $q) => $q
                ->where('occupies_seat', true)
                ->whereNotNull('seat_id')]);
    }

    /** Derive the badge status from persisted columns — shared by column + filter. */
    public static function deriveStatus(Showtime $record): string
    {
        if ($record->cancelled_at !== null) {
            return self::STATUS_CANCELLED;
        }

        return $record->start_time->isPast() ? self::STATUS_PAST : self::STATUS_SCHEDULED;
    }

    /**
     * Location Select that drives the cascade. `dehydrated(false)` keeps it
     * off the persisted payload — only `auditorium_id` gets saved. Clears the
     * downstream auditorium on change. Callers can chain edit-mode hydration
     * (default/afterStateHydrated) when there's a record to read.
     */
    public static function locationCascadeSelect(): Select
    {
        return Select::make('location_id')
            ->label('Location')
            ->options(fn () => Location::orderBy('name')->pluck('name', 'id'))
            ->required()
            ->live()
            ->dehydrated(false)
            ->afterStateUpdated(fn (callable $set) => $set('auditorium_id', null));
    }

    /** Auditorium Select whose options depend on the reactive location_id. */
    public static function auditoriumCascadeSelect(): Select
    {
        return Select::make('auditorium_id')
            ->label('Auditorium')
            ->options(function (Get $get) {
                $locationId = $get('location_id');

                if (! $locationId) {
                    return [];
                }

                return Auditorium::where('location_id', $locationId)
                    ->orderBy('name')
                    ->pluck('name', 'id');
            })
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->disabled(fn (Get $get) => ! $get('location_id'));
    }
}
