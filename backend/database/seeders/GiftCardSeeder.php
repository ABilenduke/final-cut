<?php

namespace Database\Seeders;

use App\Enums\GiftCardDeliveryMethod;
use App\Enums\GiftCardEdition;
use App\Enums\GiftCardStatus;
use App\Models\GiftCard;
use App\Support\SeederUuid;
use Illuminate\Database\Seeder;

class GiftCardSeeder extends Seeder
{
    public function run(): void
    {
        // 3 active gift cards with varying balances (deterministic codes for test references).
        // Vary edition / delivery method / scheduled-send across the seeds so admin and
        // customer surfaces have realistic stateful data to render.
        GiftCard::factory()->create([
            'id' => SeederUuid::for('giftcard:SEED-ACTIVE-2500'),
            'code' => 'SEED-ACTIVE-2500',
            'initial_balance' => 2500,
            'current_balance' => 2500,
            'recipient_email' => 'test@finalcut.test',
            'recipient_name' => 'Test User',
            'sender_name' => 'Gift Sender',
            'message' => 'Enjoy the movies!',
        ]);

        GiftCard::factory()->create([
            'id' => SeederUuid::for('giftcard:SEED-ACTIVE-5000'),
            'code' => 'SEED-ACTIVE-5000',
            'initial_balance' => 5000,
            'current_balance' => 3200,
            'recipient_email' => 'member@finalcut.test',
            'recipient_name' => 'Member User',
            'sender_name' => 'A Friend',
            'message' => 'Happy birthday!',
            'edition' => GiftCardEdition::CharterGold,
        ]);

        GiftCard::factory()->create([
            'id' => SeederUuid::for('giftcard:SEED-ACTIVE-10000'),
            'code' => 'SEED-ACTIVE-10000',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'recipient_email' => 'guest@finalcut.test',
            'recipient_name' => 'Guest Moviegoer',
            'sender_name' => 'Final Cut Theatre',
            'message' => null,
            'edition' => GiftCardEdition::PureVoid,
            'delivery_method' => GiftCardDeliveryMethod::Print,
            'scheduled_send_at' => now()->addDays(3),
        ]);

        // 1 depleted gift card
        GiftCard::factory()->depleted()->create([
            'id' => SeederUuid::for('giftcard:SEED-DEPLETED-5000'),
            'code' => 'SEED-DEPLETED-5000',
            'initial_balance' => 5000,
            'recipient_email' => 'test@finalcut.test',
            'recipient_name' => 'Test User',
            'sender_name' => 'Old Friend',
            'message' => 'This one is used up.',
        ]);

        // 1 expired gift card
        GiftCard::factory()->create([
            'id' => SeederUuid::for('giftcard:SEED-EXPIRED-5000'),
            'code' => 'SEED-EXPIRED-5000',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Expired,
            'purchased_at' => now()->subYears(2),
            'recipient_email' => 'test@finalcut.test',
            'recipient_name' => 'Test User',
            'sender_name' => 'Distant Relative',
            'message' => 'From two years ago.',
        ]);
    }
}
