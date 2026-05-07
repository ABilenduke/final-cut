<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CalendarEventType;
use App\Models\BookingSeat;
use App\Models\CalendarEvent;
use App\Models\Showtime;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ShowtimeCalendarProjector
{
    /**
     * Synthesize CalendarEvent rows from the showtimes table for a date range.
     *
     * Showtimes never live as physical rows in `calendar_events` — the customer
     * calendar API merges them on the fly so the showtimes table stays the
     * single source of truth (matching the contract documented on
     * `App\Filament\Resources\CalendarEventResource`).
     *
     * Multiple showtimes for the same movie at the same location on the same
     * local date collapse to a single entry, but the individual screening
     * times are exposed via the synthetic `showtimes` attribute so the
     * customer-side detail rail can render the per-screening tile grid
     * without a second round-trip.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForRange(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?string $locationSlug = null,
    ): Collection {
        // Showtimes are bucketed by their *venue-local* date, not by their
        // stored UTC value. Pad the UTC fetch window by ±1 day so a showtime
        // whose UTC stamp crosses midnight relative to its venue (e.g. 23:00
        // PT on local-day-end → 06:00 UTC next day) is still considered, then
        // post-filter the synthesized groups by computed local date so
        // showtimes whose local date falls outside the requested range are
        // dropped (e.g. 19:00 PT on local-prev-day → 02:00 UTC of the first
        // day in the requested UTC bucket). ±1 day covers UTC-12 → UTC+14.
        $query = Showtime::query()
            ->with(['movie', 'auditorium.location'])
            ->whereNull('cancelled_at')
            ->whereBetween('start_time', [
                $startDate->copy()->startOfDay()->subDay(),
                $endDate->copy()->endOfDay()->addDay(),
            ]);

        if ($locationSlug !== null && $locationSlug !== '') {
            $query->whereHas(
                'auditorium.location',
                fn ($q) => $q->where('slug', $locationSlug),
            );
        }

        $showtimes = $query->get();

        $occupyingSeatCounts = $this->occupyingSeatCounts(
            $showtimes->pluck('id')->all(),
        );

        $startKey = $startDate->copy()->toDateString();
        $endKey = $endDate->copy()->toDateString();

        return $showtimes
            ->groupBy(fn (Showtime $s) => $this->groupKey($s))
            ->map(fn (Collection $group) => $this->synthesize($group, $occupyingSeatCounts))
            ->filter(function (CalendarEvent $event) use ($startKey, $endKey) {
                $localDate = $event->date->toDateString();

                return $localDate >= $startKey && $localDate <= $endKey;
            })
            ->values();
    }

    private function groupKey(Showtime $showtime): string
    {
        $tz = $showtime->auditorium->location->timezone;
        $localDate = $showtime->start_time->copy()->setTimezone($tz)->toDateString();

        return implode('|', [
            $showtime->movie_id,
            $showtime->auditorium->location_id,
            $localDate,
        ]);
    }

    /**
     * Bulk-fetch occupying seat counts for a list of showtimes in a single
     * query so per-tile sold-out computation stays O(1) per showtime.
     *
     * @param  string[]  $showtimeIds
     * @return array<string, int> Map of showtime ID → count of seats whose
     *                            booking sits in `occupyingStatuses()`.
     */
    private function occupyingSeatCounts(array $showtimeIds): array
    {
        if (empty($showtimeIds)) {
            return [];
        }

        return BookingSeat::query()
            ->whereIn('showtime_id', $showtimeIds)
            ->whereHas(
                'booking',
                fn ($q) => $q->whereIn('status', BookingStatus::occupyingStatuses()),
            )
            ->selectRaw('showtime_id, count(*) as occupying_count')
            ->groupBy('showtime_id')
            ->pluck('occupying_count', 'showtime_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  Collection<int, Showtime>  $group
     * @param  array<string, int>  $occupyingSeatCounts
     */
    private function synthesize(Collection $group, array $occupyingSeatCounts): CalendarEvent
    {
        /** @var Showtime $first */
        $first = $group->first();
        $movie = $first->movie;
        $location = $first->auditorium->location;
        $localDate = $first->start_time->copy()->setTimezone($location->timezone)->toDateString();

        $earliestStart = $group->reduce(
            fn (?CarbonInterface $carry, Showtime $s) => $carry === null || $s->start_time->lt($carry)
                ? $s->start_time
                : $carry,
        );
        $latestEnd = $group->reduce(
            fn (?CarbonInterface $carry, Showtime $s) => $carry === null || $s->end_time->gt($carry)
                ? $s->end_time
                : $carry,
        );

        $showtimePayload = $group
            ->sortBy(fn (Showtime $s) => $s->start_time->getTimestamp())
            ->values()
            ->map(fn (Showtime $s) => [
                'id' => $s->id,
                'startTime' => $s->start_time->copy()->setTimezone($location->timezone)->toIso8601String(),
                'auditoriumLabel' => $s->auditorium->name,
                'soldOut' => $this->isSoldOut($s, $occupyingSeatCounts),
            ])
            ->all();

        // The Bridge calendar surfaces format times via `formatWireTime`, which
        // reads the literal HH:MM out of the ISO string without applying any
        // timezone conversion. Convert the synthesized event's start/end to
        // venue-local time so the day-cell event lines render the venue's
        // wall-clock value rather than UTC. The `datetime` cast roundtrip
        // preserves the wall-clock portion (formatted as a tz-naive string,
        // re-parsed as UTC on read) — semantically the emitted ISO claims UTC
        // but the wall-clock matches the venue, which is the only contract
        // the consuming surfaces care about. The embedded `showtimes[]`
        // payload below carries proper venue-local ISO with offsets for the
        // detail-rail tile grid.
        return (new CalendarEvent)->forceFill([
            'id' => "showtime-{$movie->id}-{$location->id}-{$localDate}",
            'type' => CalendarEventType::Showtime,
            'title' => $movie->title,
            'date' => $localDate,
            'start_time' => $earliestStart->copy()->setTimezone($location->timezone),
            'end_time' => $latestEnd->copy()->setTimezone($location->timezone),
            'description' => null,
            'movie_slug' => $movie->slug,
            'image_path' => null,
            'image_url' => $movie->poster_url,
            'slug' => null,
            'ticket_url' => null,
            'loyalty_only' => false,
            'accessibility_tags' => [],
            'location_id' => $location->id,
            'showtimes' => $showtimePayload,
        ]);
    }

    /**
     * @param  array<string, int>  $occupyingSeatCounts
     */
    private function isSoldOut(Showtime $showtime, array $occupyingSeatCounts): bool
    {
        $capacity = (int) ($showtime->auditorium->total_seats ?? 0);
        if ($capacity <= 0) {
            return false;
        }

        $occupied = $occupyingSeatCounts[$showtime->id] ?? 0;

        return $occupied >= $capacity;
    }
}
