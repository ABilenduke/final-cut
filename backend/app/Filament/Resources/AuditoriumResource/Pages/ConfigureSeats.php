<?php

namespace App\Filament\Resources\AuditoriumResource\Pages;

use App\Enums\SeatType;
use App\Exceptions\AuditoriumSeatRegenerationBlockedException;
use App\Filament\Resources\AuditoriumResource;
use App\Models\Auditorium;
use App\Services\AuditoriumService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * @property Schema $form injected by the InteractsWithForms trait.
 */
class ConfigureSeats extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = AuditoriumResource::class;

    protected string $view = 'filament.resources.auditorium-resource.pages.configure-seats';

    /** Public route-binding key. Stays a string so Livewire hydration never
     *  conflicts with a model-typed property (see VisualEditor for the same
     *  pattern). Access the resolved Auditorium via {@see getRecord()}. */
    public string $record;

    protected ?Auditorium $resolvedRecord = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(int|string $record): void
    {
        abort_unless(auth('admin')->user()?->can('seats.update') ?? false, 403);

        $this->record = (string) $record;
        // Touch the accessor so a bad id 404s immediately.
        $this->getRecord();

        $this->form->fill([
            'rows' => 10,
            'seats_per_row' => 12,
            'section_map' => [],
            'unavailable_seats' => [],
        ]);
    }

    public function getRecord(): Auditorium
    {
        return $this->resolvedRecord ??= Auditorium::findOrFail($this->record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Configure seats — '.$this->getRecord()->name;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Grid size')
                    ->schema([
                        TextInput::make('rows')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(26)
                            ->required()
                            ->helperText('Number of rows (e.g. 10 creates rows A–J). Max 26 (A–Z).'),
                        TextInput::make('seats_per_row')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Section assignment')
                    ->description('Map row letters (or ranges like "A-C") to sections. Rows you omit default to section=none, type=standard.')
                    ->schema([
                        Repeater::make('section_map')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('row_range')
                                    ->required()
                                    ->placeholder('A-B or C')
                                    ->helperText('Single letter ("A") or contiguous range ("A-C"). Uppercase A–Z only.')
                                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                        if (! self::isValidRowRange((string) $value)) {
                                            $fail('Use a single letter (A) or contiguous range (A-C). Uppercase A–Z only.');
                                        }
                                    }),
                                Select::make('section_id')
                                    ->label('Section')
                                    ->options(fn () => $this->getRecord()->sections->pluck('name', 'id'))
                                    ->required(),
                                Select::make('type')
                                    ->options(collect(SeatType::cases())
                                        ->mapWithKeys(fn (SeatType $t) => [$t->value => ucfirst($t->value)])
                                        ->all())
                                    ->required()
                                    ->default(SeatType::Standard->value),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add row mapping'),
                    ]),

                Section::make('Unavailable seats')
                    ->description('Aisles, gaps, permanently-out-of-service seats. IDs use the row+number format (A3, J11).')
                    ->schema([
                        TagsInput::make('unavailable_seats')
                            ->hiddenLabel()
                            ->placeholder('Type A3 then Enter, then A4, then J11')
                            ->separator(',')
                            ->helperText('Type individual tags (press Enter or comma to commit each one) OR paste a comma-separated list like "A3, A4, J11" — each token becomes its own tag.'),
                    ]),

                Placeholder::make('destructive_warning')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<div class="fi-in-warning">⚠️ <strong>Submitting this form will DELETE the existing seat layout and rebuild it.</strong> '
                        .'If anything fails mid-rebuild, the previous layout is kept — nothing is half-written.</div>'
                    ))
                    ->visible(fn () => $this->getRecord()->seats()->exists()),

                Placeholder::make('blocker_summary')
                    ->hiddenLabel()
                    ->content(fn () => new HtmlString($this->renderBlockerSummary()))
                    ->visible(fn () => $this->hasBlockers()),
            ]);
    }

    public function submit(): void
    {
        if ($this->hasBlockers()) {
            Notification::make()
                ->title('Seat regeneration refused')
                ->body('This auditorium has live showtimes, active bookings, or held seats. Resolve them first.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $config = $this->buildConfig($data);

        try {
            app(AuditoriumService::class)
                ->generateSeats($this->getRecord(), $config, auth('admin')->user());
        } catch (AuditoriumSeatRegenerationBlockedException $e) {
            Notification::make()
                ->title('Seat regeneration refused')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Regeneration failed')
                ->body('Your existing seat layout has not been changed.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Seat layout regenerated')
            ->body($this->getRecord()->refresh()->total_seats.' seats created.')
            ->success()
            ->send();

        $this->redirect(AuditoriumResource::getUrl('view', ['record' => $this->getRecord()]));
    }

    public function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Regenerate seats')
                ->submit('submit')
                ->disabled(fn () => $this->hasBlockers()),
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn () => AuditoriumResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{rows: int, seats_per_row: int, section_map: array<int, array{rows: array<int, string>, section_id: string, type: string}>, unavailable_seats: array<int, string>}
     */
    private function buildConfig(array $data): array
    {
        $sectionMap = [];
        foreach ($data['section_map'] ?? [] as $entry) {
            $sectionMap[] = [
                'rows' => self::expandRowRange($entry['row_range']),
                'section_id' => $entry['section_id'],
                'type' => $entry['type'],
            ];
        }

        return [
            'rows' => (int) $data['rows'],
            'seats_per_row' => (int) $data['seats_per_row'],
            'section_map' => $sectionMap,
            'unavailable_seats' => array_values(array_filter(array_map(
                'trim',
                is_array($data['unavailable_seats'] ?? null)
                    ? $data['unavailable_seats']
                    : explode(',', (string) ($data['unavailable_seats'] ?? '')),
            ))),
        ];
    }

    public static function isValidRowRange(string $range): bool
    {
        if (preg_match('/^[A-Z]$/', $range) === 1) {
            return true;
        }
        if (preg_match('/^([A-Z])-([A-Z])$/', $range, $m) === 1) {
            return ord($m[1]) <= ord($m[2]);
        }

        return false;
    }

    /** @return array<int, string> */
    public static function expandRowRange(string $range): array
    {
        if (! self::isValidRowRange($range)) {
            return [];
        }
        if (! str_contains($range, '-')) {
            return [$range];
        }
        [$start, $end] = explode('-', $range);
        $startIdx = ord($start) - ord('A');
        $endIdx = ord($end) - ord('A');

        return array_map(fn ($i) => chr(ord('A') + $i), range($startIdx, $endIdx));
    }

    /**
     * Per-request blocker cache. `visible()` / `disabled()` closures fire
     * several times during a single Filament render and each call otherwise
     * issues 3 count queries. Livewire rebuilds the component per request,
     * so no explicit invalidation is needed.
     *
     * @var array{future_showtimes: int, active_bookings: int, held_seats: int}|null
     */
    private ?array $blockersCache = null;

    /** @return array{future_showtimes: int, active_bookings: int, held_seats: int} */
    private function blockers(): array
    {
        return $this->blockersCache ??= app(AuditoriumService::class)
            ->getRegenerationBlockers($this->getRecord());
    }

    private function hasBlockers(): bool
    {
        return array_sum($this->blockers()) > 0;
    }

    private function renderBlockerSummary(): string
    {
        return '<div class="fi-in-danger"><strong>Regeneration blocked.</strong> '
            .'This auditorium has '.AuditoriumSeatRegenerationBlockedException::formatBlockers($this->blockers()).'. '
            .'Cancel or reschedule the affected showtimes or bookings before retrying.</div>';
    }
}
