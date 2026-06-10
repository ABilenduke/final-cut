<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Exceptions\ShowtimeConflictException;
use App\Filament\Resources\ShowtimeResource;
use App\Models\Location;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Copy an entire week's schedule to another week (admin-v2 Plan 06). Mirrors
 * BulkCreateShowtimes' two-phase preview/commit shape: conflicting rows are
 * reported and auto-skipped (they could not be force-included anyway — the
 * DB's EXCLUDE constraint would reject them); the clean subset is created in
 * one transaction by `ShowtimeService::copyWeek`.
 *
 * @property Schema $form
 */
class CopyWeekShowtimes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ShowtimeResource::class;

    protected string $view = 'filament.resources.showtime-resource.pages.copy-week';

    protected static ?string $title = 'Copy week';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Populated by submit(). Structure:
     * [
     *   'creatable' => [{label, movie_id, auditorium_id, start_iso, price_*}, ...],
     *   'conflicting' => [{label, conflicts: [string, ...]}, ...],
     *   'skipped_missing_runtime' => int,
     * ]
     *
     * @var array<string, mixed>
     */
    public array $preview = [];

    public function mount(): void
    {
        abort_unless(auth('admin')->user()?->can('showtimes.create') ?? false, 403);

        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Weeks')
                    ->description('The whole Monday-to-Sunday week containing each date is used.')
                    ->schema([
                        DatePicker::make('source_week')
                            ->label('A date in the source week')
                            ->required()
                            ->live(),
                        DatePicker::make('target_week')
                            ->label('A date in the target week')
                            ->required()
                            ->minDate(today())
                            ->live(),
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => Location::orderBy('name')->pluck('name', 'id'))
                            ->placeholder('All locations'),
                    ])
                    ->columns(3),
            ]);
    }

    /** Phase 1 — build the plan, split copyable vs conflicting. */
    public function submit(): void
    {
        $data = $this->form->getState();

        $sourceWeek = Carbon::parse($data['source_week'])->startOfWeek(CarbonInterface::MONDAY);
        $targetWeek = Carbon::parse($data['target_week'])->startOfWeek(CarbonInterface::MONDAY);

        if ($sourceWeek->equalTo($targetWeek)) {
            Notification::make()
                ->warning()
                ->title('Source and target are the same week')
                ->send();

            return;
        }

        $plan = app(ShowtimeService::class)->buildWeekCopyPlan(
            $sourceWeek,
            $targetWeek,
            $data['location_id'] ?? null,
        );

        if ($plan['rows'] === [] && $plan['skipped_missing_runtime']->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nothing to copy')
                ->body('The source week has no scheduled showtimes for the chosen scope.')
                ->send();

            return;
        }

        $creatable = [];
        $conflicting = [];

        foreach ($plan['rows'] as $row) {
            $sourceShowtime = $row['source'];
            $tz = $sourceShowtime->auditorium->location->timezone ?? config('app.timezone');
            $label = sprintf(
                '%s — %s @ %s',
                $sourceShowtime->movie->title,
                $sourceShowtime->auditorium->name,
                $row['start']->copy()->setTimezone($tz)->format('D M j G:i'),
            );

            if ($row['conflicts']->isEmpty()) {
                $creatable[] = [
                    'label' => $label,
                    'movie_id' => $sourceShowtime->movie_id,
                    'auditorium_id' => $sourceShowtime->auditorium_id,
                    'start_iso' => $row['start']->toIso8601String(),
                    'price_standard' => $sourceShowtime->price_standard,
                    'price_premium' => $sourceShowtime->price_premium,
                    'price_accessible' => $sourceShowtime->price_accessible,
                ];
            } else {
                $conflicting[] = [
                    'label' => $label,
                    'conflicts' => $row['conflicts']
                        ->map(fn (Showtime $s) => $s->movie->title.' @ '.$s->start_time->format('M j g:i A'))
                        ->all(),
                ];
            }
        }

        $this->preview = [
            'creatable' => $creatable,
            'conflicting' => $conflicting,
            'skipped_missing_runtime' => $plan['skipped_missing_runtime']->count(),
        ];

        if (empty($conflicting)) {
            $this->commit();
        }
    }

    /** Phase 2 — create the clean subset in one transaction. */
    public function commit(): void
    {
        if (empty($this->preview['creatable'] ?? [])) {
            Notification::make()
                ->warning()
                ->title('Nothing to copy')
                ->send();

            return;
        }

        // Collection<T> is invariant in T — annotate to the service's exact
        // row shape (same workaround as BulkCreateShowtimes::parseStart).
        /** @var Collection<int, array{movie_id: int|string, auditorium_id: string, start_time: CarbonInterface, price_standard: int, price_premium: int, price_accessible: int}> $rows */
        $rows = collect($this->preview['creatable'])
            ->map(fn (array $row): array => [
                'movie_id' => (int) $row['movie_id'],
                'auditorium_id' => (string) $row['auditorium_id'],
                'start_time' => self::parseStart($row['start_iso']),
                'price_standard' => (int) $row['price_standard'],
                'price_premium' => (int) $row['price_premium'],
                'price_accessible' => (int) $row['price_accessible'],
            ])
            ->values();

        try {
            $created = app(ShowtimeService::class)->copyWeek($rows, auth('admin')->user());
        } catch (ShowtimeConflictException $e) {
            Notification::make()
                ->danger()
                ->title('Another admin scheduled a conflicting showtime while you were reviewing')
                ->body('Nothing was copied. Re-run the preview to see the current conflicts.')
                ->persistent()
                ->send();

            $this->preview = [];

            return;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Copy failed')
                ->body('No showtimes were created. Please try again.')
                ->persistent()
                ->send();

            return;
        }

        $skippedConflicts = count($this->preview['conflicting'] ?? []);
        $skippedRuntime = (int) ($this->preview['skipped_missing_runtime'] ?? 0);

        $suffix = '';
        if ($skippedConflicts > 0) {
            $suffix .= ", skipped {$skippedConflicts} conflict(s)";
        }
        if ($skippedRuntime > 0) {
            $suffix .= ", skipped {$skippedRuntime} with missing runtime";
        }

        Notification::make()
            ->success()
            ->title("Copied {$created->count()} showtime(s)".$suffix)
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
                ->label(fn () => 'Skip conflicts and copy '.count($this->preview['creatable'] ?? []))
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
        $runtimeCount = (int) ($this->preview['skipped_missing_runtime'] ?? 0);

        $html = '<div class="fi-section-content-ctn p-4 space-y-4">';
        $html .= "<p><strong>{$creatableCount}</strong> showtime(s) ready to copy. <strong>{$conflictingCount}</strong> skipped due to conflicts.</p>";

        if ($runtimeCount > 0) {
            $html .= "<p>{$runtimeCount} showtime(s) skipped because their movie no longer has a runtime.</p>";
        }

        if ($creatableCount > 0) {
            $html .= '<div><strong>Will copy:</strong><ul class="list-disc pl-6 mt-2">';
            foreach ($this->preview['creatable'] as $row) {
                $html .= '<li>'.e($row['label']).'</li>';
            }
            $html .= '</ul></div>';
        }

        if ($conflictingCount > 0) {
            $html .= '<div class="fi-in-danger"><strong>Skipped (conflicts):</strong><ul class="list-disc pl-6 mt-2">';
            foreach ($this->preview['conflicting'] as $row) {
                $conflictList = implode(', ', array_map(fn ($s) => e($s), $row['conflicts']));
                $html .= '<li>'.e($row['label'])." — conflicts with: {$conflictList}</li>";
            }
            $html .= '</ul></div>';
        }

        return new HtmlString($html);
    }

    /** Widens `Carbon::parse`'s concrete return to `CarbonInterface` for the service's row typing. */
    private static function parseStart(string $iso): CarbonInterface
    {
        return Carbon::parse($iso);
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title;
    }
}
