<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Concerns\FormatsCurrency;
use App\Models\Booking;
use App\Models\Showtime;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "How is today going" stats (admin-v2 Plan 07). "Today" means the VENUE day:
 * boundaries are computed in `config('app.default_location_timezone')`
 * (falling back to the app timezone) and converted to UTC for querying.
 */
class TodayKpisWidget extends StatsOverviewWidget
{
    use FormatsCurrency;

    protected ?string $heading = 'Today';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('bookings.view') ?? false;
    }

    /** @return array{bookings: int, revenue: int, showtimes: int} */
    public static function metrics(): array
    {
        [$dayStart, $dayEnd] = self::venueDayBounds();

        $confirmedToday = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        return [
            'bookings' => (clone $confirmedToday)->count(),
            'revenue' => (int) (clone $confirmedToday)->sum('total'),
            'showtimes' => Showtime::query()
                ->whereNull('cancelled_at')
                ->whereBetween('start_time', [$dayStart, $dayEnd])
                ->count(),
        ];
    }

    protected function getStats(): array
    {
        $metrics = self::metrics();

        return [
            Stat::make('Bookings', number_format($metrics['bookings']))
                ->description('confirmed today')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('primary'),
            Stat::make('Revenue', self::centsToDisplay($metrics['revenue']))
                ->description('confirmed bookings today')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Showtimes', number_format($metrics['showtimes']))
                ->description('scheduled today')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info'),
        ];
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} UTC instants bounding the venue day. */
    public static function venueDayBounds(): array
    {
        $tz = config('app.default_location_timezone') ?? config('app.timezone');

        $start = now($tz)->startOfDay()->setTimezone(config('app.timezone'));

        return [$start, $start->copy()->addDay()];
    }
}
