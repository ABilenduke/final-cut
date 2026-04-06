# Gift Card 3DS & Idempotency Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 3D Secure payment support and request-level idempotency to the gift card purchase flow, so that 3DS-required payments can complete and client retries never create duplicate charges or gift cards.

**Architecture:** The purchase endpoint gains an `Idempotency-Key` header contract with app-level replay semantics (DB for succeeded, cache for pending/failed states). A new `/gift-cards/confirm` endpoint handles 3DS completion, following the same cache-based pending state pattern used by `BookingController`. `StripeService::createPaymentIntent()` gains an optional idempotency key parameter passed to Stripe's API.

**Tech Stack:** Laravel 13 (PHP 8.4), Pest, Redis cache, Stripe PHP SDK, PostgreSQL

**Design doc:** `docs/plans/backend/2026-04-06-gift-card-3ds-idempotency-design.md`

---

## Task 1: Add idempotency_key column to gift_cards migration

Since the project is pre-launch, we edit the existing migration directly (per project convention — no additive migrations).

**Files:**
- Modify: `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php:22` (add column before `purchased_at`)
- Modify: `backend/app/Models/GiftCard.php:12-16` (add to Fillable attribute)

**Step 1: Add the column to the migration**

In `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php`, add this line after `stripe_payment_intent_id` (line 21) and before `purchased_at` (line 22):

```php
$table->string('idempotency_key')->nullable()->unique();
```

**Step 2: Add to model's Fillable attribute**

In `backend/app/Models/GiftCard.php`, update the Fillable attribute to include `idempotency_key`:

```php
#[Fillable([
    'code', 'initial_balance', 'current_balance', 'recipient_email',
    'recipient_name', 'sender_name', 'message', 'status',
    'stripe_payment_intent_id', 'idempotency_key', 'purchased_at',
])]
```

**Step 3: Reset the database**

Run from project root:
```bash
make fresh
```

**Step 4: Verify existing tests still pass**

