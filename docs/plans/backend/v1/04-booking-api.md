# Plan 04: Booking & Payment API

> **Priority:** Must Have
> **Complexity:** XL
> **Depends On:** Plan 02 (Booking models), Plan 03 (Showtime/Seat availability)
> **Unlocks:** None (end of critical path)

## Overview

Implement the booking creation endpoint with Stripe payment integration, seat availability validation, and confirmation code generation. This is the most complex backend feature — it handles the atomic transaction of validating seats, processing payment, and creating the booking record. Also includes the booking retrieval endpoint and 3DS confirmation handler.

## Reference Documents

- `docs/DATA_MODELS.md` — Section 2 (Booking routes), Section 4 (Stripe integration)
- `docs/PURCHASE_FLOW.md` — Section 4 (Payment flow), error handling matrix

---

## Tasks

### Task 1: Stripe Service

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/StripeService.php`
- **Details:**
  Wraps the Stripe PHP SDK. Uses `config('services.stripe.secret')`.

  **Methods:**
  - `createPaymentIntent(amount, currency, paymentMethodId, metadata)` — Creates and confirms PaymentIntent
  - `confirmPaymentIntent(paymentIntentId)` — Confirms after 3DS
  - `createCustomer(email, name)` — Creates Stripe Customer
  - `getCustomer(customerId)` — Retrieves Customer
  - `createSetupIntent(customerId)` — For saving payment methods
  - `listPaymentMethods(customerId)` — Lists saved cards
  - `detachPaymentMethod(paymentMethodId)` — Removes saved card

  **Error handling:** Catches Stripe exceptions and translates to appropriate HTTP status codes (402 for payment failure, 400 for invalid data).

- **Acceptance Criteria:**
  - [ ] PaymentIntent creation and confirmation work with test keys
  - [ ] 3DS handling returns `requiresAction` when needed
  - [ ] Customer management CRUD works
  - [ ] Stripe errors translated to HTTP errors

---

### Task 2: Seat Availability Service

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Services/SeatAvailabilityService.php`
- **Details:**
  Atomic check-and-reserve using database transactions.

  **Methods:**
  - `checkAvailability(showtimeId, seatIds[])` — Returns array of unavailable seat IDs (empty = all available)
  - `reserveSeats(showtimeId, seatIds[], bookingId)` — Creates BookingSeat records within a transaction

  **Implementation:**
  ```php
  DB::transaction(function () {
    // Pre-check: are any of these seats already booked for this showtime?
    $takenSeats = BookingSeat::where('showtime_id', $showtimeId)
      ->whereIn('seat_id', $seatIds)
      ->pluck('seat_id');

    if ($takenSeats->isNotEmpty()) {
      throw new SeatConflictException($takenSeats->toArray());
    }

    // Attempt to insert — the unique constraint on (showtime_id, seat_id)
    // is the real concurrency guard. If a concurrent transaction already
    // inserted the same seat, this will raise a unique-violation exception.
    try {
      foreach ($seatIds as $seatId) {
        BookingSeat::create([
          'booking_id'  => $bookingId,
          'showtime_id' => $showtimeId,
          'seat_id'     => $seatId,
          'section'     => $seat->type,
          'price'       => $price,
        ]);
      }
    } catch (UniqueConstraintViolationException $e) {
      // Another transaction won the race — re-query to identify which seats
      $conflicting = BookingSeat::where('showtime_id', $showtimeId)
        ->whereIn('seat_id', $seatIds)
        ->where('booking_id', '!=', $bookingId)
        ->pluck('seat_id');
      throw new SeatConflictException($conflicting->toArray());
    }
  });
  ```

  Uses a **database unique constraint** on `booking_seats(showtime_id, seat_id)` as the authoritative concurrency guard. The pre-check avoids unnecessary payment processing for obviously-taken seats, but the unique constraint prevents double-booking even under concurrent inserts. This is strictly safer than `lockForUpdate()` on existing rows, which cannot lock rows that don't yet exist.

