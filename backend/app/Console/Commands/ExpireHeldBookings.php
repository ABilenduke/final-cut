<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

/**
 * Compensating control for the booking flow's reserve-then-charge design
 * (ultrareview P0 #3). A `Held` booking holds its seats while Stripe is
 * called with the row lock released; the request finalizes it to `Confirmed`
 * or discards it on failure. If the PHP process dies between reserving and
 * finalizing, the `Held` booking — and its seats — would leak forever.
 *
 * A `Held` booking only ever exists for the few seconds of an in-flight
 * checkout (the 3DS path discards it immediately), so anything older than the
 * threshold is definitively abandoned. Deleting cascades to booking_seats /
 * booking_food_items (freeing the seats) and frees the idempotency_key.
 */
class ExpireHeldBookings extends Command
{
    protected $signature = 'bookings:expire-held {--minutes=20 : Age threshold in minutes}';

    protected $description = 'Delete abandoned Held bookings (crashed in-flight checkouts), freeing their seats';

    public function handle(): int
    {
        $minutesOption = $this->option('minutes');

        // A zero / negative / non-numeric threshold would put the cutoff at or
        // after "now" and delete actively in-flight Held bookings mid-checkout.
        if (! is_numeric($minutesOption) || (int) $minutesOption < 1) {
            $this->error("--minutes must be a positive integer; got: {$minutesOption}");

            return self::INVALID;
        }

        $minutes = (int) $minutesOption;
        $cutoff = now()->subMinutes($minutes);

        $deleted = Booking::query()
            ->where('status', BookingStatus::Held)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Expired {$deleted} stale Held booking(s) older than {$minutes} minute(s).");

        return self::SUCCESS;
    }
}
