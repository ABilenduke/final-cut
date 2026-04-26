<?php

namespace App\Models;

use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $code Uppercase; enforced by PromoCodeService.
 * @property string $discount_type 'percentage' | 'fixed_cents'.
 * @property int $amount Percent (1-100) OR cents, per discount_type.
 * @property int|null $usage_limit Null = unlimited.
 * @property int|null $per_user_limit Reserved for v2 enforcement.
 * @property int $uses_count
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
#[Fillable([
    'code', 'discount_type', 'amount', 'usage_limit',
    'per_user_limit', 'uses_count', 'expires_at', 'is_active',
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
            'is_active' => 'boolean',
        ];
    }
}
