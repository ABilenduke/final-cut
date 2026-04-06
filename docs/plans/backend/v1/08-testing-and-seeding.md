# Plan 08: Comprehensive Testing & Data Seeding

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plans 03–07 (all API plans — needs implemented endpoints to test)
> **Unlocks:** None (final plan)

## Overview

Build a comprehensive Pest test suite organized by feature domain, create reusable test helpers, write an integration test for the full purchase flow, and enhance the database seeder for realistic development data. This plan ensures the backend is reliable and provides confidence for frontend integration.

## Reference Documents

- `docs/PURCHASE_FLOW.md` — Full purchase flow to test end-to-end
- `docs/DATA_MODELS.md` — All API routes and expected responses

---

## Tasks

### Task 1: Test Suite Organization & Helpers

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/tests/Pest.php` — Update with shared setup
  - `backend/tests/Feature/Api/` — Feature test directory
  - `backend/tests/Unit/Services/` — Unit test directory
  - `backend/tests/Helpers/AuthHelper.php` — Authentication test helper
  - `backend/tests/Helpers/StripeHelper.php` — Stripe mock helper
- **Details:**
  **Test organization:**
  ```
  tests/
  ├── Feature/Api/
  │   ├── MovieTest.php
  │   ├── ShowtimeTest.php
  │   ├── BookingTest.php
  │   ├── AuthTest.php
  │   ├── AccountTest.php
  │   ├── CalendarEventTest.php
  │   ├── FoodMenuTest.php
  │   ├── GiftCardTest.php
  │   ├── ContactTest.php
  │   └── RentalTest.php
  ├── Unit/Services/
  │   ├── TmdbServiceTest.php
  │   ├── StripeServiceTest.php
  │   ├── SeatAvailabilityServiceTest.php
  │   ├── LoyaltyServiceTest.php
  │   └── ConfirmationCodeServiceTest.php
  └── Helpers/
      ├── AuthHelper.php
      └── StripeHelper.php
  ```

  **AuthHelper:** Trait with methods:
  - `actingAsUser()` — Create and authenticate as a test user
  - `actingAsPremierUser()` — Premier tier user
  - `actingAsGuest()` — No authentication

  **StripeHelper:** Trait with methods:
  - `mockStripeSuccess()` — Mock successful PaymentIntent
  - `mockStripeDeclined()` — Mock declined payment
  - `mockStripe3DS()` — Mock 3DS-required response

- **Acceptance Criteria:**
  - [ ] Test directory structure mirrors controller structure
  - [ ] Auth helper creates authenticated test users quickly
  - [ ] Stripe helper mocks all payment scenarios
  - [ ] `RefreshDatabase` trait used for isolation
  - [ ] `composer test` runs full suite

---

### Task 2: Feature Test Coverage

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - All test files in `tests/Feature/Api/`
- **Details:**
  Ensure every API endpoint has feature tests covering:

  **Per endpoint:**
  - Happy path (200/201 with correct response shape)
  - Validation errors (422 with field-level errors)
  - Auth protection (401 for protected routes)
  - Not found (404 for invalid IDs/slugs)
  - Edge cases specific to the endpoint

  **Minimum test counts by domain:**
  - Movies: 5 tests (list, filter, detail, showtimes, 404)
  - Showtimes: 3 tests (show with seats, seat availability, 404)
  - Bookings: 10 tests (success, seat conflict, payment declined, 3DS, guest, auth, promo, gift card, expired, retrieval)
  - Auth: 8 tests (register, duplicate email, login, wrong password, logout, me, forgot password, full flow)
  - Account: 8 tests (profile get/update, orders pagination, bookings upcoming, loyalty, payment methods CRUD, auth check)
  - Calendar: 5 tests (list, month filter, type filter, accessibility filter, detail)
  - Food: 3 tests (list, category filter, excludes unavailable)
  - Gift Cards: 4 tests (purchase, balance valid, balance invalid, depleted)
  - Contact: 2 tests (valid, validation errors)
  - Rentals: 3 tests (valid, invalid type, past date)

  **Total: ~51 feature tests minimum**

- **Acceptance Criteria:**
  - [ ] Every API endpoint has at least one happy-path test
  - [ ] All error responses tested (401, 404, 409, 422)
  - [ ] Response shapes validated (correct JSON structure)
  - [ ] All tests pass with `composer test`

---

### Task 3: Full Purchase Flow Integration Test

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/tests/Feature/Api/PurchaseFlowIntegrationTest.php`
- **Details:**
  End-to-end test simulating the complete purchase journey:

  ```
  1. Register user → verify user created
  2. GET /api/movies → verify movie list
  3. GET /api/movies/{slug}/showtimes → verify showtimes
  4. GET /api/showtimes/{id} → verify seat map with availability
  5. POST /api/bookings → create booking with seats + food
  6. Verify:
     a. Booking record created with confirmation code
     b. Seats marked as taken
     c. Food items recorded
     d. Stripe payment processed (mocked)
     e. Loyalty points awarded
  7. GET /api/bookings/{id} → verify retrieval
  8. GET /api/account/orders → verify booking appears
  9. GET /api/showtimes/{id} → verify booked seats now show as taken
  ```

  Also test the concurrency scenario:
  ```
  1. Two users select the same seats
  2. User A books first → success
  3. User B attempts same seats → 409 Conflict
  ```

