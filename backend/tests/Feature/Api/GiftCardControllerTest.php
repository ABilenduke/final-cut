<?php

use App\Enums\GiftCardLedgerType;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
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

    $giftCard = GiftCard::first();
    $ledger = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->type)->toBe(GiftCardLedgerType::Purchase)
        ->and($ledger->amount_cents)->toBe(5000)
        ->and($ledger->balance_after_cents)->toBe(5000);
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

/*
|--------------------------------------------------------------------------
| Purchase — Idempotency Replay
|--------------------------------------------------------------------------
*/

test('purchase replay returns same gift card when first attempt succeeded', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();
    $payload = validPurchasePayload();

    $first = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $first->assertStatus(201);
    $giftCardId = $first->json('data.id');

    $replay = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $replay->assertStatus(201);
    $replay->assertJsonPath('data.id', $giftCardId);

    expect(GiftCard::count())->toBe(1);
});

test('purchase replay returns requires_action when first attempt triggered 3DS', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();
    $key = (string) Str::uuid();
    $payload = validPurchasePayload();

    $first = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $first->assertOk();
    $first->assertJsonPath('data.requiresAction', true);
    $clientSecret = $first->json('data.clientSecret');

    $replay = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $replay->assertOk();
    $replay->assertJsonPath('data.requiresAction', true);
    $replay->assertJsonPath('data.clientSecret', $clientSecret);

    expect(GiftCard::count())->toBe(0);
});

test('purchase replay returns cached error when first attempt was declined', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldDecline();
    $key = (string) Str::uuid();
    $payload = validPurchasePayload();

    $first = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $first->assertStatus(402);

    // Switch to succeed — but replay should still return cached decline
    $fake->shouldSucceed();

    $replay = postJson('/api/gift-cards/purchase', $payload, idempotencyHeader($key));
    $replay->assertStatus(402);
    $replay->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

test('purchase returns 409 when same key used with different payload', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 5000]), idempotencyHeader($key))
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 7500]), idempotencyHeader($key))
        ->assertStatus(409)
        ->assertJsonPath('errors.0.field', 'idempotencyKey');

    expect(GiftCard::count())->toBe(1);
});

test('purchase returns 409 on payload mismatch against cached 3DS state', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 5000]), idempotencyHeader($key))
        ->assertOk();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['amount' => 7500]), idempotencyHeader($key))
        ->assertStatus(409);
});

/*
|--------------------------------------------------------------------------
| Purchase — Payload Fingerprint Normalization
|--------------------------------------------------------------------------
*/

test('purchase replay treats email casing differences as same payload', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientEmail' => 'Test@Example.COM']), idempotencyHeader($key))
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientEmail' => 'test@example.com']), idempotencyHeader($key))
        ->assertStatus(201);

    expect(GiftCard::count())->toBe(1);
});

test('purchase replay treats email whitespace differences as same payload', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientEmail' => '  test@example.com  ']), idempotencyHeader($key))
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientEmail' => 'test@example.com']), idempotencyHeader($key))
        ->assertStatus(201);

    expect(GiftCard::count())->toBe(1);
});

test('purchase replay treats null and empty message as same payload', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['message' => null]), idempotencyHeader($key))
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(['message' => '']), idempotencyHeader($key))
        ->assertStatus(201);

    expect(GiftCard::count())->toBe(1);
});

test('purchase returns 409 when name casing differs', function () {
    fakeGiftCardStripe();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientName' => 'Jane Doe']), idempotencyHeader($key))
        ->assertStatus(201);

    postJson('/api/gift-cards/purchase', validPurchasePayload(['recipientName' => 'jane doe']), idempotencyHeader($key))
        ->assertStatus(409);
});

/*
|--------------------------------------------------------------------------
| Purchase — 3DS Flow
|--------------------------------------------------------------------------
*/

test('purchase returns requires_action with client secret when 3DS required', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['requiresAction', 'clientSecret', 'paymentIntentId'],
        ])
        ->assertJsonPath('data.requiresAction', true);
});

test('purchase does not create gift card when 3DS is required', function () {
    fakeGiftCardStripe()->shouldRequire3ds();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertOk();

    expect(GiftCard::count())->toBe(0);
});

test('purchase writes both cache keys for 3DS pending state', function () {
    fakeGiftCardStripe()->shouldRequire3ds();
    $key = (string) Str::uuid();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key));
    $piId = $response->json('data.paymentIntentId');

    expect(Cache::has("gift_card_idempotency:{$key}"))->toBeTrue();
    expect(Cache::has("pending_gift_card:{$piId}"))->toBeTrue();

    $pending = Cache::get("pending_gift_card:{$piId}");
    expect($pending['idempotency_key'])->toBe($key);
    expect($pending['amount'])->toBe(5000);
});

/*
|--------------------------------------------------------------------------
| Purchase — Failure Caching Distinction
|--------------------------------------------------------------------------
*/

