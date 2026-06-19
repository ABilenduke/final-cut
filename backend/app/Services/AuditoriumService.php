<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Exceptions\AuditoriumHasBookingsException;
use App\Exceptions\AuditoriumSeatRegenerationBlockedException;
use App\Exceptions\AuditoriumSectionInUseException;
use App\Exceptions\InvalidPriceMultiplierException;
use App\Exceptions\LocationHasBookingsException;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Location;
use App\Models\Seat;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Write boundary for Location / Auditorium / Section / Seat mutations.
 *
 * Section ↔ Seat cascade contract:
 *   - `updateSectionConfig` does NOT touch seats. Price multiplier changes
 *     take effect for future pricing lookups via `seat.section_id`.
 *   - Deleting a section referenced by any seat is refused
 *     (`AuditoriumSectionInUseException`). Reassign seats first.
 *   - `generateSeats` is destructive and is the only path that creates or
 *     removes seat rows.
 *   - `updateSeatBatch` only mutates existing seat rows (`section_id`,
 *     `unavailable_at`). It is intentionally NOT blocked by future
 *     showtimes or active bookings because seat IDs are preserved and
 *     existing bookings keep pointing at the same physical seat; only
 *     the seat's section membership or availability flag changes. Past
 *     bookings keep their historical `booking_seats.section` snapshot.
 *
 * Admin attribution:
 *   - Every write method accepts `?User $actor = null`.
 *   - Activity-log rows are only emitted when `$actor !== null`.
 *   - Customer-side writes (none today) pass `null`.
 *
 * Regeneration safety:
 *   - `generateSeats` refuses when future showtimes, active bookings, or
 *     live seat holds exist for the auditorium. The whole refusal check
 *     → delete → rebuild runs in a single `DB::transaction`, so any
 *     failure mid-flight rolls back and the previous seat layout stays
 *     fully intact. The `force = true` escape hatch is reserved for
 *     future internal use — Plan 05 never exposes it to the UI.
 */
class AuditoriumService
{
    use LogsAdminActivity;

    // ------------------------------------------------------------------
    // Location
    // ------------------------------------------------------------------

    public function createLocation(array $attributes, ?User $actor = null): Location
    {
        $location = Location::create($attributes);

        $this->logIfAdmin('location.created', $location, $actor, $attributes);

        return $location;
    }

    public function updateLocation(Location $location, array $attributes, ?User $actor = null): Location
    {
        $location->fill($attributes);
        $dirtyKeys = array_keys($location->getDirty());
        $before = array_intersect_key($location->getOriginal(), array_flip($dirtyKeys));
        $location->save();
        $after = array_intersect_key($location->getAttributes(), array_flip($dirtyKeys));

        if ($dirtyKeys !== []) {
            $this->logIfAdmin('location.updated', $location, $actor, [
                'before' => $before,
                'after' => $after,
            ]);
        }

        return $location;
    }

    /**
     * @throws LocationHasBookingsException if any showtime at any auditorium of this
     *                                      location still carries a booking. The FK chain is cascade-on-delete,
     *                                      so without this guard `$location->delete()` silently destroys
     *                                      historical `stripe_payment_intent_id` / `confirmation_code` values
     *                                      needed for refund reconciliation.
     */
    public function deleteLocation(Location $location, ?User $actor = null): void
    {
        if ($location->showtimes()->whereHas('bookings')->exists()) {
            throw new LocationHasBookingsException;
        }

        $this->logIfAdmin('location.deleted', $location, $actor);
        $location->delete();
    }

    // ------------------------------------------------------------------
    // Auditorium
    // ------------------------------------------------------------------

    public function createAuditorium(Location $location, array $attributes, ?User $actor = null): Auditorium
    {
        $auditorium = $location->auditoriums()->create($attributes);

        $this->logIfAdmin('auditorium.created', $auditorium, $actor, $attributes);

        return $auditorium;
    }

    public function updateAuditorium(Auditorium $auditorium, array $attributes, ?User $actor = null): Auditorium
    {
        $auditorium->fill($attributes);
        $dirtyKeys = array_keys($auditorium->getDirty());
        $before = array_intersect_key($auditorium->getOriginal(), array_flip($dirtyKeys));
        $auditorium->save();
        $after = array_intersect_key($auditorium->getAttributes(), array_flip($dirtyKeys));

        if ($dirtyKeys !== []) {
            $this->logIfAdmin('auditorium.updated', $auditorium, $actor, [
                'before' => $before,
                'after' => $after,
            ]);
        }

        return $auditorium;
    }

