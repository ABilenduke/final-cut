# Plan 10: Content Domain

> **Priority:** Should Have (FAQ/Contact: Must Have)
> **Complexity:** M
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts), Plan 05 (server routes for contact/rentals/gift cards/food menu)
> **Unlocks:** None (leaf node)

## Overview

Build the content pages that round out the site experience: FAQ, Contact, Food & Drink menu, Gift Cards, and Private Screenings. These pages serve operational needs (how to reach the theater, dietary information) and secondary revenue streams (gift cards, private event bookings).

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — Tier 2: Domain Components — Content
- `docs/PAGE_SPECS.md` — FAQ, Contact, Food & Drink, Gift Cards, Private Screenings
- `docs/DATA_MODELS.md` — MenuItem, GiftCard, RentalInquiry interfaces

---

## Tasks

### Task 1: FaqAccordionGroup

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/content/FaqAccordionGroup.vue`
  - `frontend/app/components/content/FaqAccordionGroup.stories.ts`
- **Details:**
  **Props:** `category: string`, `items: Array<{ question: string; answer: string }>`

  Category heading + multiple CvAccordion instances. Data from `app/data/faq.ts`.

- **Acceptance Criteria:**
  - [ ] Category title renders as heading
  - [ ] Each FAQ item uses CvAccordion
  - [ ] Multiple items can be open simultaneously

---

### Task 2: ContactForm + ContactMap

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/content/ContactForm.vue`
  - `frontend/app/components/content/ContactMap.vue`
  - Stories for each
- **Details:**
  **ContactForm:** Self-contained. Name, email, subject (CvInput), message (CvTextarea), submit (CvButton). Emits `submit({ name, email, subject, message })`. Posts to `/api/contact`.

  **ContactMap:** Embedded map with dark theme. Props: `coordinates: { lat, lng }`. Uses iframe or static image with `alt` text.

- **Acceptance Criteria:**
  - [ ] Form validates required fields
  - [ ] Success feedback on submission (toast)
  - [ ] Map renders with dark theme
  - [ ] Map iframe has accessible title

---

### Task 3: MenuItem + MenuCategoryTabs

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/content/MenuItem.vue`
  - `frontend/app/components/content/MenuCategoryTabs.vue`
  - Stories for each
- **Details:**
  **MenuItem:** Image (4:3 aspect), name (headline-sm), description (body-sm), price (formatted), dietary badges (CvBadge — vegan, GF, nuts, etc.).

  **MenuCategoryTabs:** Horizontal scrolling tabs. Props: `categories: string[]`, `active: string`. Events: `select(category)`. `role="tablist"`, tabs with `role="tab"`, `aria-selected`.

  The food menu API (`GET /api/locations/{location}/food-menu`) returns items grouped by category as `{ data: Record<string, MenuItem[]> }`, not a flat array. MenuCategoryTabs can use the group keys directly. MenuItem component renders individual items from the grouped response.

- **Acceptance Criteria:**
  - [ ] Menu item displays all fields with correct typography
  - [ ] Dietary badges use CvBadge
  - [ ] Price formatted via formatCurrency
  - [ ] Category tabs scrollable and keyboard accessible

---

### Task 4: GiftCardPurchase + BalanceChecker

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/content/GiftCardPurchase.vue`
  - `frontend/app/components/content/BalanceChecker.vue`
  - Stories for each
- **Details:**
  **GiftCardPurchase:** Amount selector (preset: $25, $50, $75, $100 + custom CvInput), recipient name, email, personal message, purchase CTA. Integrates with Stripe for payment. Emits `purchase({ amount, recipientEmail, recipientName, message })`.

  **Idempotency:** Generate a UUID per purchase attempt and send as `Idempotency-Key` header. Store the key so retries reuse the same one with the same payload.

  **3DS flow:** Purchase can return `{ requiresAction: true, clientSecret, paymentIntentId }`. Handle via Stripe.js next-action, then call `POST /api/gift-cards/confirm` with `paymentIntentId`.

  **Duplicate detection:** If the server returns 409 with payload mismatch, show an error. If same payload, show 'retrieving status...' and fetch the existing result.

  **Amount validation:** Gift card amounts: 500-50000 cents ($5-$500).

  **BalanceChecker:** Code input (CvInput), "Check Balance" button (CvButton), balance display area. Uses `useGiftCards().checkBalance()`.

