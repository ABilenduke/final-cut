# Admin v2 — Audit Remediation & Content CMS

Phased follow-up to Admin v1 (Plans 01–09), driven by the 2026-06-09 admin-panel audit.
Approved scope decisions: (1) bookings/scheduling fixes before CMS, (2) full content CMS
(home page + blog/FAQ/careers/packages/contact), (3) admin refunds issue **real Stripe refunds**.

Progress journal: [`docs/progress/admin-v2.md`](../../../progress/admin-v2.md).

## Phase 1 — Bookings, scheduling, ops platform

| Step | Plan doc | Summary |
| ---- | -------- | ------- |
| 1.1 | [01-booking-refund-service.md](01-booking-refund-service.md) | `BookingRefundService`: Stripe refund + gift-card restore + loyalty clawback + seat release |
| 1.2 ✅ | [02](02-refund-notifications.md) | Refund + booking-confirmation notifications via dispatch outbox |
| 1.3 ✅ | [03](03-booking-admin-actions.md) | BookingResource cancel/refund/resend/flag actions; real refunds in CancellationFollowupQueue |
| 1.4 ✅ | [04](04-showtime-occupancy.md) | Per-showtime seat-occupancy map |
| 1.5 ✅ | [05](05-walkup-bookings.md) | Walk-up / POS booking creation |
| 1.6 ✅ | [06](06-copy-week.md) | Copy-week scheduling tool |
| 1.7 ✅ | [07](07-dashboard-widgets.md) | Dashboard KPI widgets |
| 1.8 ✅ | [08](08-outbox-resource.md) | Dispatch-outbox ops surface (visibility + retry/un-park) |
| 1.9 ✅ | [09](09-admin-users.md) | AdminUserResource (staff management) |
| 1.10 ✅ | [10](10-inquiry-inboxes.md) | RentalInquiryResource + ContactSubmission persistence/resource |

## Phase 2 — Content CMS

| Step | Plan doc | Summary |
| ---- | -------- | ------- |
| 2.1 ✅ | [11](11-ticker-cms.md) | CMS pilot: Neural Ticker (model → resource → cached API → frontend) |
| 2.2 ✅ | 12 (journal only) | Blog (replaces `frontend/app/data/blog.ts`) |
| 2.3 ✅ | 13 (journal only) | FAQ + Careers (prerender → ISR flips) |
| 2.4 ✅ | 14 (journal only) | Private-screening packages + contact/hours from Location data |
| 2.5 ✅ | [15](15-site-settings.md) | Site settings store + home membership pitch |
| 2.6 ✅ | [16](16-home-curation.md) | Home hero slots (real showtimes) + featured-movie / menu-item curation flags |

## Sequencing

1.1 → 1.2 → 1.3 strict; 1.4 → 1.5 strict; 1.6–1.10 independent; 1.10 before 2.4.
Phase 2: 2.1 first (establishes the CMS vertical template), then 2.2/2.3/2.4 independent, 2.5/2.6 last.

## Audit corrections (verified 2026-06-09)

Claims from older plan docs that are NO LONGER true — do not re-implement:
- `outbox:dispatch` worker + `bookings:expire-held` sweeper shipped (`routes/console.php`).
- ShowtimeService admin-edit vs checkout TOCTOU race fixed in commit `2ee19a6` (lock + regression test).
- Promo `per_user_limit` enforcement shipped (PR #63).
- FeaturedSlide publish action is permission-guarded via `marketing.featured_slides.update`.
