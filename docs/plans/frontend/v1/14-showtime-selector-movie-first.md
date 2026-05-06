# Plan 14: ShowtimeSelector — Movie-First, Closest-Venue Default

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** 13 (Content Refactor — provides `useGeolocation`, the cross-location showtimes endpoint, and the location-at-intent contract)
> **Unlocks:** Tighter movie → showtime → checkout funnel; lower attention split on the busiest content page; foundation for venue-default personalization across the site

## Context

The browse-then-book funnel for Final Cut is *movie-first*: users come in for a film, not a theatre. The conversion path the business cares about is "I want to see *Past Lives*; what's the next showing close to me, today, and how fast can I check out?" — anything that splits attention across both venues at once is friction in that path.

Plan 13 made the movie detail page cross-location: `ShowtimeSelector` now renders **one accordion group per venue** for the active date and "expands" the closest venue when geolocation is granted, leaving the other(s) collapsed-but-visible. That removed the `activeLocation` boot-gate and made the page SSR-cacheable, but it still surfaces every venue's showtime grid simultaneously. Two near-equal panels create a comparison-shopping read of the page rather than a "you're seeing this here" read. Users who don't want to comparison-shop (the majority) pay attention cost they didn't ask for; users who do want to compare don't gain much because both lists are already side-by-side.

This plan replaces the venue accordion with a **single-venue showtime panel + a venue chip row**. The selector commits to one venue at a time. Closest-venue auto-selects post-hydration; one tap switches. Comparison shopping costs one tap — close enough to free for the rare flow that wants it, while removing the visual split for the common flow that doesn't.

The page stays statically renderable: the SSR HTML reflects a deterministic default (first alphabetical venue), and all user-specific affordances (closest venue swap, distance captions, last-used-venue restore) are client-only enhancements that arrive after hydration. The URL stays clean (`/movies/<slug>` — no venue query param), so each movie continues to occupy a single ISR cache entry.

## North-Star UX

After this plan ships, a user clicking a film card from `/movies` lands on the movie detail page and sees, above the fold:

```
Title · Tagline · Synopsis ·  ⏵
─────────────────────────────────────────────────
SHOWTIMES                       Today  Tue  Wed  …
[● Downtown · 2.3 mi]  [○ Uptown · 5.1 mi]
─────────────────────────────────────────────────
6:30 PM   Screen 2   from $14.99
9:15 PM   Screen 3   from $14.99
```

- One date strip (existing).
- One row of venue chips, the closest one filled. Distance captions render only after geolocation grant; SSR ships chips with name only.
- One showtime grid below, scoped to the selected venue + selected date.
- Tapping a different chip swaps the showtime grid in-place (no refetch — data is already in the cross-location payload).
- Tapping a showtime button takes them to `/purchase/<showtimeId>?loc=<venue>` (already wired in Plan 13).

Empty states inherit Plan 13's behavior: zero showtimes anywhere → "Showtimes coming soon" + Notify Me; one venue dark for that date → that venue's chip is disabled with a tooltip ("No showings tonight at Uptown — try Downtown or another date").

## Architectural Contract

### SSR (cacheable, no user-specific data)

- Venue chip row renders with name only, alphabetical.
- The first alphabetical venue is the default-selected chip.
- That venue's showtime grid renders inline for the default-selected date (today in venue timezone, falling back to the next day with showtimes when today is empty).
- All HTML is deterministic across users — Nuxt's ISR cache for `/movies/<slug>` remains a single entry.

### Post-hydration (client-only, never serialized into the SSR HTML)

In strict precedence order, the first that resolves wins:

1. **Geolocation granted** (`useGeolocation.status === 'granted'`)
   → swap selected chip to the closest venue (Haversine via `useGeolocation.distanceTo()`)
   → add `· X.Y mi away` captions to every chip
   → write the resolved venue slug to localStorage as the new last-used preference

2. **Last-used venue from localStorage** (`fc:lastUsedVenue`)
   → swap selected chip to the stored slug (only if it matches a venue in the current showtime payload)
   → no distance captions (no geolocation grant)

3. **Default** — leave the SSR-selected first-alphabetical chip as-is.

Switching the selected chip on user click writes the new slug to localStorage, so the next visit to any movie defaults to the venue they last booked at (or last selected, even without booking).