    /**
     * Create or update an auditorium alongside its section list in one pass.
     * Section reconciliation runs only when `$sections !== null`; pass `null`
     * (the default) when the caller isn't editing sections.
     *
     * @param  array<string, mixed>  $attributes  Auditorium columns only — callers
     *                                            must strip any form-layer keys
     *                                            (e.g. `location_id`) before
     *                                            passing.
     * @param  array<int, array{id?: ?string, name: string, price_multiplier?: float|string, display_order?: int}>|null  $sections
     */
    public function saveAuditoriumWithSections(
        Location $location,
        ?Auditorium $record,
        array $attributes,
        ?array $sections = null,
        ?User $actor = null,
    ): Auditorium {
        // Outer transaction so the auditorium row and its activity_log entry
        // roll back if section reconciliation fails (AuditoriumSectionInUseException,
        // unique-name violation, etc.). updateSectionConfig starts its own
        // nested transaction — Postgres savepoints compose cleanly.
        return DB::transaction(function () use ($location, $record, $attributes, $sections, $actor) {
            $auditorium = $record === null
                ? $this->createAuditorium($location, $attributes, $actor)
                : $this->updateAuditorium($record, $attributes, $actor);

            if ($sections !== null) {
                $this->updateSectionConfig($auditorium, $sections, $actor);
            }

            return $auditorium;
        });
    }

