# Plan 06: Account Management API

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 02 (User model, Booking model), Plan 05 (Auth — all routes require authentication)
> **Unlocks:** None (leaf node)

## Overview

Implement the authenticated account management endpoints: profile CRUD, order history, upcoming bookings, loyalty program, and payment method management via Stripe.

## Reference Documents

- `docs/DATA_MODELS.md` — Section 2 (Account routes)
- `docs/PAGE_SPECS.md` — Account pages (data requirements)

---

## Tasks

### Task 1: AccountController — Profile

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Http/Controllers/Api/AccountController.php`
  - `backend/app/Http/Requests/UpdateProfileRequest.php`
  - `backend/app/Http/Resources/UserProfileResource.php`
- **Details:**
  **`profile` — GET `/api/account/profile`:**
  Return full UserProfile (User + phone, date_of_birth, avatar_url).

  **`updateProfile` — PATCH `/api/account/profile`:**
  Accept partial updates. Validate:
  - `name`: string, max:255
  - `email`: email, unique:users (excluding current user)
  - `phone`: string, nullable
  - `date_of_birth`: date, nullable
  - `password`: min:8, confirmed (only if provided, requires `current_password`)

- **Acceptance Criteria:**
  - [ ] Profile returns all user fields including extended profile
  - [ ] Partial updates work (send only changed fields)
  - [ ] Email uniqueness enforced (excluding self)
  - [ ] Password change requires current password verification

---

### Task 2: AccountController — Orders & Bookings

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - Same controller as Task 1
- **Details:**
  **`orders` — GET `/api/account/orders`:**
  - Query: `page` (default 1), `limit` (default 10)
  - Returns paginated bookings for current user, ordered by most recent
  - Each booking includes: showtime details, seats, food items, total
  - Return: `{ data: Booking[], meta: { total, page, per_page } }`

  **`bookings` — GET `/api/account/bookings`:**
  - Query: `upcoming=true` (filter to future showtimes only)
  - Returns upcoming bookings with poster, title, date, time, seats
  - Return: `{ data: Booking[] }`

- **Acceptance Criteria:**
  - [ ] Orders paginated correctly
  - [ ] Only returns bookings for authenticated user
  - [ ] Upcoming filter shows only future showtimes
  - [ ] Each booking includes related data (showtime, movie, seats)

---

### Task 3: AccountController — Loyalty

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Services/LoyaltyService.php`
  - Same controller
- **Details:**
  **`loyalty` — GET `/api/account/loyalty`:**
  Return: `{ data: { points, tier, premierExpiry, history: [] } }`

  **LoyaltyService:**
  - `getPoints(user)` — Returns current points
  - `getTier(user)` — Returns tier info
  - `awardPoints(user, amount, description)` — Awards points (called from BookingController on purchase)
  - `getHistory(user)` — Returns point transaction history

  For MVP, history is derived from bookings (each booking earns points). Premier tier management is manual (set via database or admin).

- **Acceptance Criteria:**
  - [ ] Points and tier returned correctly
  - [ ] Points history derived from bookings
  - [ ] Premier expiry shown when applicable

---

### Task 4: PaymentMethodController

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `backend/app/Http/Controllers/Api/PaymentMethodController.php`
- **Details:**
  All routes require authentication. Uses StripeService from Plan 04.

  **`index` — GET `/api/account/payment-methods`:**
  - Get or create Stripe Customer for user
  - List payment methods from Stripe
  - Return: `{ data: [{ id, brand, last4, expMonth, expYear }] }`

  **`store` — POST `/api/account/payment-methods`:**
  - Create SetupIntent for Stripe Customer
  - Return: `{ data: { clientSecret: '...' } }`
  - Frontend completes card setup via Stripe.js, card attached to Customer

  **`destroy` — DELETE `/api/account/payment-methods/{id}`:**
  - Detach payment method from Stripe Customer
  - Return: `{ data: { success: true } }`

- **Acceptance Criteria:**
  - [ ] Lists saved cards from Stripe
  - [ ] SetupIntent creation works
  - [ ] Card deletion works
  - [ ] Stripe Customer created on first use and stored on User model

---

## Testing Requirements

- **Pest Feature Tests:**
  - Profile: get, update name, update email (unique check), password change
  - Orders: paginated list, correct user isolation
  - Bookings: upcoming filter works
  - Loyalty: points and tier correct
  - Payment methods: list, setup intent, delete
  - All routes return 401 without auth
- **Authorization Tests:**
  - User A cannot see User B's orders/bookings
  - Unauthenticated requests return 401

## Dependencies Map

```
Task 1 (Profile) ← independent
Task 2 (Orders & Bookings) ← independent
Task 3 (Loyalty) ← independent
Task 4 (Payment Methods) ← uses StripeService from Plan 04
```

## Risks & Open Questions

1. **Stripe Customer lifecycle** — When is the Stripe Customer created? Options: on registration, on first payment method save, or on first purchase. Recommendation: on first payment method save or first authenticated purchase. Store `stripe_customer_id` on User model.
2. **Points calculation for past bookings** — If the user registers after making guest purchases, the loyalty opt-in magic link (per PURCHASE_FLOW.md) should retroactively award points. This requires matching guest bookings by email and awarding points on account creation.
