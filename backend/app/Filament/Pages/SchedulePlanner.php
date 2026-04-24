<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowtimeResource;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Showtime;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Read-oriented weekly schedule grid — auditoriums as columns, days as rows.
 *
 * v1 scope: visualise the week, click-through to create or view. Drag-and-drop
 * rescheduling is explicitly deferred so we don't take on a JS dependency for
 * this first pass. Every mutation still routes through ShowtimeResource →
 * ShowtimeService (the same write boundary used by the MVP list/form), so the
 * planner is a navigation aid, not an alternate write path.
 *
 * URL query string carries week + location so the view is shareable.
 */
class SchedulePlanner extends Page
{
    protected string $view = 'filament.pages.schedule-planner';

    protected static ?string $slug = 'schedule';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    /** ISO date (Y-m-d) anchoring the visible week's Monday. */
    #[Url(as: 'week')]
    public ?string $weekStart = null;

    /** Selected location slug. Null = first location in alphabetical order. */
    #[Url(as: 'loc')]
    public ?string $locationSlug = null;

    public function getTitle(): string|Htmlable
    {
        return 'Schedule Planner';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('showtimes.view') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->weekStart === null) {
            $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        if ($this->locationSlug === null) {
            $first = Location::orderBy('name')->first();
            $this->locationSlug = $first?->slug;
        }
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function setLocation(string $slug): void
    {
        $this->locationSlug = $slug;
    }

    /** @return array<int, Location> */
    public function getLocations(): array
    {
        return Location::orderBy('name')->get()->all();
    }

    public function getActiveLocation(): ?Location
    {
        if ($this->locationSlug === null) {
            return null;
        }

        return Location::where('slug', $this->locationSlug)->first();
    }

    /**
     * @return Collection<int, Auditorium> Auditoriums for the active location,
     *                                     preloaded with the week's showtimes.
     */
    public function getAuditoriumsForWeek(): Collection
    {
        $location = $this->getActiveLocation();

        if ($location === null) {
            return new Collection;
        }

        $weekStart = Carbon::parse($this->weekStart)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(7)->startOfDay();

        return Auditorium::where('location_id', $location->id)
            ->orderBy('name')
            ->with([
                // Eager-load only the live showtimes overlapping the visible week.
                // Cancelled showtimes are intentionally hidden — the DB's EXCLUDE
                // constraint treats them as released slots.
                'showtimes' => fn ($q) => $q
                    ->whereNull('cancelled_at')
                    ->where('start_time', '<', $weekEnd)
                    ->where('end_time', '>', $weekStart)
                    ->with('movie')
                    ->orderBy('start_time'),
            ])
            ->get();
    }

    /**
     * @return array<int, array{label: string, iso: string, dayOfWeek: string}>
     *                                                                          7 days of the visible week, for rendering row headers.
     */
    public function getWeekDays(): array
    {
        $start = Carbon::parse($this->weekStart);

        return collect(range(0, 6))->map(function (int $offset) use ($start) {
            $day = $start->copy()->addDays($offset);

            return [
                'label' => $day->format('D M j'),
                'iso' => $day->toDateString(),
                'dayOfWeek' => $day->format('l'),
            ];
        })->all();
    }

    /**
     * Filter a collection of showtimes down to just the ones whose start date
     * matches the given ISO date. Used by the blade template for per-cell
     * rendering — avoids an N+1 of per-cell DB calls.
     *
     * @param  Collection<int, Showtime>  $showtimes
     * @return Collection<int, Showtime>
     */
    public function showtimesForDay(Collection $showtimes, string $isoDate): Collection
    {
        return $showtimes->filter(
            fn (Showtime $s) => $s->start_time->format('Y-m-d') === $isoDate
        )->values();
    }

    public function createShowtimeUrl(string $auditoriumId, string $isoDate): string
    {
        // The ShowtimeResource create page doesn't natively accept query-string
        // defaults, but linking with these params keeps the planner intent
        // legible for a future enhancement that pre-fills the form.
        return ShowtimeResource::getUrl('create', [
            'auditorium_id' => $auditoriumId,
            'date' => $isoDate,
        ]);
    }

    public function viewShowtimeUrl(Showtime $showtime): string
    {
        return ShowtimeResource::getUrl('view', ['record' => $showtime]);
    }

    public function canCreate(): bool
    {
        return auth('admin')->user()?->can('showtimes.create') ?? false;
    }
}