Run:
```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All 17 tests pass. The new column is nullable, so no existing behavior changes.

**Step 5: Commit**

```bash
git add backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php backend/app/Models/GiftCard.php
git commit -m "feat: add idempotency_key column to gift_cards table"
```

---

## Task 2: Add idempotency key parameter to StripeService

Backward-compatible change. Existing callers are unaffected.

**Files:**
- Modify: `backend/app/Services/StripeService.php:50-63`
- Modify: `backend/tests/Helpers/FakeStripeService.php:91-151`

**Step 1: Write the failing test**

Add to `backend/tests/Feature/Api/GiftCardControllerTest.php`, at the end of the "Purchase — Success" section (after line 101):

```php
test('purchase passes idempotency key to Stripe', function () {
    $fake = fakeGiftCardStripe();

    postJson('/api/gift-cards/purchase', validPurchasePayload(), [
        'Idempotency-Key' => '550e8400-e29b-41d4-a716-446655440000',
    ])->assertStatus(201);

    expect($fake->createdPaymentIntents)->toHaveCount(1);
    expect($fake->createdPaymentIntents[0]['idempotencyKey'])->toBe('550e8400-e29b-41d4-a716-446655440000');
});
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec -u 1000 backend php artisan test --filter="purchase passes idempotency key to Stripe"
```
Expected: FAIL — the test will fail because (a) the controller doesn't read the header yet, and (b) FakeStripeService doesn't track idempotency keys.

**Step 3: Update StripeService signature**

In `backend/app/Services/StripeService.php`, replace the `createPaymentIntent` method (lines 50-63):

```php
public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = [], ?string $idempotencyKey = null): PaymentIntent
{
    $options = [];
    if ($idempotencyKey !== null) {
        $options['idempotency_key'] = $idempotencyKey;
    }

    return $this->client()->paymentIntents->create([
        'amount' => $amount,
        'currency' => 'usd',
        'payment_method' => $paymentMethodId,
        'confirm' => true,
        'automatic_payment_methods' => [
            'enabled' => true,
            'allow_redirects' => 'never',
        ],
        'metadata' => $metadata,
    ], $options);
}
```

**Step 4: Update FakeStripeService to track idempotency keys**

In `backend/tests/Helpers/FakeStripeService.php`, update the `createPaymentIntent` method signature (line 91) and tracking (lines 93-98):

```php
public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = [], ?string $idempotencyKey = null): PaymentIntent
{
    $this->createCallCount++;
    $this->createdPaymentIntents[] = [
        'amount' => $amount,
        'paymentMethodId' => $paymentMethodId,
        'metadata' => $metadata,
        'idempotencyKey' => $idempotencyKey,
    ];
```

The rest of the method body (behavior switches) stays the same.

**Step 5: Run test — still fails**

```bash
docker compose exec -u 1000 backend php artisan test --filter="purchase passes idempotency key to Stripe"
```
Expected: Still FAIL — the controller doesn't read the header or pass it to StripeService yet. That comes in Task 4. Leave this test in place; it will pass once the controller is updated.

**Step 6: Verify existing tests still pass**

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: 17 existing tests pass. The new test fails. The signature change is backward-compatible so no existing callers break.

Also run the booking tests to verify no regression:
```bash
docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest
```
Expected: All pass.

**Step 7: Commit**

```bash
git add backend/app/Services/StripeService.php backend/tests/Helpers/FakeStripeService.php backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "feat: add optional idempotency key parameter to StripeService::createPaymentIntent"
```

---

## Task 3: Add payload fingerprint helper

A small utility used by the controller for idempotency hash computation.

**Files:**
- Create: `backend/app/Support/PayloadFingerprint.php`
- Create: `backend/tests/Unit/Support/PayloadFingerprintTest.php`

**Step 1: Write the failing tests**

Create `backend/tests/Unit/Support/PayloadFingerprintTest.php`:

```php
<?php

use App\Support\PayloadFingerprint;

test('computes deterministic hash from gift card payload', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'test@example.com', 'Jane', 'John', 'Happy birthday');
    $hash2 = PayloadFingerprint::giftCard(5000, 'test@example.com', 'Jane', 'John', 'Happy birthday');

    expect($hash1)->toBe($hash2);
    expect(strlen($hash1))->toBe(64); // SHA-256 hex
});

test('different amounts produce different hashes', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'test@example.com', 'Jane', 'John', 'Hi');
    $hash2 = PayloadFingerprint::giftCard(7500, 'test@example.com', 'Jane', 'John', 'Hi');

    expect($hash1)->not->toBe($hash2);
});

test('normalizes email to lowercase and trims whitespace', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'Test@Example.COM', 'Jane', 'John', null);
    $hash2 = PayloadFingerprint::giftCard(5000, '  test@example.com  ', 'Jane', 'John', null);

    expect($hash1)->toBe($hash2);
});

test('trims whitespace on names', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'a@b.com', ' Jane ', ' John ', null);
    $hash2 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', null);

    expect($hash1)->toBe($hash2);
});

test('treats null and empty string message as equivalent', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', null);
    $hash2 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', '');

    expect($hash1)->toBe($hash2);
});

test('trims message before hashing', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', ' Hello ');
    $hash2 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', 'Hello');

    expect($hash1)->toBe($hash2);
});

test('preserves name casing in hash', function () {
    $hash1 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'Jane', 'John', null);
    $hash2 = PayloadFingerprint::giftCard(5000, 'a@b.com', 'jane', 'john', null);

    expect($hash1)->not->toBe($hash2);
});
```

**Step 2: Run tests to verify they fail**

```bash
docker compose exec -u 1000 backend php artisan test --filter=PayloadFingerprintTest
```
Expected: FAIL — class does not exist.

**Step 3: Implement the fingerprint class**

Create `backend/app/Support/PayloadFingerprint.php`:

```php
<?php

