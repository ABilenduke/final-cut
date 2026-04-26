<?php

use App\Models\CalendarEvent;
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
