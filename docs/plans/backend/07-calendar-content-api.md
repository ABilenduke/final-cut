# Plan 07: Calendar, Food Menu, Gift Cards, Contact & Rentals API

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 02 (CalendarEvent, MenuItem, GiftCard, RentalInquiry models)
> **Unlocks:** None (leaf node)

## Overview

Implement the supporting API endpoints: calendar events with filtering, food menu, gift card purchase and balance check, rental inquiries, and contact form submissions. These endpoints serve the content pages and secondary revenue streams.

## Reference Documents

- `docs/DATA_MODELS.md` — Section 2 (Calendar, Food Menu, Gift Cards, Rentals, Contact routes)
- `docs/PAGE_SPECS.md` — What's On, Food & Drink, Gift Cards, Private Screenings, Contact

---

## Tasks

### Task 1: CalendarEventController

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `backend/app/Http/Controllers/Api/CalendarEventController.php`
  - `backend/app/Http/Resources/CalendarEventResource.php`
- **Details:**
  **`index` — GET `/api/calendar/events`:**
  - Query params: `month`, `year`, `type` (showtime|special_event|loyalty_exclusive), `accessibility` (comma-separated: sensory_friendly,open_caption,audio_described)
  - Filter by month/year, event type, and accessibility tags
  - Accessibility filter: JSON column query — events where `accessibility_tags` contains any of the requested tags
  - Return: `{ data: CalendarEvent[] }`

  **`show` — GET `/api/calendar/events/{slug}`:**
  - Return full event detail by slug
  - Return: `{ data: CalendarEvent }`

  **Accessibility filter implementation:**
  ```php
  if ($accessibility = $request->query('accessibility')) {
    $tags = explode(',', $accessibility);
    $query->where(function ($q) use ($tags) {
      foreach ($tags as $tag) {
        $q->orWhereJsonContains('accessibility_tags', $tag);
      }
    });
  }
  ```

- **Acceptance Criteria:**
  - [ ] Month/year filtering works
  - [ ] Type filter returns correct event types
  - [ ] Accessibility filter searches JSON column correctly
  - [ ] Multiple accessibility tags work (OR logic)
  - [ ] Event detail returns by slug, 404 for invalid

---

### Task 2: FoodMenuController

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `backend/app/Http/Controllers/Api/FoodMenuController.php`
  - `backend/app/Http/Resources/MenuItemResource.php`
- **Details:**
  **`index` — GET `/api/food-menu`:**
  - Query: `category` (popcorn|drinks|snacks|combos|specials)
  - Filter by category, only return available items
  - Return: `{ data: MenuItem[] }`

- **Acceptance Criteria:**
  - [ ] Returns all available menu items
  - [ ] Category filter works
  - [ ] Unavailable items excluded
  - [ ] Allergens and dietary tags included in response

---

### Task 3: GiftCardController

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `backend/app/Http/Controllers/Api/GiftCardController.php`
  - `backend/app/Http/Requests/PurchaseGiftCardRequest.php`
  - `backend/app/Http/Resources/GiftCardResource.php`
- **Details:**
  **`purchase` — POST `/api/gift-cards/purchase`:**
  - Validate: amount (required, integer, min:500, max:50000), recipientEmail, recipientName, senderName, message, paymentMethodId
  - Create Stripe PaymentIntent for gift card amount
  - On payment success: create GiftCard record with generated code
  - Return: `{ data: GiftCard }`

  **`balance` — GET `/api/gift-cards/balance`:**
  - Query: `code` (required)
  - Look up gift card by code
  - Return: `{ data: { balance: number, status: string } }`
  - Invalid code: 404

  **Code generation:** Similar to confirmation codes — unique alphanumeric, e.g., "GC-" + 8 characters.

- **Acceptance Criteria:**
  - [ ] Gift card purchase processes payment and creates record
  - [ ] Generated code is unique
  - [ ] Balance check returns correct current balance
  - [ ] Invalid code returns 404
  - [ ] Amount in cents, validated range ($5 to $500)

---

### Task 4: ContactController + RentalController

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Http/Controllers/Api/ContactController.php`
  - `backend/app/Http/Controllers/Api/RentalController.php`
  - `backend/app/Http/Requests/ContactRequest.php`
  - `backend/app/Http/Requests/RentalInquiryRequest.php`
- **Details:**
  **Contact — POST `/api/contact`:**
  - Validate: name, email, subject, message (all required)
  - For MVP: log the submission (mail driver: log). Future: send email notification.
  - Return: `{ data: { success: true } }`

  **Rental — POST `/api/rentals/inquiry`:**
  - Validate: event_type (enum), preferred_date (date, after:today), guest_count (integer, min:1), name, email, message (all required), phone (optional)
  - Create RentalInquiry record with status: 'pending'
  - Return: `{ data: { success: true, inquiryId: uuid } }`

- **Acceptance Criteria:**
  - [ ] Contact form validates and logs
  - [ ] Rental inquiry creates record with pending status
  - [ ] Event type validates against allowed enum values
  - [ ] Preferred date must be in the future
  - [ ] Both return success response

---

## Testing Requirements

- **Pest Feature Tests:**
  - Calendar events: list with month/year filter, type filter, accessibility filter, multiple filters combined, event detail by slug
  - Food menu: list all, filter by category, unavailable items excluded
  - Gift cards: purchase with Stripe, balance check (valid code, invalid code, depleted card)
  - Contact: valid submission, missing fields (422)
  - Rentals: valid submission, invalid event type (422), past date (422)
- **Unit Tests:**
  - Gift card code generation uniqueness
  - Calendar accessibility filter logic

## Dependencies Map

```
Task 1 (CalendarEventController) ← independent
Task 2 (FoodMenuController) ← independent
Task 3 (GiftCardController) ← uses StripeService from Plan 04
Task 4 (ContactController + RentalController) ← independent
```

## Risks & Open Questions

1. **JSON column queries** — PostgreSQL JSON queries (`whereJsonContains`) have different syntax than MySQL. Verify Laravel's query builder handles PostgreSQL JSON correctly.
2. **Gift card email** — Gift card purchase should email the recipient with the code. Deferred to future (mail driver: log). The code is returned in the API response for now.
3. **Contact form spam** — No CAPTCHA or honeypot in MVP. Consider adding rate limiting (throttle:5,1) on the contact and rental endpoints.
