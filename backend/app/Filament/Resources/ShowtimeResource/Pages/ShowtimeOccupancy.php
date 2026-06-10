<?php

namespace App\Filament\Resources\ShowtimeResource\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\ShowtimeResource;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Showtime;
use App\Services\SeatAvailabilityService;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only seat-occupancy map for a single showtime (admin-v2 Plan 04).
 *
 * Seat state is derived from the occupancy guard's own data —
 * `booking_seats.occupies_seat` — so this page can never disagree with what
 * the checkout flow or the refund service consider "taken". Follows the
 * VisualEditor custom-page precedent; state lives in public Livewire
 * properties so tests assert data instead of scraping markup.
 */
class ShowtimeOccupancy extends Page
{
    protected static string $resource = ShowtimeResource::class;

    protected string $view = 'filament.resources.showtime-resource.pages.occupancy';

    /** Route-binding key (string, same convention as VisualEditor). */
    public string $record;

    protected ?Showtime $resolvedRecord = null;

    /**
     * Seat states keyed by seat id:
     * `['label' => 'A1', 'row' => 'A', 'number' => 1, 'state' => 'sold|held|refund_pending|unavailable|available', 'confirmation_code' => ?string]`.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $seatStates = [];

    /** @var array{capacity: int, sold: int, held: int, refund_pending: int, unavailable: int, available: int} */
    public array $counts = [
        'capacity' => 0,
        'sold' => 0,
        'held' => 0,
        'refund_pending' => 0,
        'unavailable' => 0,
        'available' => 0,
    ];

    public int $seatsPerRow = 0;

    public function mount(int|string $record): void
    {
        abort_unless(auth('admin')->user()?->can('showtimes.view') ?? false, 403);

        $this->record = (string) $record;
        $this->getRecord(); // 404 on bad id early.
        $this->loadState();
    }

    public function getRecord(): Showtime
    {
        return $this->resolvedRecord ??= Showtime::with('movie', 'auditorium.location')
            ->findOrFail($this->record);
    }

    public function getTitle(): string|Htmlable
    {
        $showtime = $this->getRecord();

        return sprintf(
            'Occupancy — %s, %s',
            $showtime->movie->title,
            $showtime->start_time->format('M j G:i'),
        );
    }

    /** Deep link to the bookings list pre-filtered to this showtime. */
    public function bookingsUrl(): string
    {
        return BookingResource::getUrl('index', [
            'tableFilters' => [
                'showtime_id' => ['value' => $this->getRecord()->id],
            ],
        ]);
    }

    protected function loadState(): void
    {
        $derived = self::seatStatesFor($this->getRecord());

        $this->seatStates = $derived['states'];
        $this->counts = $derived['counts'];
        $this->seatsPerRow = $derived['seatsPerRow'];
    }

    /**
     * Shared seat-state derivation — used by this page and by the walk-up
     * sale's seat picker (admin-v2 Plan 05) so both surfaces can never
     * disagree about what is takeable.
     *
     * @return array{states: array<string, array<string, mixed>>, counts: array<string, int>, seatsPerRow: int}
     */
    public static function seatStatesFor(Showtime $showtime): array
    {
        // The occupancy guard keeps occupies_seat in lockstep with the parent
        // booking's status — one row per (showtime, seat) can be true at most.
        $occupants = BookingSeat::query()
            ->with('booking')
            ->where('showtime_id', $showtime->id)
            ->where('occupies_seat', true)
            ->whereNotNull('seat_id')
            ->get()
            ->keyBy('seat_id');

        $seats = $showtime->auditorium->seats()
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        // Capacity means SELLABLE capacity — the same semantics as
        // auditorium.total_seats (which AuditoriumService recomputes
        // excluding unavailable_at seats). Admin-blocked seats render on
        // the grid and get their own count, but never inflate the
        // "occupied / capacity" denominator.
        $counts = [
            'capacity' => $seats->filter(fn ($seat) => $seat->unavailable_at === null)->count(),
            'sold' => 0,
            'held' => 0,
            'refund_pending' => 0,
            'unavailable' => 0,
            'available' => 0,
        ];

        $states = [];
        foreach ($seats as $seat) {
            $occupant = $occupants->get($seat->id);
            /** @var Booking|null $occupantBooking */
            $occupantBooking = $occupant?->booking;

            if ($occupantBooking !== null) {
                $state = match ($occupantBooking->status) {
                    BookingStatus::Held => 'held',
                    BookingStatus::RefundPending => 'refund_pending',
                    default => 'sold',
                };
            } elseif ($seat->unavailable_at !== null) {
                $state = 'unavailable';
            } else {
                $state = 'available';
            }

            $counts[$state]++;

            $states[$seat->id] = [
                'label' => $seat->label,
                'row' => $seat->row,
                'number' => $seat->number,
                'state' => $state,
                'confirmation_code' => $occupantBooking?->confirmation_code,
                // Section-multiplied price — the walk-up picker's running
                // total must match what reserveSeats() will charge.
                'price' => SeatAvailabilityService::priceForSeat($showtime, $seat),
            ];
        }

        return [
            'states' => $states,
            'counts' => $counts,
            'seatsPerRow' => (int) ($seats->max('number') ?? 0),
        ];
    }
}
