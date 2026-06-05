<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

/**
 * HTTP-layer proof that the lock + balance re-validation prevents a gift card
 * being spent twice. RefreshDatabase runs on one connection, so true parallelism
 * isn't reproducible — model the race as sequential requests sharing the card,
 * which still exercises the depletion guard and live-balance sizing the lock
 * exists for. (Standard seat = 1200 cents.)
 */
test('a depleted gift card cannot be redeemed by a second booking', function () {
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldSucceed();

    // Exactly enough for one seat.
    $giftCard = GiftCard::factory()->create([
        'current_balance' => 1200,
        'status' => GiftCardStatus::Active,
    ]);

    // Booking #1 — fully covered by the gift card, no card charge.
    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ])->assertStatus(201)->assertJsonPath('data.total', 0);

    $giftCard->refresh();
    expect($giftCard->current_balance)->toBe(0)
        ->and($giftCard->status)->toBe(GiftCardStatus::Depleted);

    // Booking #2 — same (now-depleted) card, different seat, no other payment.
    $second = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][1]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    $second->assertStatus(400)
        ->assertJsonPath('errors.0.field', 'giftCardCode');

    // Only one redemption ever happened; balance never went negative.
    expect(GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)
        ->where('type', GiftCardLedgerType::Redemption)->count())->toBe(1);
    expect($giftCard->fresh()->current_balance)->toBe(0);
});

test('a second redemption sizes against the live (reduced) balance, not the stale one', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe()->shouldSucceed();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 1500,
        'status' => GiftCardStatus::Active,
    ]);

    // Booking #1 — redeems 1200 of the 1500, leaving 300.
    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ])->assertStatus(201);

    expect($giftCard->fresh()->current_balance)->toBe(300);

    // Booking #2 — code covers only the live 300; the remaining 900 hits the card.
    $second = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][1]->id],
        'giftCardCode' => $giftCard->code,
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
    ]);

    $second->assertStatus(201)
        ->assertJsonPath('data.discount', 300)
        ->assertJsonPath('data.total', 900)
        ->assertJsonPath('data.paymentMethod', 'mixed');

    // Card sized against the live balance: Stripe charged 900, not 0 or 1200.
    expect($fakeStripe->createdPaymentIntents)->toHaveCount(1)
        ->and($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(900);

    // Two redemptions summing to the full 1500; balance bottoms out at 0.
    $redemptions = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)
        ->where('type', GiftCardLedgerType::Redemption)->get();
    expect($redemptions)->toHaveCount(2)
        ->and($redemptions->sum('amount_cents'))->toBe(-1500);
    expect($giftCard->fresh()->current_balance)->toBe(0);
});
