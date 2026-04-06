# Gift Card Purchase: 3DS Support & Idempotency

Design document for adding 3D Secure payment support and request-level idempotency to the gift card purchase flow.

---

## 1. Problem Statement

The `GiftCardController::purchase()` endpoint has two correctness gaps:

**3DS payments fail silently.** `StripeService::createPaymentIntent()` can return `requires_action` status when the card issuer requires 3D Secure authentication. The controller treats any non-`succeeded` status as a hard 402 failure, so legitimate 3DS-required payments can never complete.

**Client retries create duplicate charges.** Each retry calls `createPaymentIntent()` which creates a new Stripe PaymentIntent. A network timeout followed by a retry produces two charges and two gift cards. The `stripe_payment_intent_id` unique constraint does not help because each PI has a different ID.

---

## 2. Scope

- 3DS support with `POST /api/gift-cards/confirm` endpoint
- Request idempotency contract on `POST /api/gift-cards/purchase`
- Stripe PI-level idempotency via `idempotency_key` option
- App-level dedupe/replay handling keyed by idempotency key
- Confirm endpoint also idempotent
- Payload consistency enforcement (same key + different payload = rejection)
- Replay returns the original successful response shape

**The invariant:** A repeated request with the same idempotency key must return the same logical outcome state — succeeded, requires_action, or failed — and must never create additional Stripe or application-side artifacts.

---

## 3. API Contract

### Modified: `POST /api/gift-cards/purchase`

New required header: `Idempotency-Key` (client-generated UUID v4). Returns 422 if missing or not a valid UUID.

**Response shapes:**

```
// Succeeded immediately
201 { "data": { GiftCardResource } }

// 3DS required — client must complete authentication
200 { "data": { "requiresAction": true, "clientSecret": "pi_..._secret_...", "paymentIntentId": "pi_..." } }

// Replay of succeeded purchase (same idempotency key, same payload)
201 { "data": { GiftCardResource } }   // Same gift card as original

// Replay of pending 3DS (same idempotency key, same payload)
200 { "data": { "requiresAction": true, "clientSecret": "pi_..._secret_...", "paymentIntentId": "pi_..." } }

// Replay of cached hard failure (same idempotency key, same payload)
// Returns the original error status and body in the same error envelope shape:
402 { "errors": [{ "field": "payment", "message": "Your card was declined." }] }

// Payload mismatch on reused key
409 { "errors": [{ "field": "idempotencyKey", "message": "This key was already used with different parameters." }] }
```

### New: `POST /api/gift-cards/confirm`

Accepts `{ "paymentIntentId": "pi_..." }`. Completes a 3DS-pending gift card purchase.

The cached pending purchase record is the **authoritative app context** for the gift card fields. `paymentIntentId` is the external handle used to resolve to that record; the app never derives gift card data from Stripe.

```
// Success
201 { "data": { GiftCardResource } }

// Replay (gift card already created for this PI)
201 { "data": { GiftCardResource } }

// Pending state expired
410 { "errors": [{ "message": "Session expired. Please start over." }] }
```

### Frontend change

`useGiftCards.purchase()` currently sends only body fields. The updated flow must:
1. Generate a UUID v4 before the first attempt
2. Send it as the `Idempotency-Key` header
3. Reuse the same UUID on retries

---

## 4. Payload Fingerprint

The following fields participate in the canonical payload fingerprint, used to detect key reuse with different parameters:

| Field | Normalization |
|-------|---------------|
| `amount` | Integer, exact match |
| `recipientEmail` | Lowercased, trimmed |
| `recipientName` | Trimmed |
| `senderName` | Trimmed |
| `message` | Trimmed; `null`, absent, and `""` all collapse to `""` |

The fingerprint is a SHA-256 hash of these fields JSON-encoded in the canonical order listed above. Whitespace-only or casing-only differences in email are treated as the same payload (due to normalization). Casing differences in names are treated as different payloads.

---

## 5. Persistence Model

### Storage tiers

| State | Storage | Key | TTL |
|-------|---------|-----|-----|
| Succeeded | `gift_cards` table | `idempotency_key` column (unique, nullable) | Permanent |
| Pending 3DS | Cache (two entries) | See below | 15 minutes |
| Hard failure (declined, invalid PM) | Cache (one entry) | Idempotency key | 15 minutes |
| Transient failure (Stripe unavailable, unexpected status) | **Not stored** | — | — |

