# Plan 11 — CMS pilot: Neural Ticker (Phase 2 opener)

## Goal

First end-to-end CMS vertical, replacing the 9 hardcoded ticker items in
`frontend/app/layouts/default.vue`. Every later Phase-2 step copies this template:
model → observer-bumped versioned cache → Filament resource → public API → composable →
page wiring with a hardcoded fallback.

## Design (clone of the FeaturedSlide vertical, field-for-field where it fits)

- **`TickerItem`** model + new migration: uuid, `label`, `text`, nullable `href`,
  `display_order`, nullable `published_at`/`starts_at`/`ends_at`. Same `scopeActive()` +
  `displayStatus()` semantics as FeaturedSlide. (The plan index said `message`; the live
  frontend contract is `{label, text, href?}` — reality wins.)
- **`TickerItemObserver`** — version-key bump on meaningful save/delete (identical guard
  logic); registered in `AppServiceProvider` beside FeaturedSlide; key added to
  `RefreshContentCacheVersions` so time-windowed items get the daily re-resolve floor.
- **`GET /api/ticker-items`** — versioned `Cache::remember(…, 300)` of resolved
  `TickerItemResource` arrays (`{id, label, text, href}`), active scope, display_order.
- **Filament `TickerItemResource`** (Marketing, `marketing.ticker.*` for admin + manager —
  mirrors featured_slides): content + scheduling sections, status badge, drag reorder,
  Publish action.
- **`TickerItemSeeder`** imports the 9 current hardcoded items (content parity on
  `make fresh`).
- **Frontend**: `useTickerItems()` composable (clone of `useFeaturedSlides`) plus a pure
  `resolveTickerItems(apiItems, fallback)` — API items when non-empty, hardcoded fallback
  otherwise — used by `layouts/default.vue` so the ticker NEVER renders empty on every page
  (incl. ISR) even when the API is empty or unreachable.

## Tests

Backend (`TickerItemResourceTest`): API active-window filtering/ordering/shape, cache bust
on save, empty-OK; manager CRUD via the resource; publish action; ops denied; seeder parity
(9 items live). Frontend: `useTickerItems` fetch contract (clone of the featured-slides
test) + `resolveTickerItems` fallback behavior.
