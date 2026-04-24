<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Support\Collection;

/**
 * Thrown by `ShowtimeService` when a conflicting showtime already occupies
 * part of the requested interval in the same auditorium. Two sources:
 *
 * 1. Pre-submit UX affordance — `detectConflicts()` returned rows before
 *    the write was attempted. The Filament form surfaces this as a
 *    readable validation error listing collisions.
 * 2. TOCTOU guard — the `showtimes_no_overlap` EXCLUDE constraint (Postgres
 *    SQLSTATE 23P01) fired because another transaction committed a
 *    conflicting row between the pre-check and the insert. The service
 *    translates the SQLSTATE into this exception carrying the same shape
 *    so both paths render identically.
 */
class ShowtimeConflictException extends DomainException
{
    /**
     * @param  Collection<int, array<string, mixed>>  $conflicts  Each row carries `id`, `movie_title`, `start_time`, `end_time`.
     */
    public function __construct(public readonly Collection $conflicts)
    {
        parent::__construct(
            'Showtime conflicts with '.self::formatConflicts($conflicts).'. '
                .'Reschedule or cancel the conflicting showtime and retry.'
        );
    }

    /** @param Collection<int, array<string, mixed>> $conflicts */
    public static function formatConflicts(Collection $conflicts): string
    {
        if ($conflicts->isEmpty()) {
            return 'an existing showtime in the same auditorium';
        }

        return $conflicts
            ->map(function (array $row): string {
                $label = $row['movie_title'] ?? 'Showtime';
                $start = $row['start_time'] ?? '?';

                return "{$label} at {$start}";
            })
            ->join(', ');
    }
}
