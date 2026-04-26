<?php

use App\Enums\CalendarEventType;
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

test('admin can create a special event', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Hitchcock Marathon')
        ->set('data.slug', 'hitchcock-marathon')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDays(5)->toDateString())
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
        ->and($event->loyalty_only)->toBeFalse();
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
        ->set('data.accessibility_tags', ['sensory_friendly', 'audio_described'])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'multi-tag-event')->first();
    expect($event->accessibility_tags)->toBe(['sensory_friendly', 'audio_described']);
});

test('loyalty_exclusive event persists loyalty_only flag', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Premier Members Preview')
        ->set('data.slug', 'premier-preview')
        ->set('data.type', CalendarEventType::LoyaltyExclusive->value)
        ->set('data.date', now()->addDays(3)->toDateString())
        ->set('data.loyalty_only', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::where('slug', 'premier-preview')->first();
    expect($event->loyalty_only)->toBeTrue()
        ->and($event->type)->toBe(CalendarEventType::LoyaltyExclusive);
});

test('slug uniqueness is enforced', function (): void {
    CalendarEvent::factory()->create(['slug' => 'duplicate-slug']);

    Livewire::test(CreateCalendarEvent::class)
        ->set('data.title', 'Another Event')
        ->set('data.slug', 'duplicate-slug')
        ->set('data.type', CalendarEventType::SpecialEvent->value)
        ->set('data.date', now()->addDay()->toDateString())
        ->call('create')
        ->assertHasFormErrors(['slug']);
});