- **Acceptance Criteria:**
  - [ ] Full flow passes end-to-end
  - [ ] Seat availability updates after booking
  - [ ] Concurrency test verifies only one booking succeeds
  - [ ] Loyalty points correctly awarded

---

### Task 4: Unit Test Coverage for Services

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - All test files in `tests/Unit/Services/`
- **Details:**
  Unit tests for service layer methods:

  **TmdbService:**
  - `tmdbToMovie` transform with various TMDB response shapes
  - Missing trailer handling (null trailer_key)
  - Missing cast (empty credits)
  - HTTP failure handling

  **SeatAvailabilityService:**
  - All seats available → returns empty array
  - Some seats taken → returns taken seat IDs
  - Concurrent check simulation

  **ConfirmationCodeService:**
  - Code format validation (CVF- + 6 alphanumeric)
  - Uniqueness on collision

  **LoyaltyService:**
  - Point calculation (1 point per dollar)
  - Tier lookup

- **Acceptance Criteria:**
  - [ ] Service methods tested in isolation
  - [ ] Edge cases covered (null values, empty arrays)
  - [ ] HTTP calls mocked in TMDB tests

---

### Task 5: Enhanced Database Seeder

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/database/seeders/` — Enhance existing seeders from Plan 02
- **Details:**
  Enhance seeders for a more realistic development experience:

  - **Time-relative showtimes:** Generate showtimes relative to today, not hardcoded dates. Mix of: already started (past), starting soon (within 2 hours), upcoming today, this week, next week.
  - **Varied seat occupancy:** Some showtimes 10% booked, some 50%, one nearly sold out (90%). This tests the seat selection UI with various densities.
  - **Booking variety:** 5 completed bookings (past showtimes), 2 upcoming bookings, 1 cancelled. Mix of guest and authenticated. Some with food, some without.
  - **Gift cards:** 3 active (various balances), 1 depleted, 1 expired.
  - **Calendar events:** Mix of types, some with accessibility tags, some loyalty-only.
  - **Test accounts:**
    - `test@finalcut.test` / `password` — Premier member, 500 points, has bookings and saved cards
    - `member@finalcut.test` / `password` — Regular member, 50 points
    - `guest@finalcut.test` — No account, has guest bookings by email

- **Acceptance Criteria:**
  - [ ] `php artisan migrate:fresh --seed` creates realistic development data
  - [ ] Showtimes are relative to current date
  - [ ] Varied seat occupancy across showtimes
  - [ ] Test accounts usable for manual testing
  - [ ] Seed completes in under 30 seconds

---

## Testing Requirements

Meta-testing: ensure the test suite itself is healthy:
- `composer test` runs all tests and passes
- Test execution time under 60 seconds
- No flaky tests (no timing dependencies, no external API calls)
- Test coverage report shows >80% on controllers and services

## Dependencies Map

```
Task 1 (Organization & Helpers) ← foundational
Task 2 (Feature Tests) ← uses Task 1 helpers
Task 3 (Integration Test) ← uses Task 1 helpers + all endpoints
Task 4 (Unit Tests) ← independent
Task 5 (Enhanced Seeder) ← independent of tests
```

## Risks & Open Questions

1. **Test database** — Need a separate test database (`final_cut_test`). Verify Docker PostgreSQL container creates both databases.
2. **Stripe mocking** — Use `Http::fake()` or Stripe's built-in test mode. Recommendation: use `Http::fake()` for speed and no external dependency during CI.
3. **Test parallelism** — Pest supports parallel test execution. Ensure database isolation with `RefreshDatabase` works in parallel mode (each process needs its own database or transaction isolation).