### State ownership

| Concern | Lives in | Why |
|---|---|---|
| Selected venue slug | Local component `ref<string>` inside `ShowtimeSelector` | Page-local, not URL — preserves the single ISR cache entry per movie |
| Selected date | Existing `activeDate` `ref<string>` | Unchanged from current implementation |
| Closest venue resolution | `useGeolocation.distanceTo()` (Plan 13 Task 3 composable) | Already-correct primitive; no new state |
| Last-used venue preference | New `useLastUsedVenue()` composable wrapping localStorage | Client-only, sessionStorage-tier ephemerality is too short (booking sessions span days) |
| `useLocations.activeLocation` | **Untouched.** | Booking flow still owns it; this plan only adds the new lighter-weight `lastUsedVenue` for the showtime selector. The two should converge in a later plan, but bundling that here would balloon scope. |

### Data flow

```
SSR render
  └─ useShowtimes.fetchByMovie(slug)
       └─ /api/movies/{slug}/showtimes (cross-location payload, all venues)

ShowtimeSelector mounts
  ├─ Computes venueChips from payload (alphabetical, name-only)
  ├─ Computes selectedVenueSlug
  │    SSR: first venue in venueChips
  │    Hydration tick:
  │      if (useGeolocation.status === 'granted') closestVenueSlug
  │      else if (useLastUsedVenue.value && payloadHas(useLastUsedVenue.value)) useLastUsedVenue.value
  │      else (unchanged)
  └─ Computes shownSlots = showtimes
       .filter(s => s.location.slug === selectedVenueSlug)
       .filter(s => sameLocalDate(s.startTime, activeDate, venueTz))
       .map(formatSlot)
```

No new HTTP calls. No URL mutation. The whole interaction is a reactive recomputation of `selectedVenueSlug` against the same in-memory payload.

## Reference Documents

- `docs/architecture/CONTENT_ARCHITECTURE.md` — location-at-intent pattern this extends
- `docs/architecture/SITE_ARCHITECTURE.md` — composables table; will gain `useLastUsedVenue`
- `docs/specs/PAGE_SPECS.md` § `/movies/:slug` — the showtime selector spec
- `docs/plans/frontend/v1/13-content-refactor.md` § Tasks 3 & 6 — geolocation composable + cross-location showtime fetch this builds on
- `frontend/app/components/movie/ShowtimeSelector.vue` — the component being rewritten
- `frontend/app/composables/useGeolocation.ts` — the post-hydration distance/closest source
- `frontend/app/types/showtime.ts` — `Showtime.location` shape consumed here

---

## Tasks

