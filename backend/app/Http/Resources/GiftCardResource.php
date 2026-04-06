<?php

namespace App\Http\Resources;

use App\Models\GiftCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GiftCard */
class GiftCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'initialBalance' => $this->initial_balance,
            'currentBalance' => $this->current_balance,
            'recipientEmail' => $this->recipient_email,
            'recipientName' => $this->recipient_name,
            'senderName' => $this->sender_name,
            'message' => $this->message,
            'status' => $this->status->value,
            'purchasedAt' => $this->purchased_at?->toIso8601String(),
        ];
    }
}
