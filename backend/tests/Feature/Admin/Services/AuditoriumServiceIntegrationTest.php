<?php

use App\Enums\BookingStatus;
use App\Exceptions\AuditoriumHasBookingsException;
use App\Exceptions\AuditoriumSectionInUseException;
use App\Exceptions\LocationHasBookingsException;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\AuditoriumService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(AuditoriumService::class);
});

test('createLocation with actor writes a location.created activity row', function (): void {
    $admin = $this->actingAsAdmin();
    Activity::query()->delete();

    $location = $this->service->createLocation([
        'name' => 'Logged',
        'slug' => 'logged',
        'timezone' => 'America/New_York',
    ], $admin);

    $activity = Activity::where('subject_type', Location::class)
        ->where('subject_id', $location->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('location.created');
    expect($activity->causer_type)->toBe(AdminUser::class);
    expect((int) $activity->causer_id)->toBe($admin->id);
    expect($activity->log_name)->toBe('admin');
});

test('createLocation with null actor writes no activity row', function (): void {
    Activity::query()->delete();

    $location = $this->service->createLocation([
        'name' => 'Quiet',
        'slug' => 'quiet',
        'timezone' => 'UTC',
    ], null);

    expect(Activity::where('subject_type', Location::class)
        ->where('subject_id', $location->id)
        ->count())->toBe(0);
});

test('updateLocation with actor writes before/after diff, skips when nothing is dirty', function (): void {
    $admin = $this->actingAsAdmin();
    $location = Location::factory()->create(['name' => 'Original']);
    Activity::query()->delete();

    $this->service->updateLocation($location, ['name' => 'Revised'], $admin);

    $a1 = Activity::where('subject_type', Location::class)->latest('id')->first();
    expect($a1?->description)->toBe('location.updated');
    expect($a1->properties->toArray()['before']['name'])->toBe('Original');
    expect($a1->properties->toArray()['after']['name'])->toBe('Revised');

    // Second update with the same value — nothing dirty, no new row.
    $countBefore = Activity::count();
    $this->service->updateLocation($location, ['name' => 'Revised'], $admin);
    expect(Activity::count())->toBe($countBefore);
});

test('deleteLocation logs before deletion and routes through the service', function (): void {
    $admin = $this->actingAsAdmin();
    $location = Location::factory()->create();
    Activity::query()->delete();

    $this->service->deleteLocation($location, $admin);

    $a = Activity::where('subject_type', Location::class)->latest('id')->first();
    expect($a?->description)->toBe('location.deleted');
    expect((int) $a->causer_id)->toBe($admin->id);
    expect(Location::find($location->id))->toBeNull();
});

test('createAuditorium writes an auditorium.created row attributed to the admin', function (): void {
    $admin = $this->actingAsAdmin();
    $location = Location::factory()->create();
    Activity::query()->delete();

    $auditorium = $this->service->createAuditorium($location, [
        'name' => 'Bridge',
        'slug' => 'bridge',
        'cleanup_minutes' => 30,
    ], $admin);

    $a = Activity::where('subject_type', Auditorium::class)->latest('id')->first();
    expect($a?->description)->toBe('auditorium.created');
    expect((int) $a->causer_id)->toBe($admin->id);
    expect($auditorium->location_id)->toBe($location->id);
});

test('updateSectionConfig with actor writes a row per section change', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $existing = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    Activity::query()->delete();

    $this->service->updateSectionConfig($auditorium, [
        ['id' => $existing->id, 'name' => 'Standard', 'price_multiplier' => 1.10, 'display_order' => 10],
        ['id' => null, 'name' => 'Premium', 'price_multiplier' => 1.25, 'display_order' => 20],
    ], $admin);

    expect(Activity::where('subject_type', Auditorium::class)
        ->where('description', 'auditorium.section_updated')
        ->count())->toBe(1);
    expect(Activity::where('subject_type', Auditorium::class)
        ->where('description', 'auditorium.section_created')
        ->count())->toBe(1);

    expect($auditorium->sections()->count())->toBe(2);
});

test('updateSectionConfig refuses to delete a section that still has seats referencing it', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();

    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'section_id' => $section->id,
    ]);

    // Send an empty sections list — implies the existing section should be removed.
    $this->service->updateSectionConfig($auditorium, [
        ['id' => null, 'name' => 'Replacement', 'price_multiplier' => 1.00, 'display_order' => 0],
    ], $admin);
})->throws(AuditoriumSectionInUseException::class);

test('updating a section price_multiplier does NOT touch any seat row (cascade contract)', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    $seat = Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'section_id' => $section->id,
    ]);
    $originalUpdatedAt = $seat->updated_at->toIso8601String();

    $this->service->updateSectionConfig($auditorium, [
        ['id' => $section->id, 'name' => 'Standard', 'price_multiplier' => 1.50, 'display_order' => 10],
    ], $admin);

    $seat->refresh();
    expect($seat->updated_at->toIso8601String())->toBe($originalUpdatedAt);
});

test('updateSeatBatch reassigns sections, logs per-seat, and is NOT blocked by confirmed future bookings', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $standard = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    $premium = AuditoriumSection::factory()->for($auditorium)->premium()->create();
    $seat1 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'section_id' => $standard->id]);
    $seat2 = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'section_id' => $standard->id]);

    // A live confirmed booking on a future showtime referencing seat1.
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHours(2),
    ]);
    $booking = Booking::factory()->create(['showtime_id' => $showtime->id, 'status' => BookingStatus::Confirmed]);
    BookingSeat::factory()->create([
        'booking_id' => $booking->id,
        'showtime_id' => $showtime->id,
        'seat_id' => $seat1->id,
    ]);

    Activity::query()->delete();

    $this->service->updateSeatBatch($auditorium, [
        ['seat_id' => $seat1->id, 'section_id' => $premium->id],
        ['seat_id' => $seat2->id, 'unavailable_at' => now()],
    ], $admin);

    expect($seat1->fresh()->section_id)->toBe($premium->id);
    expect($seat2->fresh()->unavailable_at)->not->toBeNull();

    expect(Activity::where('subject_type', Auditorium::class)
        ->where('description', 'auditorium.seat_updated')
        ->count())->toBe(2);
});

