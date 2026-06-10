# Plan 16 — Home hero slots + curation flags

**Step:** 2.6 · **Status:** ✅ Complete

## Goal

Remove the last fake data from the home page (the hero's hardcoded
`placeholderShowtimeSlots`) and give admins curation control over the two
algorithmic picks: which movie leads the hero, and which menu items lead
the food teaser.

## Design

### Hero showtime slots (real data)

`HomeCinemaHero.vue` fetches the featured movie's upcoming cross-location
showtimes via the existing `GET /api/movies/{slug}/showtimes` (already
upcoming-filtered, start-time sorted, 7-day default window). The new pure
util `buildHeroSlots(showtimes, timeZone)` maps them to chips — time split
into value + meridiem via `Intl.DateTimeFormat` in the app display
timezone, capped at eight, each linking into the purchase flow
(`/purchase/{id}?loc={slug}`, the same contract as `ShowtimeSelector`).
Zero showtimes renders a quiet "being scheduled" note; the panel chrome
and calendar link survive. Panel copy goes from "Typical Programme /
Sample screening times" to "This Week / Upcoming screening times".
`placeholderShowtimeSlots` + `HeroShowtimeSlot` are deleted from
`data/homepage.ts`.

### Featured movie flag (single-holder)

- `movies.home_featured_at` nullable timestamp (in-place edit of the
  create-movies migration, pre-launch rule).
- `MovieService::featureOnHome()` — transaction clears any other holder
  before stamping, so at most one movie carries the flag; logs
  `movie.featured_on_home`. `unfeatureFromHome()` mirrors it.
- MovieResource: paired Feature/Remove table actions (visible by flag
  state, gated on `movies.update`) + an "On home" icon column.
- `MovieResource`/`MovieListResource` (API) expose `homeFeaturedAt`;
  `selectFeaturedMovie()` prefers a flagged movie that qualifies (now
  showing + backdrop), breaking multi-flag drift by latest flag time,
  falling back to the existing newest-release algorithm.

### Featured menu items flag (many-holder)

- `menu_items.featured_on_home_at` nullable timestamp (in-place edit).
  Added to the model's activity `logOnly` list so flips are audited.
- MenuItemResource: paired Feature/Remove table actions gated on
  `menu.update`. Several items may be flagged — no invariant.
- `CrossLocationMenuItemResource` exposes `featuredOnHomeAt`;
  `HomeFoodDrink.curate()` leads with flagged items (latest first) and
  tops the trio up from the category algorithm. `MenuItemObserver`
  already busts the food-menu cache on save.

## Decisions

- **No new endpoints** — hero slots ride the existing movie-showtimes
  endpoint; curation flags ride the existing movies/food-menu payloads.
- **No `soldOut` chips** — the cross-location endpoint carries no
  occupancy; chips link into the purchase flow where availability is
  authoritative. The old placeholder's sold-out strikethrough CSS was
  removed as dead.
- **PHPStan**: the three touched API resources gained `@mixin` (the
  TickerItemResource precedent); the stale "undefined property" baseline
  suppressions for them were dropped (baseline regenerated, −126 lines).

## Tests

- `backend/tests/Feature/Admin/Services/HomeCurationTest.php` — service
  invariant + activity, both API exposures, both admin action pairs +
  ops-hidden matrix (8 tests).
- `frontend/tests/utils/buildHeroSlots.test.ts` — formatting, cap, empty.
- `frontend/tests/utils/selectFeaturedMovie.test.ts` — flag preference,
  unqualified-flag fallback, multi-flag drift (3 added).
- `frontend/tests/components/home/HomeCinemaHero.test.ts` — rewritten for
  the live contract (chips, purchase links, cap, empty note, panel copy).
- `frontend/tests/components/home/HomeFoodDrink.test.ts` — flagged-first
  curation (1 added).
