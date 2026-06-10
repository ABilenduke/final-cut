# Admin v2 Progress Journal

Execution journal for [`docs/plans/admin/v2/`](../plans/admin/v2/00-index.md). One step per
loop iteration; each step lands as its own PR-sized branch.

<!-- NOTE: this file accrues entries on parallel branches. On merge conflicts keep ALL step sections - they are disjoint. -->

## Step 2.4: Screening packages + contact/hours
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] `ScreeningPackage` vertical (cents `starting_price` — the live PackageCard
  contract uses `startingPrice` in cents, not the plan's "price_label" — reality wins;
  features as a reorderable simple Repeater) + the contact page now renders venue
  hours/phone/email/map/LocalBusiness JSON-LD from `usePublicLocations` (per-venue hours
  sections when more than one location). Suites: **backend 1243, frontend 929**. PHPStan +
  Pint clean. Dev DB seeded.

### Decisions
- [2026-06-10] Contact/hours needed NO backend work — `LocationResource` already exposed
  hours/phone/email/structured address (Plan 05 + the content-curation feature). Only the
  page changed; directions/parking/accessibility prose stays editorial copy.
- [2026-06-10] `/contact` and `/private-screenings` flipped prerender → `isr: 1800`.
- [2026-06-10] Hours render from the `{day: {open, close} | null}` JSON with closed-day
  handling; `DAY_LABELS` typed against `WeekdayKey` so the index is type-safe.

### Files Changed
- Backend: screening_packages migration, model/factory/observer, controller, Filament
  resource (+3 pages), routes/registrations, `content.packages.*`, seeder (4 legacy
  packages), ScreeningPackageTest (4)
- Frontend: useScreeningPackages, private-screenings.vue + contact.vue rewritten,
  route-rule flips, contact-private-screenings.test.ts (5)

## Step 2.3: FAQ + Careers CMS
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Two CMS verticals in one step (grouped per plan): `FaqItem` (free-string
  category, grouped server-side into the static-era `[{category, items}]` contract) and
  `JobOpening` (display-string employment type; careers JSON-LD now computed from API
  data). Suites: **backend 1239, frontend 924**. PHPStan + Pint clean. Dev DB seeded.

### Decisions
- [2026-06-10] **Ground-truth correction**: the audit said "19 Q&A pairs" — the real count
  is 18 (the 19th `question:` match was the TS interface line). Seeder generated from the
  TS file (5 categories / 18 items, byte-identical).
- [2026-06-10] `/faq` and `/careers` flipped prerender → `isr: 1800` in nuxt.config.ts —
  they now depend on the API at runtime. NO architecture test pinned these rules (the plan
  expected updates to site-manifest/footer-routes tests; neither pins rendering modes).
- [2026-06-10] Careers benefits/intro/mailto stay editorial page copy — only openings are
  data. FAQ categories are free strings with a datalist suggesting existing names, so
  editors can add sections without code changes.

### Files Changed
- Backend: 2 migrations, FaqItem + JobOpening (models/factories/observers), FaqController
  (grouped) + JobOpeningController, 2 Filament resources (+6 pages, Content group),
  routes, registrations, `content.faq.*` + `content.careers.*` permissions, 2 seeders,
  FaqCareersTest (5)
- Frontend: types (faq, job-opening), useFaq + useJobOpenings, faq.vue + careers.vue
  rewritten to the API (reactive JSON-LD), `data/faq.ts` DELETED, route-rule flips,
  static-pages.test.ts updated + FAQ block added, useFaqCareers.test.ts (2)

## Step 2.2: Blog CMS
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] Replaced `frontend/app/data/blog.ts` (earmarked for this in CLAUDE.md since
  v1) with the full admin vertical, cloning the 2.1 template. Suites: **backend 1234,
  frontend 921**. PHPStan + Pint clean. Dev DB migrated + seeded.

### Decisions
- [2026-06-10] `body` stays PLAIN TEXT with blank-line paragraph breaks — the public page
  has always rendered split-on-`\n\n`; no markdown engine introduced.
- [2026-06-10] API keeps the static-era field contract (`date` = published ISO date,
  `author`); list omits `body`, detail includes it and 404s drafts (scheduling enforced
  server-side, not just hidden).
- [2026-06-10] Slug auto-suggests from the title ONLY on create — editing a published
  post's title never silently moves its URL.