namespace App\Support;

class PayloadFingerprint
{
    public static function giftCard(
        int $amount,
        string $recipientEmail,
        string $recipientName,
        string $senderName,
        ?string $message,
    ): string {
        $canonical = json_encode([
            'amount' => $amount,
            'recipientEmail' => strtolower(trim($recipientEmail)),
            'recipientName' => trim($recipientName),
            'senderName' => trim($senderName),
            'message' => trim($message ?? ''),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
docker compose exec -u 1000 backend php artisan test --filter=PayloadFingerprintTest
```
Expected: All 7 tests pass.

**Step 5: Commit**

```bash
git add backend/app/Support/PayloadFingerprint.php backend/tests/Unit/Support/PayloadFingerprintTest.php
git commit -m "feat: add PayloadFingerprint utility for gift card idempotency hashing"
```

---

## Task 4: Rewrite GiftCardController::purchase with idempotency + 3DS

This is the largest task. It replaces the current `purchase` method with the full idempotency and 3DS flow from the design doc.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/GiftCardController.php` (rewrite `purchase` method)
- Modify: `backend/app/Http/Requests/PurchaseGiftCardRequest.php` (add idempotency key validation)
- Modify: `backend/routes/api.php:59` (add confirm route)
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php` (update existing tests, add new ones)

### Step 1: Update PurchaseGiftCardRequest to validate idempotency key from header

Replace the entire `PurchaseGiftCardRequest` class in `backend/app/Http/Requests/PurchaseGiftCardRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotencyKey' => $this->header('Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotencyKey' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:500', 'max:50000'],
            'recipientEmail' => ['required', 'email'],
            'recipientName' => ['required', 'string', 'max:255'],
            'senderName' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'paymentMethodId' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotencyKey.required' => 'The Idempotency-Key header is required.',
            'idempotencyKey.uuid' => 'The Idempotency-Key header must be a valid UUID.',
        ];
    }
}
```

### Step 2: Rewrite the purchase method on GiftCardController

Replace the entire `GiftCardController` class in `backend/app/Http/Controllers/Api/GiftCardController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\GiftCardStatus;
use App\Http\Requests\PurchaseGiftCardRequest;
use App\Http\Resources\GiftCardResource;
use App\Models\GiftCard;
use App\Services\StripeService;
use App\Support\PayloadFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class GiftCardController extends Controller
{
    private const PENDING_TTL_MINUTES = 15;

    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    public function purchase(PurchaseGiftCardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotencyKey'];

        $payloadHash = PayloadFingerprint::giftCard(
            $validated['amount'],
            $validated['recipientEmail'],
            $validated['recipientName'],
            $validated['senderName'],
            $validated['message'] ?? null,
        );

        // 1. Check DB for completed purchase with this idempotency key
        $existing = GiftCard::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->payload_hash !== $payloadHash) {
                return $this->payloadMismatchResponse();
            }

            return $this->successResponse(new GiftCardResource($existing), status: 201);
        }

        // 2. Check cache for pending or failed state
        $cached = Cache::get("gift_card_idempotency:{$idempotencyKey}");
        if ($cached) {
            if ($cached['payload_hash'] !== $payloadHash) {
                return $this->payloadMismatchResponse();
            }

            if ($cached['status'] === 'requires_action') {
                return $this->successResponse([
                    'requiresAction' => true,
                    'clientSecret' => $cached['client_secret'],
                    'paymentIntentId' => $cached['payment_intent_id'],
                ]);
            }

            if ($cached['status'] === 'failed') {
                return $this->errorResponse([
                    ['field' => $cached['error_field'], 'message' => $cached['error_message']],
                ], $cached['error_status']);
            }
        }

        // 3. No prior state — proceed with Stripe
        try {
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $validated['amount'],
                $validated['paymentMethodId'],
                ['type' => 'gift_card'],
                $idempotencyKey,
            );

            if ($paymentIntent->status === 'requires_action') {
                Cache::put("gift_card_idempotency:{$idempotencyKey}", [
                    'status' => 'requires_action',
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'payload_hash' => $payloadHash,
                ], now()->addMinutes(self::PENDING_TTL_MINUTES));

                Cache::put("pending_gift_card:{$paymentIntent->id}", [
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $validated['amount'],
                    'recipientEmail' => $validated['recipientEmail'],
                    'recipientName' => $validated['recipientName'],
                    'senderName' => $validated['senderName'],
                    'message' => $validated['message'] ?? null,
                    'payload_hash' => $payloadHash,
                ], now()->addMinutes(self::PENDING_TTL_MINUTES));

                return $this->successResponse([
                    'requiresAction' => true,
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentIntentId' => $paymentIntent->id,
                ]);
            }

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse([
                    ['field' => 'payment', 'message' => 'Payment is in an unexpected state. Please try again or contact support.'],
                ], 502);
            }
        } catch (CardException $e) {
            $this->cacheHardFailure($idempotencyKey, 'payment', $e->getMessage(), 402, $payloadHash);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 402);
        } catch (InvalidRequestException $e) {
            $this->cacheHardFailure($idempotencyKey, 'payment', $e->getMessage(), 400, $payloadHash);

            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 400);
        } catch (ApiErrorException $e) {
            report($e);

            // Transient — NOT cached, retry goes through full flow
            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.'],
            ], 502);
        }

        // 4. Payment succeeded — create gift card
        return $this->createGiftCard(
            $validated,
            $paymentIntent->id,
            $idempotencyKey,
        );
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => ['required', 'string'],
        ]);

        $paymentIntentId = $request->input('paymentIntentId');

        // 1. Check if gift card already exists for this PI (replay)
        $existing = GiftCard::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($existing) {
            return $this->successResponse(new GiftCardResource($existing), status: 201);
        }

        // 2. Check cache for pending state (authoritative app context)
        $pendingData = Cache::get("pending_gift_card:{$paymentIntentId}");
        if (! $pendingData) {
            return $this->errorResponse([
                ['message' => 'Session expired. Please start over.'],
            ], 410);
        }

        // 3. Confirm payment with Stripe
        try {
            $paymentIntent = $this->stripeService->confirmPaymentIntent($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                // Cache preserved for retry
                return $this->errorResponse([
                    ['field' => 'payment', 'message' => 'Payment confirmation failed.'],
                ], 402);
            }
        } catch (CardException $e) {
            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 402);
        } catch (InvalidRequestException $e) {
            return $this->errorResponse([
                ['field' => 'payment', 'message' => $e->getMessage()],
            ], 400);
        } catch (ApiErrorException $e) {
            report($e);

            // Cache preserved for retry
            return $this->errorResponse([
                ['field' => 'payment', 'message' => 'Payment service is temporarily unavailable. Please try again.'],
            ], 502);
        }

        // 4. Payment confirmed — create gift card
        $result = $this->createGiftCard(
            [
                'amount' => $pendingData['amount'],
                'recipientEmail' => $pendingData['recipientEmail'],
                'recipientName' => $pendingData['recipientName'],
                'senderName' => $pendingData['senderName'],
                'message' => $pendingData['message'],
            ],
            $paymentIntentId,
            $pendingData['idempotency_key'],
        );

        // Only clear cache on success
        if ($result->getStatusCode() === 201) {
            Cache::forget("pending_gift_card:{$paymentIntentId}");
            Cache::forget("gift_card_idempotency:{$pendingData['idempotency_key']}");
        }

        return $result;
    }

    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $giftCard = GiftCard::where('code', $request->query('code'))->first();

        if (! $giftCard) {
            return $this->errorResponse([
                ['field' => 'code', 'message' => 'Gift card not found.'],
            ], 404);
        }

        return $this->successResponse([
            'balance' => $giftCard->current_balance,
            'status' => $giftCard->status->value,
        ]);
    }

    /**
     * Create a gift card after successful payment, with race-condition handling.
     *
     * If a unique constraint violation occurs on idempotency_key (concurrent
     * request raced us), fetches and returns the existing record instead of
     * surfacing a 500.
     */
    private function createGiftCard(array $data, string $paymentIntentId, string $idempotencyKey): JsonResponse
    {
        try {
            $code = $this->generateUniqueCode();

            $giftCard = GiftCard::create([
                'code' => $code,
                'initial_balance' => $data['amount'],
                'current_balance' => $data['amount'],
                'recipient_email' => $data['recipientEmail'],
                'recipient_name' => $data['recipientName'],
                'sender_name' => $data['senderName'],
                'message' => $data['message'] ?? null,
                'status' => GiftCardStatus::Active,
                'stripe_payment_intent_id' => $paymentIntentId,
                'idempotency_key' => $idempotencyKey,
                'purchased_at' => now(),
            ]);

            return $this->successResponse(new GiftCardResource($giftCard), status: 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition: another request with the same key completed first
            $existing = GiftCard::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $this->successResponse(new GiftCardResource($existing), status: 201);
            }

            // If not found by idempotency_key, try stripe_payment_intent_id (confirm path)
            $existing = GiftCard::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($existing) {
                return $this->successResponse(new GiftCardResource($existing), status: 201);
            }

            // Neither found — unexpected, re-throw
            throw $e;
        } catch (\Throwable $e) {
            $this->refundOrReport($paymentIntentId);

            throw $e;
        }
    }

    private function cacheHardFailure(
        string $idempotencyKey,
        string $field,
        string $message,
        int $status,
        string $payloadHash,
    ): void {
        Cache::put("gift_card_idempotency:{$idempotencyKey}", [
            'status' => 'failed',
            'error_field' => $field,
            'error_message' => $message,
            'error_status' => $status,
            'payload_hash' => $payloadHash,
        ], now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * Attempt to refund a captured PaymentIntent as a compensating action.
     * If the refund itself fails, report it so it can be resolved manually.
     */
    private function refundOrReport(string $paymentIntentId): void
    {
        try {
            $this->stripeService->refundPaymentIntent($paymentIntentId);
        } catch (\Throwable $refundException) {
            report($refundException);
        }
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'GC-'.strtoupper(Str::random(8));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }

    private function payloadMismatchResponse(): JsonResponse
    {
        return $this->errorResponse([
            ['field' => 'idempotencyKey', 'message' => 'This key was already used with different parameters.'],
        ], 409);
    }
}
```

### Step 3: Add the confirm route

In `backend/routes/api.php`, add after line 58 (the purchase route):

```php
Route::post('/gift-cards/confirm', [GiftCardController::class, 'confirm']);
```

### Step 4: Add `payload_hash` column to GiftCard model

We need to store the payload hash on completed gift cards for mismatch detection on replay. In `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php`, add after the `idempotency_key` line:

```php
$table->string('payload_hash')->nullable();
```

Update the Fillable attribute in `backend/app/Models/GiftCard.php`:

```php
#[Fillable([
    'code', 'initial_balance', 'current_balance', 'recipient_email',
    'recipient_name', 'sender_name', 'message', 'status',
    'stripe_payment_intent_id', 'idempotency_key', 'payload_hash', 'purchased_at',
])]
```

And update the `createGiftCard` private method to also persist the hash. In the `GiftCard::create()` call, add:

```php
'payload_hash' => PayloadFingerprint::giftCard(
    $data['amount'],
    $data['recipientEmail'],
    $data['recipientName'],
    $data['senderName'],
    $data['message'] ?? null,
),
```

### Step 5: Reset database

```bash
make fresh
```

### Step 6: Update existing tests to send Idempotency-Key header

Every existing test that calls `postJson('/api/gift-cards/purchase', ...)` needs an `Idempotency-Key` header, since it's now required (422 without it).

Update the `validPurchasePayload` helper function at the top of `backend/tests/Feature/Api/GiftCardControllerTest.php` — it stays the same.

Add a helper function for generating a unique idempotency key header:

```php
function idempotencyHeader(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
}
```

Add `use Illuminate\Support\Str;` to the imports.

Then update **every** `postJson('/api/gift-cards/purchase', validPurchasePayload(...))` call to include the header as the third argument:

```php
// Before:
postJson('/api/gift-cards/purchase', validPurchasePayload())

