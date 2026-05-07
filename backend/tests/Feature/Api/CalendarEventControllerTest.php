<?php

use App\Enums\BookingStatus;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\CalendarEvent;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('returns events for requested month/year', function () {
    CalendarEvent::factory()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);

    CalendarEvent::factory()->create([
        'date' => '2026-07-10',
        'start_time' => '2026-07-10 19:00:00',
        'end_time' => '2026-07-10 21:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('defaults to current month when no params', function () {
    CalendarEvent::factory()->create([
        'date' => now()->startOfMonth()->addDays(5),
        'start_time' => now()->startOfMonth()->addDays(5)->setHour(19),
        'end_time' => now()->startOfMonth()->addDays(5)->setHour(21),
    ]);

    CalendarEvent::factory()->create([
        'date' => now()->addMonths(2),
        'start_time' => now()->addMonths(2)->setHour(19),
        'end_time' => now()->addMonths(2)->setHour(21),
    ]);

    getJson('/api/calendar/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('filters by event type', function () {
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
    ]);

    CalendarEvent::factory()->loyaltyExclusive()->create([
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 19:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026&type=special_event')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'special_event');
});

test('filters by single accessibility tag', function () {
    CalendarEvent::factory()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'accessibility_tags' => ['sensory_friendly'],
    ]);

    CalendarEvent::factory()->create([
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 19:00:00',
        'accessibility_tags' => [],
    ]);

    getJson('/api/calendar/events?month=6&year=2026&accessibility=sensory_friendly')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('filters by multiple accessibility tags with OR logic', function () {
    CalendarEvent::factory()->create([
        'date' => '2026-06-10',
        'start_time' => '2026-06-10 19:00:00',
        'accessibility_tags' => ['sensory_friendly'],
    ]);

    CalendarEvent::factory()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'accessibility_tags' => ['open_caption'],
    ]);

    CalendarEvent::factory()->create([
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 19:00:00',
        'accessibility_tags' => [],
    ]);

    getJson('/api/calendar/events?month=6&year=2026&accessibility=sensory_friendly,open_caption')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('combines type and accessibility filters', function () {
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-10',
        'start_time' => '2026-06-10 19:00:00',
        'accessibility_tags' => ['sensory_friendly'],
    ]);

    CalendarEvent::factory()->loyaltyExclusive()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'accessibility_tags' => ['sensory_friendly'],
    ]);

    getJson('/api/calendar/events?month=6&year=2026&type=special_event&accessibility=sensory_friendly')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'special_event');
});

test('returns empty array when no events match', function () {
    getJson('/api/calendar/events?month=1&year=2020')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('orders events by date and start_time', function () {
    CalendarEvent::factory()->create([
        'title' => 'Later Event',
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 14:00:00',
    ]);

    CalendarEvent::factory()->create([
        'title' => 'Early Event',
        'date' => '2026-06-10',
        'start_time' => '2026-06-10 19:00:00',
    ]);

    CalendarEvent::factory()->create([
        'title' => 'Same Day Earlier',
        'date' => '2026-06-10',
        'start_time' => '2026-06-10 14:00:00',
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['Same Day Earlier', 'Early Event', 'Later Event']);
});

test('resource includes all expected fields', function () {
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
        'accessibility_tags' => ['sensory_friendly'],
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'type',
                    'title',
                    'date',
                    'startTime',
                    'endTime',
                    'description',
                    'movieSlug',
                    'imageUrl',
                    'slug',
                    'ticketUrl',
                    'loyaltyOnly',
                    'accessibilityTags',
                ],
            ],
        ]);
});

test('returns event detail by slug', function () {
    $event = CalendarEvent::factory()->specialEvent()->create([
        'slug' => 'opening-night-gala',
        'title' => 'Opening Night Gala',
    ]);

    getJson('/api/calendar/events/opening-night-gala')
        ->assertOk()
        ->assertJsonPath('data.slug', 'opening-night-gala')
        ->assertJsonPath('data.title', 'Opening Night Gala');
});

test('returns 404 for invalid slug', function () {
    getJson('/api/calendar/events/nonexistent-event')
        ->assertNotFound();
});

test('imageUrl is null when image_path is null', function () {
    $event = CalendarEvent::factory()->specialEvent()->create([
        'slug' => 'no-image-event',
        'image_path' => null,
    ]);

    getJson('/api/calendar/events/no-image-event')
        ->assertOk()
        ->assertJsonPath('data.imageUrl', null);
});