- **Acceptance Criteria:**
  - [ ] Concurrent booking attempts for the same seat result in only one success
  - [ ] SeatConflictException thrown with unavailable seat IDs
  - [ ] Transaction rolls back on failure
  - [ ] Performance: check + reserve completes in <200ms

---

### Task 3: Confirmation Code Generator

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/app/Services/ConfirmationCodeService.php` (or utility method)
- **Details:**
  Generates unique confirmation codes: "CVF-" + 6 uppercase alphanumeric characters. Checks uniqueness against database. Retries on collision (extremely unlikely with 36^6 = 2.17B combinations).

- **Acceptance Criteria:**
  - [ ] Format: "CVF-" followed by 6 alphanumeric characters
  - [ ] Uniqueness guaranteed
  - [ ] Collision retry logic works

---

### Task 4: BookingController — Create Booking

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `backend/app/Http/Controllers/Api/BookingController.php`
  - `backend/app/Http/Requests/CreateBookingRequest.php`
  - `backend/app/Http/Resources/BookingResource.php`
- **Details:**
  **`store` — POST `/api/bookings`:**

  Request body:
  ```json
  {
    "showtimeId": "uuid",
    "seatIds": ["uuid-1", "uuid-2"],
    "foodItems": [{ "itemId": "uuid", "quantity": 2 }],
    "paymentMethodId": "pm_...",
    "promoCode": "SAVE10",
    "giftCardCode": "GIFT-XXXX",
    "email": "guest@example.com",
    "loyaltyOptIn": true
  }
  ```

  **Flow (per PURCHASE_FLOW.md Section 4):**
  1. Validate request (CreateBookingRequest)
  2. Verify showtime exists and is in the future
  3. Check seat availability via SeatAvailabilityService
  4. Validate food items exist and are available
  5. Validate promo code (if provided)
  6. Validate gift card balance (if provided)
  7. Calculate total: (seat prices) + (food prices) - (promo discount) - (gift card amount)
  8. Create Stripe PaymentIntent via StripeService
  9. On payment success:
     a. Reserve seats (within transaction)
     b. Deduct gift card balance (if used)
     c. Create Booking record with confirmation code
     d. Create BookingSeat records
     e. Create BookingFoodItem records
     f. Award loyalty points (if authenticated)
  10. Return Booking

  **Error responses:**
  | Condition | Status | Response |
  | --- | --- | --- |
  | Seats unavailable | 409 | `{ errors: [{ field: 'seatIds', unavailableSeatIds: [...] }] }` |
  | Payment declined | 402 | `{ errors: [{ field: 'payment', message: '...' }] }` |
  | 3DS required | 200 | `{ data: { requiresAction: true, clientSecret: '...' } }` |
  | Invalid promo | 400 | `{ errors: [{ field: 'promoCode', message: '...' }] }` |
  | Insufficient gift card | 400 | `{ errors: [{ field: 'giftCardCode', remainingBalance: N }] }` |
  | Showtime expired | 410 | `{ errors: [{ message: 'Session expired' }] }` |
  | Server error | 500 | `{ errors: [{ message: 'Something went wrong...' }] }` |

- **Acceptance Criteria:**
  - [ ] Successful booking creates all records (booking, seats, food items)
  - [ ] Confirmation code generated and returned
  - [ ] 409 returned with specific unavailable seat IDs
  - [ ] 402 returned for payment failures
  - [ ] 3DS flow returns client secret
  - [ ] Promo code validation works
  - [ ] Gift card balance deducted correctly
  - [ ] Loyalty points awarded for authenticated users
  - [ ] Entire operation is atomic (rolls back on any failure)

---

### Task 5: BookingController — Show & Confirm

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - Same controller as Task 4
- **Details:**
  **`show` — GET `/api/bookings/{id}`:**
  - Returns booking with all related data (seats, food items, showtime, movie info)
  - **Authenticated users:** Must own the booking (`user_id` matches). Returns 403 otherwise.
  - **Guest lookup:** `GET /api/bookings/lookup?confirmation_code=CVF-XXXXXX&email=guest@example.com` — requires both the confirmation code and the guest email to match. This avoids treating a bare UUID as a bearer token (UUIDs leak via logs, browser history, referrers, support tooling).
  - Returns 404 if no matching booking found (do not distinguish "exists but unauthorized" from "not found").

  **`confirm` — POST `/api/bookings/confirm`:**
  - 3DS confirmation handler
  - Request: `{ paymentIntentId: string }`
  - Confirms the PaymentIntent via Stripe
  - Creates booking records if confirmation succeeds
  - Returns Booking

- **Acceptance Criteria:**
  - [ ] Authenticated user can retrieve their own booking by ID
  - [ ] 403 returned when authenticated user tries to access another user's booking
  - [ ] Guest booking retrievable via `GET /api/bookings/lookup?confirmation_code=...&email=...`
  - [ ] Guest lookup returns 404 when code or email don't match (no information leakage)
  - [ ] Includes all related data (seats, food, showtime details)
  - [ ] 3DS confirmation completes the booking
  - [ ] 404 for invalid booking ID

---

## Testing Requirements

- **Pest Feature Tests:**
  - Successful booking creation (all steps)
  - Seat conflict (409): create booking A, then try booking B with overlapping seats
  - Payment declined (402): use Stripe test card that declines
  - 3DS flow: use Stripe test card requiring 3DS
  - Guest checkout: booking with email, no user
  - Authenticated checkout: booking with user, loyalty points
  - Invalid promo code (400)
  - Gift card partial payment
  - Expired showtime (410)
  - Booking retrieval by authenticated owner (200)
  - Booking retrieval by non-owner authenticated user (403)
  - Guest booking lookup with correct confirmation_code + email (200)
  - Guest booking lookup with wrong email (404)
  - Guest booking lookup with wrong confirmation_code (404)
- **Unit Tests:**
  - SeatAvailabilityService: concurrent availability checks
  - SeatAvailabilityService: unique constraint violation caught and translated to SeatConflictException
  - ConfirmationCodeService: uniqueness
  - Total calculation: seats + food - promo - gift card
  - StripeService: PaymentIntent creation (with Stripe mock)
- **Concurrency Test:** Simulate 2 users booking the same seat simultaneously — only one should succeed

## Dependencies Map

```
Task 1 (StripeService) ← independent
Task 2 (SeatAvailabilityService) ← uses Booking, BookingSeat models
Task 3 (ConfirmationCodeService) ← uses Booking model
Task 4 (BookingController — store) �� uses Tasks 1, 2, 3
Task 5 (BookingController — show, confirm) ← uses Task 1
```

## Risks & Open Questions

1. **Stripe test keys** — Development requires Stripe test API keys. Set `STRIPE_SECRET_KEY` in `.env`. Use Stripe test card numbers for various scenarios.
2. **Race condition handling** — The unique constraint on `booking_seats(showtime_id, seat_id)` is the authoritative double-booking guard. The application pre-checks for taken seats to give a fast 409 on the common case, but the database constraint prevents races even under concurrent inserts. This approach avoids the gap-lock pitfall of `lockForUpdate()` on nonexistent rows.
3. **Promo code validation** — The spec mentions promo codes but doesn't define a promo code model or validation rules. For MVP, implement simple code validation (check a config array or a small promo_codes table). Full promo engine is future work.
4. **Loyalty points calculation** — How many points per dollar? Per DATA_MODELS.md: member earns 1 point per dollar. Implement as `floor(total / 100)` (total is in cents).
5. **Webhook resilience** — The current flow is synchronous (server confirms payment inline). If the server crashes between Stripe confirmation and booking creation, the payment is charged but no booking exists. For MVP, this edge case is rare and can be handled manually. Production should add a Stripe webhook listener as a safety net.
