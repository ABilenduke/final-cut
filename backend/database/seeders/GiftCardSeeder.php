<?php

namespace Database\Seeders;

use App\Enums\GiftCardStatus;
use App\Models\GiftCard;
use Illuminate\Database\Seeder;

class GiftCardSeeder extends Seeder
{
    public function run(): void
    {
        // 3 active gift cards with varying balances
        GiftCard::factory()->create([
            'initial_balance' => 2500,
            'current_balance' => 2500,
            'recipient_email' => 'test@finalcut.test',
            'recipient_name' => 'Test User',
            'sender_name' => 'Gift Sender',
            'message' => 'Enjoy the movies!',
        ]);

        GiftCard::factory()->create([
            'initial_balance' => 5000,
            'current_balance' => 3200,
            'recipient_email' => 'member@finalcut.test',
            'recipient_name' => 'Member User',
            'sender_name' => 'A Friend',
            'message' => 'Happy birthday!',
        ]);

        GiftCard::factory()->create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'recipient_email' => 'guest@finalcut.test',
            'recipient_name' => 'Guest Moviegoer',
            'sender_name' => 'Final Cut Theatre',
            'message' => null,
        ]);

        // 1 depleted gift card
        GiftCard::factory()->depleted()->create([
            'initial_balance' => 5000,
            'recipient_email' => 'test@finalcut.test',
            'recipient_name' => 'Test User',
            'sender_name' => 'Old Friend',
            'message' => 'This one is used up.',
        ]);

        // 1 expired gift card
        GiftCard::factory()->create([
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
