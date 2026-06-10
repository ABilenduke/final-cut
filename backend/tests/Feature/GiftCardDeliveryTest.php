<?php

use App\Enums\GiftCardStatus;
use App\Jobs\SendGiftCardDelivery;
use App\Mail\GiftCardDeliveryMail;
use App\Models\DispatchOutbox;
use App\Models\GiftCard;
use App\Outbox\OutboxDispatcher;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function purchaseAttributes(array $overrides = []): array
{
    return array_merge([
        'code' => 'GC-DELIV'.fake()->unique()->numerify('##'),
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'recipient_email' => 'margot@example.com',
        'recipient_name' => 'Margot Renard',
        'sender_name' => 'Henri',
        'message' => 'Enjoy the show.',
        'edition' => 'gold',
        'delivery_method' => 'email',
        'scheduled_send_at' => null,
        'status' => GiftCardStatus::Active,
        'purchased_at' => now(),
    ], $overrides);
}

test('an email purchase writes a delivery outbox row available immediately', function (): void {
    $giftCard = app(GiftCardService::class)->purchase(purchaseAttributes());

    $row = DispatchOutbox::query()
        ->where('event_type', GiftCardService::EVENT_DELIVERY)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->payload['gift_card_id'])->toBe($giftCard->id)
        ->and($row->available_at->lte(now()->addSecond()))->toBeTrue();
});

test('a scheduled purchase defers the delivery row to scheduled_send_at', function (): void {
    $sendAt = now()->addDays(3)->startOfHour();

    app(GiftCardService::class)->purchase(purchaseAttributes([
        'scheduled_send_at' => $sendAt,
    ]));

    $row = DispatchOutbox::query()
        ->where('event_type', GiftCardService::EVENT_DELIVERY)
        ->firstOrFail();

    expect($row->available_at->equalTo($sendAt))->toBeTrue();
});

test('a print purchase writes no delivery row', function (): void {
    app(GiftCardService::class)->purchase(purchaseAttributes([
        'delivery_method' => 'print',
    ]));

    expect(DispatchOutbox::query()
        ->where('event_type', GiftCardService::EVENT_DELIVERY)
        ->exists())->toBeFalse();
});

test('the dispatcher maps a delivery row to the delivery job', function (): void {
    Queue::fake();
    $giftCard = GiftCard::factory()->create();

    $row = DispatchOutbox::create([
        'event_type' => GiftCardService::EVENT_DELIVERY,
        'payload' => ['gift_card_id' => $giftCard->id],
        'available_at' => now(),
    ]);

    app(OutboxDispatcher::class)->dispatch($row);

    Queue::assertPushed(SendGiftCardDelivery::class, fn ($job) => $job->giftCardId === $giftCard->id);
});

test('the delivery job emails the recipient with the code', function (): void {
    Mail::fake();
    $giftCard = GiftCard::factory()->create([
        'status' => GiftCardStatus::Active,
        'recipient_email' => 'margot@example.com',
    ]);

    (new SendGiftCardDelivery($giftCard->id))->handle();

    Mail::assertQueued(GiftCardDeliveryMail::class, function (GiftCardDeliveryMail $mail) use ($giftCard) {
        return $mail->hasTo('margot@example.com')
            && $mail->giftCard->is($giftCard);
    });
});

test('the delivery job skips voided cards and missing cards', function (): void {
    Mail::fake();
    $voided = GiftCard::factory()->create(['status' => GiftCardStatus::Voided]);

    (new SendGiftCardDelivery($voided->id))->handle();
    (new SendGiftCardDelivery('00000000-0000-0000-0000-000000000000'))->handle();

    Mail::assertNothingQueued();
});

test('outbox:dispatch round-trips a purchase to a queued delivery email', function (): void {
    Mail::fake();

    app(GiftCardService::class)->purchase(purchaseAttributes([
        'recipient_email' => 'roundtrip@example.com',
    ]));

    // Postgres NOW() is pinned to the test transaction start while the row's
    // available_at uses PHP wall-clock — rewind so dispatchable() matches.
    DispatchOutbox::query()->update(['available_at' => now()->subMinute()]);

    $this->artisan('outbox:dispatch')->assertSuccessful();

    Mail::assertQueued(GiftCardDeliveryMail::class, fn (GiftCardDeliveryMail $mail) => $mail->hasTo('roundtrip@example.com'));
});
