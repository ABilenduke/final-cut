<?php

use App\Enums\BookingStatus;
use App\Exceptions\AuditoriumSeatRegenerationBlockedException;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Services\AuditoriumService;
use Spatie\Activitylog\Models\Activity;

/**
 * CRITICAL. Regeneration safety is the #1 invariant in Plan 05. These tests
 * must never be relaxed to force passage — soften the contract and data loss
 * becomes likely rather than possible.
 */
beforeEach(function (): void {
    $this->service = app(AuditoriumService::class);
    $this->admin = $this->actingAsAdmin();

    $this->auditorium = Auditorium::factory()->create();
    $this->section = AuditoriumSection::factory()->for($this->auditorium)->standard()->create();

    // Seed an existing seat layout (the "previous layout" we expect to survive refusal).
    $this->existingSeats = Seat::factory()->count(3)->create([
        'auditorium_id' => $this->auditorium->id,
        'section_id' => $this->section->id,
    ]);

    $this->validConfig = [
        'rows' => 2,
        'seats_per_row' => 2,
        'section_map' => [
            ['rows' => ['A', 'B'], 'section_id' => $this->section->id, 'type' => 'standard'],
        ],
        'unavailable_seats' => [],
    ];
});

test('blocked when a future Showtime exists — exception carries count, no seats deleted', function (): void {
    Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHours(2),
    ]);

    $before = $this->auditorium->seats()->count();

    $thrown = null;
    try {
        $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
    } catch (AuditoriumSeatRegenerationBlockedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->blockers['future_showtimes'])->toBe(1);
    expect($this->auditorium->seats()->count())->toBe($before);
});

test('blocked when a confirmed booking references a seat in this auditorium', function (): void {
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subDay(), // past — the showtime itself is not a blocker.
        'end_time' => now()->subDay()->addHours(2),
    ]);
    $booking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::Confirmed]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $this->existingSeats->first()->id,
    ]);

    $before = $this->auditorium->seats()->count();

    $thrown = null;
    try {
        $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
    } catch (AuditoriumSeatRegenerationBlockedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->blockers['active_bookings'])->toBe(1);
    expect($this->auditorium->seats()->count())->toBe($before);
});

test('blocked when a Held booking references a seat (new status must be honoured)', function (): void {
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subHour(),
        'end_time' => now()->subHour()->addHours(2),
    ]);
    $booking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::Held]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $this->existingSeats->first()->id,
    ]);

    $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
})->throws(AuditoriumSeatRegenerationBlockedException::class);

test('blocked when a RefundPending booking references a seat', function (): void {
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subHour(),
        'end_time' => now()->subHour()->addHours(2),
    ]);
    $booking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::RefundPending]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $this->existingSeats->first()->id,
    ]);

    $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
})->throws(AuditoriumSeatRegenerationBlockedException::class);

test('blocked when a live seat_holds row exists for a showtime of the auditorium', function (): void {
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subHour(),
        'end_time' => now()->subHour()->addHours(2),
    ]);
    SeatHold::factory()->create([
        'showtime_id' => $showtime->id,
        'seat_id' => $this->existingSeats->first()->id,
        'expires_at' => now()->addMinutes(5),
    ]);

    $thrown = null;
    try {
        $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
    } catch (AuditoriumSeatRegenerationBlockedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->blockers['held_seats'])->toBe(1);
});

test('succeeds when only past showtimes exist and all bookings are in terminal states', function (): void {
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->subDay(),
        'end_time' => now()->subDay()->addHours(2),
    ]);
    // A cancelled booking (terminal) is not a blocker.
    $booking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::Cancelled]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $this->existingSeats->first()->id,
    ]);

    $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);

    // 2 rows * 2 seats = 4 new seats, old seats gone.
    expect($this->auditorium->seats()->count())->toBe(4);
    expect($this->auditorium->seats()->pluck('label')->sort()->values()->all())
        ->toEqual(['A1', 'A2', 'B1', 'B2']);
});

test('mid-generation DB failure rolls back fully — previous seat layout intact, no success activity row', function (): void {
    // Inject a malformed section_map row that passes pre-flight but fails when
    // the loop runs — the section_id points to a section on a DIFFERENT
    // auditorium. The FK nullOnDelete rule allows this at the DB level, but the
    // outer service's transaction still rolls back cleanly because we call
    // inside DB::transaction and the SQL layer doesn't reject this particular
    // path. To genuinely force a mid-flight failure, drop a bogus
    // `unavailable_seats` containing a seat label that would collide on insert.
    //
    // Simpler approach: use a row count > 26 to exceed A–Z and produce an
    // invalid `chr()` value. We bypass the UI's validation which caps at 26 and
    // go straight at the service, so the service itself produces a
    // downstream constraint violation during Seat::insert.
    $config = $this->validConfig;
    $config['rows'] = 30; // > 26 → non-letter row characters are generated → non-unique/strange.
    // Actually this may still succeed with multi-byte chars. Let's force a
    // unique constraint violation instead by including a duplicate label in
    // unavailable_seats — not an error per se, they're just flags. Use
    // `seats_per_row` that's 0 to force the Seat::insert to receive rows
    // referencing a constrained column with an invalid value.
    $config = $this->validConfig;
    $config['seats_per_row'] = 0; // unsignedSmallInteger allows this; but then no seats inserted → no failure.

    // Actually, the simplest way to force a mid-generation failure is to inject
    // an invalid section_id (FK nullOnDelete is set, but we want the insert to
    // throw). Use an invalid uuid that will fail FK validation.
    $config = $this->validConfig;
    $config['section_map'] = [
        ['rows' => ['A', 'B'], 'section_id' => '00000000-0000-0000-0000-000000000000', 'type' => 'standard'],
    ];

    $before = $this->auditorium->seats()->pluck('id')->sort()->values()->all();
    Activity::query()->delete();

    $thrown = null;
    try {
        $this->service->generateSeats($this->auditorium, $config, $this->admin);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();

    // Previous layout intact — same seat ids present afterwards.
    $after = $this->auditorium->seats()->pluck('id')->sort()->values()->all();
    expect($after)->toEqual($before);

    // No success activity row was written.
    expect(Activity::where('description', 'auditorium.seats_generated')->count())->toBe(0);
});

test('force = true path is documented but unused by the UI — exists as a guardrail only', function (): void {
    // The force flag bypasses the refusal check. Plan 05's UI never exposes
    // it. This test locks in the behaviour so a future refactor that removes
    // or repurposes the flag is caught.
    Showtime::factory()->create([
        'auditorium_id' => $this->auditorium->id,
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHours(2),
    ]);

    // Without force: blocked.
    try {
        $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
        $blocked = false;
    } catch (AuditoriumSeatRegenerationBlockedException $e) {
        $blocked = true;
    }
    expect($blocked)->toBeTrue();

    // With force: succeeds.
    $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin, force: true);
    expect($this->auditorium->seats()->count())->toBe(4);
});