test('card decline is cached as hard failure and replayed', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldDecline();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
        ->assertStatus(402);

    expect(Cache::has("gift_card_idempotency:{$key}"))->toBeTrue();

    $cached = Cache::get("gift_card_idempotency:{$key}");
    expect($cached['status'])->toBe('failed');
    expect($cached['error_status'])->toBe(402);
});

test('Stripe unavailability is NOT cached and retry goes through full flow', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldFailWithApiError();
    $key = (string) Str::uuid();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
        ->assertStatus(502);

    expect(Cache::has("gift_card_idempotency:{$key}"))->toBeFalse();

    // Switch to succeed — retry should work because nothing was cached
    $fake->shouldSucceed();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
        ->assertStatus(201);

    expect(GiftCard::count())->toBe(1);
});

test('unexpected processing status is NOT cached and returns 502', function () {
    fakeGiftCardStripe()->shouldReturnNonTerminalStatus('processing');

    postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
        ->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'payment');

    expect(GiftCard::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Confirm — Success
|--------------------------------------------------------------------------
*/

test('confirm creates gift card after 3DS completion', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldSucceed();

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'code', 'initialBalance', 'currentBalance', 'recipientEmail', 'recipientName', 'senderName', 'status', 'purchasedAt'],
        ])
        ->assertJsonPath('data.initialBalance', 5000)
        ->assertJsonPath('data.recipientEmail', 'recipient@example.com')
        ->assertJsonPath('data.status', 'active');

    expect(GiftCard::count())->toBe(1);
    expect($fake->confirmedPaymentIntents)->toHaveCount(1);
});

test('confirm stores correct idempotency key and stripe PI on gift card', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();
    $key = (string) Str::uuid();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key));
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldSucceed();

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(201);

    $giftCard = GiftCard::first();
    expect($giftCard->idempotency_key)->toBe($key);
    expect($giftCard->stripe_payment_intent_id)->toBe($piId);
});

test('confirm clears both cache keys on success', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();
    $key = (string) Str::uuid();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key));
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldSucceed();

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(201);

    expect(Cache::has("gift_card_idempotency:{$key}"))->toBeFalse();
    expect(Cache::has("pending_gift_card:{$piId}"))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Confirm — Idempotency
|--------------------------------------------------------------------------
*/

test('confirm replay returns existing gift card without duplicate', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldSucceed();

    $first = postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId]);
    $first->assertStatus(201);
    $giftCardId = $first->json('data.id');

    $replay = postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId]);
    $replay->assertStatus(201);
    $replay->assertJsonPath('data.id', $giftCardId);

    expect(GiftCard::count())->toBe(1);
    // Second confirm should NOT call Stripe again
    expect($fake->confirmedPaymentIntents)->toHaveCount(1);
});

test('confirm returns 410 when pending state has expired', function () {
    postJson('/api/gift-cards/confirm', ['paymentIntentId' => 'pi_nonexistent'])
        ->assertStatus(410);
});

test('confirm returns 422 when paymentIntentId is missing', function () {
    postJson('/api/gift-cards/confirm', [])
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Confirm — Failure Handling
|--------------------------------------------------------------------------
*/

test('confirm returns 402 when Stripe declines on confirm', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldDecline();

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(402);

    expect(GiftCard::count())->toBe(0);
    // Cache preserved for retry
    expect(Cache::has("pending_gift_card:{$piId}"))->toBeTrue();
});

test('confirm returns 502 when Stripe is unavailable', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldFailWithApiError();

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(502);

    expect(Cache::has("pending_gift_card:{$piId}"))->toBeTrue();
});

test('confirm succeeds on retry after prior failed attempt', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    // First confirm fails
    $fake->shouldFailWithApiError();
    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(502);

    // Retry succeeds
    $fake->shouldSucceed();
    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(201);

    expect(GiftCard::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Confirm — Compensating Refund
|--------------------------------------------------------------------------
*/

test('confirm issues compensating refund when DB write fails after payment', function () {
    $fake = fakeGiftCardStripe();
    $fake->shouldRequire3ds();

    $response = postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader());
    $piId = $response->json('data.paymentIntentId');

    $fake->shouldSucceed();

    // Force GiftCard::create to fail
    GiftCard::creating(function () {
        throw new RuntimeException('Simulated DB failure');
    });

    postJson('/api/gift-cards/confirm', ['paymentIntentId' => $piId])
        ->assertStatus(500);

    // Verify Stripe was confirmed
    expect($fake->confirmedPaymentIntents)->toHaveCount(1);

    // Verify compensating refund was issued
    expect($fake->refundedPaymentIntents)->toHaveCount(1);
    expect($fake->refundedPaymentIntents[0]['paymentIntentId'])->toBe($piId);

    expect(GiftCard::count())->toBe(0);
});
