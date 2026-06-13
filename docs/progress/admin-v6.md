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
**Status:** 🔲 Not Started

## Step 3: Scheduling ops (S2, S6)
**Status:** 🔲 Not Started

## Step 4: CMS completion (G1–G6)
**Status:** 🔲 Not Started
