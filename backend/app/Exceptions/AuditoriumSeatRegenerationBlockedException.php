<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Support\Str;

/**
 * Thrown when `AuditoriumService::generateSeats` would destroy seats that
 * are still referenced by live showtimes, active bookings, or active seat
 * holds. The UI renders the structured blocker counts so staff can resolve
 * them before retrying.
 */
class AuditoriumSeatRegenerationBlockedException extends DomainException
{
    /**
     * @param  array{future_showtimes: int, active_bookings: int, held_seats: int}  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct(
            'Seat regeneration refused — the auditorium has live dependencies: '
            .self::formatBlockers($blockers).'. Cancel or reschedule these first, then retry.'
        );
    }

    /**
     * Shared formatter used by this exception and by the Filament page's
     * pre-flight blocker banner.
     *
     * @param  array{future_showtimes: int, active_bookings: int, held_seats: int}  $blockers
     */
    public static function formatBlockers(array $blockers): string
    {
        $parts = collect([
            'future showtime' => $blockers['future_showtimes'],
            'active booking' => $blockers['active_bookings'],
            'held seat' => $blockers['held_seats'],
        ])
            ->filter(fn (int $n) => $n > 0)
            ->map(fn (int $n, string $word) => $n.' '.Str::plural($word, $n))
            ->values();

        return $parts->isEmpty() ? 'unknown blockers' : $parts->join(', ');
    }
}
