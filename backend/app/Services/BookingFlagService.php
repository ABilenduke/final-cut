<?php

namespace App\Services;

use App\Exceptions\BookingFlagException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Manual booking flag/unflag transitions — the admin-initiated counterpart to
 * the automatic flagging `ShowtimeService::cancel` performs. Both writes run
 * under a row lock so two admins racing get a clean already-flagged /
 * not-flagged failure instead of silently clobbering each other.
 */
class BookingFlagService
{
    use LogsAdminActivity;

    public const EVENT_FLAGGED = 'booking.flagged';

    public const EVENT_UNFLAGGED = 'booking.unflagged';

    /**
     * Owned by `ShowtimeService::cancel` (mirrored as
     * `CancellationFollowupQueue::SHOWTIME_CANCEL_FLAG_PREFIX`) — the
     * cancellation follow-up queue scopes on it, so a manual flag using this
     * prefix would impersonate a showtime cancellation.
     */
    public const RESERVED_PREFIX = 'showtime_cancelled:';

    /**
     * @throws BookingFlagException When already flagged or the reason uses the reserved prefix.
     */
    public function flag(Booking $booking, string $reason, ?User $actor = null): void
    {
        if (str_starts_with($reason, self::RESERVED_PREFIX)) {
            throw new BookingFlagException(BookingFlagException::REASON_RESERVED_PREFIX);
        }

        DB::transaction(function () use ($booking, $reason, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->flagged_at !== null) {
                throw new BookingFlagException(BookingFlagException::REASON_ALREADY_FLAGGED);
            }

            $locked->flagged_at = now();
            $locked->flag_reason = $reason;
            $locked->save();

            $this->logIfAdmin(self::EVENT_FLAGGED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * @throws BookingFlagException When the booking is not flagged.
     */
    public function unflag(Booking $booking, ?User $actor = null): void
    {
        DB::transaction(function () use ($booking, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->flagged_at === null) {
                throw new BookingFlagException(BookingFlagException::REASON_NOT_FLAGGED);
            }

            $previousReason = $locked->flag_reason;

            $locked->flagged_at = null;
            $locked->flag_reason = null;
            $locked->save();

            $this->logIfAdmin(self::EVENT_UNFLAGGED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
                'previous_reason' => $previousReason,
            ]);
        });
    }
}