test('imageUrl is derived from image_path via the public disk url', function () {
    $event = CalendarEvent::factory()->specialEvent()->create([
        'slug' => 'with-image-event',
        'image_path' => 'calendar-events/sample.jpg',
    ]);

    $expectedUrl = Storage::disk('public')->url('calendar-events/sample.jpg');

    getJson('/api/calendar/events/with-image-event')
        ->assertOk()
        ->assertJsonPath('data.imageUrl', $expectedUrl);

    // The wire field name is `imageUrl`, not the underlying column
    // (`image_path`) — the customer contract is what the Nuxt app reads.
    expect($expectedUrl)->toContain('calendar-events/sample.jpg');
});

/*
|--------------------------------------------------------------------------
| GET /api/calendar/events?location={slug} — location filter
|--------------------------------------------------------------------------
*/

test('GET /api/calendar/events?location= returns events scoped to that location', function () {
    $downtown = Location::factory()->create(['slug' => 'downtown-cal-test']);
    $uptown = Location::factory()->create(['slug' => 'uptown-cal-test']);

    // Event scoped to downtown
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'location_id' => $downtown->id,
    ]);

    // Event scoped to uptown (should be excluded when filtering for downtown)
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-16',
        'start_time' => '2026-06-16 19:00:00',
        'location_id' => $uptown->id,
    ]);

    getJson("/api/calendar/events?month=6&year=2026&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('GET /api/calendar/events?location= includes venue-agnostic events (location_id IS NULL)', function () {
    $downtown = Location::factory()->create(['slug' => 'downtown-cal-agnostic']);
    $uptown = Location::factory()->create(['slug' => 'uptown-cal-agnostic']);

    // Venue-agnostic event — should appear in ALL location filters
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-10',
        'start_time' => '2026-06-10 19:00:00',
        'location_id' => null,
    ]);

    // Downtown-scoped event
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'location_id' => $downtown->id,
    ]);

    // Uptown-scoped event — excluded when filtering for downtown
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 19:00:00',
        'location_id' => $uptown->id,
    ]);

    // Downtown filter: should return venue-agnostic + downtown = 2 events
    getJson("/api/calendar/events?month=6&year=2026&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Uptown filter: should return venue-agnostic + uptown = 2 events
    getJson("/api/calendar/events?month=6&year=2026&location={$uptown->slug}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/calendar/events?location= excludes events scoped to other locations', function () {
    $downtown = Location::factory()->create(['slug' => 'downtown-cal-excl']);
    $uptown = Location::factory()->create(['slug' => 'uptown-cal-excl']);

    // Uptown-only event
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'location_id' => $uptown->id,
    ]);

    // Downtown sees zero events
    getJson("/api/calendar/events?month=6&year=2026&location={$downtown->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('GET /api/calendar/events?location= with invalid slug returns 422', function () {
    getJson('/api/calendar/events?month=6&year=2026&location=nonexistent-location')
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'location');
});

test('GET /api/calendar/events without location returns all events (no regression)', function () {
    $downtown = Location::factory()->create(['slug' => 'downtown-no-filter']);

    // Scoped event
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
        'location_id' => $downtown->id,
    ]);

    // Venue-agnostic event
    CalendarEvent::factory()->loyaltyExclusive()->create([
        'date' => '2026-06-20',
        'start_time' => '2026-06-20 19:00:00',
        'location_id' => null,
    ]);

    // Without ?location=, all events returned
    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| GET /api/calendar/events — synthesized showtime entries
|--------------------------------------------------------------------------
|
| Showtimes do not live in `calendar_events`; they are projected on the fly
| from the `showtimes` table by ShowtimeCalendarProjector. One synthetic
| entry per (movie, location, local_date) — multiple showtimes for the same
| movie at the same venue on the same day collapse into a single entry that
| routes to /movies/{slug}.
|
*/

