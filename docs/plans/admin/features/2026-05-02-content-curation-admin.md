# Plan: Content Curation Admin (Featured Slides + Per-Location Menu Availability)

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plan 08 (MenuItemResource exists), Plan 05 (LocationResource exists), and the backend feature plan `docs/plans/backend/features/2026-05-02-cross-location-content-api.md` (Task 4 creates the `featured_slides` table)
> **Unlocks:** The home hero carousel and the cross-location food page in `docs/plans/frontend/v1/13-content-refactor.md`

## Overview

Two admin surfaces:

1. **`FeaturedSlideResource`** — Filament 3 resource for the admin-curated home page hero carousel. Editors compose slides (image, headline, sub-headline, CTA, publish window, display order). Sits under a new `Marketing` navigation group.
2. **MenuItem location-availability matrix** — extend the existing `MenuItemResource` form with an "Available at" multi-select that writes the `location_menu_item` pivot. This is the operational source of truth for which venues stock which items; the customer food page reads through the new `GET /api/food-menu` endpoint and the checkout uses the same data to dim items unavailable at a booking's venue.

A small audit ensures `LocationResource` exposes the address, hours, phone, email, and lat/lng fields needed by the public `/locations` and `/locations/:slug` pages. If the backend feature plan added an `hours` JSON column to `locations`, the `LocationResource` form gets a structured weekly hours editor.

Filament resources consume `App\Models\FeaturedSlide`, `App\Models\MenuItem`, and `App\Models\Location` directly — no admin-side model mirror, no shared package. All writes go through standard Eloquent (no dedicated service layer for these surfaces; they have no downstream invariants beyond what the model and migration enforce). Activity log writes via the existing `LogsActivity` trait.

## Reference Documents

- `docs/plans/backend/features/2026-05-02-cross-location-content-api.md` — backend table + endpoints
- `docs/plans/frontend/v1/13-content-refactor.md` — consumer plan
- `docs/architecture/CONTENT_ARCHITECTURE.md` — § 7 Featured Slides Contract, § 8 Cross-Location Menu Contract
- `docs/plans/admin/v1/03-shared-models-and-base-resources.md` — `BaseResource`, `FormatsCurrency`, `TimestampColumns` traits to reuse
- `docs/plans/admin/v1/05-locations-auditoriums-seat-editor.md` — `LocationResource` baseline
- `docs/plans/admin/v1/08-menu-promo-gift-cards.md` — `MenuItemResource` baseline

---

## Tasks

### Task 1: `FeaturedSlideResource` (Marketing navigation group)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - New: `backend/app/Filament/Resources/FeaturedSlideResource.php`
  - New: `backend/app/Filament/Resources/FeaturedSlideResource/Pages/ListFeaturedSlides.php`
  - New: `backend/app/Filament/Resources/FeaturedSlideResource/Pages/CreateFeaturedSlide.php`
  - New: `backend/app/Filament/Resources/FeaturedSlideResource/Pages/EditFeaturedSlide.php`
  - Modify: `backend/database/seeders/PermissionSeeder.php` (or equivalent) — add `marketing.featured_slides.view`, `.create`, `.update`, `.delete` permissions seeded for `admin` and `manager` roles
  - New: `backend/tests/Feature/Admin/FeaturedSlideResourceTest.php`
- **Details:**
  - Navigation group: `Marketing` (new). `navigationSort` puts FeaturedSlideResource first under it; future marketing surfaces (campaigns, promotions copy, etc.) join the same group.
  - Form fields:
    - `headline` — `TextInput`, required, max 80
    - `sub_headline` — `TextInput`, optional, max 160
    - `image_url` — `FileUpload` against `disk('public')`, image preview, accepted types `image/jpeg image/png image/webp`, max 5 MB. Stores the public URL on the model.
    - `cta_label` — `TextInput`, required, max 24
    - `cta_href` — `TextInput`, required. Helper text: "URL or internal route path (e.g. `/movies/the-brutalist`)". Validate as a URL or a path starting with `/`.
    - `display_order` — `TextInput::numeric()`, default 0
    - `starts_at` — `DateTimePicker`, optional. Helper text: "When the slide becomes visible. Leave blank for immediate."
    - `ends_at` — `DateTimePicker`, optional. Helper text: "When the slide stops being visible. Leave blank for indefinite."
    - `published_at` — hidden field set when the user clicks the "Publish" action; null = draft.
  - Table columns:
    - Image thumbnail (50×30)
    - Headline
    - Status badge — computed from `published_at`, `starts_at`, `ends_at`:
      - **Draft** (`published_at` null) — neutral
      - **Scheduled** (`published_at` set, `starts_at` in future) — info (steel)
      - **Live** (`published_at` set, in window) — success (sage)
      - **Expired** (`published_at` set, `ends_at` in past) — neutral, dimmed
    - `display_order` (sortable, drag handle for reordering)
    - `updated_at`
  - Header actions: `Create`, the standard table search and bulk-delete.
  - Row actions: `Edit`, `Publish` (sets `published_at = now()` if null), `Unpublish` (sets `published_at = null`), `Delete`.
  - Drag-to-reorder (`reorderable('display_order')`) on the table.
  - `LogsActivity` trait writes `log_name = 'admin'` activity rows for create / update / delete / publish / unpublish, with the diff captured.
  - Authorization: gated by the four `marketing.featured_slides.*` permissions; `BaseResource` already wires `can-view`, `can-create`, etc. via Spatie's permission package.