- [2026-06-10] The sitemap source (`server/api/__sitemap__/urls.get.ts`) gained a fourth
  parallel fetch for `/api/blog-posts` — it previously imported the static file directly.
  Per-post sitemap entries are therefore now LIVE-data driven (the static-era tests pinned
  this; they now pin the API path).
- [2026-06-10] `BlogPostSeeder` was GENERATED from the TS data file (script-parsed) so the
  three legacy posts carry over byte-identical — no transcription drift.
- [2026-06-10] Page tests mock `~/utils/api` with path-keyed fixtures (the composable-test
  idiom extended to `mountSuspended` pages — first precedent for API-driven page tests).

### Files Changed
- Backend: `blog_posts` migration, `BlogPost` model+factory+observer, cached
  `BlogPostController` (index+show), `Http/Resources/BlogPostResource`, Filament
  `BlogPostResource` (+3 pages, Content group), routes, observer/cache-key registrations,
  `content.blog.*` permissions, `BlogPostSeeder`, `BlogPostResourceTest` (6)
- Frontend: `types/blog-post.ts`, `useBlogPosts.ts`, `pages/blog/{index,[slug]}.vue`
  rewritten to the API, `BlogPostCard` type import, sitemap source 4th fetch,
  `data/blog.ts` DELETED, `useBlogPosts.test.ts` (3) + `blog.test.ts` rewritten (8) +
  sitemap test updated
- `docs/plans/admin/v2/12-blog-cms.md` — implicit in this entry (spec folded here)

## Step 2.1: Neural Ticker CMS pilot (Phase 2 opener)
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] First full-stack CMS vertical — the template every Phase-2 step copies:
  model → observer-bumped versioned cache → Filament resource → public API → composable →
  layout wiring with hardcoded fallback. TDD both sides: 5 backend + 5 frontend tests
  first. Full suites: **backend 1228 passed, frontend 919 passed** (5 pre-existing skips).
  PHPStan + Pint clean. Dev DB migrated + ticker/permission seeders applied.

### Decisions
- [2026-06-10] Field shape is `{label, text, href?}` matching the live NeuralTicker
  contract (the plan index said `message` — reality won). Publish/window semantics cloned
  from FeaturedSlide verbatim (`scopeActive`, `displayStatus`).
- [2026-06-10] The fallback rule lives in a PURE function (`resolveTickerItems`) exported
  from the composable so the layout stays dumb and the rule is unit-testable: API items
  when non-empty, the hardcoded brand items otherwise — the ticker never renders empty,
  even with an unreachable API on ISR pages.
- [2026-06-10] `marketing.ticker.*` granted to admin + manager (mirrors featured_slides).
- [2026-06-10] Testing gotcha (same family as callAction data): Filament v5 `fillForm()`
  doesn't bind on page tests either — use `->set('data.field', …)`.

### Files Changed
- `backend/database/migrations/2026_06_10_010000_create_ticker_items_table.php` — new
- `backend/app/Models/TickerItem.php` + factory + `TickerItemObserver` — new
- `backend/app/Http/Controllers/Api/TickerItemController.php` + `Http/Resources/TickerItemResource.php` — new
- `backend/app/Filament/Resources/TickerItemResource.php` (+ 3 pages) — new
- `backend/routes/api.php` — GET /api/ticker-items
- `backend/app/Providers/AppServiceProvider.php` — observer registration
- `backend/app/Console/Commands/RefreshContentCacheVersions.php` — ticker version key
- `backend/database/seeders/TickerItemSeeder.php` — new (9 legacy items); DatabaseSeeder wired
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — marketing.ticker.*
- `frontend/app/types/ticker-item.ts`, `app/composables/useTickerItems.ts` — new
- `frontend/app/layouts/default.vue` — API-driven ticker with brand fallback
- `backend/tests/.../TickerItemResourceTest.php` (5), `frontend/tests/composables/useTickerItems.test.ts` (5) — new

## Step 1.10: Rental-inquiry + contact-message inboxes
**Status:** ✅ Complete — **PHASE 1 COMPLETE** (all ten bookings/scheduling/ops steps done)
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] TDD: 6 tests first, then the two inboxes. Full backend suite: **1223
  passed**. PHPStan + Pint clean.

### Decisions
- [2026-06-10] `ContactSubmission` is a NEW table (new migration — additive by nature);
  `ContactController::store` now persists while keeping its log line for ops grep parity.
