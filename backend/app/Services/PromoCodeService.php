<?php

namespace App\Services;

use App\Exceptions\PromoCodeInUseException;
use App\Models\AdminUser;
use App\Models\PromoCode;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Promo code CRUD + customer-path validation/consumption.
 *
 * Write methods accept an optional `?AdminUser $actor` and emit an
 * `activity_log` row on the 'admin' channel when `$actor !== null` (via
 * `LogsAdminActivity`). Customer callers pass `null` for `$actor` — the
 * `uses_count` increment on the row is the customer-path audit trail.
 *
 * `validateCode()` and `incrementUsage()` are the only methods called from
 * the customer booking flow.
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
    public function create(array $attributes, ?AdminUser $actor = null): PromoCode
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
    public function update(PromoCode $promo, array $attributes, ?AdminUser $actor = null): PromoCode
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

    public function deactivate(PromoCode $promo, ?AdminUser $actor = null): void
    {
        DB::transaction(function () use ($promo, $actor): void {
            $promo->is_active = false;
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
     */
    public function delete(PromoCode $promo, ?AdminUser $actor = null): void
    {
        if ($promo->uses_count > 0) {
            throw new PromoCodeInUseException;
        }

        DB::transaction(function () use ($promo, $actor): void {
            $snapshot = [
                'code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'amount' => $promo->amount,
            ];
            $this->logIfAdmin(self::EVENT_DELETED, $promo, $actor, $snapshot);
            $promo->delete();
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
     * Atomically increment `uses_count`. Called from the booking confirmation
     * transaction; customer callers pass `null` for `$actor` so no activity
     * log row is written — the counter change itself is the audit trail.
     *
     * `lockForUpdate` serialises concurrent confirmations so two simultaneous
     * bookings cannot both succeed on the last remaining use.
     */
    public function incrementUsage(PromoCode $promo, ?AdminUser $actor = null): void
    {
        DB::transaction(function () use ($promo, $actor): void {
            /** @var PromoCode $locked */
            $locked = PromoCode::query()->whereKey($promo->id)->lockForUpdate()->firstOrFail();
            $locked->increment('uses_count');
            $promo->setRawAttributes($locked->getAttributes(), sync: true);

            // Not expected to fire in customer path (actor = null), but supported
            // in case a future admin-path "apply on behalf of" emerges.
            $this->logIfAdmin('promo_code.used', $locked, $actor, [
                'code' => $locked->code,
                'uses_count' => $locked->uses_count,
            ]);
        });
    }
}
