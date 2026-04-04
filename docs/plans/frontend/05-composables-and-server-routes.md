# Plan 05: Composables & Server Routes

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 01 (types, config), Plan 02 (CSS for any UI feedback)
> **Unlocks:** Plans 06, 07, 08, 09, 10 (all domain plans need data)

## Overview

Build all 9 composables and their corresponding Nuxt server routes (BFF layer). This plan implements the entire data layer: TMDB movie fetching, authentication, cart management, seat selection state, and all API proxy routes. Server routes use mock JSON data initially, with a toggle for real API integration.

## Reference Documents

- `docs/STATE_MANAGEMENT.md` — All composable signatures, state management rules
- `docs/DATA_MODELS.md` — API route inventory (Section 2), TMDB integration (Section 3), Stripe integration (Section 4), mock data (Section 5)
- `docs/SITE_ARCHITECTURE.md` — BFF pattern, composable responsibilities, environment variables
- `docs/PURCHASE_FLOW.md` — Cart lifecycle, session timeout

---

## Tasks

### Task 1: Mock Data Files

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/server/data/movies.json` — 20 movies (mix of now_showing/coming_soon)
  - `frontend/server/data/showtimes.json` — 50+ showtimes across next 14 days
  - `frontend/server/data/auditoriums.json` — 3 auditorium layouts with seat maps
  - `frontend/server/data/menu.json` — Full food/drink menu
  - `frontend/server/data/events.json` — 10 calendar events
  - `frontend/server/utils/config.ts` — Mock data toggle (`MOCK_DATA` env var)
- **Details:**
  JSON fixtures matching TypeScript interfaces from Plan 01. These serve as the data contract. Movies should include realistic TMDB-like IDs and slug values. Auditoriums should have varied layouts (small: 8 rows × 10 seats, medium: 12 × 14, large/IMAX: 15 × 20) with standard, premium, and accessible sections.

- **Acceptance Criteria:**
  - [ ] All 5 JSON files contain realistic mock data
  - [ ] Data satisfies the TypeScript interfaces from Plan 01
  - [ ] Auditoriums have varied layouts with all seat types
  - [ ] Showtimes span 14 days with multiple per movie
  - [ ] `useMockData` flag reads from `process.env.MOCK_DATA`

---

### Task 2: TMDB Server Utility

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/server/utils/tmdb.ts` — TMDB API client + `tmdbToMovie` transform function
- **Details:**
  Per DATA_MODELS.md Section 3:
  - HTTP client wrapping TMDB API v3 (`https://api.themoviedb.org/3`)
  - Bearer token auth via `useRuntimeConfig().tmdbApiKey`
  - Image URL builder: poster (w500/w780), backdrop (w1280), profile (w185)
  - `tmdbToMovie()` transform exactly as specified in DATA_MODELS.md
  - When `MOCK_DATA=true`, return data from `server/data/movies.json` instead

- **Acceptance Criteria:**
  - [ ] `tmdbToMovie` transform matches DATA_MODELS.md spec exactly
  - [ ] Image URLs constructed with correct size prefixes
  - [ ] Cast limited to first 12 members
  - [ ] Trailer extracted (type=Trailer, site=YouTube)
  - [ ] Falls back to mock data when `MOCK_DATA=true`

---

