# Plan 09: Auth & Account Domain

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts — blank, account), Plan 05 (useAuth, useAccount composables)
> **Unlocks:** None (leaf node)

## Overview

Build the authentication pages and account management dashboard: 5 domain components, 3 auth pages, and 6 account pages. This enables user registration, login, profile management, order history, loyalty tracking, and payment method management.

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — Tier 2: Domain Components — Account
- `docs/PAGE_SPECS.md` — Auth pages, Account pages
- `docs/STATE_MANAGEMENT.md` — useAuth, useAccount composables
- `docs/DATA_MODELS.md` — User, UserProfile, Booking interfaces

---

## Tasks

### Task 1: Auth Pages (`/auth/login`, `/auth/register`, `/auth/forgot-password`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/auth/login.vue`
  - `frontend/app/pages/auth/register.vue`
  - `frontend/app/pages/auth/forgot-password.vue`
  - `frontend/app/pages/auth/reset-password.vue`
- **Details:**
  All use `blank` layout, Close-Up composition, client-only rendering (`ssr: false`). All apply `guest` middleware (redirects authenticated users to `/account`).

  **Login:** Theater logo, email + password (CvInput), "Forgot password?" link, "Create account" link. On submit: `useAuth().login()`. On success: redirect to `redirect` query param or `/account`. SEO: `noindex`.

  **Register:** Logo, name + email + password + confirm password (CvInput), terms checkbox, "Already have an account?" link. On submit: `useAuth().register()`. SEO: `noindex`.

  **Forgot Password:** Logo, email input, "Send Reset Link" button, back to login link. On submit: `POST /api/auth/forgot-password`. SEO: `noindex`.

  **Reset Password:** Logo, new password + confirm password (CvInput), submit button. Reads `token` and `email` from URL query params (`/auth/reset-password?token=...&email=...`). On submit: `POST /api/auth/reset-password` with `{ token, email, password, password_confirmation }`. On success: redirect to login with success toast. SEO: `noindex`.

- **Acceptance Criteria:**
  - [ ] All 4 pages render with blank layout (no chrome)
  - [ ] `guest` middleware redirects authenticated users
  - [ ] Login redirects to `redirect` query param on success
  - [ ] Register validates password confirmation match
  - [ ] Form validation errors display via CvInput error prop
  - [ ] `noindex` meta tag set on all auth pages
  - [ ] Reset password page extracts token and email from query params
  - [ ] Form validates password confirmation match
  - [ ] Successful reset redirects to login

---

### Task 2: ProfileForm

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/account/ProfileForm.vue`
  - `frontend/app/components/account/ProfileForm.stories.ts`
- **Details:**
  **Props:** `profile: User`
  **Events:** `save(Partial<User>)`

  Avatar upload area, name (CvInput), email (CvInput), password change section (current, new, confirm). Save button (CvButton).

- **Acceptance Criteria:**
  - [ ] Pre-fills with current profile data
  - [ ] Password change validates current password and confirmation
  - [ ] Save emits only changed fields
  - [ ] Avatar upload area (placeholder; file upload can be deferred)

---

### Task 3: OrderHistoryList + UpcomingBookings

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/account/OrderHistoryList.vue`
  - `frontend/app/components/account/UpcomingBookings.vue`
  - Stories for each
- **Details:**
  **OrderHistoryList:** Paginated order list. Each order expandable via CvAccordion showing: movie, date, time, seats, food items, total, booking reference.

  **Props:** `orders: Booking[]`

  **UpcomingBookings:** Future booking cards. Each: movie poster thumbnail, title, date, time, seats. Links to confirmation page.

  **Props:** `bookings: Booking[]`

- **Acceptance Criteria:**
  - [ ] Orders expand to show full details
  - [ ] Pagination controls work
  - [ ] Upcoming bookings display with poster, title, date
  - [ ] Booking cards link to confirmation page

---