- **Acceptance Criteria:**
  - [ ] Preset amounts selectable
  - [ ] Custom amount input validates range
  - [ ] Stripe payment integration for purchase
  - [ ] Balance check returns and displays result
  - [ ] Error handling for invalid codes
  - [ ] Idempotency-Key UUID sent on purchase request
  - [ ] 3DS continuation flow works (requiresAction → confirm)
  - [ ] Duplicate submission handled gracefully
  - [ ] Amount validated within $5-$500 range

---

### Task 5: RentalInquiryForm + PackageCard

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/content/RentalInquiryForm.vue`
  - `frontend/app/components/content/PackageCard.vue`
  - Stories for each
- **Details:**
  **RentalInquiryForm:** Event type (CvSelect: birthday, corporate, proposal, custom), date (CvInput type date), guest count (CvInput type number), name, email, message (CvTextarea), submit. Posts to `/api/rentals/inquiry`.

  **PackageCard:** CvCard with package name (headline-sm), description, feature list, starting price.
  **Props:** `package: { name, description, startingPrice, features[] }`

- **Acceptance Criteria:**
  - [ ] Form validates all required fields
  - [ ] Event type dropdown has all options
  - [ ] Success feedback on submission
  - [ ] Package cards display features and pricing

---

### Task 6: Content Pages

- **MoSCoW:** Must Have (FAQ, Contact), Should Have (others)
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/faq.vue` — Must Have
  - `frontend/app/pages/contact.vue` — Must Have
  - `frontend/app/pages/food-drink.vue` — Should Have
  - `frontend/app/pages/gift-cards.vue` — Should Have
  - `frontend/app/pages/private-screenings.vue` — Should Have
- **Details:**
  **FAQ (`/faq`):** Close-Up, prerendered. FaqAccordionGroup per category. Data from `app/data/faq.ts`. SEO: `FAQPage` structured data.

  **Contact (`/contact`):** Establishing Shot 65/35, prerendered. Left: ContactMap, directions, parking, accessibility info. Right: hours, phone, email, ContactForm. SEO: `LocalBusiness` structured data.

  **Food & Drink (`/food-drink`):** Ensemble grid with MenuCategoryTabs. ISR (30 min). Data: `GET /api/locations/{location}/food-menu`. Location slug from `useLocations().activeLocation`. SEO: `Menu` structured data.

  **Gift Cards (`/gift-cards`):** Establishing Shot 65/35. Left: GiftCardPurchase. Right: BalanceChecker.

  **Private Screenings (`/private-screenings`):** Rack Focus 35/65. Left: RentalInquiryForm. Right: PackageCards.

- **Acceptance Criteria:**
  - [ ] FAQ renders all categories with working accordions
  - [ ] FAQ has FAQPage structured data
  - [ ] Contact page has map, form, and LocalBusiness structured data
  - [ ] Food menu loads with category filtering
  - [ ] Gift card purchase and balance check work
  - [ ] Rental inquiry form submits successfully
  - [ ] Correct layout compositions per page spec

---

## Testing Requirements

- **Storybook:** Stories for all content components
- **E2E Tests:**
  - FAQ: accordion expand/collapse, all categories visible
  - Contact: form submission, validation errors
  - Food menu: category tab switching, item display
  - Gift card: balance check with valid/invalid code
- **SEO:** Verify structured data (FAQPage, LocalBusiness, Menu) in page source

## Dependencies Map

```
Task 1 (FaqAccordionGroup) ← uses CvAccordion
Task 2 (ContactForm + ContactMap) ← uses CvInput, CvTextarea, CvButton
Task 3 (MenuItem + MenuCategoryTabs) ← uses CvBadge, CvCard
Task 4 (GiftCardPurchase + BalanceChecker) ← uses CvInput, CvButton, Stripe
Task 5 (RentalInquiryForm + PackageCard) ← uses CvInput, CvSelect, CvTextarea, CvCard
Task 6 (Content Pages) ← uses all above
```

## Risks & Open Questions

1. **Map provider** — ContactMap needs a dark-themed map. Options: Google Maps with dark mode styling, Mapbox with dark theme, or a static image. Decision: start with a static image or Mapbox; Google Maps requires API key and has usage costs.
2. **Gift card email delivery** — Gift card purchase should send an email to the recipient. This is a backend concern (stub for now). The frontend just needs to show success and the gift card code.
3. **FAQ data source** — Currently in `app/data/faq.ts`. Could migrate to `@nuxt/content` markdown in Plan 11 if content editing is needed. For now, TypeScript data is simpler.