test('updateSeatBatch with an invalid seat id rolls back the whole batch — no partial update, no activity rows', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $standard = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    $premium = AuditoriumSection::factory()->for($auditorium)->premium()->create();
    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id, 'section_id' => $standard->id]);

    Activity::query()->delete();

    $thrown = null;
    try {
        $this->service->updateSeatBatch($auditorium, [
            ['seat_id' => $seat->id, 'section_id' => $premium->id],
            ['seat_id' => 'not-a-real-uuid', 'section_id' => $premium->id],
        ], $admin);
    } catch (InvalidArgumentException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($seat->fresh()->section_id)->toBe($standard->id);
    expect(Activity::count())->toBe(0);
});

test('adding a new section does not auto-populate seats', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $existing = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    Seat::factory()->create(['auditorium_id' => $auditorium->id, 'section_id' => $existing->id]);

    $this->service->updateSectionConfig($auditorium, [
        ['id' => $existing->id, 'name' => 'Standard', 'price_multiplier' => 1.00, 'display_order' => 10],
        ['id' => null, 'name' => 'Accessible', 'price_multiplier' => 1.00, 'display_order' => 30],
    ], $admin);

    $newSection = $auditorium->sections()->where('name', 'Accessible')->first();
    expect($newSection)->not->toBeNull();
    expect($newSection->seats()->count())->toBe(0);
});

test('deleteAuditorium refuses when any showtime still carries a booking (cascade-safety guard)', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->subDay(),
        'end_time' => now()->subDay()->addHours(2),
    ]);
    Booking::factory()->create(['showtime_id' => $showtime->id]);

    $thrown = null;
    try {
        $this->service->deleteAuditorium($auditorium, $admin);
    } catch (AuditoriumHasBookingsException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(AuditoriumHasBookingsException::class);
    expect(Auditorium::find($auditorium->id))->not->toBeNull();
});

test('deleteLocation refuses when any auditorium still has a booking (cascade-safety guard)', function (): void {
    $admin = $this->actingAsAdmin();
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    $showtime = Showtime::factory()->create([
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->subDay(),
        'end_time' => now()->subDay()->addHours(2),
    ]);
    Booking::factory()->create(['showtime_id' => $showtime->id]);

    $thrown = null;
    try {
        $this->service->deleteLocation($location, $admin);
    } catch (LocationHasBookingsException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(LocationHasBookingsException::class);
    expect(Location::find($location->id))->not->toBeNull();
});

test('deleteAuditorium succeeds once all bookings are gone', function (): void {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create();
    Showtime::factory()->create([
        'auditorium_id' => $auditorium->id,
        'start_time' => now()->subDay(),
        'end_time' => now()->subDay()->addHours(2),
    ]);

    $this->service->deleteAuditorium($auditorium, $admin);

    expect(Auditorium::find($auditorium->id))->toBeNull();
});

test('generateSeats rejects section_ids that belong to a different auditorium', function (): void {
    $admin = $this->actingAsAdmin();
    $auditoriumA = Auditorium::factory()->create();
    $auditoriumB = Auditorium::factory()->create();
    $sectionFromB = AuditoriumSection::factory()->for($auditoriumB)->standard()->create();

    // Seed an existing seat on A so we can prove the previous layout survives.
    $existingSeat = Seat::factory()->create(['auditorium_id' => $auditoriumA->id]);

    $config = [
        'rows' => 2,
        'seats_per_row' => 2,
        'section_map' => [
            ['rows' => ['A', 'B'], 'section_id' => $sectionFromB->id, 'type' => 'standard'],
        ],
        'unavailable_seats' => [],
    ];

    $thrown = null;
    try {
        $this->service->generateSeats($auditoriumA, $config, $admin);
    } catch (InvalidArgumentException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getMessage())->toContain('section ids do not belong');
    // Previous layout intact because the pre-check throws before the destructive delete.
    expect(Seat::find($existingSeat->id))->not->toBeNull();
});

test('saveAuditoriumWithSections is atomic — partial failure in section sync rolls back the auditorium update', function () {
    $admin = $this->actingAsAdmin();
    $auditorium = Auditorium::factory()->create(['name' => 'Before', 'cleanup_minutes' => 20]);
    $section = AuditoriumSection::factory()->for($auditorium)->standard()->create();
    Seat::factory()->create([
        'auditorium_id' => $auditorium->id,
        'section_id' => $section->id,
    ]);

    Activity::query()->delete();

    // Attempt to drop the section that still has a seat — updateSectionConfig
    // throws AuditoriumSectionInUseException. Because saveAuditoriumWithSections
    // wraps create/update + section sync in one transaction, the auditorium
    // name change must roll back too.
    $thrown = null;
    try {
        $this->service->saveAuditoriumWithSections(
            $auditorium->location,
            $auditorium,
            ['name' => 'After', 'cleanup_minutes' => 25, 'slug' => $auditorium->slug],
            [], // empty sections list — implies delete the existing section
            $admin,
        );
    } catch (AuditoriumSectionInUseException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();

    $auditorium->refresh();
    expect($auditorium->name)->toBe('Before');
    expect($auditorium->cleanup_minutes)->toBe(20);

    // No activity_log row survives the rollback.
    expect(Activity::where('subject_type', Auditorium::class)->count())->toBe(0);
});
