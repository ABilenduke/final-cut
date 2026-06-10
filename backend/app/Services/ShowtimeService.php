<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\MovieRuntimeMissingException;
use App\Exceptions\ShowtimeAlreadyCancelledException;
use App\Exceptions\ShowtimeConflictException;
use App\Exceptions\ShowtimeHasBookingsException;
use App\Http\Requests\BulkShowtimeRequest;
use App\Models\Auditorium;
use App\Models\DispatchOutbox;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Write boundary for every admin showtime mutation — and a narrower gateway
 * (sans actor) for any customer-originated writes that might land here in the
 * future. All writes:
 *
 * - Compute `end_time = start_time + movie.runtime + auditorium.cleanup_minutes`.
 * - Refuse when movie.runtime is NULL (`MovieRuntimeMissingException`).
 * - Surface Postgres exclusion violations (SQLSTATE 23P01 from the
 *   `showtimes_no_overlap` constraint) as `ShowtimeConflictException` so the
 *   UI path matches the pre-submit detectConflicts path.
 * - Emit `activity_log` rows on the 'admin' channel when `$actor !== null`
 *   via `LogsAdminActivity::logIfAdmin` (skipped for customer writes).
 */
class ShowtimeService
{
    use LogsAdminActivity;

    /** Postgres SQLSTATE for exclusion_violation — `showtimes_no_overlap` raises this. */
    private const SQLSTATE_EXCLUSION_VIOLATION = '23P01';

    /** Activity-log descriptions + dispatch_outbox event types. */
    public const EVENT_CREATED = 'showtime.created';

    public const EVENT_UPDATED = 'showtime.updated';

    public const EVENT_CANCELLED = 'showtime.cancelled';

    /**
     * @param  array{movie_id: int, auditorium_id: string, start_time: CarbonInterface|string, price_standard: int, price_premium: int, price_accessible: int}  $attributes
     */
    public function create(array $attributes, ?User $actor = null): Showtime
    {
        [$movie, $auditorium, $start] = $this->resolveInputs($attributes);
        $end = self::computeEndTime($movie, $auditorium, $start);

        try {
            $showtime = DB::transaction(function () use ($attributes, $start, $end, $actor, $movie, $auditorium) {
                $showtime = Showtime::create([
                    'movie_id' => $movie->id,
                    'auditorium_id' => $auditorium->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'price_standard' => (int) $attributes['price_standard'],
                    'price_premium' => (int) $attributes['price_premium'],
                    'price_accessible' => (int) $attributes['price_accessible'],
                ]);

                $this->logIfAdmin(self::EVENT_CREATED, $showtime, $actor, [
                    'movie_id' => $movie->id,
                    'auditorium_id' => $auditorium->id,
                    'start_time' => $start->toIso8601String(),
                    'end_time' => $end->toIso8601String(),
                ]);

                return $showtime;
            });
        } catch (QueryException $e) {
            throw $this->translateExclusionViolation($e, $auditorium->id, $start, $end);
        }

        return $showtime;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Showtime $showtime, array $attributes, ?User $actor = null): Showtime
    {
        // Resolve movie/auditorium/start from a blended view: requested attributes
        // override the existing record, so partial updates work correctly.
        $blended = array_merge([
            'movie_id' => $showtime->movie_id,
            'auditorium_id' => $showtime->auditorium_id,
            'start_time' => $showtime->start_time,
        ], array_intersect_key($attributes, array_flip(['movie_id', 'auditorium_id', 'start_time'])));

        [$movie, $auditorium, $start] = $this->resolveInputs($blended);
        $end = self::computeEndTime($movie, $auditorium, $start);

        // Refuse structural edits (movie / auditorium / start_time) once any
        // booking exists — booking_seats point at the *current* auditorium's
        // seat rows, and silently re-parenting the showtime would orphan them
        // and hand the house a double-booking window. Pricing-only edits are
        // still allowed (they don't touch booking_seats).
        $structurallyChanged = (string) $movie->id !== (string) $showtime->movie_id
            || (string) $auditorium->id !== (string) $showtime->auditorium_id
            || ! $start->equalTo($showtime->start_time);

        try {
            $updated = DB::transaction(function () use ($showtime, $attributes, $movie, $auditorium, $start, $end, $actor, $structurallyChanged) {
                // Re-read under a row lock so the booking-count guard is
                // serialized against the customer booking flow, which also takes
                // lockForUpdate on this showtime before reserving seats. The
                // check used to run *outside* the transaction, leaving a TOCTOU
                // window where a booking could commit between the count and the
                // structural save — orphaning its booking_seats on the old
                // layout / silently re-parenting a sold showtime. (mirrors the
                // lock-first pattern in cancel().)
                $locked = Showtime::whereKey($showtime->id)->lockForUpdate()->firstOrFail();

                if ($structurallyChanged) {
                    $bookingCount = $locked->bookings()
                        ->whereIn('status', BookingStatus::occupyingStatuses())
                        ->count();

                    if ($bookingCount > 0) {
                        throw new ShowtimeHasBookingsException($locked, $bookingCount);
                    }
                }

                $locked->fill([
                    'movie_id' => $movie->id,
                    'auditorium_id' => $auditorium->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'price_standard' => (int) ($attributes['price_standard'] ?? $locked->price_standard),
                    'price_premium' => (int) ($attributes['price_premium'] ?? $locked->price_premium),
                    'price_accessible' => (int) ($attributes['price_accessible'] ?? $locked->price_accessible),
                ]);

                $dirtyKeys = array_keys($locked->getDirty());
                $before = array_intersect_key($locked->getOriginal(), array_flip($dirtyKeys));
                $locked->save();
                $after = array_intersect_key($locked->getAttributes(), array_flip($dirtyKeys));

                if ($dirtyKeys !== []) {
                    $this->logIfAdmin(self::EVENT_UPDATED, $locked, $actor, [
                        'before' => $before,
                        'after' => $after,
                    ]);
                }

                return $locked;
            });
        } catch (QueryException $e) {
            throw $this->translateExclusionViolation($e, $auditorium->id, $start, $end, $showtime->id);
        }

        return $updated;
    }