- [2026-06-10] Rental status transitions use one explicit map
  (`RentalInquiryService::allowedTransitions`) consumed by BOTH the service guard and the
  Filament action's options — the UI cannot offer an illegal move; confirmed/declined are
  terminal.
- [2026-06-10] Permissions: admin + manager get all four (`rentals.*`, `contact.*`); ops
  gets the two views only. (First pass missed the role lists — only the master list — which
  the permission tests caught immediately; worth remembering the seeder has three lists.)

### Files Changed
- `backend/database/migrations/2026_06_10_000000_create_contact_submissions_table.php` — new
- `backend/app/Models/ContactSubmission.php` + factory — new
- `backend/app/Models/RentalInquiry.php` — property docblock (PHPStan enum-cast visibility)
- `backend/app/Services/RentalInquiryService.php`, `ContactSubmissionService.php` — new
- `backend/app/Exceptions/InquiryTransitionException.php`, `ContactSubmissionException.php` — new
- `backend/app/Filament/Resources/RentalInquiryResource.php` (+ pages) — new
- `backend/app/Filament/Resources/ContactSubmissionResource.php` (+ pages) — new
- `backend/app/Http/Controllers/Api/ContactController.php` — persists submissions
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — rentals.*, contact.*
- `backend/tests/Feature/Admin/Resources/InquiryInboxesTest.php` — new: 6 tests
- `docs/plans/admin/v2/10-inquiry-inboxes.md` — new: Step 1.10 spec

## Step 1.9: AdminUserResource (staff management)
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] TDD: 6 tests first, then `AdminUserService` + read-only `AdminUserResource`
  (System group) with provision / change-role / disable / enable actions. Full backend
  suite: **1217 passed**. PHPStan + Pint clean.

### Decisions
- [2026-06-10] **No migration needed** — the plan expected to add a deactivation column,
  but `admin_profiles.disabled_at` has existed since v1 and is already enforced by
  `User::isAdmin()` + `AdminUserProvider` (live sessions die on the next request). The
  resource only drives the existing flag; the provider rejection is asserted in tests.
- [2026-06-10] Rows are read-only: identity belongs to the shared customer account.
  Provisioning mirrors `admin:create-user` create-or-promote semantics (promotion never
  clobbers the customer password unless one is supplied); roles via `syncRoles` (replace).
- [2026-06-10] Self-guards in the SERVICE (not just UI visibility): you cannot change your
  own role or disable yourself — lockout safety + privilege changes need a second admin.
- [2026-06-10] Admins are disabled, never deleted — audit-trail integrity.

### Files Changed
- `backend/app/Services/AdminUserService.php` — new
- `backend/app/Exceptions/AdminUserException.php` — new
- `backend/app/Filament/Resources/AdminUserResource.php` (+ ListAdminUsers page) — new
- `backend/tests/Feature/Admin/Resources/AdminUserResourceTest.php` — new: 6 tests
- `docs/plans/admin/v2/09-admin-users.md` — new: Step 1.9 spec

## Step 1.8: Dispatch-outbox ops surface
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] TDD: 4 tests first, then `OutboxRetryService` + read-only
  `DispatchOutboxResource` (System group, parked-count nav badge, status filter, payload
  view page, Retry action on parked rows). Full backend suite: **1211 passed**. PHPStan +
  Pint clean.

### Decisions
- [2026-06-10] **Admin-role only** (`outbox.view`/`outbox.retry` added to the master
  PERMISSIONS list but to neither the manager nor ops lists): payloads expose customer
  emails/ids and retrying is an ops-level call.
- [2026-06-10] Retry = reset to a fresh retry budget (`attempts = 0`, clear
  `failed_at`/`last_error`, `available_at = now()`) — the worker's `dispatchable()` scope
  is untouched, so the next minute-tick re-attempts naturally. Only parked rows qualify
  (`OutboxRetryException` otherwise); the end-to-end test proves a retried row flows
  through `outbox:dispatch` to its mapped job.

### Files Changed
- `backend/app/Services/OutboxRetryService.php` — new
- `backend/app/Exceptions/OutboxRetryException.php` — new
- `backend/app/Filament/Resources/DispatchOutboxResource.php` (+ List/View pages) — new
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — outbox.view, outbox.retry
- `backend/tests/Feature/Admin/Resources/DispatchOutboxResourceTest.php` — new: 4 tests
- `docs/plans/admin/v2/08-outbox-resource.md` — new: Step 1.8 spec

