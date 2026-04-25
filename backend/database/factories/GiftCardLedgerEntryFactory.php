<?php

namespace Database\Factories;

use App\Enums\GiftCardLedgerType;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GiftCardLedgerEntry> */
class GiftCardLedgerEntryFactory extends Factory
{
    protected $model = GiftCardLedgerEntry::class;

    public function definition(): array
    {
        return [
            'gift_card_id' => GiftCard::factory(),
            'type' => GiftCardLedgerType::Purchase,
            'amount_cents' => 5000,
            'balance_after_cents' => 5000,
            'booking_id' => null,
            'admin_user_id' => null,
            'reason' => null,
            'created_at' => now(),
        ];
    }

    public function purchase(int $amount): static
    {
        return $this->state(fn () => [
            'type' => GiftCardLedgerType::Purchase,
            'amount_cents' => $amount,
            'balance_after_cents' => $amount,
        ]);
    }

    public function redemption(int $amount, int $balanceAfter): static
    {
        return $this->state(fn () => [
            'type' => GiftCardLedgerType::Redemption,
            'amount_cents' => -$amount,
            'balance_after_cents' => $balanceAfter,
        ]);
    }

    public function void(int $amountBefore): static
    {
        return $this->state(fn () => [
            'type' => GiftCardLedgerType::Void,
            'amount_cents' => -$amountBefore,
            'balance_after_cents' => 0,
        ]);
    }
}