// After:
postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader())
```

For tests that need to reuse the same key (like the Stripe idempotency key test from Task 2), use a fixed key:

```php
$key = (string) Str::uuid();
postJson('/api/gift-cards/purchase', validPurchasePayload(), idempotencyHeader($key))
```

### Step 7: Run all existing tests to verify they pass

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All 18 tests pass (17 existing + the one from Task 2 that now has the full controller implementation).

### Step 8: Commit

```bash
git add backend/app/Http/Controllers/Api/GiftCardController.php \
    backend/app/Http/Requests/PurchaseGiftCardRequest.php \
    backend/routes/api.php \
    backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php \
    backend/app/Models/GiftCard.php \
    backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "feat: rewrite gift card purchase with idempotency and 3DS support"
```

---

## Task 5: Tests — Purchase idempotency replay

Tests that the same idempotency key replays the correct response for each outcome.

**Files:**
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php`

### Step 1: Write the idempotency replay tests

Add a new test section in `backend/tests/Feature/Api/GiftCardControllerTest.php`:

```php
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
```

### Step 2: Run the tests

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All tests pass — both existing (updated with headers) and new idempotency tests.

### Step 3: Commit

```bash
git add backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "test: add gift card purchase idempotency replay tests"
```

---

## Task 6: Tests — Purchase payload fingerprint normalization

