# Admin v6 — Progress Journal

**Spec / source of truth:** [`docs/design-review/2026-06-10-admin-cms-gap-audit.md`](../design-review/2026-06-10-admin-cms-gap-audit.md) (the gap audit). Sprint sequencing follows the audit's §5 recommendation:

1. **Polish batch** — nav-sort collisions, stale comments, docs drift (one small PR). ← *fastest, de-risks the codebase first*
2. **Bookings ops** — B2 (notes editing) + B4 (guest-email correction) first (low-risk field edits, highest support ROI), then B7 (activity timeline), then B1/B3 (need service design).
3. **Scheduling ops** — S2 (live conflict feedback on edit) + S6 (louder bulk-create skip warning), then S1 (recurring series), then S3/S4.
4. **CMS completion** — G1–G6 (SiteSettings keyed-store pattern) toward a true home-page CMS.

Each step is TDD'd (Pest), runs against the live stack (`docker compose exec -u 1000 backend php artisan test`), and is branched independently of the unmerged design-round-1 stack.

---

## Step 1: Polish batch
**Status:** ✅ Complete
**Branch:** `feat/admin-v6-polish` (off `main`)
**Started:** 2026-06-13
**Completed:** 2026-06-13

### Work Done
- [2026-06-13] Fixed `navigationSort` collisions in the **Operations** group (the only group with collisions): Booking/Location both `20`; User/Auditorium/GiftCard all `30`. Minimal-churn tie-breaks keeping each resource in its tier: Location `20→22`, GiftCard `30→32`, Auditorium `30→34` (Booking 20, User 30, Rental 40, Contact 45 unchanged; Operations pages sit at 1/10, untouched).
- [2026-06-13] New Pest guard `NavigationSortUniquenessTest` — reflects over every `App\Filament\Resources\*Resource`, groups by `navigationGroup`, asserts distinct `navigationSort` per group. Pins the fix; fails CI on any future collision. **2 passed (7 assertions).**
- [2026-06-13] Corrected the stale `GiftCardResource` docblock ("the only write action is `void`") — it has **two** write actions (`adjust_balance` + `void`).
- [2026-06-13] Documented the missing **cross-location** `GET /api/movies/:slug/showtimes` row in `DATA_MODELS.md` (the public movie-detail path, `MovieShowtimesController`, embedded per-entry `location`), and clarified the per-location variant is for the booking flow / admin.

### Decisions
- [2026-06-13] The audit also flagged `BookingController` saved-card comments and a `BookingNotificationService` "deferral" note as stale. **Verified on inspection — NOT stale:** the BookingController comments accurately describe the shipped saved-card feature (admin-v5 Plan 03 refs); no "deferred" note remains in `BookingNotificationService` (only a normal docblock). Left both untouched; the audit was imprecise there.
- [2026-06-13] Branched off `main` (not the design stack) because the polish touches only backend Filament + docs — independent of the unmerged design-round-1 frontend changes, so it merges cleanly in any order.

### Verification
- `NavigationSortUniquenessTest`: 2 passed.
- Touched-resource suites (`GiftCardResource|LocationResource|AuditoriumResource`): **50 passed (279 assertions)**.

### Files Changed
- `backend/app/Filament/Resources/{Location,GiftCard,Auditorium}Resource.php` — nav sorts (+ GiftCard docblock)
- `backend/tests/Feature/Admin/NavigationSortUniquenessTest.php` — new guard
- `docs/architecture/DATA_MODELS.md` — cross-location showtimes row

## Step 2: Bookings ops (B2 notes, B4 guest-email)
**Status:** ✅ Complete
**Branch:** `feat/admin-v6-polish` (continued)
**Started:** 2026-06-13
**Completed:** 2026-06-13