### Task 1: `useLastUsedVenue` composable

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useLastUsedVenue.ts` (new)
  - `frontend/tests/composables/useLastUsedVenue.test.ts` (new)
- **Details:**
  - SSR-safe wrapper around `localStorage[fc:lastUsedVenue]`. Storage key namespaced under `fc:` so it doesn't collide with the existing `active-location` key (which the booking flow still owns).
  - Public API: `useLastUsedVenue()` returns `{ slug: Ref<string | null>, set(slug: string): void, clear(): void }`.
  - On first call inside a client context, hydrates `slug` from localStorage. Server-side returns `slug.value === null` deterministically.
  - `set()` writes through to localStorage and updates the ref. `clear()` removes the localStorage entry and nulls the ref.
  - Uses `useState('lastUsedVenue', ...)` so multiple components share the same ref without re-reading storage on each call.
  - Storage failures (private browsing, quota, disabled) degrade gracefully: the ref stays `null`, no exception escapes the composable.
- **Acceptance Criteria:**
  - [ ] SSR import returns `slug.value === null` and never accesses `localStorage` (verified by mocking `localStorage` to throw)
  - [ ] First client mount with a stored `fc:lastUsedVenue=downtown` resolves `slug.value === 'downtown'`
  - [ ] `set('uptown')` updates both the ref and the persisted value
  - [ ] `clear()` removes the persisted entry and sets the ref to null
  - [ ] localStorage failures (mocked `setItem` to throw) don't propagate; ref stays in last successful state

---

### Task 2: Refactor `ShowtimeSelector` to chip row + single panel

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/movie/ShowtimeSelector.vue` (modify — significant rewrite of `<template>` and the `venueGroups` computed)
- **Details:**
  - Replace the venue accordion with two stacked elements:
    1. **`VenueChipRow`** — inline component (or a small dedicated child component if size warrants — author's call). Renders one `<button>` chip per venue, alphabetical, with `aria-pressed` reflecting active state. Click sets `selectedVenueSlug.value = chip.slug` and writes through `useLastUsedVenue.set(chip.slug)`. After geolocation grant, chips include a `· X.Y mi away` caption.
    2. **`VenueSlotPanel`** — the existing slot grid, scoped to `selectedVenueSlug`. Renders `formattedSlots` for the active date filtered by venue.
  - **Drop the `venueOpenState` map and the `isVenueOpen` / `toggleVenue` / `chevron` UI entirely.** That model belonged to the accordion. The new model has exactly one open panel always.
  - Replace the existing `VenueGroup` shape with:
    ```ts
    interface VenueChip {
      slug: string
      name: string
      latitude: number | null
      longitude: number | null
      hasShowtimesOnActiveDate: boolean // chip disabled when false; tooltip on hover
    }
    interface VenueSlot {
      id: string
      screenName: string
      priceStandard: number
      time: string
      meridiem: string
    }
    ```
  - `selectedVenueSlug` is a local `ref<string>`. SSR initial value is the first chip's slug (alphabetical). On client mount inside an `onMounted` hook (so the swap happens after hydration, not during it):
    - If `useGeolocation.status.value === 'granted'`, compute closest venue with valid coords and `selectedVenueSlug.value = closest.slug`, then `useLastUsedVenue.set(closest.slug)`.
    - Else if `useLastUsedVenue.slug.value` matches a chip in the current payload, `selectedVenueSlug.value = useLastUsedVenue.slug.value`.
    - Else leave SSR default.
  - Distance captions: a separate `geoDistances = computed(() => Map<slug, number | null>)` populated only when `useGeolocation.status.value === 'granted'`. The chip template reads from this map; SSR sees an empty map and renders no captions.
  - Empty states:
    - Selected venue has zero showtimes for the active date → render a small inline note inside the slot panel: "No showings at {Venue} on {date}. Try a different date or {OtherVenue}." with `OtherVenue` linking via plain chip click (no nav).
    - All venues have zero showtimes anywhere in the 7-day window → existing behavior: "Showtimes coming soon" + Notify Me CTA. (Already implemented; preserve.)
    - Single venue in the payload (only one location offers this film) → still render the chip row (with one chip) so the UI structure is consistent. The chip is non-toggleable but matches the visual language of the multi-venue case.
  - Reduced motion: chip swap is instant. The slot panel doesn't fade; it just re-renders. If the slot list height changes meaningfully, allow a CSS height transition under `@media (prefers-reduced-motion: no-preference)` only.
- **Acceptance Criteria:**
  - [ ] SSR HTML for `/movies/<slug>` shows one chip row + one slot panel; no second panel exists in the rendered tree
  - [ ] First chip in alphabetical order is `aria-pressed="true"` at SSR
  - [ ] Clicking a different chip swaps the slot panel content with no network request
  - [ ] Clicking a chip writes the slug to `localStorage[fc:lastUsedVenue]`
  - [ ] When the selected venue has no showtimes for the active date, the inline empty note renders inside the panel and the chip remains pressed
  - [ ] When a venue has no showtimes for the active date but others do, that chip is `disabled` with `aria-disabled="true"` and a `title` attribute explaining why
  - [ ] Tab order: date strip → chips → first focusable slot. Shift-tab reverses cleanly.
  - [ ] Existing roving-tabindex pattern on the date strip is preserved

---

### Task 3: Hydration enhancement layer

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/movie/ShowtimeSelector.vue` (modify — wire the post-hydration logic from Task 2's spec)
- **Details:**
  - All client-only behavior from the contract above lives inside an `onMounted` hook in `ShowtimeSelector` so the SSR snapshot remains deterministic. The hook executes the geolocation → last-used-venue → default precedence chain exactly once per mount.
  - When `useGeolocation.status.value` changes from `idle`/`prompting` to `granted` *after* mount (the user clicks "use my location" mid-page), the same precedence runs again. Implement via `watch(geoStatus, (next) => { if (next === 'granted') applyClosest() })`. Don't re-run for `denied` or `unsupported` — those states should not retroactively undo a user's manual chip click.
  - When `useGeolocation.coords.value` updates while granted (user moves), the closest-venue calc should *not* automatically swap an already-user-selected chip. Track an internal `userPickedSlug = ref(false)` flag; once the user clicks any chip, this flag stays true and geolocation refreshes only update distance captions, not the selected chip.
  - Distance captions update reactively when coords change.
- **Acceptance Criteria:**
  - [ ] With geolocation pre-granted (sessionStorage hydration in `useGeolocation`), mounting the component selects the closest venue's chip
  - [ ] With no geolocation but `fc:lastUsedVenue=uptown` in localStorage, mounting selects the Uptown chip
  - [ ] With neither, the SSR-selected chip stays selected
  - [ ] Granting geolocation after mount swaps the selected chip exactly once and adds distance captions
  - [ ] Manually clicking a chip and then granting geolocation updates captions but does NOT change the selected chip
  - [ ] Denying geolocation after mount leaves the current selection untouched

---

### Task 4: Update consuming page + types

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/pages/movies/[slug].vue` (modify — verify props and remove any leftover venue accordion plumbing if present)
  - `frontend/app/types/showtime.ts` (verify; no field changes expected — Plan 13 already added `location` to `Showtime`)
- **Details:**
  - The page already fetches via `useShowtimes.fetchByMovie(slug)` and passes the showtime list to `<ShowtimeSelector :showtimes="showtimes" />`. Confirm no other props are required, no parent-side `activeVenue` state needs to be threaded through (the selector now owns that internally).
  - Verify the link from a slot button still carries `?loc=<slug>` for the seat-selection bootstrap (Plan 13's HIGH fix). Update the slot template if Task 2's rewrite dropped it.
- **Acceptance Criteria:**
  - [ ] `pages/movies/[slug].vue` does not own a venue selection state — the selector is self-contained
  - [ ] Slot links carry `?loc=<slug>` exactly as Plan 13 specified
  - [ ] `Showtime` type unchanged (or, if changed, the change is documented in the progress journal with the reason)

---

### Task 5: Test coverage

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/tests/composables/useLastUsedVenue.test.ts` (new — created in Task 1)
  - `frontend/tests/components/movie/ShowtimeSelector.test.ts` (modify — adapt and extend)
  - `frontend/tests/e2e/movie-detail-cross-location.spec.ts` (modify — add scenarios)
- **Details:**

  **Vitest — `ShowtimeSelector`:**
  - SSR snapshot shows one chip row + one slot panel; no accordion structure remains.
  - Default-selected chip is the first alphabetical venue when neither geolocation nor last-used is available.
  - With `useGeolocation` mocked to `granted` + coords near Downtown, the Downtown chip is `aria-pressed="true"` after mount.
  - With geolocation denied and `useLastUsedVenue` mocked to return `'uptown'`, Uptown chip is selected after mount.
  - Manual chip click swaps selection AND writes to `useLastUsedVenue.set`.
  - Manual click followed by a `geoStatus` change to `'granted'` does NOT change the selected chip.
  - Distance captions render only when `useGeolocation.status === 'granted'` and coords are available.
  - Selected venue with zero showtimes for the active date renders the inline empty note.
  - Venue with zero showtimes for the active date renders a `disabled` chip with `aria-disabled="true"`.
  - Single-venue payload renders one non-toggleable chip plus the slot panel.

  **Vitest — `useLastUsedVenue`:** covered fully in Task 1's acceptance criteria.

  **Playwright — `movie-detail-cross-location.spec.ts`:**
  - Add: visiting `/movies/<slug>` shows exactly one venue chip with `aria-pressed="true"` in the rendered HTML.
  - Add: clicking a different chip swaps the visible showtime list (assert on slot count change or screen names).
  - Add: visiting `/movies/<slug>` after granting geolocation in the test context selects the closest venue's chip (use `context.grantPermissions(['geolocation'])` + `setGeolocation({ latitude, longitude })`).
  - Keep: the existing assertion that slot links navigate to `/purchase/<id>?loc=<slug>`.

- **Acceptance Criteria:**
  - [ ] All four hydration precedence cases (geo-granted, last-used, none, manual override) have Vitest coverage
  - [ ] `make test-frontend` is green
  - [ ] New Playwright scenario passes when run against the dev stack with seeded showtimes at both venues

---

### Task 6: Progress journal + index update

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `docs/progress/frontend-v1.md` (append Plan 14 entries)
  - `docs/plans/frontend/v1/00-index.md` (modify — add Plan 14 row, mark Complete on land)
  - `docs/architecture/SITE_ARCHITECTURE.md` (modify — add `useLastUsedVenue` to the composables table; clarify that ShowtimeSelector owns its own venue state independent of `useLocations.activeLocation`)
- **Details:**
  - Per-task journal entries: Status, Started, Completed, Work Done, Decisions, Blockers, Files Changed.
  - Decisions worth recording explicitly:
    - Why `useLastUsedVenue` is separate from `useLocations.activeLocation` instead of folded in (the booking flow still has a hard activeLocation requirement that this plan doesn't unwind; converging the two is a Plan 15 candidate).
    - Why selection state is component-local instead of URL-encoded (single ISR cache per movie; SEO; bookmarks land on a deterministic default).
    - Why post-hydration geolocation refreshes don't override a manual click (preserves user agency once they've made an explicit choice).

---

## Out of Scope

These were considered and deferred:

- **Show-all-venues comparison view.** Discussed in the brainstorm. Skipped on first ship — the data we have so far suggests comparison shopping isn't the dominant flow. Revisit if user feedback or analytics flag it.
- **Venue picker on the home page or `/movies` listing.** That's `?location=` filter territory, already shipped in Plan 13. The home/movies pages remain cross-location browse; venue commitment happens at the showtime selector.
- **Folding `useLocations.activeLocation` into `useLastUsedVenue`.** Tempting, but the booking flow's `activeLocation` carries Location object payloads, not just a slug, and unwinding that touches the seatmap fetch path. Out of scope.
- **Server-side personalization of the SSR default** (e.g., set the default chip based on a `geoip` header). Would require reading request headers in Nuxt's server middleware, which works but introduces cache fragmentation per region. Cost > benefit until we have data.
- **A "remember this date" preference** alongside last-used-venue. Date defaults to today; that's good enough.

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Hydration mismatch between SSR-selected chip and client-resolved chip causes a visible flicker | Medium | Low (single chip change, no layout shift) | Limit the swap to `onMounted` so it happens after hydration commits, not during; use a CSS transition that respects `prefers-reduced-motion` |
| `useLastUsedVenue` reads localStorage during SSR and crashes the render | Low | High (page 500s) | Composable is client-only-by-construction; SSR returns `slug.value === null` without touching `localStorage`. Test enforces this. |
| Geolocation prompts feel intrusive when triggered automatically | N/A | N/A | This plan never auto-triggers geolocation. Plan 13 already established `useGeolocation` as strict opt-in. The selector reads `useGeolocation.status` reactively but never calls `request()`. |
| Single-venue payload (only one venue offers this film) creates an awkward one-chip row | Low | Low | Render the single chip exactly like the multi-chip case — the visual grammar stays consistent. Tests cover this. |
| Component-local venue state means a venue switch doesn't survive a hard reload | Medium | Low | `useLastUsedVenue` IS the survival mechanism — the next visit defaults to the last-clicked venue. Hard reload of the same URL is rare in this flow; users navigate from movies index. |

## Verification

End-to-end manual smoke after the plan lands:

1. `make up`. Open `https://finalcut.test/movies/<seeded-slug>` in an incognito window.
2. Confirm SSR HTML shows one chip pressed (the alphabetically-first venue) and that venue's showtime grid only — view source to verify nothing user-specific bled into the cached HTML.
3. Click the other chip — slot grid swaps with no network request (DevTools Network tab is silent).
4. Refresh the page — the venue you last clicked is now selected (last-used-venue persisted).
5. Grant geolocation when prompted by some other UI surface (e.g., the locations page chip-suggestion). Return to the movie detail page. Closest venue is selected; distance captions appear on chips.
6. Click the *non-closest* chip. Refresh the page. Your manual click stuck (the closest-venue swap doesn't override an explicit selection on the next mount until you `useLastUsedVenue.clear()`).
7. Run `make test-frontend` — green.
8. Run the new Playwright scenarios — green.
