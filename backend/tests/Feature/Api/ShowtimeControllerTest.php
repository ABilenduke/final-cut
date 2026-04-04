<?php

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;

use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| GET /api/showtimes/{id}
|--------------------------------------------------------------------------
*/

test('returns showtime with auditorium and seat map', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['name' => 'Screen 1', 'total_seats' => 2]);

    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1', 'type' => SeatType::Standard]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 2, 'label' => 'A2', 'type' => SeatType::Standard]);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    getJson("/api/showtimes/{$showtime->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'showtime' => ['id', 'movieId', 'movieSlug', 'movieTitle', 'screenId', 'screenName',
                    'startTime', 'endTime', 'priceStandard', 'pricePremium', 'priceAccessible'],
                'auditorium' => ['id', 'name', 'totalSeats', 'rows', 'sections'],
                'seats' => [
                    '*' => ['id', 'row', 'number', 'label', 'type', 'status', 'price'],
                ],
            ],
        ])
        ->assertJsonPath('data.auditorium.name', 'Screen 1')
        ->assertJsonCount(2, 'data.seats');
});

test('all seats are available when no bookings exist', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 2]);

    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1']);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 2, 'label' => 'A2']);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    $response = getJson("/api/showtimes/{$showtime->id}")->assertOk();

    $seats = $response->json('data.seats');
    expect($seats)->each(fn ($seat) => $seat->toHaveKey('status', 'available'));
});

test('seats are marked taken when a confirmed booking exists', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 2]);

    $seatA1 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1']);
    $seatA2 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 2, 'label' => 'A2']);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    // Book seat A1
    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seatA1->id,
    ]);

    $response = getJson("/api/showtimes/{$showtime->id}")->assertOk();

    $seats = collect($response->json('data.seats'));
    $a1 = $seats->firstWhere('label', 'A1');
    $a2 = $seats->firstWhere('label', 'A2');

    expect($a1['status'])->toBe('taken');
    expect($a2['status'])->toBe('available');
});

test('cancelled bookings do NOT mark seats as taken', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 1]);

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1']);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    $booking = Booking::factory()->cancelled()->create([
        'showtime_id' => $showtime->id,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $response = getJson("/api/showtimes/{$showtime->id}")->assertOk();

    $seats = collect($response->json('data.seats'));
    expect($seats->first()['status'])->toBe('available');
});

test('bookings for a different showtime do NOT affect availability', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 1]);

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1']);

    $showtime1 = Showtime::factory()->create(['movie_id' => $movie->id, 'auditorium_id' => $auditorium->id]);
    $showtime2 = Showtime::factory()->create(['movie_id' => $movie->id, 'auditorium_id' => $auditorium->id]);

    // Book seat for showtime2
    $booking = Booking::factory()->create(['showtime_id' => $showtime2->id]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime2->id,
        'seat_id' => $seat->id,
    ]);

    // Showtime1 should still have the seat available
    $response = getJson("/api/showtimes/{$showtime1->id}")->assertOk();

    $seats = collect($response->json('data.seats'));
    expect($seats->first()['status'])->toBe('available');
});

test('seat price is computed from seat type and showtime pricing', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 3]);

    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1', 'type' => SeatType::Standard]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'B', 'number' => 1, 'label' => 'B1', 'type' => SeatType::Premium]);
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'C', 'number' => 1, 'label' => 'C1', 'type' => SeatType::Accessible]);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);

    $response = getJson("/api/showtimes/{$showtime->id}")->assertOk();

    $seats = collect($response->json('data.seats'));

    expect($seats->firstWhere('label', 'A1')['price'])->toBe(1200);
    expect($seats->firstWhere('label', 'B1')['price'])->toBe(1800);
    expect($seats->firstWhere('label', 'C1')['price'])->toBe(1000);
});

test('returns 404 for non-existent showtime', function () {
    getJson('/api/showtimes/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

test('seat map handles large auditorium efficiently', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 300]);

    // Create 300 seats (15 rows x 20 seats)
    foreach (range('A', 'O') as $row) {
        for ($num = 1; $num <= 20; $num++) {
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => $row,
                'number' => $num,
                'label' => "{$row}{$num}",
                'type' => SeatType::Standard,
            ]);
        }
    }

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    // Book 50 seats
    for ($i = 0; $i < 50; $i++) {
        $seat = $auditorium->seats()->skip($i)->first();
        $booking = Booking::factory()->create(['showtime_id' => $showtime->id]);
        BookingSeat::factory()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
        ]);
    }

    $response = getJson("/api/showtimes/{$showtime->id}")
        ->assertOk()
        ->assertJsonCount(300, 'data.seats');

    $seats = collect($response->json('data.seats'));
    expect($seats->where('status', 'taken')->count())->toBe(50);
    expect($seats->where('status', 'available')->count())->toBe(250);
});

test('cancelled booking seat can be rebooked by another booking', function () {
    $movie = Movie::factory()->create();
    $auditorium = Auditorium::factory()->create(['total_seats' => 1]);

    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'row' => 'A', 'number' => 1, 'label' => 'A1']);

    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    // First booking — cancelled
    $cancelledBooking = Booking::factory()->cancelled()->create([
        'showtime_id' => $showtime->id,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $cancelledBooking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    // Second booking — confirmed, same seat (should NOT violate constraint)
    $confirmedBooking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $confirmedBooking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $response = getJson("/api/showtimes/{$showtime->id}")->assertOk();

    $seats = collect($response->json('data.seats'));
    expect($seats->first()['status'])->toBe('taken');
});