Verifies that the normalization rules in the design doc are enforced end-to-end.

**Files:**
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php`

### Step 1: Write the normalization tests

```php
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
```

### Step 2: Run the tests

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All pass.

### Step 3: Commit

```bash
git add backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "test: add gift card payload fingerprint normalization tests"
```

---

## Task 7: Tests — Purchase 3DS flow

Tests that the 3DS pending state is correctly written and that no gift card is created prematurely.

**Files:**
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php`

### Step 1: Write the 3DS purchase tests

```php
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
```

### Step 2: Write the failure caching distinction tests

```php
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
```

### Step 3: Run the tests

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All pass.

### Step 4: Commit

```bash
git add backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "test: add gift card 3DS flow and failure caching tests"
```

---

## Task 8: Tests — Confirm endpoint success + idempotency

**Files:**
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php`

### Step 1: Write the confirm success tests

```php
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
```

### Step 2: Write the confirm idempotency tests

```php
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
```

### Step 3: Run the tests

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All pass.

### Step 4: Commit

```bash
git add backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "test: add gift card confirm success and idempotency tests"
```

---

## Task 9: Tests — Confirm failure handling + compensating refunds

**Files:**
- Modify: `backend/tests/Feature/Api/GiftCardControllerTest.php`

### Step 1: Write the confirm failure tests

```php
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
```

### Step 2: Write the compensating refund tests

```php
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
```

### Step 3: Update the existing compensating refund test for purchase path

The existing test `purchase issues compensating refund when database write fails after payment` (around line 194) needs updating to include the `Idempotency-Key` header. This was done in Task 4 step 6, but verify it still passes.

### Step 4: Run all tests

```bash
docker compose exec -u 1000 backend php artisan test --filter=GiftCardControllerTest
```
Expected: All tests pass.

Also run the full test suite to verify no regressions:
```bash
docker compose exec -u 1000 backend composer test
```
Expected: All tests pass.

### Step 5: Commit

```bash
git add backend/tests/Feature/Api/GiftCardControllerTest.php
git commit -m "test: add gift card confirm failure handling and compensating refund tests"
```

---

## Task 10: Update GiftCardResource and run PHPStan

The `GiftCardResource` does not yet include the `payloadHash` field (which should stay internal — not exposed to clients). Verify this is correct and run static analysis.

**Files:**
- Verify: `backend/app/Http/Resources/GiftCardResource.php` — should NOT expose `idempotency_key` or `payload_hash`
- Run: PHPStan baseline regeneration if needed

### Step 1: Verify GiftCardResource does not leak internal fields

Read `backend/app/Http/Resources/GiftCardResource.php` and confirm that `idempotency_key` and `payload_hash` are NOT in the `toArray` output. These are internal fields for server-side replay logic, not client-facing data.

The existing resource already returns only the public fields — no changes needed.

### Step 2: Run Pint for code style

```bash
docker compose exec -u 1000 backend php artisan pint
```

### Step 3: Run PHPStan

```bash
docker compose exec -u 1000 backend ./vendor/bin/phpstan analyse --memory-limit=512M
```

If new errors appear in the new/modified files, fix them. If errors appear in unrelated files, regenerate the baseline:
```bash
docker compose exec -u 1000 backend ./vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline
```

### Step 4: Run the full test suite one final time

```bash
docker compose exec -u 1000 backend composer test
```
Expected: All tests pass (existing 372+ plus ~25 new gift card tests).

### Step 5: Commit any Pint/PHPStan fixes

```bash
git add -A
git commit -m "chore: fix code style and regenerate PHPStan baseline"
```

---

## Task 11: Update progress journal

**Files:**
- Modify: `docs/PROGRESS.md`

Add a new section documenting this work:

```markdown
## Gift Card 3DS & Idempotency
**Status:** ✅ Complete
**Started:** 2026-04-06
**Completed:** 2026-04-06