### Work Done
- [2026-06-13] New `BookingAmendmentService` (mirrors `BookingFlagService`: row-lock + `LogsAdminActivity` + actor attribution) with `updateNotes()` (B2) and `correctGuestEmail()` (B4). Notes trim→null-on-empty; email trim+lowercase normalized; guest-email guarded to guest bookings only (throws `BookingAmendmentException` for registered-user bookings — their email lives on the `User`).
- [2026-06-13] Two `BookingResource` header actions wired on `ViewBooking`: `edit_notes` (Textarea, prefilled, always available to permitted admins) and `correct_guest_email` (email TextInput, prefilled, visible only for guest bookings, confirmation + "resend afterwards" hint).
- [2026-06-13] New permissions `bookings.edit_notes` + `bookings.correct_email` seeded to **admin + manager** (NOT ops). `RoleSeederTest` derives its expectations from the seeder constants, so it stayed green without edits.
- [2026-06-13] TDD: `BookingAmendmentServiceTest` (6) + 6 new cases in `BookingResourceActionsTest` (visibility gating per role / guest-vs-registered, persistence, activity log, email validation).

### Decisions
- [2026-06-13] Notes/email edits granted to **admin + manager only**, matching the existing flag/refund/resend convention that keeps `ops` read-only on bookings. (The audit framed these as "support ROI"; if ops should write, that's a deliberate follow-up role change, not an inconsistency to slip in here.) The ops-hidden path is pinned by tests.
- [2026-06-13] Hit the documented Pint gotcha **3×** — imports added before their usage edit get stripped; re-added `TextInput`, `BookingAmendmentService`, `BookingAmendmentException` (resource) and `BookingAmendmentService` (test) after the usages landed.

### Verification
- `BookingAmendmentServiceTest`: 6 passed. `BookingResourceActionsTest`: 16 passed (10 existing + 6 new).
- **Full admin suite (`tests/Feature/Admin` + `tests/Unit/Admin`): 578 passed (2305 assertions)** — zero regressions.

### Files Changed
- `backend/app/Services/BookingAmendmentService.php`, `backend/app/Exceptions/BookingAmendmentException.php` — new
- `backend/app/Filament/Resources/BookingResource.php` (+2 action builders, imports), `.../Pages/ViewBooking.php` (registration)
- `backend/database/seeders/AdminRolesAndPermissionsSeeder.php` (+2 perms)
- `backend/tests/Feature/Admin/Services/BookingAmendmentServiceTest.php` — new; `.../Resources/BookingResourceActionsTest.php` — +6

## Step 2b: Bookings ops — B7 activity timeline
**Status:** ✅ Complete
**Completed:** 2026-06-13

### Work Done
- [2026-06-13] Added a **History** section to the `BookingResource` view page — an inline newest-first activity timeline (refunds, flags, notes, email corrections), gated by `activity.view`, with an empty-state. New `BookingResource::recentActivityFor()` matches the morph subject directly (Booking doesn't use the `LogsActivity` trait; events are written explicitly by the services), ordered by `id` desc for a stable sort across same-second events. Cohesive with B2/B4 — the trail those now write is visible without leaving the booking.
- [2026-06-13] TDD: 2 cases (newest-first ordering; the view renders the humanized event for a permitted admin). `BookingResourceActionsTest` now **18 passed (95 assertions)**.

## Step 2c: Bookings ops — B6 bulk refunds
**Status:** ✅ Complete
**Completed:** 2026-06-13

### Work Done
- [2026-06-13] `BookingResource::bulkRefundAction()` — a table `toolbarActions` `BulkAction` (gated `bookings.resolve_refund`, confirmation + required reason) that loops the selected bookings through the existing idempotent `BookingRefundService::refund()`, catching `BookingNotRefundableException` (skip) and `\Throwable` (fail) per booking so one Stripe failure never rolls back the rest, then reports a refunded/skipped/failed tally. Reuses all the proven money logic — orchestration only.
- [2026-06-13] TDD: `BookingBulkRefundTest` (4 — bulk refund several, skip already-terminal, required reason, admin-visible/ops-hidden). Full admin suite **615 passed**; Pint clean.

### Decisions / gotchas
- [2026-06-13] **Filament bulk-action-with-a-form test harness:** plain `callTableBulkAction($name, $records, $data)` did NOT drive a bulk action that has both `->schema()` and `requiresConfirmation()` — the closure never ran and `$data` never reached the form. The working pattern is the mounted flow: `->mountTableBulkAction($name, [$models])->set('mountedActions.0.data.<field>', $value)->callMountedTableBulkAction()->assertHasNoTableBulkActionErrors()`. Pass **model instances** (not raw ids) as records. (Cost me a long debug; recorded so it's a one-liner next time.)
- [2026-06-13] Bulk money op de-risked by: confirmation modal, required reason, per-booking idempotent service + try/catch, and the admin's explicit row selection bounding the blast radius.

## Step 3: Scheduling ops — S2/S6 verified already-resolved
**Status:** ✅ Complete (no code change needed)
**Completed:** 2026-06-13

### Findings (verify-before-acting closed both)
- [2026-06-13] **S6 (silent skip of missing-runtime movies in bulk-create): already handled.** Single-movie bulk-create **blocks loudly** with a danger notification when the movie has no runtime (`BulkCreateShowtimes` ~L193); copy-week tracks and displays a `skipped_missing_runtime` count in its preview + confirmation (`CopyWeekShowtimes` L225–284). The audit's line reference was stale.
- [2026-06-13] **S2 (no live conflict feedback on edit): premise inaccurate.** Both Create and Edit run `ShowtimeResource::validateAgainstConflicts()` at **submit** time through the same shared form; the only `->live()` element is the `computed_end_time` placeholder, which both pages share. There is no create-vs-edit asymmetry to fix.
- Remaining scheduling gaps (S1 recurring series, S4 templates, S5 drag-drop) are large features, each warranting its own iteration. (S7 section closure now done — see its block below.)

### S3 — bulk price updates (✅ Complete, 2026-06-13)
- `ShowtimeResource::bulkUpdatePricingAction()` — a table `toolbarActions` `BulkAction` (gated `showtimes.update`, confirmation + three required cents inputs) that sets all three seat-tier prices on the selected showtimes by looping the existing `ShowtimeService::update()`. A price-only change is non-structural, so update() skips the booking-count guard (row-locked + activity-logged). Existing bookings keep their snapshot prices (`booking_seats.price`); only future bookings use the new prices. Reuses the B6 bulk-action + mounted-flow-test pattern. TDD: `ShowtimeBulkPricingTest` (3). Full admin suite **618 passed**; Pint clean.
- `Collection` here is already `Illuminate\Support\Collection` (the B6 Eloquent-vs-Support type-hint trap didn't apply).

### S7 — temporary section closures (✅ Complete, 2026-06-13)
Whole-section open/close for maintenance (e.g. close "Premium" while a row is repaired) without flagging each seat individually. Built in two committed stages.

- **Stage 1 — service core (85d65fb):** `auditorium_sections.closed_at` nullable timestamp (booleans-as-timestamps; added **in-place** to `2026_04_23_100000_create_auditorium_sections_table.php` → `migrate:fresh --seed` reseeded dev). `AuditoriumSection::isClosed()` + cast. `AuditoriumService::closeSection()/reopenSection()` mirror `markSeatUnavailable/markSeatAvailable` (idempotent guard, `forceFill`, `logIfAdmin` `auditorium.section_closed`/`section_reopened`). **Removal-from-sale is additive at the single chokepoint:** `SeatAvailabilityService::checkAvailability` merges seats whose section is closed into the unavailable set (provably can't cause double-booking — it can only over-restrict), and `ShowtimeController` renders those seats `'taken'` (already eager-loads `auditorium.seats.section`). Tests: `SeatAvailabilityServiceTest` (+2), `AuditoriumServiceIntegrationTest` (+2).
- **Stage 2 — admin UI (db9083d):** `AuditoriumResource::manageSectionClosuresAction()` — per-record "Open / close sections" `Action` (gated `auditoriums.update`, visible only when the auditorium has sections). A `sections` Repeater (Hidden id + disabled name + `closed` Toggle) prefilled from current state; the action loops rows and calls `closeSection`/`reopenSection` **only where the desired state differs** (no spurious activity rows), with a tally notification. Tests: `AuditoriumSectionClosureActionTest` (3 — close, reopen, ops-hidden/no-sections-hidden).
- **Test-harness note:** Filament 5 unified actions — table-action mounted state lives on `mountedActions` (NOT `mountedTableActions`, which 404s with `PublicPropertyNotFoundException`). The repeater data won't take via `callTableAction(name, record, data)` (flat list = no-op on the keyed repeater); drive it with `->mountTableAction(...)->set('mountedActions.0.data.sections', ['item-0' => [...]])->callMountedTableAction()`. Auditorium regression suite **86 passed**; Pint clean.

## Step 4: CMS completion (G1–G6)
**Status:** 🟡 In Progress — G5 done; G1/G2/G3/G4/G6 remain.

### G5 — careers benefits (✅ Complete, 2026-06-13)
End-to-end vertical slice, mirroring the `site-content/home`+`/contacts` pattern:
- **Backend:** `SiteSettingsService::KEY_CAREERS_BENEFITS`; `SiteContentController::careers()` → `GET /api/site-content/careers` (versioned cache; `benefits` null until saved); `CareersContent` Filament page (Content group, `content.careers.update`-gated) with a simple `Repeater` of benefit rows, defaults mirroring the frontend list, blank-row trim/drop on save.
- **Frontend:** `useCareersContent()` + `resolveCareersBenefits()` (falls back on null/empty so the section never renders blank); `careers.vue` consumes the API, built-in list as fallback.
- **Tests:** backend `CareersContentTest` (6 — api null default, role gating, prefill-roundtrip, custom save, trim/drop, reload+cache-bust); frontend `useSiteContent.test` (+4) + `static-pages.test` (+1, admin benefits replace the built-ins). Backend admin **586 passed**; route-scoping **4 passed**; frontend **978 passed**.

### Decisions
- [2026-06-13] Simple `Repeater` (not `TagsInput`) for benefits — benefits are sentence-length, and `TagsInput` would split them on commas. Its Livewire state is a UUID-keyed `['benefit'=>…]` map; tests set that nested shape directly (`fillForm` with a flat array is a no-op on a simple repeater — verified by probe).
- [2026-06-13] Gated on `content.careers.update` (careers domain), not `content.site_settings.update`, so it sits with JobOpeningResource under the careers permission. Both admin + manager have it.

### G6 — contact "getting here" prose (✅ Complete, 2026-06-13)
- **Backend:** `KEY_CONTACT_INFO`; `SiteContentController::contactInfo()` → `GET /api/site-content/contact-info` (versioned cache, null until saved); `ContactContent` Filament page (Content group, `content.site_settings.update`-gated) with three required Textareas (By Car / By Transit / Accessibility), defaults mirroring the page, trim on save.
- **Frontend:** `ContactInfo` type + `useContactInfo()` + `resolveContactInfo()`; `contact.vue` binds the three prose blocks to the API with the built-in copy as fallback.
- **Tests:** backend `ContactContentTest` (6); frontend `useSiteContent.test` (+3) + `contact-private-screenings.test` (+1, admin prose replaces built-ins). Backend admin **596 passed**; frontend **982 passed**; route scoping passed.
- **Decision:** modelled brand-level (one SiteSettings blob), NOT per-`Location` columns as the gap audit suggested — the contact page is brand-led and defers per-venue detail to `/locations/:slug`, so a per-location migration would be the wrong shape here. Recorded as a deliberate divergence.

### G3 — private-screenings page intro (✅ Complete, 2026-06-13)
The packages themselves were already admin-managed (`ScreeningPackageResource` + `/api/screening-packages`), so G3 reduced to the page's hardcoded title + lead paragraph.
- **Backend:** `KEY_PRIVATE_SCREENINGS`; `SiteContentController::privateScreenings()` → `GET /api/site-content/private-screenings`; `PrivateScreeningsContent` Filament page (Content group, `content.site_settings.update`-gated) — title TextInput + intro Textarea, defaults mirroring the page, trim on save.
- **Frontend:** `PrivateScreeningsCopy` type + `usePrivateScreeningsCopy()` + `resolvePrivateScreeningsCopy()`; `private-screenings.vue` binds the h1 + intro with the built-in copy as fallback, and derives the SEO `<title>` reactively from the editable title (`{title} — Final Cut`).
- **Tests:** backend `PrivateScreeningsContentTest` (6); frontend `useSiteContent.test` (+3) + `contact-private-screenings.test` (+2). Backend admin **602 passed**; frontend **987 passed**; route scoping passed.

### G4 — accessibility statement (✅ Complete, 2026-06-13)
- **Backend:** `KEY_ACCESSIBILITY_STATEMENT`; `SiteContentController::accessibility()` → `GET /api/site-content/accessibility`; `AccessibilityContent` Filament page (Content group, `content.site_settings.update`-gated) — 7 required Textareas (intro + the six section paragraphs), defaults mirroring the page, trim on save.
- **Frontend:** `AccessibilityStatement` type + `useAccessibilityStatement()` + `resolveAccessibilityStatement()`; `accessibility.vue` binds the 7 paragraphs with built-in fallback. Headings, the three calendar links, and the contact block stay structural (contact email/phone already come from `SiteContacts`).
- **Tests:** backend `AccessibilityContentTest` (6); frontend `useSiteContent.test` (+3) + `static-pages.test` (+1). Backend admin **608 passed**; frontend **991 passed**; route scoping passed.

### G1 — header + footer navigation (✅ Complete, 2026-06-13)
- **Backend:** `KEY_NAVIGATION` (`{ header, footer }`); `SiteContentController::navigation()` → `GET /api/site-content/navigation` (versioned cache, null lists until saved); `NavigationContent` Filament page (Content group, `content.site_settings.update`-gated) — two label+href Repeaters, defaults mirroring the components, href scheme-guard (`/^(\/|https?:\/\/)/`) rejecting `javascript:`/`data:` at the form layer, blank/invalid rows dropped on save.
- **Frontend:** `NavItem` type + `useNavigation()` (shared key dedupes the header+footer fetch) + `resolveNavItems()` (drops malformed/unsafe items; falls back to the built-in list when null/empty/all-unsafe so the shell never renders an empty nav); `SiteHeader.vue` (desktop + mobile menu) and `SiteFooter.vue` consume their respective lists with built-in fallbacks.
- **Tests:** backend `NavigationContentTest` (7, incl. `javascript:` rejection + nested-repeater roundtrip); frontend `useSiteContent.test` resolveNavItems matrix (+8) + `SiteFooter.test` (+2). Backend admin **615 passed**; frontend **995 passed / 5 skipped** (in-container — host-side runner timed out environmentally; no test mounts the layout shell, so SiteHeader's new fetch is regression-free). Footer-routes architecture test stays green (it only matches `to="/…"` template literals, not the fallback const's object props).

### G9 — now-showing reel tag override (✅ Complete, 2026-06-13)
- Movies gain optional `home_teaser_tag` (added **in-place** to the movies create migration per the pre-launch convention — so `migrate:fresh --seed` was needed, which reseeded the **dev** DB; the test DB rebuilds from migrations per-run). Fillable + MovieResource form field + exposed as `homeTeaserTag` on the movie API. The home now-showing reel renders the curated tag (gold) over the computed New/70mm/IMAX/Select; blank/null → computed. TDD: MovieControllerTest case + `HomeNowShowingReel.test` (3). Backend Movie suite 123 passed; full frontend 998 passed/5 skipped.

### G8 — gift-cards masthead copy (✅ Complete, 2026-06-14)
The last enumerated home/site CMS gap. Same vertical slice as G3/G6, eyebrow + lede only.
- **Backend:** `KEY_GIFT_CARDS_EDITORIAL`; `SiteContentController::giftCards()` → `GET /api/site-content/gift-cards` (versioned cache, null until saved); `GiftCardsContent` Filament Content page (`content.site_settings.update`-gated) — eyebrow TextInput + lede Textarea, defaults mirror `gift-cards.vue`, trim on save. The stylized `<h1>` title stays structural (brand typography — `<em>`/styled `<span>` markup an admin shouldn't free-edit).
- **Frontend:** `GiftCardsEditorial` type + `useGiftCardsEditorial()` + `resolveGiftCardsEditorial()`; `gift-cards.vue` binds eyebrow + lede with the built-in copy as fallback so the masthead never renders blank.
- **Tests:** backend `GiftCardsContentTest` (6); frontend `useSiteContent.test` (+5). Backend GiftCardsContent **6 passed**; route-scoping + site-content regression **23 passed**; affected frontend suite **64 passed**. Commit `ee5d5f7`.
- **Process miss (recorded):** I launched `make test-frontend` in the **background** and waited passively for hours — a KNOWN hang (vitest zombie in the shared container late in a session). Recovery is `docker compose restart frontend` then re-run targeted. **Rule going forward: never passively wait on a backgrounded `make test-frontend`; run targeted foreground with an explicit `timeout`, and if a frontend run produces no output within a couple minutes, restart the container immediately rather than waiting.**

### Remaining (G2) — optional
- G2 terms/privacy: deliberately **deferred / arguably out-of-scope** — legal text is better kept version-controlled + PR-reviewed than freely editable by a manager in a CMS (compliance/audit). If pursued, do plain-text-per-section like G4 (NOT rich-text). **CMS sprint complete (G1, G3, G4, G5, G6, G8, G9 done); essentially all customer-visible editorial content is now admin-editable.**

## Step 5: Bookings — B3 seat reassignment (✅ Complete, 2026-06-13)

Money-neutral seat reassignment — the admin equivalent of a customer asking to switch seats without refund-and-rebook. Two committed stages.

- **Stage 1 — service core (30f5bf8):** `BookingAmendmentService::reassignSeats(Booking, string[] $newSeatIds, ?User $actor)` moves an active booking (confirmed/held only) to a different seat set **in the same showtime**. One transaction, **showtime locked first** (matching the customer reservation lock order so the two serialize): drop the current `booking_seats`, then re-reserve via `SeatAvailabilityService::reserveSeats` — which re-runs availability (so a partial move keeps freed seats selectable) and leans on the partial-unique occupancy index as the TOCTOU backstop. **Money-neutral invariant:** the new seats must cost exactly what the old ones did (`BookingAmendmentException::REASON_SEAT_PRICE_MISMATCH`) so the Stripe charge + booking `total` stay valid — a price delta rolls the whole thing back. A racing grab → `SeatConflictException` rollback (original seats intact); foreign-auditorium seat → `ValidationException`; non-active booking → `REASON_NOT_REASSIGNABLE`; empty set → `REASON_NO_SEATS`. New exception reason constants. Tests: `BookingSeatReassignmentTest` (8). Booking regression **314 passed**.
- **Stage 2 — admin UI (fbf6332):** `BookingResource::reassignSeatsAction()` — a `ViewBooking` header `Action` with a `CheckboxList` seat picker (`selectableSeatOptions()` = every selectable seat in the auditorium + the booking's own current seats, each labelled with price so a mismatch is self-explanatory). Catches the three service exceptions as danger notifications. New permission **`bookings.reassign_seats`** (admin + manager, not ops) added to `AdminRolesAndPermissionsSeeder::PERMISSIONS` + `MANAGER_PERMISSIONS` — `RoleSeederTest` derives expectations from the consts so it stayed green. Tests: `BookingReassignSeatsActionTest` (6). Full admin suite **637 passed**.
- **Test-harness note (recurring):** page header-action form data is driven via `->mountAction(name)->set('mountedActions.0.data.<field>', …)->callMountedAction()` — `setActionData()` did NOT populate the CheckboxList (same Filament-5 unified-actions family as the S7 section-closure repeater; `mountedActions`, not `mountedTableActions`).

### S1 (recurring schedules) — investigated, deliberately NOT built
Verify-before-acting found S1's *data-entry* value is **already shipped**: `BulkCreateShowtimes` does date-range × days-of-week × times-of-day with conflict preview — that IS "every Mon–Fri at 19:00 for the run". The only genuinely-missing piece is a **persisted series** (manage-the-run-as-a-unit: cancel/bump the whole run), a much larger schema+cascade feature. I built a `buildRecurrencePlan` service method (commit e375ef6) then **reverted it** (reset, unpushed) because (a) it duplicated `BulkCreateShowtimes::buildTuples`, and (b) it interpreted the daily time in the **venue tz**, diverging from the app's established convention — single + bulk create store the admin-entered wall-clock as-is in app tz (UTC); `formatTime` renders tz-naively. Shipping venue-tz recurrence would store "19:00" as 23:00 UTC for an ET venue while every other path stores 19:00 UTC. **Flagged finding (not acted on — cross-cutting):** the whole app assumes a single timezone (admin enters wall-clock → stored UTC → rendered tz-naive), which is wrong for genuinely multi-tz venues despite the `locations.timezone` column existing. That's a deliberate design decision for the owner, not an autonomous-loop refactor.
