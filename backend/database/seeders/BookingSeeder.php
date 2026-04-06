<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SeatType;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\Showtime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BookingSeeder extends Seeder
{
    /**
     * Track used seats per showtime to prevent (showtime_id, seat_id) collisions.
     * Cancelled bookings are excluded since they don't block seat availability.
     *
     * @var array<string, array<string, true>>
     */
    private array $usedSeats = [];

    public function run(): void
    {
        $testUser = User::where('email', 'test@finalcut.test')->firstOrFail();
        $memberUser = User::where('email', 'member@finalcut.test')->firstOrFail();

        // Separate past and future showtimes (eager-load location for menu item scoping)
        $pastShowtimes = Showtime::with('auditorium.seats', 'auditorium.location.menuItems')
            ->where('start_time', '<', Carbon::now())
            ->orderBy('start_time')
            ->get();

        $futureShowtimes = Showtime::with('auditorium.seats', 'auditorium.location.menuItems')
            ->where('start_time', '>=', Carbon::now())
            ->orderBy('start_time')
            ->get();

        if ($pastShowtimes->isEmpty() || $futureShowtimes->isEmpty()) {
            $this->command?->warn('BookingSeeder: insufficient showtimes — skipping.');

            return;
        }

        // Preload confirmed occupied seats from the database so reruns
        // don't create duplicate seat assignments for the same showtime.
        $this->preloadOccupiedSeats();

        // ── Named booking plan ──────────────────────────────────────────
        // Each entry: [collection, index, user|guestEmail, seatCount, foodCount, status]
        $bookingPlan = [
            // Past bookings (completed)
            ['past', 0, $testUser, 2, 2, BookingStatus::Confirmed],
            ['past', 1, $testUser, 3, 1, BookingStatus::Confirmed],
            ['past', 2, $testUser, 2, 0, BookingStatus::Confirmed],
            // Future bookings (upcoming)
            ['future', 0, $testUser, 2, 0, BookingStatus::Confirmed],
            ['future', 1, $memberUser, 2, 1, BookingStatus::Confirmed],
            // Cancelled booking
            ['future', 2, $testUser, 2, 0, BookingStatus::Cancelled],
        ];

        foreach ($bookingPlan as [$pool, $index, $user, $seatCount, $foodCount, $status]) {
            $collection = $pool === 'past' ? $pastShowtimes : $futureShowtimes;
            $showtime = $collection->get($index);

            if (! $showtime) {
                $this->command?->warn("BookingSeeder: no {$pool} showtime at index {$index} — skipping.");

                continue;
            }

            $this->createBooking(
                showtime: $showtime,
                user: $user,
                seatCount: $seatCount,
                foodCount: $foodCount,
                status: $status,
            );
        }

        // ── Guest bookings (only if enough showtimes exist) ─────────────
        if ($pastShowtimes->count() > 3) {
            $this->createBooking(
                showtime: $pastShowtimes->get(3),
                guestEmail: 'guest@finalcut.test',
                seatCount: 2,
            );
        }

        if ($futureShowtimes->count() > 3) {
            $this->createBooking(
                showtime: $futureShowtimes->get(3),
                guestEmail: 'guest@finalcut.test',
                seatCount: 2,
            );
        }

        // ── Varied seat occupancy ───────────────────────────────────────
        // Use future showtimes at indices 4, 5, 6 (distinct from named bookings above)
        $occupancyTargets = [
            4 => 0.10,  // ~10% occupancy
            5 => 0.50,  // ~50% occupancy
            6 => 0.90,  // ~90% occupancy
        ];

        $fakeUsers = User::whereNotIn('email', [
            'test@finalcut.test',
            'member@finalcut.test',
        ])->take(10)->get();

        foreach ($occupancyTargets as $showtimeIndex => $targetRatio) {
            $showtime = $futureShowtimes->get($showtimeIndex);
            if (! $showtime || $fakeUsers->isEmpty()) {
                continue;
            }

            $this->fillOccupancy($showtime, $targetRatio, $fakeUsers);
        }
    }

    /**
     * Create a single booking with deterministic seat selection.
     */
    private function createBooking(
        Showtime $showtime,
        ?User $user = null,
        ?string $guestEmail = null,
        int $seatCount = 2,
        int $foodCount = 0,
        BookingStatus $status = BookingStatus::Confirmed,
    ): void {
        $availableSeats = $this->getAvailableSeats($showtime, $seatCount);

        if ($availableSeats->count() < $seatCount) {
            $this->command?->warn(
                "BookingSeeder: not enough seats for showtime {$showtime->id} (need {$seatCount}, got {$availableSeats->count()})"
            );

            return;
        }

        $seatTotal = $availableSeats->sum(fn ($seat) => match ($seat->type) {
            SeatType::Standard => $showtime->price_standard,
            SeatType::Premium => $showtime->price_premium,
            SeatType::Accessible => $showtime->price_accessible,
        });

        $foodTotal = 0;
        $resolvedFood = [];

        $locationMenuItems = $showtime->auditorium->location?->menuItems ?? collect();

        if ($foodCount > 0 && $locationMenuItems->isNotEmpty()) {
            $selectedFood = $locationMenuItems->take($foodCount);
            foreach ($selectedFood as $menuItem) {
                $qty = 1;
                $itemTotal = $menuItem->price * $qty;
                $foodTotal += $itemTotal;

                $resolvedFood[] = [
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $qty,
                    'unit_price' => $menuItem->price,
                    'total_price' => $itemTotal,
                ];
            }
        }

        $subtotal = $seatTotal + $foodTotal;

        $booking = Booking::create([
            'showtime_id' => $showtime->id,
            'user_id' => $user?->id,
            'guest_email' => $guestEmail,
            'status' => $status,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'payment_method' => PaymentMethod::Card,
            'stripe_payment_intent_id' => 'pi_seed_'.fake()->regexify('[a-zA-Z0-9]{20}'),
        ]);

        foreach ($availableSeats as $seat) {
            BookingSeat::create([
                'booking_id' => $booking->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seat->id,
                'section' => ucfirst($seat->type->value),
                'price' => match ($seat->type) {
                    SeatType::Standard => $showtime->price_standard,
                    SeatType::Premium => $showtime->price_premium,
                    SeatType::Accessible => $showtime->price_accessible,
                },
            ]);
        }

        // Track used seats (skip cancelled — they don't block availability)
        if ($status !== BookingStatus::Cancelled) {
            $this->markSeatsUsed($showtime->id, $availableSeats->pluck('id')->all());
        }

        foreach ($resolvedFood as $food) {
            BookingFoodItem::create(array_merge($food, [
                'booking_id' => $booking->id,
            ]));
        }
    }

    /**
     * Fill a showtime to the target occupancy with 2-seat bookings.
     * Stops when target is reached; creates a 1-seat booking for the remainder.
     */
    private function fillOccupancy(
        Showtime $showtime,
        float $targetRatio,
        Collection $users,
    ): void {
        $totalSeats = $showtime->auditorium->seats->count();
        $targetCount = (int) floor($totalSeats * $targetRatio);
        $seated = 0;
        $userIndex = 0;

        while ($seated < $targetCount) {
            $remaining = $targetCount - $seated;
            $batchSize = min(2, $remaining);

            $availableSeats = $this->getAvailableSeats($showtime, $batchSize);
            if ($availableSeats->count() < $batchSize) {
                break; // No more seats available
            }

            $user = $users->get($userIndex % $users->count());
            $userIndex++;

            $this->createBooking(
                showtime: $showtime,
                user: $user,
                seatCount: $batchSize,
                foodCount: ($userIndex % 3 === 0) ? 1 : 0,
            );

            $seated += $batchSize;
        }
    }

    /**
     * Preload confirmed occupied seats from existing booking_seats rows
     * so that reruns don't create duplicate seat assignments.
     */
    private function preloadOccupiedSeats(): void
    {
        $occupied = BookingSeat::query()
            ->join('bookings', 'booking_seats.booking_id', '=', 'bookings.id')
            ->where('bookings.status', BookingStatus::Confirmed)
            ->select('booking_seats.showtime_id', 'booking_seats.seat_id')
            ->get();

        foreach ($occupied as $row) {
            $this->markSeatsUsed($row->showtime_id, [$row->seat_id]);
        }
    }

    /**
     * Get available seats for a showtime, filtered by the $usedSeats map.
     * Returns seats deterministically (ordered by row then number).
     */
    private function getAvailableSeats(Showtime $showtime, int $count): Collection
    {
        $usedForShowtime = $this->usedSeats[$showtime->id] ?? [];

        return $showtime->auditorium->seats
            ->reject(fn ($seat) => isset($usedForShowtime[$seat->id]))
            ->sortBy(['row', 'number'])
            ->take($count)
            ->values();
    }

    /**
     * Mark seats as used in the tracking map.
     */
    private function markSeatsUsed(string $showtimeId, array $seatIds): void
    {
        if (! isset($this->usedSeats[$showtimeId])) {
            $this->usedSeats[$showtimeId] = [];
        }

        foreach ($seatIds as $seatId) {
            $this->usedSeats[$showtimeId][$seatId] = true;
        }
    }
}
