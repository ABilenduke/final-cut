<?php

namespace App\Services;

use App\Exceptions\PromoCodeInUseException;
use App\Exceptions\PromoCodeNotConsumableException;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Promo code CRUD + customer-path validation/consumption.
 *
 * Write methods accept an optional `?User $actor` and emit an
 * `activity_log` row on the 'admin' channel when `$actor !== null` (via
 * `LogsAdminActivity`). Customer callers pass `null` for `$actor` — the
 * `uses_count` increment on the row is the customer-path audit trail.
 *
 * `validateCode()` is a pre-check; `consume()` is the authoritative
 * locked validate-and-increment used during booking finalisation.
 */
class PromoCodeService
{
    use LogsAdminActivity;

    public const EVENT_CREATED = 'promo_code.created';

    public const EVENT_UPDATED = 'promo_code.updated';

    public const EVENT_DEACTIVATED = 'promo_code.deactivated';

    public const EVENT_DELETED = 'promo_code.deleted';

    /**
     * Create a new promo code. Uppercases `code` defensively even though the
     * admin UI already uppercases — never trust UI-layer normalisation for
     * domain invariants.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null): PromoCode
    {
        return DB::transaction(function () use ($attributes, $actor): PromoCode {
            $attributes['code'] = strtoupper((string) $attributes['code']);
            $promo = PromoCode::create($attributes);

            $this->logIfAdmin(self::EVENT_CREATED, $promo, $actor, [
                'code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'amount' => $promo->amount,
            ]);

            return $promo;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PromoCode $promo, array $attributes, ?User $actor = null): PromoCode
    {
        return DB::transaction(function () use ($promo, $attributes, $actor): PromoCode {
            if (array_key_exists('code', $attributes)) {
                $attributes['code'] = strtoupper((string) $attributes['code']);
            }
            $promo->fill($attributes);
            $changes = $promo->getDirty();
            $promo->save();

            $this->logIfAdmin(self::EVENT_UPDATED, $promo, $actor, ['changes' => $changes]);

            return $promo;
        });
    }

    public function deactivate(PromoCode $promo, ?User $actor = null): void
    {
        DB::transaction(function () use ($promo, $actor): void {
            $promo->deactivated_at = now();
            $promo->save();

            $this->logIfAdmin(self::EVENT_DEACTIVATED, $promo, $actor, [
                'code' => $promo->code,
            ]);
        });
    }

    /**
     * Hard delete. Used codes must be deactivated (not deleted) to preserve
     * historical records. Throws `PromoCodeInUseException` when the service
     * layer sees `uses_count > 0`, independent of any UI guard.
     *
     * The check happens AFTER taking `lockForUpdate` on the row so a
     * customer-side `consume()` racing this admin action can't slip an
     * increment in between a stale-model pre-check and the actual delete.
     */
    public function delete(PromoCode $promo, ?User $actor = null): void
    {
        DB::transaction(function () use ($promo, $actor): void {
            /** @var PromoCode|null $locked */
            $locked = PromoCode::query()->whereKey($promo->id)->lockForUpdate()->first();

            // Already deleted by a concurrent admin action — nothing to do.
            if ($locked === null) {
                return;
            }

            if ($locked->uses_count > 0) {
                throw new PromoCodeInUseException;
            }

            $snapshot = [
                'code' => $locked->code,
                'discount_type' => $locked->discount_type,
                'amount' => $locked->amount,
            ];
            $this->logIfAdmin(self::EVENT_DELETED, $locked, $actor, $snapshot);
            $locked->delete();
        });
    }

    /**
     * Look up a code for customer application. Returns the row only when:
     *  - `is_active` is true
     *  - `expires_at` is null or in the future
     *  - `usage_limit` is null or `uses_count < usage_limit`
     *
     * Per-user limit enforcement is deferred to v2; the column exists on the
     * schema but this method ignores it.
     *
     * The `$bookingTotalCents` parameter is accepted for future conditional
     * rules (minimum spend). Not currently used by the service; the caller
     * still performs the discount math.
     */
    public function validateCode(string $code, int $bookingTotalCents): ?PromoCode
    {
        $normalised = strtoupper(trim($code));
        if ($normalised === '') {
            return null;
        }

        /** @var PromoCode|null $promo */
        $promo = PromoCode::query()->where('code', $normalised)->first();

        if ($promo === null || ! $promo->is_active) {
            return null;
        }

        if ($promo->expires_at !== null && $promo->expires_at->isPast()) {
            return null;
        }

        if ($promo->usage_limit !== null && $promo->uses_count >= $promo->usage_limit) {
            return null;
        }

        return $promo;
    }

    /**
     * Compute the discount in cents for `$promo` against `$subtotalCents`.
     *
     * Domain math, not persistence — kept here so a future caller (e.g. a
     * gift-card-purchase flow that also accepts promo codes) doesn't have to
     * re-derive the percentage/fixed branching. Caps the discount at the
     * subtotal to prevent negative totals.
     */
    public function calculateDiscount(PromoCode $promo, int $subtotalCents): int
    {
        $discount = match ($promo->discount_type) {
            PromoCode::TYPE_PERCENTAGE => (int) floor($subtotalCents * $promo->amount / 100),
            PromoCode::TYPE_FIXED_CENTS => $promo->amount,
            default => 0,
        };

        return min($discount, $subtotalCents);
    }

    /**
     * Atomically validate + consume one usage of a promo code.
     *
     * `lockForUpdate` serialises concurrent confirmations and the row is
     * re-read under the lock so we can re-check `is_active`, `expires_at`,
     * and `usage_limit` before incrementing — closing the race that
     * existed between `validateCode()` (read-only pre-check) and the
     * older `incrementUsage()`. Two confirmations both passing the
     * pre-check on a code with one remaining use cannot both succeed:
     * the second sees the post-increment count under its own lock and
     * throws `PromoCodeNotConsumableException`.
     *
     * The same revalidation handles the case where an admin deactivates,
     * expires, or deletes a code mid-flight (e.g. during a 3DS window).
     *
     * Customer callers pass `null` for `$actor` so no activity log row is
     * written — the `uses_count` increment is the customer-path audit
     * trail.
     *
     * @throws PromoCodeNotConsumableException if the code is no longer
     *                                         redeemable when the lock is acquired.
     */
    public function consume(PromoCode $promo, ?User $actor = null): PromoCode
    {
        return DB::transaction(function () use ($promo, $actor): PromoCode {
            /** @var PromoCode|null $locked */
            $locked = PromoCode::query()->whereKey($promo->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new PromoCodeNotConsumableException(
                    PromoCodeNotConsumableException::REASON_NOT_FOUND,
                );
            }

            if (! $locked->is_active) {
                throw new PromoCodeNotConsumableException(
                    PromoCodeNotConsumableException::REASON_INACTIVE,
                );
            }

            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new PromoCodeNotConsumableException(
                    PromoCodeNotConsumableException::REASON_EXPIRED,
                );
            }

            if ($locked->usage_limit !== null && $locked->uses_count >= $locked->usage_limit) {
                throw new PromoCodeNotConsumableException(
                    PromoCodeNotConsumableException::REASON_LIMIT_REACHED,
                );
            }

            $locked->increment('uses_count');
            $promo->setRawAttributes($locked->getAttributes(), sync: true);

            // Not expected to fire in customer path (actor = null), but
            // supported for a future admin-path "apply on behalf of".
            $this->logIfAdmin('promo_code.used', $locked, $actor, [
                'code' => $locked->code,
                'uses_count' => $locked->uses_count,
            ]);

            return $locked;
        });
    }
}
