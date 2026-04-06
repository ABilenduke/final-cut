<?php

use App\Models\GiftCard;
use App\Services\StripeService;
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

/*
|--------------------------------------------------------------------------
| Purchase — Success
|--------------------------------------------------------------------------
*/

test('purchase creates a gift card and returns 201 with correct structure', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
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

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 10000]))
        ->assertStatus(201);

    expect($fake->createdPaymentIntents)->toHaveCount(1);
    expect($fake->createdPaymentIntents[0]['amount'])->toBe(10000);
    expect($fake->createdPaymentIntents[0]['paymentMethodId'])->toBe('pm_test_123');
    expect($fake->createdPaymentIntents[0]['metadata'])->toBe(['type' => 'gift_card']);
});

test('purchase generates unique gift card codes', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(201);

    $codes = GiftCard::pluck('code')->toArray();
    expect($codes)->toHaveCount(2);
    expect($codes[0])->toStartWith('GC-');
    expect($codes[1])->toStartWith('GC-');
    expect($codes[0])->not->toBe($codes[1]);
});

test('purchase allows null message', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['message' => null]))
        ->assertStatus(201)
        ->assertJsonPath('data.message', null);
});

/*
|--------------------------------------------------------------------------
| Purchase — Validation Errors
|--------------------------------------------------------------------------
*/

test('purchase returns 422 when amount below minimum', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 499]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('purchase returns 422 when amount exceeds maximum', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 50001]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('purchase returns 422 when required fields are missing', function () {
    fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'amount',
            'recipientEmail',
            'recipientName',
            'senderName',
            'paymentMethodId',
        ]);
});

/*
|--------------------------------------------------------------------------
| Purchase — Stripe Failures
|--------------------------------------------------------------------------
*/

test('purchase returns 402 when card is declined', function () {
    fakeGiftCardStripe()->shouldDecline();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(402)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 400 for invalid payment method', function () {
    fakeGiftCardStripe()->shouldFailWithInvalidRequest();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 502 when Stripe is unavailable', function () {
    fakeGiftCardStripe()->shouldFailWithApiError();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase does not create gift card when payment returns non-terminal status', function () {
    fakeGiftCardStripe()->shouldReturnNonTerminalStatus();

    postJson('/api/gift-cards/purchase', validPurchasePayload())
        ->assertStatus(402)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Balance — Success
|--------------------------------------------------------------------------
*/

test('balance returns correct balance for valid code', function () {
    $card = GiftCard::factory()->create([
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
