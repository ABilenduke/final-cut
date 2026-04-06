<?php

namespace App\Models;

use App\Enums\GiftCardStatus;
use Database\Factories\GiftCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'initial_balance', 'current_balance', 'recipient_email',
    'recipient_name', 'sender_name', 'message', 'status',
    'stripe_payment_intent_id', 'idempotency_key', 'payload_hash',
    'purchased_at',
])]
class GiftCard extends Model
{
    /** @use HasFactory<GiftCardFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => GiftCardStatus::class,
            'initial_balance' => 'integer',
            'current_balance' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }
}
