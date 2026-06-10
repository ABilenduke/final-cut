<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Showtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/cinema-readout (admin-v4 Plan 04) — live operational stats for
 * the what's-on Bridge Console readout, derived from real data instead of
 * the v1 static stub. Day window follows the venue-default timezone (the
 * TodayKpisWidget convention); cached five minutes — this is ambient
 * telemetry, not a booking surface.
 */
class CinemaReadoutController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::remember('cinema_readout', 300, function (): array {
            [$dayStart, $dayEnd] = $this->dayBounds();

            $today = Showtime::query()
                ->whereNull('cancelled_at')
                ->where('start_time', '>=', $dayStart)
                ->where('start_time', '<', $dayEnd)
                ->with('auditorium')
                ->orderBy('start_time')
                ->get();

            $late = $today->last();

            $seatsLeft = null;
            if ($today->isNotEmpty()) {
                $capacity = $today->sum(fn (Showtime $showtime) => (int) $showtime->auditorium->total_seats);
                $occupied = BookingSeat::query()
                    ->whereIn('showtime_id', $today->pluck('id'))
                    ->where('occupies_seat', true)
                    ->count();
                $seatsLeft = max(0, $capacity - $occupied);
            }

            return [
                'screeningsToday' => $today->count(),
                'doorsOpen' => $this->earliestOpenToday(),
                'lateShowing' => $late === null ? null : [
                    'time' => $late->start_time->setTimezone($this->displayTimezone())->format('G:i'),
                    'auditorium' => $late->auditorium->name,
                ],
                'seatsLeftTonight' => $seatsLeft,
            ];
        });

        return $this->successResponse($data);
    }

    /** Earliest opening hour among venues for today's weekday, or null. */
    private function earliestOpenToday(): ?string
    {
        $weekday = strtolower(now($this->displayTimezone())->format('l'));

        return Location::query()
            ->get()
            ->map(function (Location $location) use ($weekday): ?string {
                $hours = $location->hours;
                if (! is_array($hours)) {
                    return null;
                }
                $open = $hours[$weekday]['open'] ?? null;

                return is_string($open) && $open !== '' ? $open : null;
            })
            ->filter()
            ->sort()
            ->first();
    }

    private function displayTimezone(): string
    {
        $tz = config('app.default_location_timezone');

        return is_string($tz) && trim($tz) !== '' ? $tz : (string) config('app.timezone');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dayBounds(): array
    {
        $startLocal = now($this->displayTimezone())->startOfDay();

        return [
            $startLocal->copy()->setTimezone(config('app.timezone')),
            $startLocal->copy()->addDay()->setTimezone(config('app.timezone')),
        ];
    }
}