### Task 4: LoyaltyPointsCard + SavedPaymentMethods

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/account/LoyaltyPointsCard.vue`
  - `frontend/app/components/account/SavedPaymentMethods.vue`
  - Stories for each
- **Details:**
  **LoyaltyPointsCard:** Points balance, tier badge (CvBadge), premier expiry if applicable.

  **Props:** `points: number`, `tier: 'member' | 'premier'`, `premierExpiry: string | null`

  **SavedPaymentMethods:** Card list (last4, brand, expiry), add new card, delete card.

  **Props:** `methods: Array<{ id, brand, last4, expMonth, expYear }>`
  **Events:** `add`, `remove(id)`

- **Acceptance Criteria:**
  - [ ] Loyalty card shows points and tier
  - [ ] Premier members see expiry date
  - [ ] Payment methods list with brand icons
  - [ ] Add and delete actions work

---

### Task 5: Account Pages

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/account/index.vue` — Dashboard
  - `frontend/app/pages/account/profile.vue`
  - `frontend/app/pages/account/orders.vue`
  - `frontend/app/pages/account/loyalty.vue`
  - `frontend/app/pages/account/bookings.vue`
  - `frontend/app/pages/account/payment-methods.vue`
- **Details:**
  All use `account` layout, client-only rendering, `auth` middleware. All `noindex`.

  **Dashboard (`/account`):** Establishing Shot 65/35. Left: upcoming bookings (next 3), recent orders (last 5). Right: profile summary, loyalty card, quick action links.

  **Profile:** Close-Up. ProfileForm component.

  **Orders:** Close-Up. OrderHistoryList with pagination (`?limit=N`).

  **Loyalty:** Close-Up. Points balance, tier status (Member/Premier), upgrade CTA for members, renewal date for premier, points history, available rewards.

  **Bookings:** Close-Up. UpcomingBookings component. Fetches upcoming bookings without query params (no `upcoming=true`).

  **Payment Methods:** Close-Up. SavedPaymentMethods component.

  **Data sources per page:** Each fetches from `useAccount()` composable methods.

- **Acceptance Criteria:**
  - [ ] All pages protected by `auth` middleware
  - [ ] Dashboard shows summary of all account data
  - [ ] Profile edits save successfully
  - [ ] Order history paginates via URL params
  - [ ] Loyalty page shows correct tier and points
  - [ ] Bookings show only future bookings
  - [ ] Payment methods support add/remove via Stripe

---

## Testing Requirements

- **Storybook:** Stories for ProfileForm, OrderHistoryList, UpcomingBookings, LoyaltyPointsCard, SavedPaymentMethods
  - Various data states (empty, single item, many items)
  - Member vs Premier tier
  - With and without saved payment methods
- **E2E Tests:**
  - Login → dashboard → verify data loads
  - Edit profile → save → verify persistence
  - View order history → expand order → verify details
  - Auth middleware: unauthenticated access redirects to login
  - Guest middleware: authenticated access to login redirects to account
- **Accessibility:**
  - Form validation error announcements
  - Accordion keyboard navigation in order history
  - Focus management on page transitions

## Dependencies Map

```
Task 1 (Auth Pages) ← uses CvInput, CvButton, blank layout, useAuth
Task 2 (ProfileForm) ← uses CvInput, CvButton
Task 3 (OrderHistoryList + UpcomingBookings) ← uses CvAccordion, CvCard, CvBadge
Task 4 (LoyaltyPointsCard + SavedPaymentMethods) ← uses CvCard, CvBadge, CvButton
Task 5 (Account Pages) ← uses Tasks 2-4 + account layout + useAccount
```

## Risks & Open Questions

1. **Avatar upload** — File upload requires either a storage solution (S3, local) or a service like Gravatar. For MVP, show a placeholder avatar and defer file upload to a later phase.
2. **Stripe saved cards** — SavedPaymentMethods requires Stripe Customer objects. Users need to be linked to a Stripe Customer ID. This integration depends on Backend Plan 06.
3. **Loyalty points history** — The loyalty page shows a points history. This requires backend tracking of point transactions (earned on purchase, redeemed). May be sparse data for MVP — stub with sample history.
4. **nuxt-auth-utils deferred** — For v1, auth state is restored via `GET /api/auth/me` on app init using Sanctum session cookies. nuxt-auth-utils SSR hydration is not needed until SSR is enabled.
