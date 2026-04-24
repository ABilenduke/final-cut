<?php

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Exceptions\SeatConflictException;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\SeatAvailabilityService;
use Illuminate\Validation\ValidationException;

function makeShowtimeFixture(array $showtimeOverrides = []): array
{
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    $movie = Movie::factory()->create();
    $showtime = Showtime::factory()->create(array_merge([
        'auditorium_id' => $auditorium->id,
        'movie_id' => $movie->id,
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ], $showtimeOverrides));

    return compact('location', 'auditorium', 'movie', 'showtime');
}

test('checkAvailability returns empty array when all seats available', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seat = Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'type' => SeatType::Standard,
    ]);

    $service = app(SeatAvailabilityService::class);
    $result = $service->checkAvailability($showtime->id, [$seat->id]);

    expect($result)->toBeEmpty();
});

test('checkAvailability returns taken seat IDs for confirmed bookings', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seatA = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);
    $seatB = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);

    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);

    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seatA->id,
    ]);

    $service = app(SeatAvailabilityService::class);
    $result = $service->checkAvailability($showtime->id, [$seatA->id, $seatB->id]);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe($seatA->id);
});

test('checkAvailability ignores cancelled bookings', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);

    $booking = Booking::factory()->cancelled()->create([
        'showtime_id' => $showtime->id,
    ]);

    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $service = app(SeatAvailabilityService::class);
    $result = $service->checkAvailability($showtime->id, [$seat->id]);

    expect($result)->toBeEmpty();
});

test('checkAvailability flags seats held by Held and RefundPending bookings', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $heldSeat = Seat::factory()->create(['auditorium_id' => $auditorium->id]);
    $refundPendingSeat = Seat::factory()->create(['auditorium_id' => $auditorium->id]);

    $heldBooking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::Held]);
    BookingSeat::factory()->create([
        'booking_id' => $heldBooking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $heldSeat->id,
    ]);

    $refundBooking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::RefundPending]);
    BookingSeat::factory()->create([
        'booking_id' => $refundBooking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $refundPendingSeat->id,
    ]);

    $service = app(SeatAvailabilityService::class);
    $result = $service->checkAvailability($showtime->id, [$heldSeat->id, $refundPendingSeat->id]);

    expect($result)->toHaveCount(2);
    expect($result)->toContain($heldSeat->id);
    expect($result)->toContain($refundPendingSeat->id);
});

test('reserveSeats creates BookingSeat records with correct prices', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $standardSeat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);
    $premiumSeat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Premium]);
    $accessibleSeat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Accessible]);

    $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);

    $service = app(SeatAvailabilityService::class);
    $service->reserveSeats($showtime, [$standardSeat->id, $premiumSeat->id, $accessibleSeat->id], $booking);

    expect(BookingSeat::where('booking_id', $booking->id)->count())->toBe(3);

    $standardRecord = BookingSeat::where('booking_id', $booking->id)->where('seat_id', $standardSeat->id)->first();
    expect($standardRecord->price)->toBe(1200);

    $premiumRecord = BookingSeat::where('booking_id', $booking->id)->where('seat_id', $premiumSeat->id)->first();
    expect($premiumRecord->price)->toBe(1800);

    $accessibleRecord = BookingSeat::where('booking_id', $booking->id)->where('seat_id', $accessibleSeat->id)->first();
    expect($accessibleRecord->price)->toBe(1000);
});

test('reserveSeats returns total seat cost', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seat1 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);
    $seat2 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Premium]);

    $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);

    $service = app(SeatAvailabilityService::class);
    $total = $service->reserveSeats($showtime, [$seat1->id, $seat2->id], $booking);

    expect($total)->toBe(3000);
});

test('reserveSeats throws SeatConflictException for taken seats', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);

    $firstBooking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $firstBooking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $secondBooking = Booking::factory()->create(['showtime_id' => $showtime->id]);
    $service = app(SeatAvailabilityService::class);

    expect(fn () => $service->reserveSeats($showtime, [$seat->id], $secondBooking))
        ->toThrow(SeatConflictException::class);
});

test('reserveSeats deduplicates seat IDs and charges only once per seat', function () {
    ['auditorium' => $auditorium, 'showtime' => $showtime] = makeShowtimeFixture();

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'type' => SeatType::Standard]);

    $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);

    $service = app(SeatAvailabilityService::class);
    $total = $service->reserveSeats($showtime, [$seat->id, $seat->id], $booking);

    // Should only create one BookingSeat record despite duplicate input
    expect(BookingSeat::where('booking_id', $booking->id)->count())->toBe(1);
    expect($total)->toBe(1200); // Not 2400
});

test('reserveSeats validates seats belong to showtime auditorium', function () {
    ['showtime' => $showtime] = makeShowtimeFixture();

    $otherLocation = Location::factory()->create();
    $otherAuditorium = Auditorium::factory()->create(['location_id' => $otherLocation->id]);
    $foreignSeat = Seat::factory()->create([
        'auditorium_id' => $otherAuditorium->id,
        'type' => SeatType::Standard,
    ]);

    $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);
    $service = app(SeatAvailabilityService::class);

    expect(fn () => $service->reserveSeats($showtime, [$foreignSeat->id], $booking))
        ->toThrow(ValidationException::class);
});