## Step 1.5: Walk-up / POS booking creation
**Status:** ✅ Complete
**Started:** 2026-06-10
**Completed:** 2026-06-10

### Work Done
- [2026-06-10] TDD: 9 tests first (service + page), then implementation. Full backend suite:
  **1207 passed**. PHPStan + Pint clean. Built on merged main (all 6 prior PRs landed,
  including #66's review-driven refund idempotency keys and two CI-caught fixes: the
  transaction-pinned-NOW outbox test flake and the blank `DEFAULT_LOCATION_TIMEZONE` crash).

### Decisions
- [2026-06-10] Walk-up bookings go **straight to Confirmed** — no Held phase: there is no
  Stripe wait, so the showtime lock is held for the entire short transaction (same lock the
  customer checkout takes in Phase A; the occupancy guard remains the TOCTOU backstop).
- [2026-06-10] `PaymentMethod` gains `Cash` / `Comp` / `PosCard` + `posMethods()`; the
  service refuses non-POS methods. **Comp records the seat value as `discount` with
  `total = 0`** so the dashboard revenue KPI (sum of `total`) stays honest.
- [2026-06-10] Seat-state derivation extracted to `ShowtimeOccupancy::seatStatesFor()`
  (now carrying section-multiplied per-seat prices) so the occupancy map and the walk-up
  picker can never disagree about takeable seats.
- [2026-06-10] v1 scope guard: seats only — no food, promo, gift cards, or Stripe Terminal.
- [2026-06-10] Wire-contract note: `cash`/`comp`/`pos_card` values can reach the customer
  lookup payload for walk-up bookings; the TS `Booking.paymentMethod` union is doc-level
  and tolerant, but worth aligning when the frontend types are next touched.

### Files Changed
- `backend/app/Services/WalkUpBookingService.php` — new
- `backend/app/Exceptions/WalkUpBookingException.php` — new
- `backend/app/Enums/PaymentMethod.php` — POS cases + posMethods()
- `backend/app/Filament/Resources/BookingResource/Pages/CreateWalkUpBooking.php` — new page
- `backend/resources/views/filament/resources/booking-resource/pages/create-walk-up-booking.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ShowtimeOccupancy.php` — seatStatesFor() extraction
- `backend/app/Filament/Resources/BookingResource.php` — walkup route
- `backend/app/Filament/Resources/BookingResource/Pages/ListBookings.php` — Walk-up sale action
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — bookings.create_walkup
- `backend/tests/Feature/Admin/Services/WalkUpBookingServiceTest.php` — new: 5 tests
- `backend/tests/Feature/Admin/Pages/CreateWalkUpBookingTest.php` — new: 4 tests
- `docs/plans/admin/v2/05-walkup-bookings.md` — new: Step 1.5 spec

## Step 1.7: Dashboard KPI widgets
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 6 tests first (`DashboardWidgetsTest`), then the three widgets + provider
  change. Full backend suite: **1130 passed**. PHPStan + Pint clean. First widgets in the
  panel — conventions per `.claude/skills/finalcut-admin-design/references/widget-patterns.md`.

### Decisions
- [2026-06-09] Plan-index deviation: `FlaggedBookingsWidget` + `OutboxHealthWidget` folded
  into one `OpsHealthWidget` StatsOverview (three related attention stats beat two
  near-empty cards; skill guidance caps the dashboard at five widgets).
- [2026-06-09] "Today" = the venue day — bounds computed in
  `config('app.default_location_timezone')` (fallback app tz), converted to UTC
  (`TodayKpisWidget::venueDayBounds()`, shared by the occupancy table).
- [2026-06-09] Occupancy via an `addSelect` correlated subquery on
  `booking_seats.occupies_seat` — keeps this branch independent of #69 (which adds the
  `Showtime::bookingSeats()` relation); same source-of-truth flag, different code path.
  Refunds-today stat deferred until #66 lands (`refunded_at` doesn't exist on main yet).
- [2026-06-09] Metric computations exposed as public static `metrics()` so tests assert
  numbers, not markup.

