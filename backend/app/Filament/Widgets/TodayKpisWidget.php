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

        // Half-open [$dayStart, $dayEnd): whereBetween's inclusive upper
        // bound would double-count anything landing exactly on midnight.
        $confirmedToday = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $dayEnd);

        return [
            'bookings' => (clone $confirmedToday)->count(),
            'revenue' => (int) (clone $confirmedToday)->sum('total'),
            'showtimes' => Showtime::query()
                ->whereNull('cancelled_at')
                ->where('start_time', '>=', $dayStart)
                ->where('start_time', '<', $dayEnd)
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

    /**
     * Half-open app-timezone instants bounding the venue-local calendar day.
     * Both bounds are computed IN the venue timezone before converting, so a
     * DST transition day correctly spans 23 or 25 hours — adding a day after
     * conversion would always yield 24h and drift an hour around changes.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function venueDayBounds(): array
    {
        $tz = config('app.default_location_timezone') ?? config('app.timezone');

        $startLocal = now($tz)->startOfDay();
        $endLocal = $startLocal->copy()->addDay();

        return [
            $startLocal->copy()->setTimezone(config('app.timezone')),
            $endLocal->setTimezone(config('app.timezone')),
        ];
    }
}