- **Acceptance Criteria:**
  - [ ] A user with `marketing.featured_slides.create` permission can create a slide via the Filament UI; one without it gets a 403
  - [ ] Status badges render correctly for draft / scheduled / live / expired states (Pest test against the column callback)
  - [ ] `Publish` action flips `published_at` to `now()` and writes an activity-log row
  - [ ] Drag-to-reorder updates `display_order` for the affected rows and persists
  - [ ] `make admin-test` passes (`tests/Feature/Admin/FeaturedSlideResourceTest.php` covers the permission matrix + happy-path CRUD)

---

### Task 2: MenuItem location-availability matrix

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - Modify: `backend/app/Filament/Resources/MenuItemResource.php` — add the "Available at" field and table column
  - Modify: `backend/tests/Feature/Admin/MenuItemResourceTest.php` — add cases for the pivot writes + validation
- **Details:**
  - Form addition: `CheckboxList::make('locations')` (Filament built-in for `belongsToMany` relations) sourced from `Location::pluck('name', 'id')`. Required, with custom validation: at least one location must be checked (`->required()` + `->minItems(1)`).
  - Helper text: "Customers will see this item on the public menu, but it will be dimmed at checkout if their booking is at a location where it isn't stocked."
  - Saving the form writes through the existing `MenuItem::locations()` `belongsToMany` (no extra glue code).
  - Table addition: an `IconColumn` "Stocked at" that shows one icon per location with tooltip; or a `BadgeColumn::make('locations_summary')` accessor returning "Both / Downtown only / Uptown only / —".
  - Activity log: the existing `LogsActivity` (or whatever the project uses on MenuItem) captures the pivot change as part of the model save event. If pivot changes don't trip activity log out of the box, hook into `saved` and write a `pivot_synced` event manually.
- **Acceptance Criteria:**
  - [ ] Creating a MenuItem with no locations checked fails validation with a clear message
  - [ ] Editing a MenuItem and changing the Available-at selection writes the new pivot rows and removes stale ones
  - [ ] Activity log captures pivot changes (Pest test asserts a row appears when the selection changes)
  - [ ] `make admin-test` passes

---

### Task 3: LocationResource — `hours` field + audit of public fields

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - Modify: `backend/app/Filament/Resources/LocationResource.php` — add the `hours` field if the backend feature plan added the column; verify all public fields are editable
- **Details:**
  - Skip if the `hours` column already exists and the resource already exposes it.
  - If `hours` was just added (per backend feature plan Task 6): add a structured form section `"Hours of Operation"` with seven `KeyValue`-style entries (one per weekday) or a custom `Forms\Components\Builder` with two `TimePicker`s per day. Empty/null = "Closed".
  - Audit checklist for the resource form: `name`, `slug`, `phone`, `email`, `street`, `city`, `state`, `postal_code`, `country`, `timezone`, `latitude`, `longitude`, and the new `hours` field — all editable.
  - The customer-facing wire contract may collapse some of these (`docs/architecture/DATA_MODELS.md` § Location-hierarchy schema notes the `LocationResource` collapses the structured address into a single string for the Nuxt contract). The new `/locations` and `/locations/:slug` pages depend on the structured fields being present in the public API response — this task verifies the backend resource exposes them.
- **Acceptance Criteria:**
  - [ ] LocationResource form lets an admin set every field listed in the audit checklist
  - [ ] Saving hours produces a JSON payload matching the contract in the backend feature plan (Task 6)
  - [ ] `make admin-test` passes

---

### Task 4: Progress journal entry

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - Modify: `docs/progress/admin-v1.md` — append a `## Plan: Content Curation Admin (2026-05-02)` section in the standard journal format, OR
  - New: `docs/progress/admin-features.md` — if the team prefers separating v1-numbered plans from feature plans (mirrors `docs/plans/admin/v1/` vs. `docs/plans/admin/features/`)
- **Details:**
  - Use the standard format — Status, Started, Completed, Work Done (dated bullets), Decisions, Blockers, Files Changed.
  - Status starts at 🔲 Not Started; flip to 🟡 In Progress when work begins; ✅ Complete when all tasks above are done and verified.
- **Acceptance Criteria:**
  - [ ] A new section exists in either `admin-v1.md` or `admin-features.md` referencing this plan file path
  - [ ] The section uses the standard fields (Status, Started, Completed, Work Done, Decisions, Blockers, Files Changed)

---

## Out of Scope

- Frontend consumption of the new endpoints — see `docs/plans/frontend/v1/13-content-refactor.md`
- Backend table creation, model, factory, seeder, public endpoint, and per-location menu pivot endpoint changes — see `docs/plans/backend/features/2026-05-02-cross-location-content-api.md`
- Admin surfaces for events `?location=` filter (the customer endpoint adds the filter; if `events` need a new `location_id` column, the customer-facing `CalendarEventResource` admin form should expose it — but that's properly handled by the existing Plan 09 admin work; this plan does not duplicate it)
