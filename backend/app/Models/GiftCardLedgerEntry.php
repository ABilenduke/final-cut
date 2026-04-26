<?php

namespace App\Models;

use App\Enums\GiftCardLedgerType;
use Database\Factories\GiftCardLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only ledger entry for a gift card balance event.
 *
 * @property GiftCardLedgerType $type
 * @property int $amount_cents Signed — negative for debits.
 * @property int $balance_after_cents
 * @property Carbon $created_at
 */
#[Fillable([
    'gift_card_id', 'type', 'amount_cents', 'balance_after_cents',
    'booking_id', 'admin_user_id', 'reason', 'created_at',
])]
class GiftCardLedgerEntry extends Model
{
    /** @use HasFactory<GiftCardLedgerEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => GiftCardLedgerType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