### Files Changed
- `backend/app/Filament/Widgets/TodayKpisWidget.php` — new
- `backend/app/Filament/Widgets/OpsHealthWidget.php` — new
- `backend/app/Filament/Widgets/TodayShowtimesOccupancyWidget.php` — new
- `backend/app/Providers/Filament/AdminPanelProvider.php` — drop FilamentInfoWidget
- `backend/tests/Feature/Admin/Widgets/DashboardWidgetsTest.php` — new: 6 tests
- `docs/plans/admin/v2/07-dashboard-widgets.md` — new: Step 1.7 spec

## Step 1.6: Copy-week scheduling tool
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 8 tests first (`CopyWeekShowtimesTest` — service plan/write + page), then
  implementation. Full backend suite: **1132 passed**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-copy-week` off main, independent of all open PRs.

### Decisions
- [2026-06-09] Wall-clock shift via tz-aware `addDays()` (Carbon preserves the local clock
  across DST — verified by a test straddling US spring-forward 2027: the 19:00 EST show lands
  at 19:00 EDT, a UTC delta of 7d−1h). Week window `[source 00:00, +7d)` is interpreted in
  the app timezone (documented on the method); venue-local week boundaries deferred as a
  non-issue for current US-only locations.
- [2026-06-09] Plan-doc deviation: conflicts are auto-skipped with a per-row report (the
  BulkCreateShowtimes policy) instead of literal per-row include checkboxes — a conflicting
  row can never be force-included anyway (the EXCLUDE constraint would reject the batch).
- [2026-06-09] `copyWeek()` reuses `EVENT_CREATED` with `via: copy_week` properties (matches
  bulk's `via: bulk`); end times recomputed at copy time so runtime/cleanup changes since the
  source week propagate.

### Files Changed
- `backend/app/Services/ShowtimeService.php` — `buildWeekCopyPlan()` + `copyWeek()`
- `backend/app/Filament/Resources/ShowtimeResource/Pages/CopyWeekShowtimes.php` — new page
- `backend/resources/views/filament/resources/showtime-resource/pages/copy-week.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource.php` — copy_week route
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ListShowtimes.php` — header action
- `backend/tests/Feature/Admin/Pages/CopyWeekShowtimesTest.php` — new: 8 tests
- `docs/plans/admin/v2/06-copy-week.md` — new: Step 1.6 spec

## Step 1.4: Per-showtime seat-occupancy map
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 7 tests first (`ShowtimeOccupancyTest`), then implementation. Full backend
  suite on this branch: **1131 passed** (main baseline 1124 + 7). PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-showtime-occupancy` is **off main** (independent of the
  refund stack #66→#68) so review of the stack doesn't block ops features.

### Decisions
- [2026-06-09] Dedicated custom resource page (`/{record}/occupancy`) following the
  `VisualEditor` precedent rather than embedding a grid in `ViewShowtime`'s schema — state in
  public Livewire properties (`$seatStates`, `$counts`) so tests `assertSet` data instead of
  scraping markup. Step 1.5's walk-up seat picker will grow from this grid.
- [2026-06-09] Seat state derives from `booking_seats.occupies_seat` (the occupancy guard) —
  the map can never disagree with checkout/refund logic. Sold/held/refund-pending are split
  visually: sold = `#550000` fill per the token-mapping rule, held = steel, pending = gold.
- [2026-06-09] List column `occupied / capacity` uses a filtered
  `withCount(['bookingSeats as occupied_seats_count'])` on `getEloquentQuery()` (new
  `Showtime::bookingSeats()` hasMany over the denormalized `booking_seats.showtime_id`).

### Files Changed
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ShowtimeOccupancy.php` — new page
- `backend/resources/views/filament/resources/showtime-resource/pages/occupancy.blade.php` — new
- `backend/app/Filament/Resources/ShowtimeResource.php` — occupancy route + column + withCount
- `backend/app/Filament/Resources/ShowtimeResource/Pages/ViewShowtime.php` — occupancy_map action
- `backend/app/Models/Showtime.php` — bookingSeats() relation
- `backend/tests/Feature/Admin/Pages/ShowtimeOccupancyTest.php` — new: 7 tests
- `docs/plans/admin/v2/04-showtime-occupancy.md` — new: Step 1.4 spec

## Step 1.3: BookingResource actions + real refunds in CancellationFollowupQueue
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 12 new tests (`BookingResourceActionsTest`) + 3 added to
  `CancellationFollowupQueueTest`; two existing queue tests updated (their fixtures now need
  `stripe_payment_intent_id => null` because mark_resolved is manual-only). Full backend
  suite green: **1173 passed / 4343 assertions**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-booking-admin-actions` stacked on Step 1.2's branch.

