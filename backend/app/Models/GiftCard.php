<?php

namespace App\Models;

use App\Enums\GiftCardDeliveryMethod;
use App\Enums\GiftCardEdition;
use App\Enums\GiftCardStatus;
use Database\Factories\GiftCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property GiftCardStatus $status
 * @property GiftCardEdition $edition
 * @property GiftCardDeliveryMethod $delivery_method
 * @property Carbon|null $purchased_at
 * @property Carbon|null $voided_at
 * @property Carbon|null $scheduled_send_at
 */
#[Fillable([
    'code', 'initial_balance', 'current_balance', 'recipient_email',
    'recipient_name', 'sender_name', 'message', 'edition', 'delivery_method',
    'scheduled_send_at', 'status',
    'stripe_payment_intent_id', 'idempotency_key', 'payload_hash',
    'purchased_at', 'voided_at', 'voided_reason', 'voided_by_admin_user_id',
])]
class GiftCard extends Model
{
    /** @use HasFactory<GiftCardFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => GiftCardStatus::class,
            'edition' => GiftCardEdition::class,
            'delivery_method' => GiftCardDeliveryMethod::class,
            'initial_balance' => 'integer',
            'current_balance' => 'integer',
            'purchased_at' => 'datetime',
            'scheduled_send_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(GiftCardLedgerEntry::class)->orderByDesc('created_at');
    }

    /**
     * The admin who voided this gift card. Returns a User; by convention this
     * column references a User who has an AdminProfile.
     */
    public function voidedByAdminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_admin_user_id');
    }
}
