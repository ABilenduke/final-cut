# Plan 05 (v3) — Gift card delivery email

**Step:** 3.5 · **Status:** ✅ Complete

## Goal

Plan 03 made gift cards sellable, but the third audit pass found the loop
was never closed: `GiftCardService::purchase()` stored `delivery_method`
and `scheduled_send_at` and then ignored both — a paying customer's
recipient never received the code. This step completes the approved 3.3
scope ("composer → confirm → email delivery").

## Design

The established durable-outbox email vertical (`gift_card.voided` pattern):

- **`GiftCardService::EVENT_DELIVERY = 'gift_card.delivery'`** — a
  `dispatch_outbox` row written inside the purchase transaction for
  email-delivery cards. **Scheduled sends ride `available_at`**: the
  worker simply won't pick the row up until `scheduled_send_at`. Print
  cards write no row (physical fulfillment).
- **`SendGiftCardDelivery` job** (dispatcher arm added): loads the card,
  delivers only when status is still **Active** — a card voided between
  purchase and a scheduled send must not email a dead code (skip is
  logged). Missing card → silent no-op (row marked processed).
  At-least-once is fine: a duplicate is a harmless re-send of the same code.
- **`GiftCardDeliveryMail`** (queued, `notifications` queue): recipient-
  facing markdown mail with sender name, optional personal message, value,
  and the redemption code.

## Tests

`backend/tests/Feature/GiftCardDeliveryTest.php` — 7 tests: immediate vs
scheduled `available_at`, print writes no row, dispatcher arm, job mails
the recipient, voided/missing-card skips, and the full
`outbox:dispatch` round trip (with the documented available_at rewind for
the transaction-pinned NOW()).