### Decisions
- [2026-06-09] Action factories live as statics on `BookingResource`
  (`refundAction()` etc.) per the `UserResource::adjustPointsAction()` precedent;
  `ViewBooking::getHeaderActions()` consumes them. `refundSplitSummary()` is shared between
  the view-page modal and the queue's issue_refund modal.
- [2026-06-09] Reserved `showtime_cancelled:` flag prefix rejected at BOTH the form layer
  (`not_regex` rule, immediate field error) and the service layer (`BookingFlagException`,
  defense in depth for non-UI callers).
- [2026-06-09] `mark_resolved` survives ONLY for rows with no PaymentIntent and no gift-card
  redemption (`hasProgrammaticRefund()` gate) — everything else must use `issue_refund`.
- [2026-06-09] New permissions `bookings.flag` + `bookings.resend_confirmation` seeded for
  admin + manager; ops stays read-only. `RoleSeederTest` derives from the seeder constants,
  so no separate matrix update needed.

### Blockers (testing gotchas, resolved)
- [2026-06-09] `callAction('x', data: [...])` does NOT bind modal-form data in this Filament
  version — use the established `mountAction → set('mountedActions.0.data.…') → callMountedAction`
  idiom (mirrors the existing queue test).
- [2026-06-09] `assertHasActionErrors()` auto-prefixes `mountedActions.0.data.` — pass bare
  field keys.
- [2026-06-09] Per-record table-action visibility assertions on a single Livewire instance can
  reuse a cached evaluation — use a fresh `Livewire::test()` per record.

### Files Changed
- `backend/app/Filament/Resources/BookingResource.php` — 4 action factories + refundSplitSummary
- `backend/app/Filament/Resources/BookingResource/Pages/ViewBooking.php` — header actions wired
- `backend/app/Filament/Pages/CancellationFollowupQueue.php` — issue_refund action;
  mark_resolved gated to manual-only rows; docblock updated
