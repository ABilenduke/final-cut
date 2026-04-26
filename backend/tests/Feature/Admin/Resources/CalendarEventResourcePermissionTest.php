<?php

use App\Filament\Resources\CalendarEventResource;
use App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEventResource\Pages\ListCalendarEvents;
use App\Models\CalendarEvent;

test('ops cannot view, create, edit, or delete calendar events', function (): void {
    $this->actingAsOps();
    $event = CalendarEvent::factory()->create();

    expect(CalendarEventResource::canViewAny())->toBeFalse();
    expect(CalendarEventResource::canCreate())->toBeFalse();
    expect(CalendarEventResource::canEdit($event))->toBeFalse();
    expect(CalendarEventResource::canDelete($event))->toBeFalse();

    $this->get(ListCalendarEvents::getUrl())->assertForbidden();
});

test('manager can create, edit, and delete calendar events', function (): void {
    $this->actingAsManager();
    // Force a non-Showtime type — CalendarEventResource hard-blocks edit/delete
    // for Showtime events regardless of role, so a random type from the factory
    // would intermittently fail this permission-matrix test.
    $event = CalendarEvent::factory()->specialEvent()->create();

    expect(CalendarEventResource::canViewAny())->toBeTrue();
    expect(CalendarEventResource::canCreate())->toBeTrue();
    expect(CalendarEventResource::canEdit($event))->toBeTrue();
    expect(CalendarEventResource::canDelete($event))->toBeTrue();
});

test('admin can create, edit, and delete calendar events', function (): void {
    $this->actingAsAdmin();
    $event = CalendarEvent::factory()->specialEvent()->create();

    expect(CalendarEventResource::canViewAny())->toBeTrue();
    expect(CalendarEventResource::canCreate())->toBeTrue();
    expect(CalendarEventResource::canEdit($event))->toBeTrue();
    expect(CalendarEventResource::canDelete($event))->toBeTrue();
});

test('nobody role cannot see calendar events list', function (): void {
    $this->actingAsNobody();

    expect(CalendarEventResource::canViewAny())->toBeFalse();

    $this->get(ListCalendarEvents::getUrl())->assertForbidden();
    $this->get(CreateCalendarEvent::getUrl())->assertForbidden();
});
