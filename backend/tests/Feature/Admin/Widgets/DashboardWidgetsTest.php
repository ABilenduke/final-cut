<?php

use App\Enums\BookingStatus;
use App\Filament\Widgets\OpsHealthWidget;
use App\Filament\Widgets\TodayKpisWidget;
use App\Filament\Widgets\TodayShowtimesOccupancyWidget;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Services\SeatAvailabilityService;
use Livewire\Livewire;
use Tests\Helpers\BookingTestHelper;
use Tests\TestCase;

uses(BookingTestHelper::class);

/** A single 5-seat showtime safely inside the venue "today". */
function widgetFixture(): array
{
    /** @var TestCase&BookingTestHelper $test */
    $test = test();
    $ctx = $test->createShowtimeWithSeats([
        'start_time' => now()->startOfDay()->addHours(12),
        'end_time' => now()->startOfDay()->addHours(14),
    ]);

    return $ctx;
}

// ── TodayKpisWidget ─────────────────────────────────────────────────────────

test('today metrics count only today and only confirmed revenue', function (): void {
    $ctx = widgetFixture();

    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'total' => 2500,
    ]);
    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'total' => 1500,
        'created_at' => now()->subDay(),
    ]);
    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Cancelled,
        'total' => 9900,
    ]);

    $metrics = TodayKpisWidget::metrics();

    expect($metrics['bookings'])->toBe(1)
        ->and($metrics['revenue'])->toBe(2500)
        ->and($metrics['showtimes'])->toBe(1);
});

test('venue day bounds are computed in the configured venue timezone', function (): void {
    config(['app.default_location_timezone' => 'America/New_York']);

    [$dayStart, $dayEnd] = TodayKpisWidget::venueDayBounds();

    // Start is venue-local midnight expressed in the app timezone…
    expect($dayStart->copy()->setTimezone('America/New_York')->format('H:i'))->toBe('00:00');
    // …and the window is half-open venue-midnight to venue-midnight.
    expect($dayEnd->copy()->setTimezone('America/New_York')->format('H:i'))->toBe('00:00');
    expect($dayEnd->greaterThan($dayStart))->toBeTrue();
});

test('a blank default_location_timezone falls back to the app timezone', function (): void {
    // A present-but-empty DEFAULT_LOCATION_TIMEZONE env var yields '' (not
    // null) — exactly how CI is configured; Carbon must never receive it.
    config(['app.default_location_timezone' => '']);

    [$dayStart, $dayEnd] = TodayKpisWidget::venueDayBounds();

    expect($dayStart->format('H:i'))->toBe('00:00')
        ->and($dayEnd->greaterThan($dayStart))->toBeTrue();
});

test('the kpi widget renders for ops and is hidden from roleless admins', function (): void {
    $this->actingAsOps();
    expect(TodayKpisWidget::canView())->toBeTrue();

    Livewire::test(TodayKpisWidget::class)->assertOk();

    $this->actingAsNobody();
    expect(TodayKpisWidget::canView())->toBeFalse();
});

// ── OpsHealthWidget ─────────────────────────────────────────────────────────

test('ops health counts flagged-pending bookings and outbox states', function (): void {
    $ctx = widgetFixture();

    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::RefundPending,
        'flagged_at' => now(),
        'flag_reason' => 'showtime_cancelled:x',
    ]);
    // Terminal — must not count as pending attention.
    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Refunded,
        'flagged_at' => now(),
    ]);
    // Manually-flagged for another reason — outside the linked queue's
    // scope, so outside this KPI too (the number must match the link).
    Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
        'flagged_at' => now(),
        'flag_reason' => 'fraud_hold:review',
    ]);

    DispatchOutbox::create(['event_type' => 'x.pending', 'payload' => []]);
    DispatchOutbox::create(['event_type' => 'x.parked', 'payload' => [], 'failed_at' => now()]);
    DispatchOutbox::create(['event_type' => 'x.done', 'payload' => [], 'processed_at' => now()]);
    // Not yet available to the worker — dispatchable() excludes it, so the
    // backlog KPI must too.
    DispatchOutbox::create(['event_type' => 'x.future', 'payload' => [], 'available_at' => now()->addHour()]);

    $metrics = OpsHealthWidget::metrics();

    expect($metrics['flagged'])->toBe(1)
        ->and($metrics['outbox_backlog'])->toBe(1)
        ->and($metrics['outbox_parked'])->toBe(1);
});

test('the ops health widget is permission gated', function (): void {
    $this->actingAsOps();
    expect(OpsHealthWidget::canView())->toBeTrue();

    $this->actingAsNobody();
    expect(OpsHealthWidget::canView())->toBeFalse();
});

// ── TodayShowtimesOccupancyWidget ───────────────────────────────────────────

test('the occupancy table lists today showtimes with occupied over capacity', function (): void {
    $this->actingAsAdmin();
    $ctx = widgetFixture();

    $booking = Booking::factory()->guest()->create([
        'showtime_id' => $ctx['showtime']->id,
        'status' => BookingStatus::Confirmed,
    ]);
    app(SeatAvailabilityService::class)->reserveSeats(
        $ctx['showtime'],
        [$ctx['seats'][0]->id, $ctx['seats'][1]->id],
        $booking,
    );

    // Yesterday's showtime must not appear.
    $yesterday = $this->createShowtimeWithSeats([
        'start_time' => now()->subDay()->startOfDay()->addHours(12),
        'end_time' => now()->subDay()->startOfDay()->addHours(14),
    ]);

    Livewire::test(TodayShowtimesOccupancyWidget::class)
        ->assertCanSeeTableRecords([$ctx['showtime']])
        ->assertCanNotSeeTableRecords([$yesterday['showtime']])
        ->assertSee('2 / 5');
});

test('the occupancy table requires showtimes.view', function (): void {
    $this->actingAsOps();
    expect(TodayShowtimesOccupancyWidget::canView())->toBeTrue();

    $this->actingAsNobody();
    expect(TodayShowtimesOccupancyWidget::canView())->toBeFalse();
});