### Cache entries

**Purchase replay path** — keyed by idempotency key:

```php
// For requires_action state
Cache::put("gift_card_idempotency:{$idempotencyKey}", [
    'status' => 'requires_action',
    'payment_intent_id' => 'pi_...',
    'client_secret' => 'pi_..._secret_...',
    'payload_hash' => 'sha256...',
], now()->addMinutes(15));

// For hard failure state
Cache::put("gift_card_idempotency:{$idempotencyKey}", [
    'status' => 'failed',
    'error_field' => 'payment',
    'error_message' => 'Your card was declined.',
    'error_status' => 402,
    'payload_hash' => 'sha256...',
], now()->addMinutes(15));
```

**Confirm path** — keyed by PaymentIntent ID (same pattern as `BookingController`):

```php
Cache::put("pending_gift_card:{$paymentIntentId}", [
    'idempotency_key' => '...',
    'amount' => 5000,
    'recipientEmail' => '...',
    'recipientName' => '...',
    'senderName' => '...',
    'message' => '...',
    'payload_hash' => 'sha256...',
], now()->addMinutes(15));
```

### Hard failure vs transient failure

- **Hard failures** (card declined via `CardException`, invalid payment method via `InvalidRequestException`): Cached. The outcome is deterministic for the same card/PM. Replay returns the cached error in the same envelope shape.
- **Transient failures** (Stripe unavailable via `ApiErrorException`, unexpected intermediate status like `processing`): **Not cached.** The outcome is non-deterministic. The next attempt goes through the full flow again. Stripe's own idempotency key prevents duplicate PI creation during this retry window.

For `InvalidRequestException` specifically: only payment-specific outcomes from `createPaymentIntent()` are cached. This avoids accidentally treating every Stripe invalid request as replayable business logic.

### DB migration

Add nullable `idempotency_key` column to `gift_cards` table with a unique index. Nullable because existing records predate this contract. PostgreSQL treats multiple `NULL` values as distinct for unique constraint purposes, which is the correct behavior here.

### 15-minute TTL note

The 15-minute TTL is the **app-side pending purchase window**, not a reflection of Stripe's PaymentIntent lifecycle. A 410 response means "your app-side pending purchase expired," not "the PaymentIntent definitely expired at Stripe." Uncaptured PIs expire on Stripe's own schedule.

---

## 6. State Machine

```
[No State]
    │
    ├─ purchase (immediate success) ──→ [Succeeded]
    │   DB: gift_card row created with idempotency_key
    │
    ├─ purchase (requires_action) ──→ [Pending 3DS]
    │   Cache: both keys written, 15-min TTL
    │   │
    │   ├─ confirm (success) ──→ [Succeeded]
    │   │   DB: gift_card row created with idempotency_key
    │   │   Cache: both keys cleared
    │   │
    │   ├─ confirm (Stripe/DB failure) ──→ [Pending 3DS]
    │   │   Cache preserved for retry
    │   │
    │   └─ cache expires (15 min) ──→ [No State]
    │       Uncaptured PI expires on Stripe's side
    │
    ├─ purchase (card declined / invalid PM) ──→ [Hard Failure]
    │   Cache: failure state under idempotency key
    │   │
    │   └─ cache expires (15 min) ──→ [No State]
    │
    └─ purchase (Stripe unavailable / unexpected status) ──→ [No State]
        Not cached — retry goes through full flow
```

### Replay behavior

| Scenario | Lookup | Response |
|----------|--------|----------|
| Purchase retry, gift card exists | `gift_cards.idempotency_key` | 201 + GiftCardResource |
| Purchase retry, 3DS pending | `gift_card_idempotency:{key}` cache | 200 + requiresAction |
| Purchase retry, hard failure cached | `gift_card_idempotency:{key}` cache | Cached error status + body |
| Purchase retry, same key, different payload | Cache or DB hit + hash mismatch | 409 |
| Confirm retry, gift card exists for this PI | `gift_cards.stripe_payment_intent_id` | 201 + GiftCardResource |
| Confirm, no pending state | Cache miss | 410 |

---

## 7. Implementation Flow

### Purchase endpoint