- `backend/app/Services/BookingFlagService.php` — new: flag/unflag with row locks + activity
- `backend/app/Exceptions/BookingFlagException.php` — new
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` — bookings.flag,
  bookings.resend_confirmation (admin + manager)
- `backend/tests/Feature/Admin/Resources/BookingResourceActionsTest.php` — new: 12 tests
- `backend/tests/Feature/Admin/Pages/CancellationFollowupQueueTest.php` — +3 tests, fixtures
- `docs/plans/admin/v2/03-booking-admin-actions.md` — new: Step 1.3 spec

## Step 1.2: Refund + confirmation notifications via outbox
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] TDD: 18 new tests (outbox round-trips, dispatcher arms, jobs with Mail::fake,
  mailable rendering, resend service) + 2 added to `BookingRefundServiceTest`. Full backend
  suite green: **1160 passed / 4252 assertions**. PHPStan + Pint clean.
- [2026-06-09] Branch `feat/admin-v2-refund-notifications` stacked on Step 1.1's branch
  (PR targets it — the refund service must never emit an event type the dispatcher can't map).

### Decisions
- [2026-06-09] `booking.refunded` outbox row written ONLY for Refunded targets — a
  Held→Cancelled release moved no money and the customer never finished checkout, so no email.
- [2026-06-09] Refund amounts ride in the outbox payload (not re-derived by the job) so the
  email always states what the refund actually moved.
- [2026-06-09] `BookingConfirmationMail` is the FIRST booking-confirmation email in the
  system (customers previously got only Stripe's hosted receipt). Auto-sending it from the
  customer checkout flow is deliberately out of scope here (no scope creep) — flagged as a
  candidate follow-up step for the backlog.
- [2026-06-09] `resendConfirmation` validates Confirmed status + recipient up front and throws
  `BookingNotResendableException` so Filament (Step 1.3) gets immediate feedback instead of a
  silently no-oping queued job.

### Blockers
- [2026-06-09] Pint PostToolUse hook strips imports added before their usages exist (two-edit
  sequences) — bit twice (`BookingRefundService`, `OutboxDispatcher`); symptom is
  `Class "App\Services\DispatchOutbox" not found` style errors or outbox rows cycling as
  "retryable failures". Fix: re-add imports after the usage edit. (Known gotcha, reconfirmed.)

### Files Changed
- `backend/app/Services/BookingNotificationService.php` — new: resendConfirmation
- `backend/app/Services/BookingRefundService.php` — Phase C writes booking.refunded outbox row
- `backend/app/Outbox/OutboxDispatcher.php` — two new match arms
- `backend/app/Jobs/SendBookingRefundConfirmation.php`, `SendBookingConfirmation.php` — new
- `backend/app/Mail/BookingRefundedMail.php`, `BookingConfirmationMail.php` — new
- `backend/resources/views/mail/booking-refunded.blade.php`, `booking-confirmation.blade.php` — new
- `backend/app/Exceptions/BookingNotResendableException.php` — new
- `backend/tests/Feature/Outbox/BookingNotificationOutboxTest.php` — new: 9 tests
- `backend/tests/Feature/Admin/Services/BookingNotificationServiceTest.php` — new: 7 tests
- `backend/tests/Feature/Admin/Services/BookingRefundServiceTest.php` — +2 outbox tests
- `docs/plans/admin/v2/02-refund-notifications.md` — new: Step 1.2 spec

## Step 1.1: BookingRefundService
**Status:** ✅ Complete
**Started:** 2026-06-09
**Completed:** 2026-06-09

### Work Done
- [2026-06-09] Audit complete (3 explore agents + verification); plan approved; branch
  `feat/admin-v2-booking-refund-service` created; plan docs written.
- [2026-06-09] TDD: 18 failing tests written first (`BookingRefundServiceTest`), then the
  service + supporting changes. Full backend suite green: **1142 passed / 4207 assertions**.
- [2026-06-09] NOTE: the bookings migration was edited in place (4 new columns) — dev
  databases need `make fresh` (or a manual ALTER) after pulling this branch.

### Decisions
- [2026-06-09] Refund claim via `refund_initiated_at` timestamp (not a status) so concurrent
  admin refunds are excluded without disturbing the seat-occupancy status machine; 15-min
  stale-claim retake self-heals crashed runs (mirrors `bookings:expire-held` philosophy).
- [2026-06-09] Held bookings refund to **Cancelled** (no money ever captured on a Held row);
  Confirmed/RefundPending refund to **Refunded**. Seat release is owned entirely by the
  `booking_seats` occupancy trigger — the service only flips booking status.
- [2026-06-09] Voided gift cards are NOT restored (balance was already zeroed by the void);
  skipped cards surface in activity-log properties + a warning log for manual follow-up.
- [2026-06-09] Customer-side `getHistory()` only derives from Confirmed bookings, so a refunded
  booking's earn line disappears while the explicit clawback adjustment keeps the balance true.
  Net balance is correct; history presentation of clawbacks is a known v1 display quirk.

### Decisions (testing)
- [2026-06-09] `refundTransactionLevels` assertions compare against a captured
  `DB::transactionLevel()` baseline (RefreshDatabase wraps tests in a transaction, so the
  "no transaction" level inside a test is 1, not 0 — same idiom as
  `BookingStripeOutsideTransactionTest`).
- [2026-06-09] `LoyaltyAdjustment` self-logs an activity row via Spatie `LogsActivity` on
  every create regardless of actor — null-actor tests assert the absence of the
  *service-level* `booking.refunded` event, not zero activity rows.

### Blockers
- none

### Files Changed
- `docs/plans/admin/v2/00-index.md` — new: v2 plan index
- `docs/plans/admin/v2/01-booking-refund-service.md` — new: Step 1.1 spec
- `backend/app/Services/BookingRefundService.php` — new: Phase A/B/C refund service
- `backend/app/Exceptions/BookingNotRefundableException.php` — new
- `backend/app/Enums/GiftCardLedgerType.php` — new `Refund` case
- `backend/app/Models/Booking.php` — refund columns: fillable/casts/hidden/docblock
- `backend/app/Services/StripeService.php` — optional partial `amount` on `refundPaymentIntent`
- `backend/database/migrations/2026_04_04_200009_create_bookings_table.php` — in-place:
  `stripe_refund_id`, `refund_initiated_at`, `refunded_at`, `cancelled_at`
- `backend/tests/Helpers/FakeStripeService.php` — `shouldFailRefund()`, refund amounts,
  `refundTransactionLevels`
- `backend/tests/Feature/Admin/Services/BookingRefundServiceTest.php` — new: 18 tests