### Work Done
- [2026-04-06] Added idempotency_key and payload_hash columns to gift_cards table
- [2026-04-06] Added optional idempotency key parameter to StripeService::createPaymentIntent
- [2026-04-06] Created PayloadFingerprint utility for canonical request hashing
- [2026-04-06] Rewrote GiftCardController::purchase with full idempotency + 3DS support
- [2026-04-06] Added POST /api/gift-cards/confirm endpoint for 3DS completion
- [2026-04-06] Added comprehensive tests for replay, normalization, 3DS, failure caching, confirm

### Decisions
- [2026-04-06] Hard failures (declined, invalid PM) are cached for 15 min to enable deterministic replay; transient failures (Stripe unavailable) are NOT cached to allow retry
- [2026-04-06] Compensating refund is a deliberate new design decision for gift cards, not assumed precedent from booking flow
- [2026-04-06] payload_hash stored on gift card row for mismatch detection; idempotency_key and payload_hash not exposed in API response

### Files Changed
- `backend/database/migrations/2026_04_04_200004_create_gift_cards_table.php` — added idempotency_key and payload_hash columns
- `backend/app/Models/GiftCard.php` — added to Fillable
- `backend/app/Services/StripeService.php` — optional idempotencyKey param on createPaymentIntent
- `backend/tests/Helpers/FakeStripeService.php` — tracks idempotency keys
- `backend/app/Support/PayloadFingerprint.php` — new utility class
- `backend/app/Http/Controllers/Api/GiftCardController.php` — full rewrite with idempotency + 3DS + confirm
- `backend/app/Http/Requests/PurchaseGiftCardRequest.php` — Idempotency-Key header validation
- `backend/routes/api.php` — added confirm route
- `backend/tests/Feature/Api/GiftCardControllerTest.php` — ~25 new tests
- `backend/tests/Unit/Support/PayloadFingerprintTest.php` — 7 unit tests
```

### Step 2: Commit

```bash
git add docs/PROGRESS.md
git commit -m "docs: update progress journal with gift card 3DS and idempotency work"
```
