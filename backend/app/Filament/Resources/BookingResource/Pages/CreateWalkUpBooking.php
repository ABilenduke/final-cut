<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Enums\PaymentMethod;
use App\Exceptions\SeatConflictException;
use App\Exceptions\WalkUpBookingException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\ShowtimeResource\Pages\ShowtimeOccupancy;
use App\Models\Showtime;
use App\Services\WalkUpBookingService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Walk-up / POS sale (admin-v2 Plan 05): pick an upcoming showtime, click
 * available seats on the shared occupancy grid, record how payment was taken
 * at the counter. Mutation goes through WalkUpBookingService.
 *
 * @property Schema $form
 */
class CreateWalkUpBooking extends Page implements HasForms
{
    use FormatsCurrency;
    use InteractsWithForms;

    protected static string $resource = BookingResource::class;

    protected string $view = 'filament.resources.booking-resource.pages.create-walk-up-booking';

    protected static ?string $title = 'Walk-up sale';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, array<string, mixed>> Seat states from ShowtimeOccupancy::seatStatesFor(). */
    public array $seatStates = [];

    public int $seatsPerRow = 0;

    /** @var list<string> */
    public array $selectedSeatIds = [];

    public function mount(): void
    {
        abort_unless(auth('admin')->user()?->can('bookings.create_walkup') ?? false, 403);

        $this->form->fill([
            'payment_method' => PaymentMethod::Cash->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Showtime')
                    ->schema([
                        Select::make('showtime_id')
                            ->label('Showtime')
                            ->options(fn () => self::upcomingShowtimeOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadSeats()),
                    ]),

                Section::make('Payment')
                    ->schema([
                        Radio::make('payment_method')
                            ->label('Collected as')
                            ->options([
                                PaymentMethod::Cash->value => 'Cash',
                                PaymentMethod::PosCard->value => 'Card (terminal)',
                                PaymentMethod::Comp->value => 'Comp (no charge)',
                            ])
                            ->required()
                            ->inline(),
                        TextInput::make('guest_email')
                            ->label('Customer email (optional)')
                            ->email()
                            ->helperText('Lets the customer use the public booking lookup.'),
                        Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->columns(2),
            ]);
    }

    /** Triggered by the showtime select; also called directly by tests. */
    public function loadSeats(): void
    {
        $this->selectedSeatIds = [];

        $showtimeId = $this->data['showtime_id'] ?? null;
        if (! $showtimeId) {
            $this->seatStates = [];
            $this->seatsPerRow = 0;

            return;
        }

        $showtime = Showtime::with('auditorium')->find($showtimeId);
        if ($showtime === null) {
            $this->seatStates = [];

            return;
        }

        $derived = ShowtimeOccupancy::seatStatesFor($showtime);
        $this->seatStates = $derived['states'];
        $this->seatsPerRow = $derived['seatsPerRow'];
    }

    public function toggleSeat(string $seatId): void
    {
        $seat = $this->seatStates[$seatId] ?? null;
        if ($seat === null || $seat['state'] !== 'available') {
            return;
        }

        $index = array_search($seatId, $this->selectedSeatIds, true);
        if ($index !== false) {
            unset($this->selectedSeatIds[$index]);
            $this->selectedSeatIds = array_values($this->selectedSeatIds);
        } else {
            $this->selectedSeatIds[] = $seatId;
        }
    }

    public function totalCents(): int
    {
        return array_sum(array_map(
            fn (string $seatId): int => (int) ($this->seatStates[$seatId]['price'] ?? 0),
            $this->selectedSeatIds,
        ));
    }

    public function create(): void
    {
        $data = $this->form->getState();

        if ($this->selectedSeatIds === []) {
            Notification::make()
                ->warning()
                ->title('Pick at least one seat')
                ->send();

            return;
        }

        try {
            $booking = app(WalkUpBookingService::class)->create(
                (string) $data['showtime_id'],
                $this->selectedSeatIds,
                PaymentMethod::from($data['payment_method']),
                $data['guest_email'] ?: null,
                auth('admin')->user(),
                $data['notes'] ?: null,
            );
        } catch (SeatConflictException $e) {
            Notification::make()
                ->danger()
                ->title('Seats just sold')
                ->body('One or more selected seats were taken while you were choosing. The grid has been refreshed — please re-select.')
                ->send();

            $this->loadSeats();

            return;
        } catch (WalkUpBookingException|ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Cannot create walk-up sale')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Walk-up sale failed')
                ->body('Nothing was created. Please try again.')
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("Booking {$booking->confirmation_code} created")
            ->body('Payment recorded as '.$booking->payment_method->value.'.')
            ->send();

        $this->redirect(BookingResource::getUrl('view', ['record' => $booking->id]));
    }

    /** @return array<string, string> */
    private static function upcomingShowtimeOptions(): array
    {
        return Showtime::query()
            ->whereNull('cancelled_at')
            ->where('start_time', '>', now())
            ->where('start_time', '<', now()->addDays(7))
            ->with('movie', 'auditorium.location')
            ->orderBy('start_time')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Showtime $s) => [
                $s->id => sprintf(
                    '%s — %s · %s — %s',
                    $s->movie->title,
                    $s->auditorium->location->name,
                    $s->auditorium->name,
                    $s->start_time->format('D M j G:i'),
                ),
            ])
            ->all();
    }
}
