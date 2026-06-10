# Admin v3 Progress Journal

Execution journal for [`docs/plans/admin/v3/`](../plans/admin/v3/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 3.6: Initial booking confirmation email
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Second delta-audit catch: customers never received a booking confirmation
  email — only the admin *resend* existed. New `booking.confirmation` event +
  `BookingNotificationService::queueConfirmation()` (no-recipient no-op), called inside
  `finalizeBooking()`'s Phase C transaction (covers immediate + 3DS paths) and from
  walk-up sales when an email was captured; dispatcher maps it to the existing
  `SendBookingConfirmation` job. Backend **1288 passed**, PHPStan + Pint clean.
  (Also verified: `/api/account/payment-methods` endpoints exist — not a gap.)

### Decisions
- [2026-06-10] Reused the resend job/mailable via a shared dispatcher arm — one renderer
  for initial + resend keeps the emails identical by construction.
- [2026-06-10] Gotcha: `BookingConfirmationMail` is sent inline from the already-queued
  job (not ShouldQueue) — round-trip tests use `Mail::assertSent`, unlike the gift-card
  mailables which queue (`assertQueued`).

### Blockers
- none

### Files Changed
- `backend/app/Services/BookingNotificationService.php` — event + `queueConfirmation()`
- `backend/app/Http/Controllers/Api/BookingController.php` — injection + finalize call
- `backend/app/Services/WalkUpBookingService.php` — injection + call when email captured
- `backend/app/Outbox/OutboxDispatcher.php` — shared arm for the new event
- `backend/tests/Feature/BookingInitialConfirmationTest.php` — 5 tests
- `docs/plans/admin/v3/06-initial-confirmation.md` — step spec

## Step 3.5: Gift card delivery email
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Third audit pass caught that Plan 03's sale loop was never closed:
  purchases stored `delivery_method`/`scheduled_send_at` and ignored both — recipients
  never got their codes. Built the delivery vertical on the durable-outbox pattern:
  `gift_card.delivery` row written inside the purchase transaction (scheduled sends ride
  `available_at`), `SendGiftCardDelivery` job (Active-only guard — never email a code
  voided before a scheduled send), recipient-facing `GiftCardDeliveryMail`. Print cards
  write no row. Backend **1283 passed**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Scheduled sends use the outbox's `available_at` instead of a new
  scheduler — the worker's existing dispatchable() scope implements "send later" for free.
- [2026-06-10] Job guards on `status === Active` so voids between purchase and a
  scheduled send can't deliver a dead code; the skip is logged for support.

### Blockers
- none

### Files Changed
- `backend/app/Services/GiftCardService.php` — `EVENT_DELIVERY` + outbox write in purchase()
- `backend/app/Jobs/SendGiftCardDelivery.php`, `backend/app/Mail/GiftCardDeliveryMail.php`,
  `backend/resources/views/mail/gift-card-delivery.blade.php` — new vertical
- `backend/app/Outbox/OutboxDispatcher.php` — `gift_card.delivery` arm
- `backend/tests/Feature/GiftCardDeliveryTest.php` — 7 tests
- `docs/plans/admin/v3/05-gift-card-delivery.md` — step spec

## Step 3.4: Checkout cleanup
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Removed the checkout controls that promised something the purchase never
  did: the guest loyalty opt-in checkbox (body field + `CreateBookingRequest` rule too;
  PURCHASE_FLOW.md marks the magic-link flow deferred), the phone/Reel-Society-ID inputs,
  and the unsent newsletter checkbox. Added the cross-stack hold-timer guard (paired
  8/20 pins: frontend architecture test + backend Pest test on the sweeper's option
  default). Defensive docs: Stripe-webhook TODO with the exact failure window in
  routes/api.php, partial-refund rationale on `refundAction()`, expanded 3DS
  seat-release trade-off comment in `BookingController::store()`.
  Suites: **backend 1276, frontend 950 (+5 skipped)**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Removed (not wired) the loyalty opt-in — wiring means building the whole
  magic-link claim flow; shipping a checkbox that silently does nothing is worse than
  no checkbox. The spec's design is preserved in PURCHASE_FLOW.md as deferred.
- [2026-06-10] Kept the authenticated "save this card" checkbox — it fronts the planned
  saved-payment-methods feature; flagged in the PR as currently unwired.
- [2026-06-10] Hold-timer guard uses paired same-side pins (8/20 contract constants with
  cross-references) because the test containers can't read across the mount boundary.

### Blockers
- none

### Files Changed
- `frontend/app/components/booking/{CheckoutPaymentBay,CheckoutContactBay,PromoCode}.vue`,
  `frontend/app/pages/purchase/checkout.vue` — dead controls removed
- `backend/app/Http/Requests/CreateBookingRequest.php` — loyaltyOptIn rule dropped
- `backend/routes/api.php`, `backend/app/Filament/Resources/BookingResource.php`,
  `backend/app/Http/Controllers/Api/BookingController.php` — defensive comments
- `frontend/tests/architecture/hold-timer-alignment.test.ts` (new),
  `backend/tests/Unit/HoldTimerContractTest.php` (new) — paired contract pins
- `frontend/tests/components/booking/{CheckoutPaymentBay,CheckoutContactBay,PromoCode}.test.ts` — pins updated
- `docs/specs/PURCHASE_FLOW.md` — loyalty opt-in marked deferred
- `docs/plans/admin/v3/04-checkout-cleanup.md` — step spec

## Step 3.3: Gift card payments
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Gift cards are sellable: new `GiftCardPaymentModal` (Stripe Elements card
  collection per the CheckoutPaymentBay idiom; purchase → 3DS `handleCardAction` →
  confirm, fresh Idempotency-Key per attempt) wired into `gift-cards.vue`
  (`pendingPayload` → modal → success section + composer reset). The
  "Payment integration coming soon" toast is gone. **Frontend-only** — the backend
  purchase/confirm endpoints existed all along with full Fake-Stripe coverage.
  Suites: **frontend 948 (+5 skipped)**, backend gift-card tests 92, Pint clean.

### Decisions
- [2026-06-10] Modal mounts fresh per attempt (`v-if="pendingPayload"`) — Elements
  lifecycle stays trivially correct vs. keeping one long-lived element instance.
- [2026-06-10] `useGiftCards.purchase` needed no change: it already passed the
  idempotency key as the apiFetch header option, matching the backend's
  `prepareForValidation` header read.
- [2026-06-10] Gotcha: CvModal teleports to `<body>` — component tests must query
  `document.body`, not the wrapper (CvModal.test.ts idiom).

### Blockers
- none

### Files Changed
- `frontend/app/components/content/GiftCardPaymentModal.vue` — new payment step
- `frontend/app/pages/gift-cards.vue` — modal wiring + Order confirmed section
- `frontend/app/components/content/GiftCardPreview.vue` — placeholder toast removed
- `frontend/tests/components/content/GiftCardPaymentModal.test.ts` — 3 tests
- `docs/plans/admin/v3/03-gift-card-payments.md` — step spec

## Step 3.2: Site contacts CMS
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] `site_contacts` blob in the keyed `site_settings` store + `SiteContacts`
  Filament page (Content group, same `content.site_settings.update` gate) + cached
  `GET /api/site-content/contacts`. Frontend: `useSiteContacts()` consumed by the footer
  and seven contact surfaces (accessibility/terms/privacy/careers/gift-cards×3) with
  `fallbackSiteContacts` as the render fallback; `/accessibility` flipped prerender→ISR.
  Suites: **backend 1275, frontend 945 (+5 skipped)**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Flat single blob (not per-page keys) — one form, one fetch, one fallback;
  the footer needs it on every page anyway so all consumers share the SSR-dedup key.