test('synthesizes one showtime entry per movie/location/day from the showtimes table', function () {
    $movie = Movie::factory()->create(['title' => 'Inception', 'slug' => 'inception']);
    $location = Location::factory()->create(['slug' => 'downtown-st-dedup', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // Two showtimes for the same movie at the same venue on the same day → one entry.
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 21:00:00',
        'end_time' => '2026-06-15 23:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'showtime')
        ->assertJsonPath('data.0.title', 'Inception')
        ->assertJsonPath('data.0.movieSlug', 'inception');
});

test('produces a separate showtime entry per location for the same movie on the same day', function () {
    $movie = Movie::factory()->create(['slug' => 'dune']);
    $loc1 = Location::factory()->create(['slug' => 'downtown-st-multi', 'timezone' => 'America/New_York']);
    $loc2 = Location::factory()->create(['slug' => 'uptown-st-multi', 'timezone' => 'America/New_York']);
    $aud1 = Auditorium::factory()->create(['location_id' => $loc1->id]);
    $aud2 = Auditorium::factory()->create(['location_id' => $loc2->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud1->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud2->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('excludes cancelled showtimes from synthesized entries', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-cancel', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);
    // Different day so we'd otherwise produce two entries.
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-16 19:00:00',
        'end_time' => '2026-06-16 21:00:00',
        'cancelled_at' => '2026-06-10 12:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'showtime');
});

test('scopes synthesized showtimes by ?location query param', function () {
    $movie = Movie::factory()->create();
    $loc1 = Location::factory()->create(['slug' => 'downtown-st-loc', 'timezone' => 'America/New_York']);
    $loc2 = Location::factory()->create(['slug' => 'uptown-st-loc', 'timezone' => 'America/New_York']);
    $aud1 = Auditorium::factory()->create(['location_id' => $loc1->id]);
    $aud2 = Auditorium::factory()->create(['location_id' => $loc2->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud1->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $aud2->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);

    // ?location=downtown returns only downtown's showtime (showtimes never inherit
    // venue-agnostic semantics — they're inherently tied to one auditorium).
    getJson("/api/calendar/events?month=6&year=2026&location={$loc1->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('merges synthesized showtimes with stored calendar events ordered by date then start_time', function () {
    $movie = Movie::factory()->create(['title' => 'Movie X']);
    $location = Location::factory()->create(['slug' => 'downtown-st-merge', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // Synthetic showtime on 2026-06-10 (earlier).
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-10 19:00:00',
        'end_time' => '2026-06-10 21:00:00',
    ]);

    // Stored special event on 2026-06-15 (later).
    CalendarEvent::factory()->specialEvent()->create([
        'title' => 'Gala Night',
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['Movie X', 'Gala Night']);
});

test('omits synthesized showtimes when type filter is special_event', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-typefilter1', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);

    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026&type=special_event')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'special_event');
});

test('returns only synthesized showtimes when type filter is showtime', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-typefilter2', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 19:00:00',
        'end_time' => '2026-06-15 21:00:00',
    ]);

    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026&type=showtime')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'showtime');
});

/*
|--------------------------------------------------------------------------
| GET /api/calendar/events — embedded showtimes payload
|--------------------------------------------------------------------------
|
| Each synthesized showtime entry exposes the full per-screening list under
| `showtimes` so the customer-side Bridge Console detail rail can render
| the 4-up tile grid without a second round-trip. Stored events return
| null for that field.
|
*/

test('synthesized showtime entries expose every uncancelled showtime sorted by start time', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-payload', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create([
        'location_id' => $location->id,
        'name' => 'Auditorium 03',
    ]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 21:00:00',
        'end_time' => '2026-06-15 23:00:00',
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $showtimes = $response->json('data.0.showtimes');
    expect($showtimes)->toHaveCount(2);
    expect($showtimes[0]['auditoriumLabel'])->toBe('Auditorium 03');
    // Earlier screening sorts first, regardless of insertion order.
    expect($showtimes[0]['startTime'])->toContain('2026-06-15');
    expect(strcmp($showtimes[0]['startTime'], $showtimes[1]['startTime']))->toBeLessThan(0);
    expect($showtimes[0])->toHaveKeys(['id', 'startTime', 'auditoriumLabel', 'soldOut']);
});

test('synthesized showtimes exclude cancelled screenings from the embedded list', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-payload-cancel', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 21:00:00',
        'end_time' => '2026-06-15 23:00:00',
        'cancelled_at' => '2026-06-10 12:00:00',
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')->assertOk();

    $showtimes = $response->json('data.0.showtimes');
    expect($showtimes)->toHaveCount(1);
});

test('synthesized showtime is marked soldOut when occupying booking seats fill the auditorium', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-soldout', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create([
        'location_id' => $location->id,
        'total_seats' => 2,
    ]);
    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);

    // Occupying booking with 2 seats — exactly equals capacity.
    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $seatA = Seat::factory()->create(['auditorium_id' => $auditorium->id]);
    $seatB = Seat::factory()->create(['auditorium_id' => $auditorium->id]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seatA->id,
    ]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seatB->id,
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')->assertOk();
    expect($response->json('data.0.showtimes.0.soldOut'))->toBeTrue();
});