### Task 3: Movie Server Routes

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/server/api/movies/index.get.ts` — List movies (now_showing/coming_soon)
  - `frontend/server/api/movies/[slug].get.ts` — Movie detail by slug
  - `frontend/server/api/movies/[slug]/showtimes.get.ts` — Showtimes for a movie by date
- **Details:**
  Per DATA_MODELS.md Section 2:

  **`/api/movies`:**
  - Query params: `status` (now_showing|coming_soon), `genre`, `page`
  - TMDB: now_showing → `/movie/now_playing`, coming_soon → `/movie/upcoming`
  - Merge with local showtime data by TMDB movie ID
  - Cache: `cachedEventHandler` with 30-minute maxAge
  - Response: `{ movies: Movie[], total: number, page: number }`

  **`/api/movies/:slug`:**
  - TMDB: `/movie/{id}` + `/movie/{id}/credits` + `/movie/{id}/videos`
  - Slug-to-ID mapping via slugify match
  - Cache: 1 hour maxAge
  - Response: `Movie`

  **`/api/movies/:slug/showtimes`:**
  - Query params: `date` (YYYY-MM-DD)
  - Source: local showtime data (no TMDB)
  - No cache (near-real-time availability)
  - Response: `{ showtimes: Showtime[] }`

- **Acceptance Criteria:**
  - [ ] All 3 routes return correctly shaped responses
  - [ ] Caching configured per spec (30min, 1hr, none)
  - [ ] TMDB integration works with real API key
  - [ ] Mock data works when `MOCK_DATA=true`
  - [ ] Genre and status filtering works

---

### Task 4: `useMovies` Composable

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useMovies.ts`
- **Details:**
  Per STATE_MANAGEMENT.md:
  ```typescript
  function useMovies() {
    const nowShowing = (options?) => useFetch('/api/movies', { query: { status: 'now_showing', ...options } })
    const comingSoon = (options?) => useFetch('/api/movies', { query: { status: 'coming_soon', ...options } })
    const getMovie = (slug) => useFetch(`/api/movies/${slug}`)
    return { nowShowing, comingSoon, getMovie }
  }
  ```

- **Acceptance Criteria:**
  - [ ] `nowShowing()` returns now-showing movies
  - [ ] `comingSoon()` returns upcoming movies
  - [ ] `getMovie(slug)` returns single movie detail
  - [ ] All methods use `useFetch` for SSR compatibility
  - [ ] Auto-imported by Nuxt

---

### Task 5: `useShowtimes` Composable + Showtime Route

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/server/api/showtimes/[id].get.ts` — Single showtime with auditorium + seat map
  - `frontend/app/composables/useShowtimes.ts`
- **Details:**
  **Server route:** Returns `Showtime & { auditorium: Auditorium, seats: Seat[] }` — the entry point for seat selection.

  **Composable:**
  ```typescript
  const getShowtimes = (movieSlug, date?) => useFetch(`/api/movies/${movieSlug}/showtimes`, { query: { date } })
  const getShowtime = (id) => useFetch(`/api/showtimes/${id}`)
  ```

- **Acceptance Criteria:**
  - [ ] `getShowtime(id)` returns showtime + full seat map with availability
  - [ ] `getShowtimes(slug, date)` returns showtimes for a movie
  - [ ] Seat statuses are accurate (available, taken, held)

---

### Task 6: Auth Server Routes + `useAuth` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/server/api/auth/login.post.ts`
  - `frontend/server/api/auth/register.post.ts`
  - `frontend/server/api/auth/logout.post.ts`
  - `frontend/server/api/auth/me.get.ts`
  - `frontend/server/api/auth/forgot-password.post.ts`
  - `frontend/server/middleware/auth.ts` — Server-side session verification
  - `frontend/server/utils/auth.ts` — Session helpers, password hashing
  - `frontend/app/composables/useAuth.ts`
- **Details:**
  Per STATE_MANAGEMENT.md and DATA_MODELS.md:

  **Composable (global state via `useState`):**
  - `user: Ref<User | null>`
  - `isAuthenticated: ComputedRef<boolean>`
  - Methods: `login(email, password)`, `register(name, email, password)`, `logout()`, `fetchUser()`
  - Session stored in encrypted HTTP-only cookie via `nuxt-auth-utils`

  **Server routes:** Session-based auth. Login validates credentials, sets session cookie. Register creates user, sets session. Logout clears session. Me returns current user from session. Forgot-password is a stub (logs email, returns success).

- **Acceptance Criteria:**
  - [ ] Login sets session cookie and returns user
  - [ ] Register creates user and sets session
  - [ ] Logout clears session
  - [ ] `fetchUser()` restores session on app init
  - [ ] `isAuthenticated` reactive computed works correctly
  - [ ] Server middleware protects authenticated routes

---

