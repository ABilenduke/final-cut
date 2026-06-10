# Purchase Flow

The most complex feature on the site. This document covers the complete user journey from deciding to buy tickets through to holding a printed confirmation.

---

## 1. User Journey Overview

```
Entry Points                    Purchase Flow                         Post-Purchase
─────────────                   ─────────────                         ─────────────
                                
Movie Detail Page    ─┐
  (ShowtimeSelector)  │         ①                ②                ③
                      ├──→  Pick Your Seats ──→ Add Food & Pay ──→ You're In
Calendar / What's On ─┤      /purchase/          /purchase/         /purchase/
  (event click)       │      :showtimeId         checkout           confirmation/
                      │                                             :bookingId
Direct URL           ─┘
  (shared link)
```

Three pages, one linear flow. The user cannot skip steps — seat selection must happen before checkout, checkout before confirmation.

### Step Indicator

The `purchase` layout renders a `PurchaseStepIndicator` component at the top of every purchase page. It displays three labeled steps:

1. **Pick Your Seats** — seat selection
2. **Add Food & Pay** — checkout
3. **You're In** — confirmation

**Navigation behavior:**

- **Completed steps** are clickable links. Clicking a completed step navigates back directly (e.g., from checkout back to seat selection) without losing cart state.
- **Current step** is visually active — gold underline using the `secondary` (#DAC769) token.
- **Future steps** are disabled and greyed using `outline_variant` (#57423E).
- **Confirmation is final** — once the user reaches step 3, `navigableSteps` is set to `[]`. Steps 1 and 2 render as completed (checkmark) but non-clickable (the transaction is complete).
- Cart state is preserved when navigating backward. If the user returns to seat selection from checkout, their existing selections remain intact.

---

## 2. Entry Points

### From Movie Detail (`/movies/:slug`)

The ShowtimeSelector component on the movie detail page displays available dates and times. Clicking a time slot navigates to `/purchase/:showtimeId`. The showtime ID encodes the movie, screen, date, and time.

### From Calendar (`/whats-on`)

Clicking a showtime-type event in the calendar event list navigates directly to `/purchase/:showtimeId`.

### From Direct URL

Showtime links are shareable. `/purchase/:showtimeId` works as a standalone entry point — the page fetches all necessary context (movie title, screen, time, seat map) from the showtime ID.

---

## 3. Step 1: Pick Your Seats (`/purchase/:showtimeId`)

**Layout:** `purchase` (minimal nav — logo + step indicator, no footer)

**On page load:**
1. `GET /api/showtimes/:id` fetches showtime details + full auditorium seat map with current availability
2. `useSeatSelection` composable initializes with seat data
3. `useCart` composable initializes with showtime reference

**Page structure:**
- Top bar: movie title, date, time, screen name
- AuditoriumScreenBar (curved bar representing the screen)
- AuditoriumGrid (interactive seat map)
- AuditoriumLegend (color key for seat states)
- CartSummary (sidebar on desktop, bottom sheet on mobile)
- "Continue to Checkout" CTA (disabled until at least one seat selected)

### Seat Selection Mechanics

**Clicking/tapping a seat:**
1. If `available` → toggles to `selected`, adds to cart
2. If `selected` → toggles back to `available`, removes from cart
3. If `taken` or `held` → no action (non-interactive, `aria-disabled`)
4. If `accessible` → same as available, but these seats are visually marked and may have different pricing

**Optimistic updates:** Selection is instant on the client. No server round-trip per selection. The server validates all selected seats at checkout time.

**Cart updates:** Each selection/deselection:
- Updates `useCart` state (seat added/removed, total recalculated)
- CartSummary re-renders with updated line items and total
- Screen reader announces: "Seat [ID] selected. [N] seats selected, [price] total."

### Seat Availability Freshness

**Mock phase:** Seat data is static — no real-time updates needed.

**Production options (choose one):**

**Option A: Polling (simpler)**
- Re-fetch seat map every 30 seconds via `useFetch` with `refresh()` on interval
- On refresh: merge new availability with current selection
- If a selected seat is now `taken`: deselect it, show toast "Seat [ID] is no longer available"

**Option B: WebSocket (better UX)**
- Server pushes seat status changes in real-time
- Client merges incoming updates with local state
- Same conflict handling: deselect + toast if a selected seat is taken

### Maximum Seat Limit

Enforce a reasonable maximum (e.g., 10 seats per transaction) to prevent abuse. Display remaining capacity: "You can select up to [N] more seats."

---

## 4. Step 2: Add Food & Pay (`/purchase/checkout`)

**Layout:** `purchase`

**Prerequisites:** Cart must contain at least one seat. If cart is empty (user navigated directly), redirect to home.

**Page structure (Establishing Shot 65/35):**

**Left column (65%):**

1. **Order Summary** — movie title, date, time, screen. List of selected seats with section and price each. Subtotal.

2. **Food & Drink Pre-Order (FoodPreOrderPanel)** — upsell moment designed to convert, not feel like a chore.
   - **Collapsed default state:** teaser banner ("Add snacks to your order?") with 2–3 popular combo thumbnail images. Uses `surface_container_high` background to visually separate it as a distinct moment in the flow.
   - **Expanded state:** "Most Popular" section at top (3–4 highlighted combos), followed by categorized menu grid from `/api/locations/{location}/food-menu` with category tabs. Each item shows image, name, price, and one-tap "Add" button with quantity selector.
   - Items added here update the cart total in real-time. User can skip this entirely — the panel collapses back down if dismissed.

3. **Promo Code (PromoCode)** — text input + "Apply" button. On apply: validates code server-side, returns discount amount. Applied code shows with discount and "Remove" option.

4. **Gift Card Redemption** — card code input + "Apply" button. Can partially or fully cover the total. Remaining balance shown.

**Right column (35%):**

5. **CheckoutForm** — Stripe Elements card input. If not authenticated: email address field (for receipt and booking access). Billing name. Order total displayed prominently. "Complete Purchase" CTA.

### Guest vs Authenticated Checkout

| Aspect | Guest | Authenticated |
| ------ | ----- | ------------- |
| Email | Required (manual input) | Pre-filled from account |
| Saved cards | Not available | Available via Stripe Customer |
| Loyalty points | Not earned (unless opt-in below) | Earned on purchase |
| Loyalty opt-in | **Deferred — not in the v1 UI.** The planned flow ("Join [Theater] Rewards" checkbox → post-purchase magic-link claim email → `/auth/register` with the booking pre-associated and points retroactively awarded) was never implemented backend-side, so the checkbox was removed from checkout rather than shipping a control that does nothing (admin-v3 Plan 04). | N/A (already a member) |
| Order history | Accessible via booking URL only | Appears in `/account/orders` |
| Receipt | Sent to provided email | Sent to account email |

### Payment Submission Flow

On "Complete Purchase" click:

```
1. Client validates all fields
2. Stripe.js creates PaymentMethod from card input
3. Client sends POST /api/bookings:
   {
     showtimeId: string
     seatIds: string[]
     foodItems: { itemId: string, quantity: number }[]
     paymentMethodId: string
     promoCode: string | null
     giftCardCode: string | null
     email: string | null          // Guest only
     // loyaltyOptIn — deferred; see the Guest vs Authenticated table above
   }
4. Server validates:
   a. Showtime exists and is in the future
   b. All seats are still available (CRITICAL — prevents double-booking)
   c. Food items are valid and available
   d. Promo code is valid (if provided)
   e. Gift card has sufficient balance (if provided)
5. Server calculates final total:
   (seat prices) + (food prices) - (promo discount) - (gift card amount)
6. Server creates Stripe PaymentIntent for remaining amount
7. Server confirms PaymentIntent
8. On success:
   a. Mark seats as taken
   b. Deduct gift card balance (if used)
   c. Create Booking record
   d. Award loyalty points (if authenticated)
   e. Return Booking to client
9. Client redirects to /purchase/confirmation/:bookingId
```

### Error Handling

| Error | Server Response | Client Behavior |
| ----- | --------------- | --------------- |
| Seats no longer available | `409 Conflict` with `{ unavailableSeatIds: string[] }` | Toast: "Some seats are no longer available." Redirect back to seat selection with unavailable seats highlighted. Cart cleared of those seats. |
| Payment declined | `402 Payment Required` with Stripe error | Toast: "Payment declined. Please try another card." Stay on checkout. |
| 3D Secure required | `200` with `{ requiresAction: true, clientSecret }` | Stripe.js opens 3DS modal. On success: `POST /api/bookings/confirm`. On failure: toast error. |
| Invalid promo code | `400` with `{ field: 'promoCode', message }` | Show error under promo code input. |
| Insufficient gift card balance | `400` with `{ field: 'giftCardCode', remainingBalance }` | Show remaining balance, prompt for additional payment method. |
| Session expired | `410 Gone` | Toast: "Your session has expired. Please start over." Redirect to movie detail. |
| Server error | `500` | Toast: "Something went wrong. Your card was not charged. Please try again." |

---

## 5. Step 3: You're In (`/purchase/confirmation/:bookingId`)

**Layout:** `purchase` (minimal chrome) — optimized for both screen and print

**On page load:** `GET /api/bookings/:id` fetches booking details.

**Page structure (Close-Up composition):**

1. **Success banner** — "Booking Confirmed" with check icon
2. **Confirmation code** — large, prominent display (e.g., "CVF-A3X9K2")
3. **Movie details** — title, poster thumbnail, date, time, screen
4. **Seats** — list with row/section labels
5. **Food pre-orders** — if any, itemized list
6. **Payment summary** — subtotal, discounts, total charged
7. **QR code** — generated client-side from booking ID, scannable at theater
8. **Actions:**
   - "Add to Calendar" — generates and downloads .ics file
   - "Print Tickets" — triggers `window.print()` with print-optimized layout
   - "View in Order History" — link to `/account/orders` (authenticated only)
   - "Back to Home" — link to `/`

### QR Code

Generated client-side using a lightweight QR library. Encodes the confirmation code (not the full URL). Theater staff scan with standard QR reader to look up booking.

**Print layout:** QR code renders as a static image in print. Theater name, booking details, and QR code are the only elements that print — all navigation and interactive elements are suppressed.

### .ics Calendar File

```
BEGIN:VCALENDAR
BEGIN:VEVENT
DTSTART:20260403T190000
DTEND:20260403T211500
SUMMARY:[Movie Title] at [Theater Name]
LOCATION:[Theater Address]
DESCRIPTION:Screen: [Screen Name]\nSeats: [Seat List]\nBooking: [Code]
END:VEVENT
END:VCALENDAR
```

---

## 6. Cart Lifecycle

The cart is **ephemeral** — it exists only during an active purchase session and is never persisted to storage.

### Creation

Cart initializes when the user enters `/purchase/:showtimeId`. It stores:
- Showtime reference (ID, movie title, date, time, screen)
- Selected seats (array of Seat objects)
- Food items (array of item + quantity)
- Promo code (if applied)
- Gift card (if applied)
- Computed total

### Session Timeout

- **15-minute timer** starts when the user first selects a seat
- **10-minute warning:** toast notification "Your session expires in 5 minutes. Complete your purchase to keep your seats."
- **15-minute expiry:** cart is cleared, toast "Your session has expired. Selected seats have been released." Redirect to seat selection to start over.
- Timer resets if the user deselects all seats and starts over

### Destruction

Cart is cleared when:
1. **Purchase completes** — redirect to confirmation, cart clears
2. **User navigates away** — leaving the `/purchase/*` route tree clears the cart (via route middleware in the purchase layout)
3. **Session timeout** — 15-minute expiry
4. **Manual clear** — user clicks "Start Over" or deselects all seats

### No Server-Side Seat Holds (MVP)

In the MVP, the server does **not** hold seats based on cart state. Seat availability is validated only at final booking time (`POST /api/bookings`). This is simpler to implement and avoids the complexity of distributed seat locking.

**Trade-off:** A user might select seats that another user books before they check out. The server-side validation at step 4b catches this, and the 409 error handling guides the user back to reselect.

**Future enhancement:** Implement server-side seat holds with TTL (e.g., `POST /api/bookings/hold` returns a hold token valid for 10 minutes). Held seats show as `held` to other users. This requires a background job to expire holds and release seats.

---

## 7. Accessibility

### Seat Selection Grid

Full keyboard navigation per WAI-ARIA grid pattern:

| Key | Action |
| --- | ------ |
| Arrow Right | Move focus to next seat in row |
| Arrow Left | Move focus to previous seat in row |
| Arrow Down | Move focus to same position in next row |
| Arrow Up | Move focus to same position in previous row |
| Home | First available seat in current row |
| End | Last available seat in current row |
| Enter / Space | Toggle seat selection |
| Escape | Deselect all seats, return focus to grid container |
| Tab | Exit grid to next interactive element |

**Screen reader announcements:**
- On seat focus: "Seat [ID], [status]. Row [letter], seat [number]. [Section] section. [Price]."
- On selection: "Seat [ID] selected. [N] seats selected, [total] total."
- On deselection: "Seat [ID] deselected."
- Taken seats: `aria-disabled="true"`, announced as "unavailable"

### Touch Targets

Mobile seat cells are 3rem (48px) — meets 48px minimum natively without pinch-to-zoom. Gap between cells is 0.25rem (4px), providing reliable touch discrimination.

### Cart Updates

CartSummary total is `aria-live="polite"` — screen readers announce total changes without interrupting current task.

### Checkout Form

- All inputs have visible labels (not placeholder-only)
- Error messages linked via `aria-describedby`
- Form submission errors announced via `aria-live="assertive"`
- Stripe Elements handles its own ARIA

### Confirmation Page

- Success message is the first content in reading order
- QR code has descriptive `alt` text
- Print button triggers native print dialog (keyboard accessible)
- All links are descriptive (no "click here")

---

## 8. Responsive Behavior

### Seat Selection

| Viewport | Behavior |
| -------- | -------- |
| Desktop (screen-md+) | Full grid visible, CartSummary in sidebar, 2.5rem cells |
| Mobile (below screen-md) | 3rem cells, horizontal scroll on grid, CartSummary as bottom sheet (collapsible), row labels pinned left |

### Checkout

| Viewport | Behavior |
| -------- | -------- |
| Desktop | Establishing Shot 65/35 — order details left, payment right |
| Mobile | Single column — order summary first, then food pre-order, then payment form. CartSummary as sticky bottom bar showing total + "Pay" CTA |

### Confirmation

Single column (Close-Up) at all breakpoints. QR code centered. Print layout suppresses all chrome.