    /**
     * Cancel a scheduled showtime. Idempotent under concurrent callers: the
     * second admin to submit receives `ShowtimeAlreadyCancelledException`
     * rather than re-flagging bookings or writing duplicate outbox rows.
     *
     * Writes one `dispatch_outbox` row per currently-un-flagged booking inside
     * the same transaction as the cancellation. Plan 09's worker drains the
     * outbox and actually dispatches `NotifyCustomerOfShowtimeCancellation`.
     */
    public function cancel(Showtime $showtime, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($showtime, $reason, $actor) {
            /** @var Showtime $fresh */
            $fresh = Showtime::whereKey($showtime->id)->lockForUpdate()->firstOrFail();

            if ($fresh->cancelled_at !== null) {
                throw new ShowtimeAlreadyCancelledException($fresh);
            }

            $fresh->update([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Scope to bookings that currently occupy a seat. `flagged_at IS NULL`
            // alone would include already-cancelled and already-refunded bookings
            // (neither flow sets `flagged_at`), and the update below would force
            // them back to `refund_pending`, silently undoing a completed refund.
            $bookingIds = $fresh->bookings()
                ->whereIn('status', BookingStatus::occupyingStatuses())
                ->whereNull('flagged_at')
                ->pluck('id');

            if ($bookingIds->isNotEmpty()) {
                // Transition to RefundPending alongside flagging: it's in
                // `occupyingStatuses()` so the seat remains blocked at this
                // (now-cancelled) showtime, AND the customer-facing upcoming
                // bookings endpoint only returns `Confirmed`, so the booking
                // drops out of the customer's active list immediately rather
                // than lingering until the manual refund is recorded.
                $fresh->bookings()
                    ->whereIn('id', $bookingIds)
                    ->update([
                        'status' => BookingStatus::RefundPending,
                        'flagged_at' => now(),
                        'flag_reason' => "showtime_cancelled:{$fresh->id}",
                    ]);
            }

            $this->logIfAdmin(self::EVENT_CANCELLED, $fresh, $actor, [
                'reason' => $reason,
                'flagged_bookings' => $bookingIds->count(),
            ]);

            // Bulk-insert outbox rows — N individual create() calls multiply the
            // lock time of this transaction for fully booked auditoriums.
            if ($bookingIds->isNotEmpty()) {
                $timestamp = now();
                $outboxRows = $bookingIds
                    ->map(fn ($id) => [
                        'event_type' => self::EVENT_CANCELLED,
                        'payload' => json_encode([
                            'booking_id' => $id,
                            'showtime_id' => $fresh->id,
                        ], JSON_THROW_ON_ERROR),
                        'available_at' => $timestamp,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->all();

                DispatchOutbox::insert($outboxRows);
            }
        });
    }

    /**
     * Insert every tuple in `$request->tuples` for the same auditorium + movie
     * inside a single transaction. Any failure rolls back the whole batch —
     * there is no partial-success path. Conflicts were already filtered out
     * upstream; a TOCTOU conflict inside the transaction still rolls back
     * cleanly via `ShowtimeConflictException`.
     *
     * @return Collection<int, Showtime>
     */
    public function bulkCreate(BulkShowtimeRequest $request, ?User $actor = null): Collection
    {
        $movie = Movie::findOrFail($request->movieId);
        $auditorium = Auditorium::findOrFail($request->auditoriumId);

        if ($movie->runtime === null) {
            throw new MovieRuntimeMissingException($movie);
        }

        try {
            return DB::transaction(function () use ($request, $movie, $auditorium, $actor) {
                $created = new Collection;

                foreach ($request->tuples as $tuple) {
                    $start = $tuple['start_time'];
                    $end = self::computeEndTime($movie, $auditorium, $start);

                    $showtime = Showtime::create([
                        'movie_id' => $movie->id,
                        'auditorium_id' => $auditorium->id,
                        'start_time' => $start,
                        'end_time' => $end,
                        'price_standard' => $request->priceStandard,
                        'price_premium' => $request->pricePremium,
                        'price_accessible' => $request->priceAccessible,
                    ]);

                    $this->logIfAdmin(self::EVENT_CREATED, $showtime, $actor, [
                        'movie_id' => $movie->id,
                        'auditorium_id' => $auditorium->id,
                        'start_time' => $start->toIso8601String(),
                        'end_time' => $end->toIso8601String(),
                        'via' => 'bulk',
                    ]);

                    $created->push($showtime);
                }

                return $created;
            });
        } catch (QueryException $e) {
            throw $this->translateExclusionViolation($e, $auditorium->id, null, null);
        }
    }

    /**
     * Build a copy plan for an entire week's schedule (admin-v2 Plan 06):
     * every non-cancelled showtime in `[sourceWeekStart 00:00, +7 days)` —
     * window interpreted in the app timezone — shifted to the target week
     * preserving the VENUE-LOCAL wall-clock time. `addDays` on a tz-aware
     * Carbon keeps the local clock across DST transitions (the UTC instant
     * absorbs the offset change), which is what a cinema schedule wants: the
     * 19:00 show stays the 19:00 show.
     *
     * End times are recomputed via `computeEndTime` (runtime/cleanup may have
     * changed since the source week); movies whose runtime went NULL are
     * partitioned out rather than failing the plan. Conflicts against the
     * target week come from `detectConflictsForBatch` per auditorium. A
     * uniform wall-clock shift preserves the source week's non-overlap except
     * in pathological DST edge cases — the DB EXCLUDE constraint stays the
     * final guard at commit time.
     *
     * @return array{
     *     rows: list<array{source: Showtime, start: CarbonInterface, end: CarbonInterface, conflicts: Collection<int, Showtime>}>,
     *     skipped_missing_runtime: Collection<int, Showtime>,
     * }
     */
    public function buildWeekCopyPlan(
        CarbonInterface $sourceWeekStart,
        CarbonInterface $targetWeekStart,
        ?string $locationId = null,
    ): array {
        $sourceStart = $sourceWeekStart->copy()->startOfDay();
        $targetStart = $targetWeekStart->copy()->startOfDay();

        // DateInterval->days is an absolute whole-day count on any Carbon
        // version; re-apply the sign from the comparison.
        $daysDelta = $sourceStart->diff($targetStart)->days
            * ($targetStart->greaterThanOrEqualTo($sourceStart) ? 1 : -1);

        $sources = Showtime::query()
            ->with('movie', 'auditorium.location')
            ->whereNull('cancelled_at')
            ->where('start_time', '>=', $sourceStart)
            ->where('start_time', '<', $sourceStart->copy()->addDays(7))
            ->when($locationId, fn ($q, $id) => $q->whereHas(
                'auditorium',
                fn ($a) => $a->where('location_id', $id),
            ))
            ->orderBy('start_time')
            ->get();

        [$copyable, $skippedMissingRuntime] = $sources->partition(
            fn (Showtime $s) => $s->movie->runtime !== null,
        );

        /** @var list<array{source: Showtime, start: CarbonInterface, end: CarbonInterface, conflicts: Collection<int, Showtime>}> $rows */
        $rows = [];
        foreach ($copyable->values() as $showtime) {
            $tz = $showtime->auditorium->location->timezone ?? config('app.timezone');

            $start = $showtime->start_time->copy()
                ->setTimezone($tz)
                ->addDays($daysDelta)
                ->setTimezone(config('app.timezone'));

            $rows[] = [
                'source' => $showtime,
                'start' => $start,
                'end' => self::computeEndTime($showtime->movie, $showtime->auditorium, $start),
                'conflicts' => new Collection,
            ];
        }

        // Conflict lookup is per-auditorium (detectConflictsForBatch's scope);
        // group row indexes by auditorium, then write results back in place.
        $byAuditorium = [];
        foreach ($rows as $idx => $row) {
            $byAuditorium[(string) $row['source']->auditorium_id][] = $idx;
        }

        foreach ($byAuditorium as $auditoriumId => $indexes) {
            $intervals = new Collection(array_map(
                fn (int $i): array => ['start' => $rows[$i]['start'], 'end' => $rows[$i]['end']],
                $indexes,
            ));

            $conflicts = $this->detectConflictsForBatch($auditoriumId, $intervals);

            foreach ($indexes as $position => $idx) {
                $rows[$idx]['conflicts'] = $conflicts[$position];
            }
        }

        return [
            'rows' => $rows,
            'skipped_missing_runtime' => $skippedMissingRuntime->values(),
        ];
    }

    /**
     * Create every row from a copy plan inside a single transaction
     * (admin-v2 Plan 06). Unlike bulkCreate, rows span movies AND
     * auditoriums. Any failure rolls back the whole batch; a TOCTOU conflict
     * that committed after the preview trips the EXCLUDE constraint and
     * surfaces as `ShowtimeConflictException`.
     *
     * @param  Collection<int, array{movie_id: int|string, auditorium_id: string, start_time: CarbonInterface, price_standard: int, price_premium: int, price_accessible: int}>  $rows
     * @return Collection<int, Showtime>
     */
    public function copyWeek(Collection $rows, ?User $actor = null): Collection
    {
        try {
            return DB::transaction(function () use ($rows, $actor) {
                $created = new Collection;

                foreach ($rows as $row) {
                    [$movie, $auditorium, $start] = $this->resolveInputs([
                        'movie_id' => $row['movie_id'],
                        'auditorium_id' => $row['auditorium_id'],
                        'start_time' => $row['start_time'],
                    ]);
                    $end = self::computeEndTime($movie, $auditorium, $start);

                    $showtime = Showtime::create([
                        'movie_id' => $movie->id,
                        'auditorium_id' => $auditorium->id,
                        'start_time' => $start,
                        'end_time' => $end,
                        'price_standard' => (int) $row['price_standard'],
                        'price_premium' => (int) $row['price_premium'],
                        'price_accessible' => (int) $row['price_accessible'],
                    ]);

                    $this->logIfAdmin(self::EVENT_CREATED, $showtime, $actor, [
                        'movie_id' => $movie->id,
                        'auditorium_id' => $auditorium->id,
                        'start_time' => $start->toIso8601String(),
                        'end_time' => $end->toIso8601String(),
                        'via' => 'copy_week',
                    ]);

                    $created->push($showtime);
                }

                return $created;
            });
        } catch (QueryException $e) {
            // No single interval to attribute — the translated exception
            // carries an empty conflict list; the page shows the generic
            // "re-run the preview" message.
            throw $this->translateExclusionViolation(
                $e,
                (string) ($rows->first()['auditorium_id'] ?? ''),
                null,
                null,
            );
        }
    }

    /**
     * UX affordance only — not an authoritative guard. The DB's EXCLUDE
     * constraint is the source of truth; this method is for friendly
     * pre-submit errors. Half-open interval rule: intervals `[a.start, a.end)`
     * and `[b.start, b.end)` overlap iff `a.start < b.end AND a.end > b.start`.
     * Back-to-back transitions (prev.end == next.start) register as NO conflict.
     *
     * @return Collection<int, Showtime>
     */
    public function detectConflicts(
        string $auditoriumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $ignoreShowtimeId = null,
    ): Collection {
        return Showtime::query()
            ->where('auditorium_id', $auditoriumId)
            ->whereNull('cancelled_at')
            ->when($ignoreShowtimeId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->with('movie:id,title')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Batched conflict lookup for bulk-create preview — one query for N
     * intervals instead of N separate `detectConflicts()` calls.
     *
     * Strategy: fetch every live showtime whose window *could* touch the full
     * span of requested intervals, then partition in PHP. The range filter
     * still uses the `(auditorium_id, start_time)` index; only the in-memory
     * partition is O(N × existing) which is negligible compared to N round
     * trips.
     *
     * @param  Collection<int, array{start: CarbonInterface, end: CarbonInterface}>  $intervals
     * @return Collection<int, Collection<int, Showtime>> Same ordering as `$intervals`; empty collections mean "no conflict".
     */
    public function detectConflictsForBatch(string $auditoriumId, Collection $intervals): Collection
    {
        if ($intervals->isEmpty()) {
            return new Collection;
        }

        $batchStart = $intervals->map(fn (array $i) => $i['start'])->min();
        $batchEnd = $intervals->map(fn (array $i) => $i['end'])->max();

        $candidates = Showtime::query()
            ->where('auditorium_id', $auditoriumId)
            ->whereNull('cancelled_at')
            ->where('start_time', '<', $batchEnd)
            ->where('end_time', '>', $batchStart)
            ->with('movie:id,title')
            ->orderBy('start_time')
            ->get();

        /** @var Collection<int, Collection<int, Showtime>> $partitioned */
        $partitioned = new Collection;

        foreach ($intervals as $interval) {
            $partitioned->push(
                new Collection(
                    $candidates->filter(
                        fn (Showtime $s) => $s->start_time < $interval['end']
                            && $s->end_time > $interval['start']
                    )->values()->all()
                )
            );
        }

        return $partitioned;
    }

    /**
     * Resolve + validate the trio of inputs every write depends on: movie,
     * auditorium, and start time. Enforces the runtime precondition once.
     *
     * @param  array{movie_id: mixed, auditorium_id: mixed, start_time: mixed}  $attributes
     * @return array{0: Movie, 1: Auditorium, 2: CarbonInterface}
     */
    private function resolveInputs(array $attributes): array
    {
        $movie = Movie::findOrFail($attributes['movie_id']);
        $auditorium = Auditorium::findOrFail($attributes['auditorium_id']);

        if ($movie->runtime === null) {
            throw new MovieRuntimeMissingException($movie);
        }

        $start = $attributes['start_time'] instanceof CarbonInterface
            ? $attributes['start_time']->copy()
            : Carbon::parse($attributes['start_time']);

        return [$movie, $auditorium, $start];
    }

    /**
     * Compute a showtime's end time from movie runtime + auditorium cleanup.
     * Public + static so the Filament preview and bulk-create page can reuse
     * the same rule the service uses on write — one source of truth.
     *
     * Assumes `$movie->runtime` is non-null; the service's write paths enforce
     * that invariant via `MovieRuntimeMissingException` before calling in.
     */
    public static function computeEndTime(Movie $movie, Auditorium $auditorium, CarbonInterface $start): CarbonInterface
    {
        return $start->copy()->addMinutes((int) $movie->runtime + (int) ($auditorium->cleanup_minutes ?? 0));
    }

    /**
     * Translate a Postgres exclusion-violation QueryException into our domain
     * exception. Re-throws unrelated QueryExceptions untouched so real failures
     * still surface.
     */
    private function translateExclusionViolation(
        QueryException $e,
        string $auditoriumId,
        ?CarbonInterface $start,
        ?CarbonInterface $end,
        ?string $ignoreShowtimeId = null,
    ): \Throwable {
        // QueryException's code comes through as a string; Postgres SQLSTATE
        // lives on the driver error. Both paths are checked to stay robust
        // across driver versions.
        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        if ($sqlState !== self::SQLSTATE_EXCLUSION_VIOLATION) {
            return $e;
        }

        // Pull the concrete conflicting rows so the exception carries useful
        // data for the UI — the EXCLUDE DETAIL message has ranges, not IDs.
        /** @var Collection<int, array<string, mixed>> $conflicts */
        $conflicts = new Collection;

        if ($start && $end) {
            $conflicts = new Collection(
                $this->detectConflicts($auditoriumId, $start, $end, $ignoreShowtimeId)
                    ->map(fn (Showtime $s): array => [
                        'id' => $s->id,
                        'movie_title' => $s->movie->title,
                        'start_time' => $s->start_time->toIso8601String(),
                        'end_time' => $s->end_time->toIso8601String(),
                    ])->all()
            );
        }

        return new ShowtimeConflictException($conflicts);
    }
}