### Task 7: `useCart` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useCart.ts`
- **Details:**
  Per STATE_MANAGEMENT.md — global state via `useState`. Ephemeral (not persisted).

  **State:** `showtime`, `seats`, `foodItems`, `promoCode`, `promoDiscount`, `giftCardCode`, `giftCardAmount`, `subtotal` (computed), `total` (computed)

  **Methods:** `initializeCart(showtime)`, `addSeat(seat)`, `removeSeat(seatId)`, `addFoodItem(...)`, `removeFoodItem(itemId)`, `applyPromoCode(code, discount)`, `removePromoCode()`, `applyGiftCard(code, amount)`, `removeGiftCard()`, `clear()`

  **Session timer:**
  - 15-minute timer starts on first seat selection
  - 10-minute mark: toast warning "Your session expires in 5 minutes"
  - 15-minute expiry: cart clears, toast "Session expired", redirect to seat selection
  - Timer resets if all seats deselected

- **Acceptance Criteria:**
  - [ ] Cart initializes with showtime reference
  - [ ] Adding/removing seats updates subtotal and total
  - [ ] Food items can be added, incremented, decremented, removed
  - [ ] Promo code applies discount to total
  - [ ] Gift card deducts from total
  - [ ] `clear()` resets all state
  - [ ] 15-minute session timer works with 5-minute warning
  - [ ] Cart is NOT persisted to localStorage

---

### Task 8: `useSeatSelection` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useSeatSelection.ts`
- **Details:**
  Per STATE_MANAGEMENT.md — local state (not global). Tracks interaction state for AuditoriumGrid.

  **State:** `seats` (server truth), `selectedSeatIds` (client-only Set), `focusedSeatId`

  **Methods:**
  - `toggleSeat(seatId)` — add/remove from selectedSeatIds
  - `isAvailable(seatId)` — checks server status
  - `isSelected(seatId)` — checks client selection
  - `selectedSeats` — computed filtered array
  - `moveFocus(direction)` — keyboard navigation (up/down/left/right)
  - `updateSeats(newSeats)` — merge new availability, deselect taken seats with toast

  **Important:** Client selection (`selectedSeatIds`) is separate from server status (`Seat.status`). This allows seat availability refreshes to merge without colliding with user selections.

- **Acceptance Criteria:**
  - [ ] Toggle adds/removes from selection set
  - [ ] Cannot select taken or held seats
  - [ ] `updateSeats` merges new data and deselects newly-taken seats
  - [ ] Toast fires when a selected seat becomes unavailable
  - [ ] `moveFocus` navigates grid correctly (wraps at row boundaries)
  - [ ] `selectedSeats` computed updates reactively

---

### Task 9: Calendar, Account, Gift Cards Composables + Routes

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/server/api/calendar/events/index.get.ts`
  - `frontend/server/api/calendar/events/[slug].get.ts`
  - `frontend/server/api/food-menu.get.ts`
  - `frontend/server/api/account/profile.get.ts`
  - `frontend/server/api/account/profile.patch.ts`
  - `frontend/server/api/account/orders.get.ts`
  - `frontend/server/api/account/bookings.get.ts`
  - `frontend/server/api/account/loyalty.get.ts`
  - `frontend/server/api/account/payment-methods/index.get.ts`
  - `frontend/server/api/account/payment-methods/index.post.ts`
  - `frontend/server/api/account/payment-methods/[id].delete.ts`
  - `frontend/server/api/gift-cards/purchase.post.ts`
  - `frontend/server/api/gift-cards/balance.get.ts`
  - `frontend/server/api/contact.post.ts`
  - `frontend/server/api/rentals/inquiry.post.ts`
  - `frontend/app/composables/useCalendarEvents.ts`
  - `frontend/app/composables/useAccount.ts`
  - `frontend/app/composables/useGiftCards.ts`
- **Details:**
  Per STATE_MANAGEMENT.md and DATA_MODELS.md Section 2.

  **Calendar:** List events with month/year/type/accessibility filters. Detail by slug.
  **Account:** Profile CRUD, orders (paginated), upcoming bookings, loyalty, payment methods CRUD.
  **Gift Cards:** Purchase (Stripe) and balance check.
  **Contact/Rentals:** Form submissions (store to JSON or log for now).

  All composables follow the same `useFetch` wrapper pattern from STATE_MANAGEMENT.md.

- **Acceptance Criteria:**
  - [ ] Calendar events filterable by type and accessibility tags
  - [ ] Account routes protected by auth middleware
  - [ ] Order history supports pagination
  - [ ] Gift card balance check returns correct data
  - [ ] Contact and rental forms return success response

---

### Task 10: Booking Server Routes

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/server/api/bookings/index.post.ts` — Create booking (Stripe PaymentIntent flow)
  - `frontend/server/api/bookings/[id].get.ts` — Get booking by ID
  - `frontend/server/api/bookings/confirm.post.ts` — 3DS confirmation handler
