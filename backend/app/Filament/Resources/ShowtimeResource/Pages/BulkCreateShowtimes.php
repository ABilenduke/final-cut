<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Exceptions\MovieRuntimeMissingException;
use App\Exceptions\ShowtimeConflictException;
use App\Filament\Resources\ShowtimeResource;
use App\Http\Requests\BulkShowtimeRequest;
use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * @property Schema $form
 */
class BulkCreateShowtimes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ShowtimeResource::class;

    protected string $view = 'filament.resources.showtime-resource.pages.bulk-create';

    protected static ?string $title = 'Bulk create showtimes';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Populated by submit() when pre-check conflicts exist. Structure:
     * [
     *   'creatable' => [{ 'date' => 'Y-m-d', 'time' => 'HH:MM', 'start_time' => 'ISO8601' }, ...],
     *   'conflicting' => [{ 'date' => ..., 'time' => ..., 'conflicts' => [ 'Title @ time', ...] }, ...],
     * ]
     *
     * @var array<string, mixed>
     */
    public array $preview = [];

    public function mount(): void
    {
        abort_unless(auth('admin')->user()?->can('showtimes.create') ?? false, 403);

        $this->form->fill([
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
            'days_of_week' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Movie & Auditorium')
                    ->schema([
                        // Standalone page (no model record) — must use options()
                        // rather than relationship() since Select::getRelationship()
                        // walks the record's relationships.
                        Select::make('movie_id')
                            ->label('Movie')
                            ->options(fn () => Movie::orderBy('title')->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        ShowtimeResource::locationCascadeSelect(),
                        ShowtimeResource::auditoriumCascadeSelect(),
                    ])
                    ->columns(2),

                Section::make('Date Range & Schedule')
                    ->schema([
                        DatePicker::make('start_date')
                            ->required()
                            ->minDate(today())
                            ->default(today()->toDateString())
                            ->live(),
                        DatePicker::make('end_date')
                            ->required()
                            ->afterOrEqual('start_date')
                            ->default(today()->addWeeks(2)->toDateString())
                            ->live(),
                        CheckboxList::make('days_of_week')
                            ->options([
                                'Mon' => 'Monday',
                                'Tue' => 'Tuesday',
                                'Wed' => 'Wednesday',
                                'Thu' => 'Thursday',
                                'Fri' => 'Friday',
                                'Sat' => 'Saturday',
                                'Sun' => 'Sunday',
                            ])
                            ->required()
                            ->columns(4)
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Times of day')
                    ->schema([
                        TagsInput::make('times')
                            ->placeholder('19:00, 21:30')
                            ->helperText('Type each time and press Enter, or paste a comma-separated list. 24-hour format (HH:MM).')
                            ->required()
                            ->live(),
                    ]),

                Section::make('Pricing (cents)')
                    ->schema([
                        TextInput::make('price_standard')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('¢')
                            ->required(),
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

                Placeholder::make('preview_count')
                    ->label('Preview')
                    ->content(fn (Get $get) => static::previewCountMessage($get)),
            ]);
    }

    /**
     * Phase 1 — pre-check. Build tuples, split into conflicting/creatable,
     * store both on the Livewire state. If nothing conflicts, proceed straight
     * to phase 2. Otherwise the view renders the conflict list and offers a
     * single "Skip conflicts and create" path.
     */
    public function submit(): void
    {
        $data = $this->form->getState();

        try {
            $movie = Movie::findOrFail($data['movie_id']);
        } catch (Throwable) {
            Notification::make()->danger()->title('Movie not found')->send();

            return;
        }

        if ($movie->runtime === null) {
            Notification::make()
                ->danger()
                ->title('Movie has no runtime')
                ->body((new MovieRuntimeMissingException($movie))->getMessage())
                ->send();

            return;
        }

        $auditorium = Auditorium::findOrFail($data['auditorium_id']);

        $tuples = self::buildTuples($data);

        if ($tuples->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No showtimes would be created')
                ->body('Check the date range, days of week, and times.')
                ->send();

            return;
        }

        $service = app(ShowtimeService::class);

        // Expand every tuple to a (start, end) interval up front so we can
        // batch the conflict lookup into a single DB query instead of one
        // query per tuple. End-time rule stays in ShowtimeService so the
        // preview, the write path, and the form resource all agree.
        $intervals = $tuples->map(fn (array $tuple) => [
            'start' => $tuple['start'],
            'end' => ShowtimeService::computeEndTime($movie, $auditorium, $tuple['start']),
        ])->values();

        $conflictsByIndex = $service->detectConflictsForBatch($auditorium->id, $intervals);

        // Intra-batch overlaps: if the operator enters overlapping times in the
        // same request (e.g. "19:00" plus "19:30" with a 2h runtime), the DB
        // check alone won't catch them — and committing would trip the EXCLUDE
        // constraint and roll back the whole batch. Surface them now so each
        // overlapping row lands in `conflicting` alongside any DB collisions.
        $siblingConflicts = self::detectSiblingOverlaps($intervals);

        $creatable = [];
        $conflicting = [];

        foreach ($intervals as $index => $interval) {
            $start = $interval['start'];
            $rowDbConflicts = $conflictsByIndex[$index];
            $rowSiblings = $siblingConflicts[$index] ?? [];

            if ($rowDbConflicts->isEmpty() && $rowSiblings === []) {
                $creatable[] = [
                    'date' => $start->format('Y-m-d'),
                    'time' => $start->format('H:i'),
                    'start_iso' => $start->toIso8601String(),
                ];

                continue;
            }

            $labels = $rowDbConflicts
                ->map(fn (Showtime $s) => $s->movie->title.' @ '.$s->start_time->format('M j g:i A'))
                ->all();

            foreach ($rowSiblings as $siblingIndex) {
                $sibling = $intervals[$siblingIndex];
                $labels[] = 'another entry in this batch at '.$sibling['start']->format('M j g:i A');
            }

            $conflicting[] = [
                'date' => $start->format('Y-m-d'),
                'time' => $start->format('H:i'),
                'conflicts' => $labels,
            ];
        }

        $this->preview = [
            'creatable' => $creatable,
            'conflicting' => $conflicting,
            'movie_id' => (int) $movie->id,
            'auditorium_id' => (string) $auditorium->id,
            'price_standard' => (int) $data['price_standard'],
            'price_premium' => (int) $data['price_premium'],
            'price_accessible' => (int) $data['price_accessible'],
        ];

        if (empty($conflicting)) {
            $this->commit();
        }
    }

    /**
     * Phase 2 — actually create the tuples in the `creatable` subset inside a
     * single transaction. Rolls back the full subset on any failure; the
     * "previously skipped" conflicts were never candidates, so they stay
     * untouched regardless.
     */
    public function commit(): void
    {
        if (empty($this->preview['creatable'] ?? [])) {
            Notification::make()
                ->warning()
                ->title('Nothing to create')
                ->send();

            return;
        }

        // Accept row as mixed so the closure's inferred return uses the DTO's
        // `CarbonInterface` parameter shape rather than the concrete `Carbon`
        // that `Carbon::parse` returns (Collection<T> is invariant in T).
        $tuples = collect($this->preview['creatable'])
            ->map(fn (array $row): array => [
                'start_time' => self::parseStart($row['start_iso']),
            ])
            ->values();

        $request = new BulkShowtimeRequest(
            movieId: (string) $this->preview['movie_id'],
            auditoriumId: (string) $this->preview['auditorium_id'],
            tuples: $tuples,
            priceStandard: (int) $this->preview['price_standard'],
            pricePremium: (int) $this->preview['price_premium'],
            priceAccessible: (int) $this->preview['price_accessible'],
        );

        try {
            $created = app(ShowtimeService::class)
                ->bulkCreate($request, auth('admin')->user());
        } catch (ShowtimeConflictException $e) {
            // TOCTOU: a concurrent insert committed a row between detectConflicts
            // and our INSERT. The EXCLUDE constraint caught it; the whole batch
            // rolled back. Kick the admin back to the form with a clear message.
            Notification::make()
                ->danger()
                ->title('Another admin scheduled a conflicting showtime while you were reviewing')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->preview = [];

            return;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Bulk create failed')
                ->body('No showtimes were created. Please try again.')
                ->persistent()
                ->send();

            return;
        }

        $createdCount = $created->count();
        $skippedCount = count($this->preview['conflicting'] ?? []);

        Notification::make()
            ->success()
            ->title("Created {$createdCount} showtime(s)".($skippedCount > 0 ? ", skipped {$skippedCount} conflict(s)" : ''))
            ->send();

        $this->redirect(ShowtimeResource::getUrl('index'));
    }

    public function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Preview')
                ->submit('submit')
                ->visible(fn () => empty($this->preview)),
            Action::make('commit')
                ->label(fn () => 'Skip conflicts and create '.count($this->preview['creatable'] ?? []))
                ->color('primary')
                ->visible(fn () => ! empty($this->preview) && ! empty($this->preview['creatable']))
                ->action('commit'),
            Action::make('reset_preview')
                ->label('Back to form')
                ->color('gray')
                ->visible(fn () => ! empty($this->preview))
                ->action(function () {
                    $this->preview = [];
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->visible(fn () => empty($this->preview))
                ->url(fn () => ShowtimeResource::getUrl('index')),
        ];
    }

    public function renderPreviewSummary(): HtmlString
    {
        if (empty($this->preview)) {
            return new HtmlString('');
        }

        $creatableCount = count($this->preview['creatable'] ?? []);
        $conflictingCount = count($this->preview['conflicting'] ?? []);

        $html = '<div class="fi-section-content-ctn p-4 space-y-4">';
        $html .= "<p><strong>{$creatableCount}</strong> showtime(s) ready to create. <strong>{$conflictingCount}</strong> skipped due to conflicts.</p>";

        if ($conflictingCount > 0) {
            $html .= '<div class="fi-in-danger"><strong>Skipped (conflicts):</strong><ul class="list-disc pl-6 mt-2">';
            foreach ($this->preview['conflicting'] as $row) {
                $conflictList = implode(', ', array_map(fn ($s) => e($s), $row['conflicts']));
                $html .= "<li>{$row['date']} {$row['time']} — conflicts with: {$conflictList}</li>";
            }
            $html .= '</ul></div>';
        }

        return new HtmlString($html);
    }

    /** Widens `Carbon::parse`'s concrete return to `CarbonInterface` for DTO typing. */
    private static function parseStart(string $iso): CarbonInterface
    {
        return Carbon::parse($iso);
    }

    /**
     * Pairwise half-open overlap check within a single batch. O(N²) in PHP —
     * fine for the form's expected N (days × times within a two-week bulk
     * request is typically <100). Returns a map of index => indices-it-overlaps
     * so the preview can label each side of the collision.
     *
     * @param  Collection<int, array{start: CarbonInterface, end: CarbonInterface}>  $intervals
     * @return array<int, list<int>>
     */
    private static function detectSiblingOverlaps(Collection $intervals): array
    {
        $out = [];
        $items = $intervals->all();
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                // Half-open [start, end): overlap iff a.start < b.end AND a.end > b.start.
                if ($items[$i]['start'] < $items[$j]['end'] && $items[$i]['end'] > $items[$j]['start']) {
                    $out[$i][] = $j;
                    $out[$j][] = $i;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{start: CarbonInterface}>
     */
    private static function buildTuples(array $data): Collection
    {
        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : null;
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : null;
        $days = (array) ($data['days_of_week'] ?? []);
        $times = (array) ($data['times'] ?? []);

        if (! $startDate || ! $endDate || empty($days) || empty($times)) {
            return collect();
        }

        $dayMap = [
            'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4,
            'Fri' => 5, 'Sat' => 6, 'Sun' => 0,
        ];

        $targetDayNums = array_values(array_intersect_key($dayMap, array_flip($days)));

        $out = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            if (in_array($cursor->dayOfWeek, $targetDayNums, true)) {
                foreach ($times as $time) {
                    if (! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) {
                        continue;
                    }

                    $out->push([
                        'start' => $cursor->copy()->setTimeFromTimeString($time),
                    ]);
                }
            }

            $cursor->addDay();
        }

        return $out;
    }

    public static function previewCountMessage(Get $get): string
    {
        $count = self::buildTuples([
            'start_date' => $get('start_date'),
            'end_date' => $get('end_date'),
            'days_of_week' => $get('days_of_week'),
            'times' => $get('times'),
        ])->count();

        if ($count === 0) {
            return 'Set date range, days of week, and times to preview how many showtimes will be created.';
        }

        return "{$count} showtime(s) will be created (conflicts will be flagged before commit).";
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title;
    }
}
