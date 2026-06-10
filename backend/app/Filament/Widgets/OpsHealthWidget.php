<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Pages\CancellationFollowupQueue;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "What needs my attention" stats (admin-v2 Plan 07): flagged bookings
 * awaiting follow-up and the dispatch-outbox backlog/parked counts — the
 * glance-level answer to the audit's "no admin visibility into outbox
 * failures" gap (the full ops surface is Plan 08).
 */
class OpsHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Needs attention';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('bookings.view') ?? false;
    }

    /** @return array{flagged: int, outbox_backlog: int, outbox_parked: int} */
    public static function metrics(): array
    {
        return [
            'flagged' => Booking::query()
                ->whereNotNull('flagged_at')
                ->whereNotIn('status', [BookingStatus::Refunded, BookingStatus::Cancelled])
                ->count(),
            'outbox_backlog' => DispatchOutbox::query()
                ->whereNull('processed_at')
                ->whereNull('failed_at')
                ->count(),
            'outbox_parked' => DispatchOutbox::query()
                ->whereNotNull('failed_at')
                ->count(),
        ];
    }

    protected function getStats(): array
    {
        $metrics = self::metrics();

        return [
            Stat::make('Flagged bookings', number_format($metrics['flagged']))
                ->description('awaiting follow-up')
                ->descriptionIcon('heroicon-o-flag')
                ->color($metrics['flagged'] > 0 ? 'warning' : 'gray')
                ->url(CancellationFollowupQueue::getUrl()),
            Stat::make('Outbox backlog', number_format($metrics['outbox_backlog']))
                ->description('notifications waiting to send')
                ->descriptionIcon('heroicon-o-envelope')
                ->color('gray'),
            Stat::make('Outbox parked', number_format($metrics['outbox_parked']))
                ->description('failed rows needing investigation')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($metrics['outbox_parked'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
