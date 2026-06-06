<?php

namespace App\Models;

use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $code Uppercase; enforced by PromoCodeService.
 * @property string $discount_type 'percentage' | 'fixed_cents'.
 * @property int $amount Percent (1-100) OR cents, per discount_type.
 * @property int|null $usage_limit Null = unlimited.
 * @property int|null $per_user_limit Null = unlimited per customer; enforced by PromoCodeService (by account id or normalized guest email).
 * @property int $uses_count
 * @property Carbon|null $expires_at
 * @property Carbon|null $deactivated_at NULL = active; set = when deactivated.
 * @property-read bool $is_active Derived: true when deactivated_at is NULL.
 */
#[Fillable([
    'code', 'discount_type', 'amount', 'usage_limit',
    'per_user_limit', 'uses_count', 'expires_at', 'deactivated_at',
])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_CENTS = 'fixed_cents';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Derived activity flag — keeps every `$promo->is_active` reader working
     * after the boolean → nullable-timestamp migration.
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->deactivated_at === null);
    }
}