test('synthesized showtime is not soldOut when occupying seats are below capacity', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-notsoldout', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create([
        'location_id' => $location->id,
        'total_seats' => 50,
    ]);
    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);

    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')->assertOk();
    expect($response->json('data.0.showtimes.0.soldOut'))->toBeFalse();
});

test('cancelled bookings do not contribute to soldOut computation', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create(['slug' => 'downtown-st-cancelled-booking', 'timezone' => 'America/New_York']);
    $auditorium = Auditorium::factory()->create([
        'location_id' => $location->id,
        'total_seats' => 1,
    ]);
    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-15 18:00:00',
        'end_time' => '2026-06-15 20:00:00',
    ]);

    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Cancelled,
    ]);
    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat->id,
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')->assertOk();
    expect($response->json('data.0.showtimes.0.soldOut'))->toBeFalse();
});

test('stored calendar_events rows return null for the showtimes field', function () {
    CalendarEvent::factory()->specialEvent()->create([
        'date' => '2026-06-15',
        'start_time' => '2026-06-15 19:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.showtimes', null);
});

/*
|--------------------------------------------------------------------------
| GET /api/calendar/events — venue-timezone month-boundary handling
|--------------------------------------------------------------------------
|
| Showtimes group by venue-local date. The UTC fetch window is padded by ±1
| day and the synthesized groups are filtered by computed local date so:
|   - late-evening local-month-end showtimes whose UTC value crosses into
|     the next day still appear in the requested month
|   - early-morning showtimes that land in the requested UTC bucket but
|     belong to the previous month locally are dropped
|
*/

test('includes a west-coast showtime whose local date is the last day of the month even when its UTC value crosses to the next month', function () {
    $movie = Movie::factory()->create(['slug' => 'tz-late-edge']);
    $location = Location::factory()->create([
        'slug' => 'la-tz-late',
        'timezone' => 'America/Los_Angeles',
    ]);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // 23:00 PT on 2026-06-30 → 06:00 UTC on 2026-07-01 (PDT, UTC-7).
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-07-01 06:00:00',
        'end_time' => '2026-07-01 08:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', '2026-06-30')
        ->assertJsonPath('data.0.type', 'showtime');
});

test('excludes a west-coast showtime whose local date is in the previous month even when its UTC value lands in the requested month bucket', function () {
    $movie = Movie::factory()->create(['slug' => 'tz-early-edge']);
    $location = Location::factory()->create([
        'slug' => 'la-tz-early',
        'timezone' => 'America/Los_Angeles',
    ]);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // 19:00 PT on 2026-05-31 → 02:00 UTC on 2026-06-01 (PDT, UTC-7). The
    // stored UTC stamp is inside the unpadded June UTC bucket, but the local
    // date is May 31 — must not surface in a June response.
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-01 02:00:00',
        'end_time' => '2026-06-01 04:00:00',
    ]);

    getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('synthesized event start_time renders the venue-local wall-clock value', function () {
    $movie = Movie::factory()->create();
    $location = Location::factory()->create([
        'slug' => 'la-tz-walltime',
        'timezone' => 'America/Los_Angeles',
    ]);
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);

    // 02:00 UTC on 2026-06-16 → 19:00 PT on 2026-06-15 (PDT, UTC-7).
    Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
        'start_time' => '2026-06-16 02:00:00',
        'end_time' => '2026-06-16 04:00:00',
    ]);

    $response = getJson('/api/calendar/events?month=6&year=2026')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // formatWireTime() reads the literal HH:MM out of the ISO string without
    // applying any timezone conversion, so the venue's wall-clock hour must
    // appear in the wire payload — never the UTC hour.
    expect($response->json('data.0.startTime'))->toContain('19:00');
    expect($response->json('data.0.startTime'))->not->toContain('02:00');
    expect($response->json('data.0.date'))->toBe('2026-06-15');
});
