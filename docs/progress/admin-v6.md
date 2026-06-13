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

## Step 3: Scheduling ops — S2/S6 verified already-resolved
**Status:** ✅ Complete (no code change needed)
**Completed:** 2026-06-13

### Findings (verify-before-acting closed both)
- [2026-06-13] **S6 (silent skip of missing-runtime movies in bulk-create): already handled.** Single-movie bulk-create **blocks loudly** with a danger notification when the movie has no runtime (`BulkCreateShowtimes` ~L193); copy-week tracks and displays a `skipped_missing_runtime` count in its preview + confirmation (`CopyWeekShowtimes` L225–284). The audit's line reference was stale.
- [2026-06-13] **S2 (no live conflict feedback on edit): premise inaccurate.** Both Create and Edit run `ShowtimeResource::validateAgainstConflicts()` at **submit** time through the same shared form; the only `->live()` element is the `computed_end_time` placeholder, which both pages share. There is no create-vs-edit asymmetry to fix.
- Remaining scheduling gaps (S1 recurring series, S3 bulk pricing, S4 templates, S5 drag-drop, S7 section closure) are all **large features**, each warranting its own plan + iteration — not quick wins. Deferred.

## Step 4: CMS completion (G1–G6)
**Status:** 🔲 Not Started — the next high-value sprint (header/footer nav, terms/privacy, accessibility statement, careers benefits, private-screenings editorial, contact directions). Reuses the `SiteSettings` keyed-store + versioned-cache pattern.
