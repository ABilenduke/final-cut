<?php

use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| bookings.showtime_id ON DELETE behaviour (ultrareview P0 #5)
|--------------------------------------------------------------------------
| The FK was cascadeOnDelete, so physically deleting a Showtime would silently
| destroy every Booking tied to it — stripe_payment_intent_id, confirmation_code
| and totals included. There is no admin UI delete path, but a raw delete (a
| future code path, a console command, or a cascade) must not be able to erase
| financial records. restrictOnDelete makes the database refuse it.
*/

beforeEach(function (): void {
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    $movie = Movie::factory()->create(['runtime' => 120]);
    $this->showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);
});

test('a showtime with a booking cannot be physically deleted — financial records survive', function (): void {
    $booking = Booking::factory()->create(['showtime_id' => $this->showtime->id]);

    // Wrap in a nested transaction so the FK violation rolls back to a savepoint
    // and leaves the surrounding RefreshDatabase transaction usable for the
    // post-condition assertions (Postgres aborts the whole tx on a raw error).
    expect(fn () => DB::transaction(fn () => $this->showtime->delete()))
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    $this->assertDatabaseHas('showtimes', ['id' => $this->showtime->id]);
});

test('a booking-free showtime can still be deleted', function (): void {
    $id = $this->showtime->id;

    $this->showtime->delete();

    $this->assertDatabaseMissing('showtimes', ['id' => $id]);
});
