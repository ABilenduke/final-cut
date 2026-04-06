<?php

use App\Models\GiftCard;
use App\Services\StripeService;
use Illuminate\Support\Str;
use Tests\Helpers\FakeStripeService;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function fakeGiftCardStripe(): FakeStripeService
{
    $fake = new FakeStripeService;
    app()->instance(StripeService::class, $fake);

    return $fake;
}

function validPurchasePayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 5000,
        'recipientEmail' => 'recipient@example.com',
        'recipientName' => 'Jane Doe',
        'senderName' => 'John Doe',
        'message' => 'Happy Birthday!',
        'paymentMethodId' => 'pm_test_123',
    ], $overrides);
}

function idempotencyHeader(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
}

/*
|--------------------------------------------------------------------------
| Purchase — Success
|--------------------------------------------------------------------------
*/

test('purchase creates a gift card and returns 201 with correct structure', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'initialBalance',
                'currentBalance',
                'recipientEmail',
                'recipientName',
                'senderName',
                'message',
                'status',
                'purchasedAt',
            ],
        ])
        ->assertJsonPath('data.initialBalance', 5000)
        ->assertJsonPath('data.currentBalance', 5000)
        ->assertJsonPath('data.recipientEmail', 'recipient@example.com')
        ->assertJsonPath('data.recipientName', 'Jane Doe')
        ->assertJsonPath('data.senderName', 'John Doe')
        ->assertJsonPath('data.message', 'Happy Birthday!')
        ->assertJsonPath('data.status', 'active');

    expect(GiftCard::count())->toBe(1);
    expect(GiftCard::first()->code)->toStartWith('GC-');
});

test('purchase charges Stripe the correct amount with gift_card metadata', function () {
    $fake = fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 10000]), idempotencyHeader())
        ->assertStatus(201);

    expect($fake->createdPaymentIntents)->toHaveCount(1);
    expect($fake->createdPaymentIntents[0]['amount'])->toBe(10000);
    expect($fake->createdPaymentIntents[0]['paymentMethodId'])->toBe('pm_test_123');
    expect($fake->createdPaymentIntents[0]['metadata'])->toBe(['type' => 'gift_card']);
});

test('purchase generates unique gift card codes', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(201);

    $codes = GiftCard::pluck('code')->toArray();
    expect($codes)->toHaveCount(2);
    expect($codes[0])->toStartWith('GC-');
    expect($codes[1])->toStartWith('GC-');
    expect($codes[0])->not->toBe($codes[1]);
});

test('purchase allows null message', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['message' => null]), idempotencyHeader())
        ->assertStatus(201)
        ->assertJsonPath('data.message', null);
});

test('purchase passes idempotency key to Stripe', function () {
    $fake = fakeGiftCardStripe();
    $key = '550e8400-e29b-41d4-a716-446655440000';

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
        ->assertStatus(201);

    expect($fake->createdPaymentIntents)->toHaveCount(1);
    expect($fake->createdPaymentIntents[0]['idempotencyKey'])->toBe($key);
});

test('purchase stores idempotency_key and payload_hash on gift card', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
        ->assertStatus(201);

    $giftCard = GiftCard::first();
    expect($giftCard->idempotency_key)->toBe($key);
    expect($giftCard->payload_hash)->not->toBeNull();
    expect(strlen($giftCard->payload_hash))->toBe(64);
});

/*
|--------------------------------------------------------------------------
| Purchase — Validation Errors
|--------------------------------------------------------------------------
*/

test('purchase returns 422 when amount below minimum', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 499]), idempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('purchase returns 422 when amount exceeds maximum', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 50001]), idempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('purchase returns 422 when required fields are missing', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', [], idempotencyHeader())
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'amount',
            'recipientEmail',
            'recipientName',
            'senderName',
            'paymentMethodId',
        ]);
});

test('purchase returns 422 when Idempotency-Key header is missing', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['idempotencyKey']);
});

test('purchase returns 422 when Idempotency-Key is not a valid UUID', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), ['Idempotency-Key' => 'not-a-uuid'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['idempotencyKey']);
});

/*
|--------------------------------------------------------------------------
| Purchase — Stripe Failures
|--------------------------------------------------------------------------
*/

test('purchase returns 402 when card is declined', function () {
    fakeGiftCardStripe()->shouldDecline();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(402)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 400 for invalid payment method', function () {
    fakeGiftCardStripe()->shouldFailWithInvalidRequest();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 502 when Stripe is unavailable', function () {
    fakeGiftCardStripe()->shouldFailWithApiError();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 502 when payment returns non-terminal status', function () {
    fakeGiftCardStripe()->shouldReturnNonTerminalStatus();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase stores stripe_payment_intent_id on gift card', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(201);

    expect(GiftCard::first()->stripe_payment_intent_id)->toBe('pi_fake_001');
});

test('purchase issues compensating refund when database write fails after payment', function () {
    $fake = fakeGiftCardStripe();

    // Force GiftCard::create to fail via a model event listener
    GiftCard::creating(function () {
        throw new RuntimeException('Simulated DB failure');
    });

    // Laravel's exception handler catches the re-thrown exception and returns 500
    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(500);

    // Verify Stripe was charged
    expect($fake->createdPaymentIntents)->toHaveCount(1);

    // Verify compensating refund was issued
    expect($fake->refundedPaymentIntents)->toHaveCount(1);
    expect($fake->refundedPaymentIntents[0]['paymentIntentId'])->toBe('pi_fake_001');

    // No gift card was persisted
    expect(GiftCard::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Balance — Success
|--------------------------------------------------------------------------
*/

test('balance returns correct balance for valid code', function () {
    GiftCard::factory()->create([
        'code' => 'GC-TESTCODE1',
        'current_balance' => 7500,
    ]);

    getJson('/api/gift-cards/balance?code=GC-TESTCODE1')
        ->assertOk()
        ->assertJson([
            'data' => [
                'balance' => 7500,
                'status' => 'active',
            ],
        ]);
});

test('balance returns depleted card info', function () {
    GiftCard::factory()->depleted()->create([
        'code' => 'GC-DEPLETED1',
    ]);

    getJson('/api/gift-cards/balance?code=GC-DEPLETED1')
        ->assertOk()
        ->assertJson([
            'data' => [
                'balance' => 0,
                'status' => 'depleted',
            ],
        ]);
});

/*
|--------------------------------------------------------------------------
| Balance — Errors
|--------------------------------------------------------------------------
*/

test('balance returns 404 for invalid code', function () {
    getJson('/api/gift-cards/balance?code=NONEXISTENT')
        ->assertNotFound();
});

test('balance returns 422 when code param is missing', function () {
    getJson('/api/gift-cards/balance')
        ->assertStatus(422);
});
