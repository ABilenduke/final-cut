# Plan 08: Purchase Flow Domain

> **Priority:** Must Have
> **Complexity:** XL
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts — purchase layout), Plan 05 (useCart, useSeatSelection, booking routes), Plan 06 (ShowtimeSelector links here)
> **Unlocks:** None (end of critical path)

## Overview

Build the most complex feature in the application: the 3-step ticket purchase flow. This is the revenue-generating core — seat selection with full keyboard accessibility, food pre-ordering, Stripe payment, and booking confirmation with QR code. The plan covers 10 domain components and 3 pages.

## Reference Documents

- `docs/PURCHASE_FLOW.md` — Complete purchase flow specification (primary reference)
- `docs/COMPONENT_INVENTORY.md` — Tier 2: Domain Components — Booking/Purchase
- `docs/PAGE_SPECS.md` — Purchase flow pages
- `docs/STATE_MANAGEMENT.md` — useCart, useSeatSelection composables
- `docs/DATA_MODELS.md` — Booking, Seat, Auditorium interfaces; Stripe integration (Section 4)

---

## Tasks

### Task 1: AuditoriumScreenBar

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/booking/AuditoriumScreenBar.vue`
- **Details:**
  Visual indicator of screen position. Height: 0.25rem, width: 60% of grid, centered. Color: `--primary-container`. `aria-hidden="true"` (decorative).

- **Acceptance Criteria:**
  - [ ] Curved bar renders at correct dimensions
  - [ ] `aria-hidden="true"` set
  - [ ] Centered above seat grid

---

### Task 2: AuditoriumLegend

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/booking/AuditoriumLegend.vue`
- **Details:**
  Row of labeled color swatches: Available (#2a2a2a), Selected (#550000), Taken (#1c1b1b at 0.4), Accessible (#2a2a2a + wheelchair icon), Premium (#2a2a2a + #675900 edge).

- **Acceptance Criteria:**
  - [ ] All 5 seat states represented with correct colors
  - [ ] Labels clear and readable

---

### Task 3: AuditoriumSeat

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/booking/AuditoriumSeat.vue`
- **Details:**
  Individual seat cell with 5 visual states.

  **Props:** `seat: Seat`, `selected: boolean`, `focused: boolean`
  **Events:** `toggle`

  **Visual states (determined by server `status` + client `selected`):**
  - Available: `--surface-container-high` (#2a2a2a)
  - Selected: `--primary-container` (#550000) + check icon in `--primary` (#FFB4A8)
  - Taken: `--surface-container-low` (#1c1b1b) at 0.4 opacity, non-interactive
  - Accessible: available styling + wheelchair icon in `--secondary` (#DAC769)
  - Premium: available styling + `--secondary-container` (#675900) bottom edge

  **Selection animation:** background-color change + `scale(1→1.05→1)`, `duration-standard`, `ease-emphasis`
  **Focus:** 0.125rem inset `--secondary` outline

  **Sizing:** Desktop: 2.5rem. Mobile (below screen-md): 3rem (meets touch target).

- **Acceptance Criteria:**
  - [ ] All 5 visual states render correctly
  - [ ] Taken/held seats are non-interactive
  - [ ] Selection animation plays (respects reduced motion)
  - [ ] Focus indicator visible on keyboard navigation
  - [ ] Mobile cells are 3rem minimum

---

### Task 4: AuditoriumGrid

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/components/booking/AuditoriumGrid.vue`
- **Details:**
  The most complex component in the system. Interactive seat selection map with full WAI-ARIA grid pattern.

  **Props:** `auditorium: Auditorium`, `seats: Seat[]`, `selectedSeatIds: string[]`
  **Events:** `seat-toggled: { seatId: string; selected: boolean }`

  **Structure:** Two-column: pinned row labels (left) + scrollable seat matrix (right). AuditoriumScreenBar above. Mobile: horizontal scroll with `scroll-snap-type: x proximity`, row labels pinned.

  **Keyboard navigation (WAI-ARIA grid pattern):**
  | Key | Action |
  | --- | ------ |
  | Arrow Right | Next seat in row |
  | Arrow Left | Previous seat in row |
  | Arrow Down | Same position next row |
  | Arrow Up | Same position previous row |
  | Home | First available seat in row |
  | End | Last available seat in row |
  | Enter/Space | Toggle selection |
  | Escape | Deselect all, return focus to container |
  | Tab | Exit grid |

  **ARIA:** `role="grid"`, `aria-label="Theater seating chart, [Screen Name]"`. Each row: `role="row"`, `aria-label="Row [letter]"`. Each seat: `role="gridcell"`, `aria-label="Seat [ID], [status]. [Section]. [Price]."`, `aria-selected`, `aria-disabled` for taken.

  **Screen reader announcements:** On selection: "Seat [ID] selected. [N] seats selected, [total] total."

  **Roving tabindex:** Only focused seat has `tabindex="0"`, all others `tabindex="-1"`.

- **Acceptance Criteria:**
  - [ ] Seat grid renders correctly for all auditorium layouts
  - [ ] Row labels pinned on mobile during horizontal scroll
  - [ ] All keyboard navigation keys work correctly
  - [ ] Roving tabindex implementation
  - [ ] Screen reader announces selection changes
  - [ ] ARIA grid pattern fully implemented
  - [ ] Click/tap toggles seat selection
  - [ ] Maximum seat limit enforced (10 per transaction)

---

### Task 5: CartSummary

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/booking/CartSummary.vue`
- **Details:**
  Running order total. Desktop: sidebar panel. Mobile: collapsible bottom sheet.

  **Props:** `items: Array<{ label: string; price: number }>`, `total: number`

  Lists selected seats (with section and price), food add-ons, promo discount, gift card deduction, and total.

  **Accessibility:** `aria-live="polite"` on total — announces updates on seat add/remove.

  **Mobile bottom sheet:** Collapsed shows total + "View Details". Expanded shows full line items. Drag handle for expand/collapse.

- **Acceptance Criteria:**
  - [ ] Desktop: sidebar panel with line items and total
  - [ ] Mobile: bottom sheet with collapse/expand
  - [ ] Total updates reactively
  - [ ] `aria-live="polite"` announces total changes
  - [ ] Prices formatted via `formatCurrency`

---

### Task 6: FoodPreOrderPanel

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/booking/FoodPreOrderPanel.vue`
- **Details:**
  Inline food selection during checkout. Per PURCHASE_FLOW.md Section 4.

  **Props:** `menuItems: MenuItem[]`, `selectedItems: Array<{ itemId: string; quantity: number }>`
  **Events:** `update`

  **Two states:**
  - Collapsed: teaser banner ("Add snacks to your order?") with 2-3 popular combo thumbnails. `surface-container-high` background.
  - Expanded: "Most Popular" section + categorized menu grid via MenuCategoryTabs. Each item: image, name, price, "Add" button with quantity selector.

  Items update cart total in real-time. Dismissable (collapses back).

- **Acceptance Criteria:**
  - [ ] Collapsed teaser with popular items
  - [ ] Expands to full menu with category tabs
  - [ ] Quantity selector (increment/decrement)
  - [ ] Cart updates in real-time
  - [ ] Can dismiss/collapse panel

---

### Task 7: PromoCode + Gift Card Input

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/booking/PromoCode.vue`
- **Details:**
  **PromoCode:** Input + "Apply" button. Client-side preview: mirrors promo config from `app/data/promoCodes.ts` to show estimated discount on 'Apply'. The preview is strictly non-authoritative — the server is the sole authority at booking time. Show an 'estimated' label on the preview discount. If the server returns a different discount at booking time, update the display. Applied state shows code + discount + "Remove". Error state shows message below input.

  **Props:** `appliedCode: string | null`
  **Events:** `apply(code)`, `remove`

  Gift card input follows same pattern (can reuse component or build separate). Code input + "Apply" → shows balance and amount to deduct.

- **Acceptance Criteria:**
  - [ ] Apply sends code for server validation
  - [ ] Applied state shows code, discount amount, and Remove option
  - [ ] Error state displays validation message
  - [ ] Gift card shows remaining balance

---

### Task 8: CheckoutForm

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/components/booking/CheckoutForm.vue`
- **Details:**
  Payment form with Stripe Elements.

  **Props:** `total: number`, `isAuthenticated: boolean`
  **Events:** `submit({ paymentMethodId, email? })`, `error(message)`

  **Structure:**
  - Stripe Elements card input (card number, expiry, CVC)
  - Guest email field (CvInput, only when not authenticated)
  - Billing name (CvInput)
  - Order total prominently displayed
  - "Complete Purchase" CTA (CvButton primary, lg)

  **Stripe integration:**
  1. Initialize Stripe with publishable key from runtime config
  2. Mount Stripe Elements card element
  3. On submit: create PaymentMethod via Stripe.js
  4. Emit `submit` with `paymentMethodId`

  **Guest vs Authenticated:** Per PURCHASE_FLOW.md Section 4 table. Authenticated: pre-fill email, show saved cards. Guest: manual email + "Join Rewards" checkbox (loyalty opt-in triggers magic-link post-purchase).

- **Acceptance Criteria:**
  - [ ] Stripe Elements card input renders and accepts input
  - [ ] PaymentMethod created on form submission
  - [ ] Guest email field shows for unauthenticated users
  - [ ] Authenticated users see pre-filled email and saved cards
  - [ ] Loyalty opt-in checkbox for guests
  - [ ] Form validation before submission
  - [ ] Error display for payment failures

---

### Task 9: BookingConfirmation

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/booking/BookingConfirmation.vue`
- **Details:**
  Post-purchase display. Per PURCHASE_FLOW.md Section 5.

  **Props:** `booking: Booking`

  **Structure:**
  1. Success banner: "Booking Confirmed" with check icon
  2. Confirmation code: large, prominent (e.g., "CVF-A3X9K2")
  3. Movie details: title, poster thumbnail, date, time, screen
  4. Seats: list with row/section labels
  5. Food pre-orders (if any)
  6. Payment summary: subtotal, discounts, total
  7. QR code: generated client-side from confirmation code
  8. Actions: "Add to Calendar" (.ics), "Print Tickets" (`window.print()`), "View in Order History" (auth only), "Back to Home"

  **QR code:** Use lightweight client-side library. Encodes confirmation code (not full URL).

  **.ics file:** Generate and trigger download per PURCHASE_FLOW.md Section 5.

- **Acceptance Criteria:**
  - [ ] All booking details display correctly
  - [ ] QR code generates from confirmation code
  - [ ] "Add to Calendar" downloads .ics file
  - [ ] "Print Tickets" triggers print dialog
  - [ ] Print layout optimized (via print.css)
  - [ ] "View in Order History" only shows for authenticated users

---

### Task 10: Seat Selection Page (`/purchase/:showtimeId`)

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/pages/purchase/[showtimeId].vue`
- **Details:**
  Per PURCHASE_FLOW.md Section 3. Layout: `purchase`. Rendering: client-only (`ssr: false`).

  **On page load:**
  1. Fetch `GET /api/locations/{location}/showtimes/{id}` (showtime + seat map). Location slug comes from `useLocations().activeLocation`.
  2. Initialize `useSeatSelection` with seat data
  3. Initialize `useCart` with showtime reference

  **Page structure:**
  - Top bar: movie title, date, time, screen name
  - AuditoriumScreenBar
  - AuditoriumGrid (integrates with useSeatSelection)
  - AuditoriumLegend
  - CartSummary (sidebar/bottom sheet, integrates with useCart)
  - "Continue to Checkout" CTA (disabled until ≥1 seat selected)

  **Seat selection flow:**
  - Click/tap available seat → toggle selection → update cart
  - Optimistic updates (no server round-trip per selection)
  - Maximum 10 seats per transaction

  **Session timer:** Starts on first selection. 10-min warning toast. 15-min expiry clears cart.

- **Acceptance Criteria:**
  - [ ] Seat map renders with current availability
  - [ ] Seat selection updates cart in real-time
  - [ ] "Continue to Checkout" disabled with 0 seats
  - [ ] Maximum seat limit enforced with message
  - [ ] Session timer starts on first selection
  - [ ] 5-minute warning toast fires at 10 minutes
  - [ ] Cart clears and redirects at 15-minute expiry
  - [ ] Keyboard-only seat selection works end-to-end

---

### Task 11: Checkout Page (`/purchase/checkout`)

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/app/pages/purchase/checkout.vue`
- **Details:**
  Per PURCHASE_FLOW.md Section 4. Layout: `purchase`. Rendering: client-only.

  **Prerequisite:** Cart must have ≥1 seat. Redirect to home if empty.

  **Page structure (Establishing Shot 65/35):**

  **Left (65%):**
  1. Order summary: movie, date, time, screen, seats with prices, subtotal
  2. FoodPreOrderPanel: food/drink upsell
  3. PromoCode input
  4. Gift card input

  **Right (35%):**
  5. CheckoutForm: Stripe Elements, email (guest), billing name, "Complete Purchase"

  **Payment submission flow (per PURCHASE_FLOW.md):**
  1. Client validates
  2. Stripe.js creates PaymentMethod
  3. POST `/api/locations/{location}/bookings` with full order
  4. Handle responses: success → redirect to confirmation, 409 → redirect to seats, 402 → show error, 3DS → Stripe modal
  - `POST /api/locations/{location}/bookings/confirm` with `paymentIntentId` after 3DS

  **Error handling:** Full error matrix from PURCHASE_FLOW.md Section 4.

  **409 seat conflict:** Parse `errors[0].unavailableSeatIds` from response body. Deselect affected seats in cart, show toast listing unavailable seats, redirect back to seat selection page.

  **410 session expired:** Clear cart, redirect to movie detail page with toast 'Your session has expired. Please start over.'

- **Acceptance Criteria:**
  - [ ] Establishing Shot 65/35 on desktop, single column on mobile
  - [ ] Empty cart redirects to home
  - [ ] Order summary displays all cart items
  - [ ] Food pre-order panel works
  - [ ] Promo code and gift card apply correctly
  - [ ] Stripe payment processes successfully
  - [ ] 409 seat conflict redirects back to seat selection
  - [ ] 402 payment error shows message, stays on page
  - [ ] 3DS modal handles verification

---

### Task 12: Confirmation Page (`/purchase/confirmation/:bookingId`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/purchase/confirmation/[bookingId].vue`
- **Details:**
  Per PURCHASE_FLOW.md Section 5. Layout: `purchase`. Rendering: client-only.

  **On page load:** Fetch `GET /api/bookings/:id`.

  **Page structure (Close-Up):** BookingConfirmation component with full booking data.

  **PurchaseStepIndicator:** On this page, `navigableSteps=[]` — all steps show as completed but non-clickable.

  **Cart clear:** Cart is cleared on reaching this page (transaction complete).

  **Guest booking lookup:** Include a 'Find My Booking' section below the main confirmation. Input: confirmation code + email. Calls `GET /api/bookings/lookup?confirmation_code=X&email=X`. This endpoint is intentionally not location-scoped — bookings are globally unique by confirmation code + email.

- **Acceptance Criteria:**
  - [ ] Booking details load and display
  - [ ] QR code generates correctly
  - [ ] Step indicator shows all completed, none clickable
  - [ ] Cart is cleared
  - [ ] Print layout works via browser print

---

## Testing Requirements

- **E2E Tests (Critical — revenue path):**
  - Full purchase flow: select seats → add food → checkout → confirmation
  - Seat conflict scenario: select seat, simulate it becoming taken, verify deselection + toast
  - Keyboard-only seat selection: Tab into grid, arrow navigate, Enter to select, Tab to CTA
  - Guest checkout: provide email, complete purchase
  - Session timeout: wait 15 minutes, verify cart clear + redirect
- **Accessibility Audit:**
  - AuditoriumGrid passes ARIA grid pattern validation
  - Screen reader announces all seat changes
  - Focus management throughout the 3-step flow
  - Stripe Elements accessibility (handled by Stripe)

## Dependencies Map

```
Task 1 (ScreenBar) ← independent
Task 2 (Legend) ← independent
Task 3 (AuditoriumSeat) ← independent
Task 4 (AuditoriumGrid) ← uses Tasks 1, 2, 3 + useSeatSelection
Task 5 (CartSummary) ← uses useCart
Task 6 (FoodPreOrderPanel) ← uses MenuCategoryTabs (Plan 10)
Task 7 (PromoCode) ← independent
Task 8 (CheckoutForm) ← needs Stripe.js
Task 9 (BookingConfirmation) ← needs QR library
Task 10 (Seat Selection Page) ← uses Tasks 1-5
Task 11 (Checkout Page) ← uses Tasks 5-8
Task 12 (Confirmation Page) ← uses Task 9
```

## Risks & Open Questions

1. **Stripe test mode** — Need Stripe test API keys for development. Use Stripe test card numbers (4242...) for E2E tests.
2. **QR code library** — Need a lightweight client-side QR generator. Options: `qrcode` (npm), `qr-code-styling`, or a simple SVG generator. Keep it small — this is a single-use feature.
3. **Seat conflict race condition** — Between seat selection and checkout, another user may book the same seat. The 409 error handling in the checkout flow addresses this, but the UX of being redirected back to seat selection needs to be smooth.
4. **FoodPreOrderPanel dependency on Plan 10** — MenuCategoryTabs component is defined in Plan 10. Either build a simple version here or stub it. Recommendation: build an inline version here; Plan 10 can extract it to the shared component.
5. **Mobile bottom sheet** — CartSummary as a mobile bottom sheet requires careful implementation. Consider using a CSS-only approach (fixed bottom, max-height transition) or a lightweight sheet library.
6. **.ics file generation** — Build the .ics template per PURCHASE_FLOW.md. No external library needed — it's a simple text format. Generate a Blob and trigger download.
7. **Location scoping** — All booking and showtime endpoints require a location slug. The location comes from `useLocations().activeLocation`. If the user changes location mid-purchase, the cart should be cleared and the flow restarted.