- [2026-06-10] `telHref()` helper derives `tel:` links from display phones (US-format)
  instead of storing a second href field per phone.
- [2026-06-10] `BridgeCinemaReadout` left as v1 static stub — live-ops data, not CMS copy.

### Blockers
- none

### Files Changed
- `backend/app/Services/SiteSettingsService.php` — `KEY_SITE_CONTACTS`
- `backend/app/Http/Controllers/Api/SiteContentController.php`, `backend/routes/api.php` — contacts endpoint
- `backend/app/Filament/Pages/SiteContacts.php` + `backend/resources/views/filament/pages/site-contacts.blade.php` — admin form
- `backend/tests/Feature/Admin/Services/SiteContactsTest.php` — 5 tests
- `frontend/app/data/siteContacts.ts` (new) — interface + fallback + `telHref()`
- `frontend/app/composables/useSiteContent.ts` — `useSiteContacts` + resolver
- `frontend/app/components/layout/SiteFooter.vue`, `frontend/app/pages/{accessibility,terms,privacy,careers,gift-cards,gift-cards/bulk}.vue`, `frontend/app/components/content/GiftCardPreview.vue` — consumption
- `frontend/nuxt.config.ts` — `/accessibility` ISR flip
- `frontend/tests/components/layout/SiteFooter.test.ts` (new), `frontend/tests/composables/useSiteContent.test.ts`, mock updates in `static-pages` + `GiftCardPreview` tests
- `docs/plans/admin/v3/02-site-contacts.md` — step spec

## Step 3.1: Admin ops polish
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Refund timestamps + Stripe refund id surfaced on the booking view
  (conditional placeholders — hidden on never-refunded bookings). New
  `GiftCardService::adjust()` (row lock, signed cents, overdraw/terminal-status guards,
  status follows balance, `Adjustment` ledger type finally used) + **Adjust balance**
  table action behind new `gift_cards.adjust` permission. New
  `PromoCodeService::reactivate()` + **Reactivate** action on deactivated promos.
  Backend suite **1269 passed**, PHPStan + Pint clean.

### Decisions
- [2026-06-10] Adjustment takes signed cents in one field (money convention: integers in
  cents) rather than a direction select + amount; the helper text spells out the sign.
- [2026-06-10] No finance outbox email for adjustments (unlike void) — they're support
  corrections; the ledger row + activity log are the audit trail.
- [2026-06-10] Gotcha: Filament v5 table-action form state binds at `mountedActions.0.data.*`
  (NOT `mountedTableActions`) — actions are unified across page/table in v5.

### Blockers
- none

### Files Changed
- `backend/app/Services/GiftCardService.php` — `adjust()` + exception import
- `backend/app/Exceptions/GiftCardNotAdjustableException.php` — new
- `backend/app/Services/PromoCodeService.php` — `reactivate()`
- `backend/app/Filament/Resources/GiftCardResource.php` — Adjust balance action
- `backend/app/Filament/Resources/PromoCodeResource.php` — Reactivate action
- `backend/app/Filament/Resources/BookingResource.php` — refund placeholders
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — `gift_cards.adjust`
- `backend/tests/Feature/Admin/Services/OpsPolishTest.php` — 11 tests
- `docs/plans/admin/v3/{00-index,01-ops-polish}.md` — plan docs
