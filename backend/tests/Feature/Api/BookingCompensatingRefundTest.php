<?php

use App\Enums\BookingStatus;
use App\Enums\GiftCardStatus;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Services\GiftCardService;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

/**
 * Non-3DS compensating-refund coverage. store() Phase C runs AFTER the charge
 * is captured; any failure there must refund the already-captured PaymentIntent
 * and discard the Held booking. Only the 3DS path had coverage before.
 */
test('store() refunds the captured charge when finalize fails after payment (non-3DS)', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe()->shouldSucceed();

    $giftCard = GiftCard::factory()->create([
        'current_balance' => 500,
        'status' => GiftCardStatus::Active,
    ]);

    // Inject a finalize failure AFTER capture: gift-card redemption throws inside
    // Phase C, exercising the generic \Throwable compensating-refund branch.
    $stub = Mockery::mock(GiftCardService::class);
    $stub->shouldReceive('redeemAgainstBooking')->andThrow(new RuntimeException('finalize boom'));
    $this->app->instance(GiftCardService::class, $stub);

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'giftCardCode' => $giftCard->code,
        'email' => 'guest@example.com',
    ]);

    // The generic branch re-throws → 500 from the global handler.
    $response->assertStatus(500);

    // Card amount (1200 seat − 500 gift card = 700) was captured then refunded.
    expect($fakeStripe->createdPaymentIntents)->toHaveCount(1)
        ->and($fakeStripe->createdPaymentIntents[0]['amount'])->toBe(700);
    expect($fakeStripe->refundedPaymentIntents)->toHaveCount(1);

    // No Confirmed booking, the Held booking discarded, no gift-card ledger entry.
    expect(Booking::where('status', BookingStatus::Confirmed)->count())->toBe(0);
    expect(Booking::count())->toBe(0);
    expect(GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)->count())->toBe(0);
});
