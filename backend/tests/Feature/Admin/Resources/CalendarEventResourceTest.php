<?php

use App\Enums\CalendarEventType;
use App\Filament\Resources\CalendarEventResource;
use App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEventResource\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEventResource\Pages\ListCalendarEvents;
use App\Models\CalendarEvent;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admin can see the calendar event list', function (): void {
    $events = CalendarEvent::factory()->count(3)->create();

    Livewire::test(ListCalendarEvents::class)
        ->assertCanSeeTableRecords($events);
});

test('admin can create a special event with combined date + time persisted as datetime', function (): void {
    $eventDate = now()->addDays(5)->toDateString();

    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Hitchcock Marathon')
        ->set('data.slug', 'hitchcock-marathon')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', $eventDate)
        ->set('data.start_time', '18:00')
        ->set('data.end_time', '23:00')
        ->set('data.description', 'Three Hitchcock films back-to-back.')
        ->set('data.accessibility_tags', ['open_caption'])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'hitchcock-marathon')->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(CalendarEventType::SpecialEvent)
        ->and($event->title)->toBe('Hitchcock Marathon')
        ->and($event->accessibility_tags)->toBe(['open_caption'])
        ->and($event->loyalty_only)->toBeFalse()
        // The TimePicker emits "HH:MM" but `start_time` / `end_time` are
        // datetime columns — the form must combine the picked date with
        // the picked time. A naive bind would persist `today + HH:MM`,
        // which would set the wrong date for events more than a day out.
        ->and($event->start_time->toDateString())->toBe($eventDate)
        ->and($event->start_time->format('H:i'))->toBe('18:00')
        ->and($event->end_time->toDateString())->toBe($eventDate)
        ->and($event->end_time->format('H:i'))->toBe('23:00');
});

test('start_time is required', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'No Start Time')
        ->set('data.slug', 'no-start-time')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDay()->toDateString())
        // start_time deliberately omitted
        ->call('create')
        ->assertHasFormErrors(['start_time']);

    expect(CalendarEvent::where('slug', 'no-start-time')->exists())->toBeFalse();
});

test('end_time may be null for all-day events', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'All Day Festival')
        ->set('data.slug', 'all-day-festival')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDays(2)->toDateString())
        ->set('data.start_time', '09:00')
        // end_time deliberately omitted
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'all-day-festival')->first();
    expect($event)->not->toBeNull()
        ->and($event->end_time)->toBeNull();
});

test('slug auto-derives from title on blur', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', "Director's Q&A: A Story")
        ->assertSet('data.slug', 'directors-qa-a-story');
});

test('loyalty_only toggle is hidden unless type is loyalty_exclusive', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->assertFormFieldIsHidden('loyalty_only')
        ->set('data.type', CalendarEventType::LoyaltyExclusive->value)
        ->assertFormFieldIsVisible('loyalty_only');
});

test('type select hides showtime', function (): void {
    // The form Select must not let an admin author a showtime-type event —
    // those are produced by the showtimes domain. Setting the field to
    // `showtime` and submitting should fail validation.
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Bogus Showtime')
        ->set('data.slug', 'bogus-showtime')
        ->set('data.type', CalendarEventType::Showtime->value)
        ->set('data.date', now()->addDay()->toDateString())
        ->set('data.start_time', '18:00')
        ->call('create')
        ->assertHasFormErrors(['type']);

    expect(CalendarEvent::where('slug', 'bogus-showtime')->exists())->toBeFalse();
});

test('admin can edit a calendar event', function (): void {
    $event = CalendarEvent::factory()->specialEvent()->create([
        'title' => 'Original Title',
    ]);

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getRouteKey()])
        ->set('data.title', 'Updated Title')
        ->call('save')
        ->assertHasNoFormErrors();

    $event->refresh();
    expect($event->title)->toBe('Updated Title');
});

test('editing preserves the existing date when only the title changes', function (): void {
    $originalDate = now()->addDays(7);
    $event = CalendarEvent::factory()->specialEvent()->create([
        'date' => $originalDate->toDateString(),
        'start_time' => $originalDate->copy()->setTime(20, 0),
        'end_time' => $originalDate->copy()->setTime(22, 30),
    ]);

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getRouteKey()])
        ->set('data.title', 'Renamed Event')
        ->call('save')
        ->assertHasNoFormErrors();

    $event->refresh();
    expect($event->start_time->toDateString())->toBe($originalDate->toDateString())
        ->and($event->start_time->format('H:i'))->toBe('20:00')
        ->and($event->end_time->format('H:i'))->toBe('22:30');
});

test('admin can delete a calendar event', function (): void {
    $event = CalendarEvent::factory()->create();

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getRouteKey()])
        ->callAction('delete');

    expect(CalendarEvent::find($event->id))->toBeNull();
});

test('accessibility_tags persist as array', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Multi-Tag Event')
        ->set('data.slug', 'multi-tag-event')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDay()->toDateString())
        ->set('data.start_time', '14:30')
        ->set('data.accessibility_tags', ['sensory_friendly', 'audio_described'])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'multi-tag-event')->first();
    expect($event->accessibility_tags)->toBe(['sensory_friendly', 'audio_described']);
});

test('loyalty_exclusive event persists loyalty_only flag', function (): void {
    $eventDate = now()->addDays(3)->toDateString();

    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Premier Members Preview')
        ->set('data.slug', 'premier-preview')
        ->set('data.type', CalendarEventType::LoyaltyExclusive->value)
        ->set('data.date', $eventDate)
        ->set('data.start_time', '19:00')
        ->set('data.end_time', '21:00')
        ->set('data.loyalty_only', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'premier-preview')->first();
    expect($event->loyalty_only)->toBeTrue()
        ->and($event->type)->toBe(CalendarEventType::LoyaltyExclusive)
        ->and($event->start_time->format('H:i'))->toBe('19:00')
        ->and($event->end_time->format('H:i'))->toBe('21:00');
});

test('slug uniqueness is enforced', function (): void {
    CalendarEvent::factory()->create(['slug' => 'duplicate-slug']);

    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Another Event')
        ->set('data.slug', 'duplicate-slug')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDay()->toDateString())
        ->set('data.start_time', '12:00')
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

test('showtime-type rows are not editable or deletable from this resource', function (): void {
    // Showtime-type calendar entries are produced by the showtimes domain.
    // The form's Type Select hides `showtime`, so opening such a row in
    // the edit form would render a value that isn't in the allowed
    // options. Both the Resource's authorization layer (canEdit / canDelete)
    // and the table actions' visibility must refuse the operation.
    $showtime = CalendarEvent::factory()->create(['type' => CalendarEventType::Showtime]);
    $special = CalendarEvent::factory()->create(['type' => CalendarEventType::SpecialEvent]);

    expect(CalendarEventResource::canEdit($showtime))->toBeFalse()
        ->and(CalendarEventResource::canDelete($showtime))->toBeFalse()
        // Sanity: a non-showtime row remains authorable when the admin has
        // the permission (actingAsAdmin in beforeEach grants the full set).
        ->and(CalendarEventResource::canEdit($special))->toBeTrue()
        ->and(CalendarEventResource::canDelete($special))->toBeTrue();
});
