<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\LoyaltyAdjustmentType;
use App\Enums\LoyaltyTier;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty point balance + premier tier mutations.
 *
 * Every balance- or tier-changing method:
 *  1. runs inside `DB::transaction`
 *  2. re-reads the target user under `User::whereKey($id)->lockForUpdate()->firstOrFail()`
 *     — this is non-negotiable: without the row lock, two parallel adjustments
 *     can read the same starting balance and one write silently clobbers the other
 *  3. mutates the freshly-locked row, never the outer `$user` reference
 *  4. accepts an optional `?AdminUser $actor`; admin-path methods
 *     (`adjustPoints`, `grantPremier`, `revokePremier`) additionally insert a
 *     `loyalty_adjustments` audit row inside the same transaction
 *  5. emits an `activity_log` row on the 'admin' channel only when `$actor !== null`
 *     (via the `LogsAdminActivity` trait).
 */
class LoyaltyService
{
    use LogsAdminActivity;

    public function getPoints(User $user): int
    {
        return $user->loyalty_points;
    }

    public function getTier(User $user): string
    {
        return $user->loyalty_tier->value;
    }

    /**
     * Award purchase-based loyalty points (1 point per whole dollar spent).
     *
     * Purchase points are derived — `getHistory` reconstructs them from
     * confirmed bookings — so this method does NOT write a
     * `loyalty_adjustments` row. Admin-initiated corrections do.
     *
     * Uses an atomic `increment` rather than the admin methods' lock-and-save
     * pattern: the customer checkout path calls this inline and the single
     * UPDATE is race-safe at the database level without a surrounding
     * transaction + row lock. Admin corrections hold the lock because they
     * read-modify-write additional fields (tier / expiry) alongside points.
     *
     * @param  int  $totalCents  The purchase total in cents.
     * @return int The user's new loyalty_points total.
     */
    public function awardPointsForPurchase(User $user, int $totalCents): int
    {
        $points = (int) floor($totalCents / 100);

        if ($points === 0) {
            return $user->loyalty_points;
        }

        $user->increment('loyalty_points', $points);
        $user->refresh();

        return $user->loyalty_points;
    }

    /** Activity-log descriptions for admin-initiated loyalty events. */
    public const EVENT_POINTS_ADJUSTED = 'loyalty.points_adjusted';

    public const EVENT_PREMIER_GRANTED = 'loyalty.premier_granted';

    public const EVENT_PREMIER_REVOKED = 'loyalty.premier_revoked';

    /**
     * Admin-initiated point correction. `$delta` may be positive or negative;
     * negative balances are permitted (fraud clawbacks).
     */
    public function adjustPoints(User $user, int $delta, string $reason, ?AdminUser $actor = null): void
    {
        $this->withLoyaltyLock(
            user: $user,
            mutate: fn (User $fresh) => $fresh->loyalty_points = $fresh->loyalty_points + $delta,
            type: LoyaltyAdjustmentType::PointsCorrection,
            delta: $delta,
            reason: $reason,
            actor: $actor,
            event: self::EVENT_POINTS_ADJUSTED,
            extraLogProperties: ['delta' => $delta, 'reason' => $reason],
        );
    }

    /**
     * Upgrade the user to premier tier through the given expiry date. The
     * reason is persisted on the audit row and the activity log; callers
     * should provide something meaningful (e.g. "Corporate partnership").
     */
    public function grantPremier(User $user, Carbon $expiry, string $reason, ?AdminUser $actor = null): void
    {
        $this->withLoyaltyLock(
            user: $user,
            mutate: function (User $fresh) use ($expiry): void {
                $fresh->loyalty_tier = LoyaltyTier::Premier;
                $fresh->premier_expiry = $expiry;
            },
            type: LoyaltyAdjustmentType::TierUpgrade,
            delta: 0,
            reason: $reason,
            actor: $actor,
            event: self::EVENT_PREMIER_GRANTED,
            extraLogProperties: ['expiry' => $expiry->toIso8601String(), 'reason' => $reason],
        );
    }

    /**
     * Revoke premier tier, returning the user to member.
     */
    public function revokePremier(User $user, string $reason, ?AdminUser $actor = null): void
    {
        $this->withLoyaltyLock(
            user: $user,
            mutate: function (User $fresh): void {
                $fresh->loyalty_tier = LoyaltyTier::Member;
                $fresh->premier_expiry = null;
            },
            type: LoyaltyAdjustmentType::TierRevoke,
            delta: 0,
            reason: $reason,
            actor: $actor,
            event: self::EVENT_PREMIER_REVOKED,
            extraLogProperties: ['reason' => $reason],
        );
    }

    /**
     * Shared write skeleton for every admin-path mutation: row-locked read,
     * caller-supplied mutation, `loyalty_adjustments` audit insert, and
     * activity-log emission — all inside one transaction. `$mutate` must
     * only assign to the fresh user; the save happens here.
     *
     * @param  array<string, mixed>  $extraLogProperties
     */
    private function withLoyaltyLock(
        User $user,
        Closure $mutate,
        LoyaltyAdjustmentType $type,
        int $delta,
        string $reason,
        ?AdminUser $actor,
        string $event,
        array $extraLogProperties = [],
    ): void {
        DB::transaction(function () use ($user, $mutate, $type, $delta, $reason, $actor, $event, $extraLogProperties): void {
            $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $mutate($fresh);
            $fresh->save();

            LoyaltyAdjustment::create([
                'user_id' => $fresh->id,
                'admin_user_id' => $actor?->id,
                'change_type' => $type,
                'points_delta' => $delta,
                'reason' => $reason,
            ]);

            $this->logIfAdmin($event, $fresh, $actor, [
                ...$extraLogProperties,
                'balance_after' => $fresh->loyalty_points,
            ]);
        });
    }

    /**
     * Loyalty points history derived from confirmed bookings.
     *
     * @return array<int, array{description: string, points: int, date: string, bookingId: string}>
     */
    public function getHistory(User $user): array
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = $user->bookings()
            ->with('showtime.movie')
            ->where('status', BookingStatus::Confirmed)
            ->latest()
            ->get();

        return $bookings->map(fn (Booking $booking) => [
            'description' => "Booking for {$booking->showtime->movie->title}",
            'points' => (int) floor($booking->total / 100),
            'date' => $booking->created_at->toIso8601String(),
            'bookingId' => $booking->id,
        ])->all();
    }
}
