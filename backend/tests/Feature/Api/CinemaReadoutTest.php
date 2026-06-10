<?php

use App\Models\Booking;
use App\Models\Showtime;
use App\Services\SeatAvailabilityService;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\getJson;

uses(BookingTestHelper::class);

function readoutTz(): string
{
    $tz = config('app.default_location_timezone');

    return is_string($tz) && trim($tz) !== '' ? $tz : config('app.timezone');
}

test('the readout derives screenings, late showing, and seats left from real data', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $auditorium = $fixture['auditorium'];
    $seatCount = count($fixture['seats']);

    // Two screenings today (one early, one late), one tomorrow, one cancelled.
    $early = $fixture['showtime'];
    $early->update(['start_time' => now(readoutTz())->setTime(14, 0), 'end_time' => now(readoutTz())->setTime(16, 0)]);

    $late = Showtime::factory()->create([
        'movie_id' => $fixture['movie']->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now(readoutTz())->setTime(22, 30),
        'end_time' => now(readoutTz())->setTime(23, 59),
    ]);
    Showtime::factory()->create([
        'movie_id' => $fixture['movie']->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now(readoutTz())->addDay()->setTime(20, 0),
        'end_time' => now(readoutTz())->addDay()->setTime(22, 0),
    ]);
    Showtime::factory()->create([
        'movie_id' => $fixture['movie']->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => now(readoutTz())->setTime(20, 0),
        'end_time' => now(readoutTz())->setTime(21, 0),
        'cancelled_at' => now(),
    ]);

    // One occupied seat on the late showing.
    $booking = Booking::factory()->create(['showtime_id' => $late->id]);
    app(SeatAvailabilityService::class)
        ->reserveSeats($late->fresh(), [$fixture['seats'][0]->id], $booking);

    $response = getJson('/api/cinema-readout')->assertOk();

    expect($response->json('data.screeningsToday'))->toBe(2)
        ->and($response->json('data.lateShowing.time'))->toBe('22:30')
        ->and($response->json('data.lateShowing.auditorium'))->toBe($auditorium->name);

    // Seats left covers today's full slate (deterministic regardless of the
    // test's wall-clock time): 2 screenings x capacity, minus the 1 occupied.
    expect($response->json('data.seatsLeftTonight'))->toBe(2 * $seatCount - 1);
});

test('the readout caches for five minutes', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $fixture['showtime']->update([
        'start_time' => now(readoutTz())->setTime(15, 0),
        'end_time' => now(readoutTz())->setTime(17, 0),
    ]);

    getJson('/api/cinema-readout')->assertJsonPath('data.screeningsToday', 1);

    Showtime::factory()->create([
        'movie_id' => $fixture['movie']->id,
        'auditorium_id' => $fixture['auditorium']->id,
        'start_time' => now(readoutTz())->setTime(18, 0),
        'end_time' => now(readoutTz())->setTime(20, 0),
    ]);

    getJson('/api/cinema-readout')->assertJsonPath('data.screeningsToday', 1);
});

test('an empty schedule yields zero screenings and null derived stats', function (): void {
    getJson('/api/cinema-readout')
        ->assertOk()
        ->assertJsonPath('data.screeningsToday', 0)
        ->assertJsonPath('data.lateShowing', null)
        ->assertJsonPath('data.seatsLeftTonight', null);
});