- **Details:**
  Per DATA_MODELS.md Section 2 and PURCHASE_FLOW.md Section 4:

  **POST `/api/bookings`:**
  1. Validate seat availability (re-check at purchase time)
  2. Validate food items, promo code, gift card
  3. Calculate total: (seat prices) + (food) - (promo discount) - (gift card)
  4. Create Stripe PaymentIntent with calculated amount
  5. Confirm PaymentIntent
  6. On success: mark seats taken, create booking, generate confirmation code ("CVF-" + alphanumeric)
  7. Return booking

  **Error responses:** 409 (seats taken), 402 (payment declined), 400 (invalid promo/gift card), 410 (session expired), 200 with `requiresAction` (3DS)

  **GET `/api/bookings/:id`:** Returns booking by ID (acts as secret URL).

- **Acceptance Criteria:**
  - [ ] Successful booking creates record and returns confirmation code
  - [ ] 409 returned when seats are no longer available
  - [ ] 402 returned when payment is declined
  - [ ] 3DS flow returns `requiresAction` with `clientSecret`
  - [ ] Confirmation code format: "CVF-" + 6 alphanumeric characters
  - [ ] Mock mode works without Stripe API key

---

### Task 11: `useToast` Composable

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/composables/useToast.ts`
- **Details:**
  Per STATE_MANAGEMENT.md — global state via `useState`.

  **State:** `queue: Ref<Array<{ id, message, type, duration }>>`
  **Methods:** `show({ message, type?, duration? })`, `dismiss(id)`

  Auto-generates unique ID per toast. CvToast component reads from this queue.

- **Acceptance Criteria:**
  - [ ] `show()` adds toast to queue with auto-generated ID
  - [ ] `dismiss(id)` removes specific toast
  - [ ] Queue is reactive and accessible from any component
  - [ ] Default duration: 5000ms, default type: 'info'

---

## Testing Requirements

- **Unit Tests:**
  - `useCart`: add/remove seats, food items, promo, gift card, computed totals, timer
  - `useSeatSelection`: toggle, merge conflicts, moveFocus navigation
  - `useToast`: show/dismiss queue management
  - `useAuth`: login/logout state transitions
  - Utility functions in `server/utils/tmdb.ts` (tmdbToMovie transform)
- **Integration Tests:**
  - Server routes return correctly shaped responses with mock data
  - Authenticated routes reject unauthenticated requests
  - Booking route validates seat availability
- **E2E Tests:** Deferred to Plan 13 (E2E & Polish)

## Dependencies Map

```
Task 1 (Mock Data) ← all server routes depend on this
Task 2 (TMDB Utility) ← Task 3 depends on this
Task 3 (Movie Routes) → Task 4 (useMovies)
Task 5 (Showtime Route + useShowtimes)
Task 6 (Auth Routes + useAuth) ← Task 9 account routes depend on auth
Task 7 (useCart) ← Task 10 booking routes reference cart shape
Task 8 (useSeatSelection) ← independent
Task 9 (Calendar/Account/GiftCards) ← needs Task 6 for auth middleware
Task 10 (Booking Routes) ← needs Tasks 1, 2, 6, 7
Task 11 (useToast) ← independent
```

## Risks & Open Questions

1. **Stripe in dev** — Need Stripe test keys for booking route. Stub with mock payment flow when `MOCK_DATA=true`.
2. **nuxt-auth-utils API** — Verify the session API matches our usage. May need to use raw `h3` session utilities if the module API differs.
3. **Slug-to-TMDB-ID mapping** — The slug is derived from the movie title. Need a reliable mapping strategy. Options: maintain a local lookup table, or slugify TMDB titles and match on the fly.
4. **Mock data realism** — Mock showtimes need realistic date/time distribution. Use a seed script or generate programmatically.
5. **Server route file naming** — Nuxt server routes use file-based naming. Verify `[slug].get.ts` and `index.get.ts` patterns work correctly with nested directories.
