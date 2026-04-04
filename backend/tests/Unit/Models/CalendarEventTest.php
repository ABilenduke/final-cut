<?php

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use Illuminate\Support\Str;

it('creates a calendar event with UUID primary key', function () {
    $event = CalendarEvent::factory()->create();
    expect($event->id)->toBeString();
    expect(Str::isUuid($event->id))->toBeTrue();
});

it('casts type to CalendarEventType enum', function () {
    $event = CalendarEvent::factory()->create(['type' => 'special_event']);
    expect($event->type)->toBe(CalendarEventType::SpecialEvent);
});

it('casts accessibility_tags to array', function () {
    $event = CalendarEvent::factory()->create([
        'accessibility_tags' => ['sensory_friendly', 'open_caption'],
    ]);
    $event->refresh();
    expect($event->accessibility_tags)->toBeArray();
    expect($event->accessibility_tags)->toContain('sensory_friendly');
});

it('defaults loyalty_only to false', function () {
    $event = CalendarEvent::factory()->create();
    expect($event->loyalty_only)->toBeFalse();
});

it('casts date to date', function () {
    $event = CalendarEvent::factory()->create(['date' => '2026-06-15']);
    expect($event->date)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('enforces unique slug constraint when not null', function () {
    CalendarEvent::factory()->create(['slug' => 'test-event']);
    CalendarEvent::factory()->create(['slug' => 'test-event']);
})->throws(\Illuminate\Database\QueryException::class);

it('allows multiple null slugs', function () {
    CalendarEvent::factory()->create(['slug' => null]);
    CalendarEvent::factory()->create(['slug' => null]);
    expect(CalendarEvent::count())->toBe(2);
});
