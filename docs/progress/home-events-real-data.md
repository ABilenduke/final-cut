# Progress: Home/Events real-data wiring + event seeding

Plan: `/home/abilenduke/.claude/plans/dreamy-hugging-bonbon.md`

Replaces three hardcoded home/events surfaces with real API data and enriches the calendar-event
seed so `/`, `/events`, and `/whats-on` render from seeded data. Adds a shared branded image
fallback (hashed-hue gradient + glyph) so seeded banner paths that have no on-disk binary degrade
to an intentional tile instead of a broken-image icon.

## Step 1: Backend — enrich CalendarEventSeeder
**Status:** ✅ Complete
**Started:** 2026-06-08
**Completed:** 2026-06-08

### Work Done
- [2026-06-08] Prepend flagship `special_event` "Kubrick in the grain" (days_offset 2 — earliest
  special event; next-lowest special offset is 3), add `image` key to every event, pass
  `image_path` in `CalendarEvent::create`. Image map: festival/marathon/retrospective →
  `final_cut_film_festival.webp`; loyalty/members → `membership_card_banner.webp`; kids/family →
  `gift_cards_banner.webp`; else → `final_cut_features.webp`.

### Decisions
- [2026-06-08] Reuse the four banner paths `FeaturedSlideSeeder` already references (no new asset
  names). The `.webp` binaries are not committed (provisioned via upload/CDN) — handled by the
  frontend branded fallback, per user's "Branded gradient fallback" choice.

### Files Changed
- `backend/database/seeders/CalendarEventSeeder.php` — flagship event + per-event `image_path`.

## Step 2: Frontend — shared branded fallback util + BridgeMiniPoster DRY
**Status:** ✅ Complete
**Started:** 2026-06-08
**Completed:** 2026-06-08

### Work Done
- [2026-06-08] New `frontend/app/utils/posterFallback.ts` (`hashToHue`, `initialsFrom`), lifted
  verbatim from `BridgeMiniPoster.vue`; refactored `BridgeMiniPoster.vue` to consume it.

## Step 3: Frontend — HomeFoodDrink + HomeRetrospectiveSplit self-fetch
**Status:** ✅ Complete
**Completed:** 2026-06-08

### Work Done
- [2026-06-08] `HomeFoodDrink.vue` now self-fetches `useFoodMenu().fetchAll()` and renders a
  curated trio (popcorn → specials → drinks, top-up to 3), `formatCurrency` prices, capitalized
  category meta; static `menuData` trio as the zero-state fallback.
- [2026-06-08] `HomeRetrospectiveSplit.vue` now self-fetches the next upcoming `special_event`
  (current + next month, `date >= today`, sorted), splits the title into lead + `<em>`, shows a
  "Doors HH:MM" line (SSR-safe `formatWireTime`), and layers the banner image over the glyph
  fallback. Hidden via `v-if="featured"` when no upcoming special events.
- [2026-06-08] Removed `foodItems`/`FoodItem` + `retrospectiveProgramme`/`RetrospectiveProgramme`/
  `RetrospectiveScreening` from `data/homepage.ts` (kept membership + hero showtime slots).

## Step 4: Frontend — branded @error fallback on image surfaces
**Status:** ✅ Complete
**Completed:** 2026-06-08

### Work Done
- [2026-06-08] `EventListCard.vue`, `pages/events/index.vue` hero, and `HomeFeaturedCarousel.vue`
  (per-slide) now degrade a null/404 image to a hashed-hue gradient + glyph via `@error`, instead
  of a broken-image icon. Carousel keeps its existing atmospheric treatment for `imageUrl: null`
  (brand fallback slide).

## Step 5: Tests + verification
**Status:** ✅ Complete
**Completed:** 2026-06-08

### Work Done
- [2026-06-08] New `HomeFoodDrink.test.ts` (3) + `HomeRetrospectiveSplit.test.ts` (6); updated
  `EventListCard.test.ts` placeholder test → branded-fallback (initials glyph) assertion.
- [2026-06-08] **Backend:** `php -l` + Pint clean; `make fresh` seeds Kubrick (slug
  `kubrick-in-the-grain`, earliest special, `image_path` set) + 11/11 events imaged;
  `php artisan test` → **1124 passed**.
- [2026-06-08] **Frontend:** `make test-frontend` → **914 passed, 5 skipped, 0 failed**;
  `deno task build` (CI prod build) → complete.
- [2026-06-08] **Live (dev SSR):** `/` renders real menu trio (Caramel Popcorn $8.99 / Churros
  $6.99 / Bottled Water $2.99) + "Kubrick in the grain" retro w/ working CTA; `/events` featured
  hero = Kubrick + 7 cards; `/whats-on` shows Kubrick. No leftover hardcoded copy.

### Blockers
- [2026-06-08] Frontend dev server crashed mid-edit — Vite/Deno file watcher `unhandledRejection`
  on the `.tmp.*` atomic-rename files created by rapid edits (not a code defect; tests/build pass
  in separate `docker compose run` containers). → Resolved by `docker compose restart frontend`.

### Files Changed
- `frontend/app/utils/posterFallback.ts` (new) — `hashToHue` / `initialsFrom`.
- `frontend/app/components/calendar/BridgeMiniPoster.vue` — consume the shared util.
- `frontend/app/data/homepage.ts` — drop food + retrospective exports.
- `frontend/app/components/home/HomeFoodDrink.vue` — API self-fetch + curated trio.
- `frontend/app/components/home/HomeRetrospectiveSplit.vue` — API self-fetch + image/glyph.
- `frontend/app/components/home/HomeFeaturedCarousel.vue` — per-slide `@error` branded fallback.
- `frontend/app/components/content/EventListCard.vue` — `@error` branded fallback.
- `frontend/app/pages/events/index.vue` — featured-hero `@error` branded fallback.
- `frontend/tests/components/home/HomeFoodDrink.test.ts` (new),
  `frontend/tests/components/home/HomeRetrospectiveSplit.test.ts` (new),
  `frontend/tests/components/content/EventListCard.test.ts` (updated).
- `backend/database/seeders/CalendarEventSeeder.php` — flagship Kubrick + per-event `image_path`.
