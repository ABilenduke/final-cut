<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingAmendmentException;
use App\Exceptions\SeatConflictException;
use App\Models\Booking;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin record corrections on a booking. The cheap ones don't touch its state
 * machine, money, or seats — editing the internal `notes` and fixing a mistyped
 * guest `guest_email`. `reassignSeats` is the one seat-touching member: it moves
 * an active booking to different seats in the SAME showtime, money-neutral (the
 * new seats must cost exactly what the old ones did). All run under row locks
 * and write an audited `admin` activity event. Refunds and flags remain the
 * responsibility of their dedicated services.
 */
class BookingAmendmentService
{
    use LogsAdminActivity;

    public const EVENT_NOTES_UPDATED = 'booking.notes_updated';

    public const EVENT_GUEST_EMAIL_CORRECTED = 'booking.guest_email_corrected';

    public const EVENT_SEATS_REASSIGNED = 'booking.seats_reassigned';

    /** Statuses whose seats may be moved — the active, sellable states only. */
    private const REASSIGNABLE_STATUSES = [BookingStatus::Confirmed, BookingStatus::Held];

    public function updateNotes(Booking $booking, ?string $notes, ?User $actor = null): void
    {
        $clean = $notes === null ? null : trim($notes);
        if ($clean === '') {
            $clean = null;
        }

        DB::transaction(function () use ($booking, $clean, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $locked->notes = $clean;
            $locked->save();

            $this->logIfAdmin(self::EVENT_NOTES_UPDATED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
            ]);
        });

        $booking->notes = $clean;
    }

    /**
     * @throws BookingAmendmentException When the booking belongs to a registered user.
     */
    public function correctGuestEmail(Booking $booking, string $email, ?User $actor = null): void
    {
        $normalized = mb_strtolower(trim($email));

        DB::transaction(function () use ($booking, $normalized, $actor): void {
            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null) {
                throw new BookingAmendmentException(BookingAmendmentException::REASON_NOT_GUEST_BOOKING);
            }

            $previous = $locked->guest_email;

            $locked->guest_email = $normalized;
            $locked->save();

            $this->logIfAdmin(self::EVENT_GUEST_EMAIL_CORRECTED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
                'previous_email' => $previous,
                'new_email' => $normalized,
            ]);
        });

        $booking->guest_email = $normalized;
    }

    /**
     * Move an active booking to a different set of seats in the SAME showtime —
     * the admin equivalent of a customer asking to switch seats without a
     * refund-and-rebook. Money-neutral by design: the new seats must cost
     * exactly what the current seats cost (else `REASON_SEAT_PRICE_MISMATCH`),
     * so the Stripe charge and the booking's `total` stay valid and untouched.
     *
     * Order of operations inside one transaction, with the showtime locked
     * first (matching the customer booking flow's lock order so the two
     * serialize): drop the current `booking_seats`, then reserve the new ones
     * via `SeatAvailabilityService::reserveSeats`. Re-reserving re-runs
     * availability (so the just-freed seats are selectable again, supporting a
     * partial move that keeps some seats) and leans on the partial-unique
     * occupancy index as the TOCTOU backstop — a racing grab surfaces as
     * `SeatConflictException`, which rolls the whole transaction back and leaves
     * the original seats intact. A price mismatch likewise rolls back.
     *
     * @param  string[]  $newSeatIds
     *
     * @throws BookingAmendmentException When the booking isn't reassignable, the seat set is empty, or the price differs.
     * @throws SeatConflictException When a target seat is unavailable.
     * @throws ValidationException When a seat is in a different auditorium.
     */
    public function reassignSeats(Booking $booking, array $newSeatIds, ?User $actor = null): void
    {
        $newSeatIds = array_values(array_unique($newSeatIds));

        if ($newSeatIds === []) {
            throw new BookingAmendmentException(BookingAmendmentException::REASON_NO_SEATS);
        }

        DB::transaction(function () use ($booking, $newSeatIds, $actor): void {
            // Lock the showtime first, then the booking — same order the customer
            // reservation path takes, so the two can't deadlock or interleave.
            /** @var Showtime $showtime */
            $showtime = Showtime::whereKey($booking->showtime_id)->lockForUpdate()->firstOrFail();

            /** @var Booking $locked */
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, self::REASSIGNABLE_STATUSES, true)) {
                throw new BookingAmendmentException(BookingAmendmentException::REASON_NOT_REASSIGNABLE);
            }

            $oldSeats = $locked->seats()->get();
            $oldSeatSubtotal = (int) $oldSeats->sum('price');
            $oldSeatIds = $oldSeats->pluck('seat_id')->all();

            // Release the current seats first so a partial move (keeping some of
            // them) sees them as available again on the re-reserve below.
            $locked->seats()->delete();

            $newTotal = app(SeatAvailabilityService::class)->reserveSeats($showtime, $newSeatIds, $locked);

            if ($newTotal !== $oldSeatSubtotal) {
                throw new BookingAmendmentException(BookingAmendmentException::REASON_SEAT_PRICE_MISMATCH);
            }

            $this->logIfAdmin(self::EVENT_SEATS_REASSIGNED, $locked, $actor, [
                'confirmation_code' => $locked->confirmation_code,
                'from_seat_ids' => $oldSeatIds,
                'to_seat_ids' => $newSeatIds,
            ]);
        });

        $booking->load('seats');
    }
}
