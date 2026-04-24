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
use Illuminate\Support\Facades\DB;
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
    $before = $this->auditorium->seats()->pluck('id')->sort()->values()->all();
    Activity::query()->delete();

    // Force a failure AFTER the destructive `$auditorium->seats()->delete()`
    // runs by throwing from a query listener the first time PostgreSQL sees
    // `INSERT INTO "seats"`. That proves the outer DB::transaction rolls
    // back the prior delete (and any activity_log entry) as a unit.
    DB::listen(function ($query) {
        if (str_starts_with(strtolower($query->sql), 'insert into "seats"')) {
            throw new RuntimeException('Simulated insert failure');
        }
    });

    $thrown = null;
    try {
        $this->service->generateSeats($this->auditorium, $this->validConfig, $this->admin);
    } catch (RuntimeException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getMessage())->toBe('Simulated insert failure');

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
