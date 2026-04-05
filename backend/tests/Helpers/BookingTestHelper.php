<?php

namespace Tests\Helpers;

use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\StripeService;

trait BookingTestHelper
{
    /**
     * Creates a Location, Movie, Auditorium, 5 Seats, and a Showtime.
     *
     * Seats: A1 (Standard), A2 (Standard), A3 (Standard), B1 (Premium), C1 (Accessible)
     * Default pricing: standard=1200, premium=1800, accessible=1000
     *
     * @param  array  $overrides  Merged into the Showtime factory definition.
     * @return array{location: Location, auditorium: Auditorium, seats: Seat[], showtime: Showtime, movie: Movie}
     */
    public function createShowtimeWithSeats(array $overrides = []): array
    {
        $location = Location::factory()->create();
        $movie = Movie::factory()->create();

        $auditorium = Auditorium::factory()->create([
            'location_id' => $location->id,
            'total_seats' => 5,
        ]);

        $seats = [
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => 'A',
                'number' => 1,
                'label' => 'A1',
                'type' => SeatType::Standard,
            ]),
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => 'A',
                'number' => 2,
                'label' => 'A2',
                'type' => SeatType::Standard,
            ]),
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => 'A',
                'number' => 3,
                'label' => 'A3',
                'type' => SeatType::Standard,
            ]),
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => 'B',
                'number' => 1,
                'label' => 'B1',
                'type' => SeatType::Premium,
            ]),
            Seat::factory()->create([
                'auditorium_id' => $auditorium->id,
                'row' => 'C',
                'number' => 1,
                'label' => 'C1',
                'type' => SeatType::Accessible,
            ]),
        ];

        $showtime = Showtime::factory()->create(array_merge([
            'movie_id' => $movie->id,
            'auditorium_id' => $auditorium->id,
            'price_standard' => 1200,
            'price_premium' => 1800,
            'price_accessible' => 1000,
        ], $overrides));

        return compact('location', 'auditorium', 'seats', 'showtime', 'movie');
    }

    /**
     * Build a location-scoped booking URL.
     */
    public function bookingUrl(Location $location, string $path = ''): string
    {
        return "/api/locations/{$location->slug}/bookings".($path ? "/{$path}" : '');
    }

    /**
     * Binds a FakeStripeService into the container and returns it.
     */
    public function fakeStripe(): FakeStripeService
    {
        $fake = new FakeStripeService;

        $this->app->instance(StripeService::class, $fake);

        return $fake;
    }
}