    /**
     * @throws AuditoriumHasBookingsException when any of the auditorium's showtimes
     *                                        still carries a booking. Same rationale as MovieService::delete —
     *                                        the FK cascade would silently destroy historical payment data.
     */
    public function deleteAuditorium(Auditorium $auditorium, ?User $actor = null): void
    {
        if ($auditorium->showtimes()->whereHas('bookings')->exists()) {
            throw new AuditoriumHasBookingsException;
        }

        $this->logIfAdmin('auditorium.deleted', $auditorium, $actor);
        $auditorium->delete();
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    /**
     * Sync the full section list for an auditorium.
     *
     * Each entry in `$sections` looks like:
     *   ['id' => string|null, 'name' => string, 'price_multiplier' => float, 'display_order' => int]
     *
     * Behaviour:
     *   - Entries with an existing `id` are updated (if any attribute changed).
     *   - Entries without an `id` become new `AuditoriumSection` rows.
     *   - Existing sections whose `id` is absent from `$sections` are deleted;
     *     if any such section still has seats, an
     *     `AuditoriumSectionInUseException` is thrown and the whole batch
     *     rolls back — nothing in the auditorium is changed.
     *
     * @param  array<int, array{id?: ?string, name: string, price_multiplier?: float|string, display_order?: int}>  $sections
     */
    public function updateSectionConfig(Auditorium $auditorium, array $sections, ?User $actor = null): void
    {
        DB::transaction(function () use ($auditorium, $sections, $actor) {
            $existing = $auditorium->sections()->get()->keyBy('id');
            $keepIds = [];

            foreach ($sections as $row) {
                $rowId = $row['id'] ?? null;

                // Reject payloads carrying a section id that isn't in this
                // auditorium — the previous silent-create branch would hide
                // corrupted form state and could trigger unintended deletes
                // because the original id would not land in `$keepIds`.
                if ($rowId !== null && ! $existing->has($rowId)) {
                    throw new \InvalidArgumentException(
                        "Section id [{$rowId}] does not belong to auditorium [{$auditorium->id}]."
                    );
                }

                // Strictly-positive price multiplier is a domain invariant: 0.00
                // → free seats, negative → negative line price / Stripe error.
                $multiplier = $row['price_multiplier'] ?? 1.00;
                if ((float) $multiplier <= 0) {
                    throw new InvalidPriceMultiplierException($row['name'], $multiplier);
                }

                if ($rowId !== null) {
                    $section = $existing->get($rowId);
                    $section->fill([
                        'name' => $row['name'],
                        'price_multiplier' => $row['price_multiplier'] ?? 1.00,
                        'display_order' => $row['display_order'] ?? 0,
                    ]);
                    $dirtyKeys = array_keys($section->getDirty());
                    $before = array_intersect_key($section->getOriginal(), array_flip($dirtyKeys));
                    $section->save();

                    if ($dirtyKeys !== []) {
                        $this->logIfAdmin('auditorium.section_updated', $auditorium, $actor, [
                            'section_id' => $section->id,
                            'before' => $before,
                            'after' => array_intersect_key($section->getAttributes(), array_flip($dirtyKeys)),
                        ]);
                    }

                    $keepIds[] = $section->id;
                } else {
                    $section = $auditorium->sections()->create([
                        'name' => $row['name'],
                        'price_multiplier' => $row['price_multiplier'] ?? 1.00,
                        'display_order' => $row['display_order'] ?? 0,
                    ]);

                    $this->logIfAdmin('auditorium.section_created', $auditorium, $actor, [
                        'section_id' => $section->id,
                        'name' => $section->name,
                        'price_multiplier' => (string) $section->price_multiplier,
                        'display_order' => $section->display_order,
                    ]);

                    $keepIds[] = $section->id;
                }
            }

            // Delete any existing section whose id is no longer in the payload.
            // Refuse if any of those sections still has seats pointing at it.
            $toDelete = $existing->whereNotIn('id', $keepIds);

            foreach ($toDelete as $section) {
                $seatCount = $section->seats()->count();
                if ($seatCount > 0) {
                    throw new AuditoriumSectionInUseException($section->name, $seatCount);
                }

                $this->logIfAdmin('auditorium.section_deleted', $auditorium, $actor, [
                    'section_id' => $section->id,
                    'name' => $section->name,
                ]);

                $section->delete();
            }
        });
    }

    // ------------------------------------------------------------------
    // Seats
    // ------------------------------------------------------------------

    /**
     * Count active blockers without throwing. The UI uses this for pre-flight
     * so it can disable the submit button and render a readable summary.
     *
     * @return array{future_showtimes: int, active_bookings: int, held_seats: int}
     */
    public function getRegenerationBlockers(Auditorium $auditorium): array
    {
        $futureShowtimes = $auditorium->showtimes()
            ->where('start_time', '>=', now())
            ->count();

        $activeBookings = DB::table('booking_seats')
            ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
            ->join('seats', 'seats.id', '=', 'booking_seats.seat_id')
            ->where('seats.auditorium_id', $auditorium->id)
            ->whereIn('bookings.status', array_map(fn ($s) => $s->value, BookingStatus::occupyingStatuses()))
            ->distinct()
            ->count('bookings.id');

        $heldSeats = DB::table('seat_holds')
            ->join('seats', 'seats.id', '=', 'seat_holds.seat_id')
            ->where('seats.auditorium_id', $auditorium->id)
            ->where('seat_holds.expires_at', '>', now())
            ->count();

        return [
            'future_showtimes' => $futureShowtimes,
            'active_bookings' => $activeBookings,
            'held_seats' => $heldSeats,
        ];
    }

    /**
     * Destructive full rebuild.
     *
     * `$config` shape (pre-validated by the UI layer — row-range tokens already
     * expanded into letter arrays):
     *
     *   [
     *     'rows'            => int,    // 1..26
     *     'seats_per_row'   => int,    // 1..30
     *     'section_map'     => array<int, array{
     *         rows       => array<int, string>,   // single letters, e.g. ['A','B']
     *         section_id => string,
     *         type       => 'standard'|'premium'|'accessible',
     *     }>,
     *     'unavailable_seats' => array<int, string>,   // e.g. ['A3', 'J11']
     *   ]
     *
     * @throws AuditoriumSeatRegenerationBlockedException when blockers exist
     *                                                    and `$force = false`
     */
    public function generateSeats(
        Auditorium $auditorium,
        array $config,
        ?User $actor = null,
        bool $force = false,
    ): void {
        DB::transaction(function () use ($auditorium, $config, $actor, $force) {
            if (! $force) {
                $blockers = $this->getRegenerationBlockers($auditorium);
                if (array_sum($blockers) > 0) {
                    throw new AuditoriumSeatRegenerationBlockedException($blockers);
                }
            }

            // Reject any section_id in the section_map that doesn't belong to
            // this auditorium — the FK only checks row existence, not
            // ownership, so a UUID from a sibling auditorium's section would
            // otherwise pass and yield cross-venue pricing (see
            // updateSeatBatch for the sibling enforcement of the same rule).
            $referencedSectionIds = array_values(array_unique(array_filter(
                array_column($config['section_map'] ?? [], 'section_id'),
                fn ($v) => $v !== null,
            )));
            if ($referencedSectionIds !== []) {
                $validSectionCount = $auditorium->sections()
                    ->whereIn('id', $referencedSectionIds)
                    ->count();
                if ($validSectionCount !== count($referencedSectionIds)) {
                    throw new \InvalidArgumentException(
                        'One or more section ids do not belong to this auditorium.'
                    );
                }
            }

            // Destructive: wipe existing seats first. Any failure below this
            // point rolls the whole transaction back, so the previous layout
            // is preserved.
            $auditorium->seats()->delete();

            $rowToSection = [];
            $rowToType = [];
            foreach ($config['section_map'] as $assignment) {
                foreach ($assignment['rows'] as $rowLetter) {
                    $rowToSection[$rowLetter] = $assignment['section_id'];
                    $rowToType[$rowLetter] = $assignment['type'];
                }
            }

            $unavailableSet = array_flip($config['unavailable_seats'] ?? []);
            $now = now();
            $rows = (int) $config['rows'];
            $seatsPerRow = (int) $config['seats_per_row'];
            $insert = [];
            $availableCount = 0;

            for ($r = 0; $r < $rows; $r++) {
                $rowLetter = chr(ord('A') + $r);
                for ($s = 1; $s <= $seatsPerRow; $s++) {
                    $label = $rowLetter.$s;
                    $isUnavailable = isset($unavailableSet[$label]);
                    $insert[] = [
                        'id' => (string) Str::uuid(),
                        'auditorium_id' => $auditorium->id,
                        'section_id' => $rowToSection[$rowLetter] ?? null,
                        'label' => $label,
                        'row' => $rowLetter,
                        'number' => $s,
                        'type' => ($rowToType[$rowLetter] ?? SeatType::Standard->value),
                        'unavailable_at' => $isUnavailable ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (! $isUnavailable) {
                        $availableCount++;
                    }
                }
            }

            foreach (array_chunk($insert, 200) as $chunk) {
                Seat::insert($chunk);
            }

            $auditorium->forceFill([
                'total_seats' => $availableCount,
            ])->save();

            $this->logIfAdmin('auditorium.seats_generated', $auditorium, $actor, [
                'rows' => $rows,
                'seats_per_row' => $seatsPerRow,
                'section_map' => $config['section_map'],
                'unavailable_seats' => array_keys($unavailableSet),
                'total_seats_created' => count($insert),
            ]);
        });
    }

    /**
     * Non-destructive batch update for existing seats.
     *
     * Each entry in `$seatUpdates`:
     *   [
     *     'seat_id'        => string,                 // must belong to $auditorium
     *     'section_id'     => ?string (optional),     // must belong to $auditorium
     *     'unavailable_at' => ?\DateTimeInterface|null (optional),
     *   ]
     *
     * The entire batch runs in a single transaction. Any invalid seat id,
     * invalid section id, or seat not belonging to `$auditorium` rolls the
     * whole batch back — no partial updates, no activity rows.
     *
     * NOT blocked by future showtimes or active bookings: seat ids are
     * preserved so bookings stay valid; only section membership and the
     * unavailability flag change.
     *
     * @param  array<int, array{seat_id: string, section_id?: ?string, unavailable_at?: ?\DateTimeInterface}>  $seatUpdates
     */
    public function updateSeatBatch(Auditorium $auditorium, array $seatUpdates, ?User $actor = null): void
    {
        if ($seatUpdates === []) {
            return;
        }

        DB::transaction(function () use ($auditorium, $seatUpdates, $actor) {
            $seatIds = array_values(array_unique(array_column($seatUpdates, 'seat_id')));

            // Pre-validate UUID shape so Postgres doesn't surface a
            // QueryException on the `whereIn(...)` lookup — the service's
            // contract is to raise a typed domain error on bad input.
            foreach ($seatIds as $id) {
                if (! Str::isUuid((string) $id)) {
                    throw new \InvalidArgumentException("Invalid seat id '{$id}'.");
                }
            }

            $seats = $auditorium->seats()->whereIn('id', $seatIds)->get()->keyBy('id');

            if ($seats->count() !== count($seatIds)) {
                // Any seat not loaded either doesn't exist or belongs to a
                // different auditorium. Fail the whole batch.
                throw new \InvalidArgumentException(
                    'One or more seat ids do not belong to this auditorium.'
                );
            }

            // Pre-validate section_ids: every referenced section must belong
            // to this auditorium.
            $referencedSectionIds = array_values(array_unique(array_filter(
                array_map(fn ($u) => $u['section_id'] ?? null, $seatUpdates),
                fn ($v) => $v !== null,
            )));
            if ($referencedSectionIds !== []) {
                $validSectionCount = $auditorium->sections()
                    ->whereIn('id', $referencedSectionIds)
                    ->count();
                if ($validSectionCount !== count($referencedSectionIds)) {
                    throw new \InvalidArgumentException(
                        'One or more section ids do not belong to this auditorium.'
                    );
                }
            }

            foreach ($seatUpdates as $update) {
                $seat = $seats->get($update['seat_id']);

                $dirty = [];
                if (array_key_exists('section_id', $update) && $seat->section_id !== $update['section_id']) {
                    $dirty['section_id'] = [
                        'before' => $seat->section_id,
                        'after' => $update['section_id'],
                    ];
                    $seat->section_id = $update['section_id'];
                }
                if (array_key_exists('unavailable_at', $update)) {
                    $newValue = $update['unavailable_at'];
                    $current = $seat->unavailable_at;
                    $changed = ($newValue === null) !== ($current === null)
                        || ($newValue !== null && $current !== null && ! $current->equalTo($newValue));
                    if ($changed) {
                        $dirty['unavailable_at'] = [
                            'before' => $current?->toIso8601String(),
                            'after' => $newValue?->format('c'),
                        ];
                        $seat->unavailable_at = $newValue;
                    }
                }

                if ($dirty === []) {
                    continue;
                }

                $seat->save();

                $this->logIfAdmin('auditorium.seat_updated', $auditorium, $actor, [
                    'seat_id' => $seat->id,
                    'seat_label' => $seat->label,
                    'changes' => $dirty,
                ]);
            }

            $auditorium->forceFill([
                'total_seats' => $auditorium->seats()->whereNull('unavailable_at')->count(),
            ])->save();
        });
    }

    public function markSeatUnavailable(Seat $seat, ?string $reason = null, ?User $actor = null): void
    {
        if ($seat->unavailable_at !== null) {
            return;
        }

        $seat->forceFill(['unavailable_at' => now()])->save();

        $this->logIfAdmin('auditorium.seat_unavailable', $seat->auditorium, $actor, [
            'seat_id' => $seat->id,
            'seat_label' => $seat->label,
            'reason' => $reason,
        ]);
    }

    public function markSeatAvailable(Seat $seat, ?User $actor = null): void
    {
        if ($seat->unavailable_at === null) {
            return;
        }

        $seat->forceFill(['unavailable_at' => null])->save();

        $this->logIfAdmin('auditorium.seat_available', $seat->auditorium, $actor, [
            'seat_id' => $seat->id,
            'seat_label' => $seat->label,
        ]);
    }

    /**
     * Temporarily close a whole section (admin-v6 S7) — e.g. for maintenance.
     * Every seat in the section is then treated as unavailable by
     * `SeatAvailabilityService` and rendered 'taken' on the seat map, without
     * touching per-seat `unavailable_at` (so individually-flagged seats keep
     * their own state across a close/reopen cycle). Idempotent.
     */
    public function closeSection(AuditoriumSection $section, ?string $reason = null, ?User $actor = null): void
    {
        if ($section->closed_at !== null) {
            return;
        }

        $section->forceFill(['closed_at' => now()])->save();

        $this->logIfAdmin('auditorium.section_closed', $section->auditorium, $actor, [
            'section_id' => $section->id,
            'section_name' => $section->name,
            'reason' => $reason,
        ]);
    }

    /** Reopen a temporarily-closed section (admin-v6 S7). Idempotent. */
    public function reopenSection(AuditoriumSection $section, ?User $actor = null): void
    {
        if ($section->closed_at === null) {
            return;
        }

        $section->forceFill(['closed_at' => null])->save();

        $this->logIfAdmin('auditorium.section_reopened', $section->auditorium, $actor, [
            'section_id' => $section->id,
            'section_name' => $section->name,
        ]);
    }
}