```
POST /api/gift-cards/purchase
│
├─ 1. Validate request fields + Idempotency-Key header
│     422 if missing, invalid UUID, or required fields fail
│
├─ 2. Compute payload fingerprint
│     SHA-256 of canonical normalized fields
│
├─ 3. Check gift_cards table by idempotency_key
│     Found + hash matches → 201 + GiftCardResource (replay)
│     Found + hash mismatch → 409
│
├─ 4. Check cache "gift_card_idempotency:{key}"
│     ├─ status=requires_action + hash matches → 200 + requiresAction (replay)
│     ├─ status=failed + hash matches → return cached error status + body (replay)
│     └─ hash mismatch on either → 409
│
├─ 5. No prior state found — proceed with Stripe
│     Call stripeService->createPaymentIntent(amount, pmId, metadata, idempotencyKey)
│     │
│     ├─ CardException:
│     │   Cache as hard failure under idempotency key
│     │   Return 402
│     │
│     ├─ InvalidRequestException:
│     │   Cache as hard failure under idempotency key
│     │   Return 400
│     │
│     ├─ ApiErrorException:
│     │   NOT cached (transient)
│     │   Return 502
│     │
│     ├─ status === 'requires_action':
│     │   Write both cache keys (idempotency + PI)
│     │   Return 200 { requiresAction, clientSecret, paymentIntentId }
│     │
│     ├─ status === 'succeeded':
│     │   Create GiftCard with idempotency_key + stripe_payment_intent_id
│     │   On unique constraint race: fetch existing by idempotency_key,
│     │     verify stripe_payment_intent_id and hash alignment,
│     │     return existing GiftCardResource
│     │   On other DB failure: compensating refund, re-throw
│     │   Return 201 + GiftCardResource
│     │
│     └─ any other status (e.g. 'processing'):
│         NOT cached (non-deterministic)
│         Return 502 "Payment is in an unexpected state."
│
└─ Done
```

### Confirm endpoint

```
POST /api/gift-cards/confirm
│
├─ 1. Validate { paymentIntentId }
│     422 if missing
│
├─ 2. Check gift_cards table by stripe_payment_intent_id
│     Found → 201 + GiftCardResource (replay — already minted)
│
├─ 3. Check cache "pending_gift_card:{paymentIntentId}"
│     Not found → 410 (session expired)
│
├─ 4. Confirm via stripeService->confirmPaymentIntent()
│     │
│     ├─ CardException → 402, cache preserved
│     ├─ InvalidRequestException → 400, cache preserved
│     ├─ ApiErrorException → 502, cache preserved
│     │
│     ├─ status !== 'succeeded':
│     │   Treated as unsuccessful confirmation.
│     │   Since purchase already handled requires_action, confirm should
│     │   normally resolve to succeeded or throw. Any other intermediate
│     │   status here is mapped to 402 "Payment confirmation failed."
│     │   Cache preserved for retry.
│     │
│     └─ status === 'succeeded':
│         Create GiftCard with idempotency_key (from cached data)
│           + stripe_payment_intent_id
│         On unique constraint race: fetch existing by stripe_payment_intent_id,
│           return existing GiftCardResource
│         On other DB failure: compensating refund, re-throw
│         Clear both cache keys (only on success)
│         Return 201 + GiftCardResource
│
└─ Done
```

---

## 8. Compensating Refunds

This is a **deliberate design decision for gift cards**, not an inherited convention. The booking controller uses a similar pattern, but the rationale stands independently:

If Stripe has captured payment and the subsequent `GiftCard::create()` fails, the customer has been charged with no gift card issued. A compensating refund via `stripeService->refundPaymentIntent()` is the correct recovery action.

**Applies to:**
- Purchase path: immediate success where DB write fails after Stripe capture
- Confirm path: DB write fails after Stripe confirm succeeds

**If the refund itself fails:** `report()` the exception for manual resolution. The original exception is re-thrown so the client receives a 500. The customer is not silently charged — the reported exception surfaces for operational follow-up.

---

## 9. StripeService Change

Add optional `idempotencyKey` parameter to `createPaymentIntent()`:

```php
public function createPaymentIntent(
    int $amount,
    string $paymentMethodId,
    array $metadata = [],
    ?string $idempotencyKey = null,
): PaymentIntent {
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

Backward-compatible. Existing callers (`BookingController`) are unaffected. The `FakeStripeService` test helper will also need to accept and track the new parameter.

---

## 10. Error Handling Matrix

### Purchase endpoint

| Scenario | Status | Error field | Cached? |
|----------|--------|-------------|---------|
| Missing/invalid `Idempotency-Key` | 422 | `idempotencyKey` | No |
| Missing required body fields | 422 | Per field | No |
| Replay: succeeded purchase | 201 | — | Already in DB |
| Replay: pending 3DS | 200 | — | Already in cache |
| Replay: hard failure | Original status | Original body (same envelope) | Already in cache |
| Payload hash mismatch | 409 | `idempotencyKey` | No change |
| Card declined (`CardException`) | 402 | `payment` | Hard failure |
| Invalid PM (`InvalidRequestException`) | 400 | `payment` | Hard failure |
| Stripe unavailable (`ApiErrorException`) | 502 | `payment` | Not cached |
| Unexpected status (`processing`, etc.) | 502 | `payment` | Not cached |
| 3DS required (`requires_action`) | 200 | — | Two cache keys |
| Immediate success (`succeeded`) | 201 | — | DB record |
| DB failure after Stripe capture | 500 | — | Compensating refund |
| Race on unique `idempotency_key` | 201 | — | Fetch existing record |

### Confirm endpoint

| Scenario | Status | Error field | Cache effect |
|----------|--------|-------------|--------------|
| Missing `paymentIntentId` | 422 | `paymentIntentId` | None |
| Replay: gift card exists for PI | 201 | — | None |
| Pending state expired | 410 | — | None |
| Card declined on confirm | 402 | `payment` | Preserved |
| Invalid request on confirm | 400 | `payment` | Preserved |
| Stripe unavailable on confirm | 502 | `payment` | Preserved |
| Non-`succeeded` status on confirm | 402 | `payment` | Preserved |
| Success | 201 | — | Both keys cleared |
| DB failure after confirm capture | 500 | — | Compensating refund |
| Race on unique `stripe_payment_intent_id` | 201 | — | Both keys cleared |

---

## 11. Testing Strategy

All tests use `FakeStripeService`. Grouped by concern.

### Purchase — idempotency

- Same key + same payload, first succeeds → replay returns 201 + same gift card (same `id`)
- Same key + same payload, first triggers 3DS → replay returns 200 + same requiresAction response
- Same key + same payload, first declines → replay returns cached 402 with same error body
- Same key + different payload → 409 regardless of prior outcome
- Missing `Idempotency-Key` header → 422
- Invalid UUID format → 422
- Race condition: two requests with same key → exactly one gift card, exactly one Stripe PI logical result, both callers converge on same response

### Purchase — payload fingerprint normalization

- Same key, `recipientEmail` with different casing → treated as same payload (replay)
- Same key, `recipientEmail` with leading/trailing whitespace → treated as same payload
- Same key, `message: null` vs `message: ""` → treated as same payload
- Same key, `recipientName` with different casing → treated as different payload (409)

### Purchase — 3DS flow

- `requires_action` → returns 200 with `clientSecret` and `paymentIntentId`
- No gift card row created during pending state
- Both cache keys written and verifiable

### Purchase — failure caching distinction

- Card declined → cached as hard failure, replay returns same 402
- Stripe unavailable → not cached, retry goes through full flow
- Unexpected `processing` status → not cached, returns 502

### Confirm — success

- Valid PI with pending state → creates gift card, returns 201
- Gift card has correct `idempotency_key` (from cached data) and `stripe_payment_intent_id`
- Both cache keys cleared after success

### Confirm — idempotency

- Confirm retry after success → returns 201 + existing gift card (no duplicate row)
- Confirm with no pending state → 410

### Confirm — failure handling and retry

- Stripe decline on confirm → 402, cache preserved
- Stripe unavailable on confirm → 502, cache preserved
- Failed confirm followed by successful retry → creates gift card (cache was preserved)

### Confirm — compensating refund

- DB failure after Stripe capture → refund issued via `stripeService->refundPaymentIntent()`
- Refund failure → reported (not surfaced as different client error)

### StripeService

- `createPaymentIntent()` accepts optional `idempotencyKey`, passes to Stripe client options
- `createPaymentIntent()` without `idempotencyKey` works unchanged (backward-compatible)
- `FakeStripeService` tracks idempotency keys in `createdPaymentIntents` array
