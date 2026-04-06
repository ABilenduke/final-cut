# Plan 05: Composables & API Integration

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 01 (types, config), Plan 02 (CSS for any UI feedback)
> **Unlocks:** Plans 06, 07, 08, 09, 10 (all domain plans need data)

## Overview

Build all 9 composables that form the frontend data layer. The frontend calls the Laravel API directly — there is no Nuxt server-side BFF layer. Composables wrap `useFetch` / `$fetch` calls targeting the Laravel backend via `NUXT_PUBLIC_API_BASE_URL`. Authentication uses Laravel Sanctum (session cookies) with `nuxt-auth-utils` for SSR hydration only.

## Reference Documents

- `docs/architecture/STATE_MANAGEMENT.md` — All composable signatures, state management rules
- `docs/architecture/DATA_MODELS.md` — API route inventory (Section 2), Stripe integration (Section 4)
- `docs/architecture/SITE_ARCHITECTURE.md` — Frontend-backend architecture, composable responsibilities, environment variables
- `docs/specs/PURCHASE_FLOW.md` — Cart lifecycle, session timeout

---

## Tasks

### Task 1: API Fetch Configuration

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/utils/api.ts` — Configured `$fetch` instance for Laravel API calls
- **Details:**
  Create a composable or utility that provides a pre-configured `$fetch` instance pointing at the Laravel backend. Reads `useRuntimeConfig().public.apiBaseUrl` to construct the base URL. All API calls should include `credentials: 'include'` to send Sanctum session cookies cross-origin.

  ```typescript
  // app/utils/api.ts
  export function useApi() {
    const config = useRuntimeConfig()
    return $fetch.create({
      baseURL: config.public.apiBaseUrl,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })
  }
  ```

- **Acceptance Criteria:**
  - [ ] `useApi()` returns a configured `$fetch` instance
  - [ ] Base URL read from `NUXT_PUBLIC_API_BASE_URL` runtime config
  - [ ] Credentials included for Sanctum cookie auth
  - [ ] Auto-imported by Nuxt from `app/utils/`

---

### Task 2: `useMovies` Composable

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useMovies.ts`
- **Details:**
  Per STATE_MANAGEMENT.md. Wraps Laravel API calls for movie data.

  ```typescript
  function useMovies() {
    const api = useApi()
    const nowShowing = (options?) => useFetch('/api/movies', {
      baseURL: useRuntimeConfig().public.apiBaseUrl,
      query: { status: 'now_showing', ...options },
      credentials: 'include',
    })
    const comingSoon = (options?) => useFetch('/api/movies', {
      baseURL: useRuntimeConfig().public.apiBaseUrl,
      query: { status: 'coming_soon', ...options },
      credentials: 'include',
    })
    const getMovie = (slug: string) => useFetch(`/api/movies/${slug}`, {
      baseURL: useRuntimeConfig().public.apiBaseUrl,
      credentials: 'include',
    })
    return { nowShowing, comingSoon, getMovie }
  }
  ```

- **Acceptance Criteria:**
  - [ ] `nowShowing()` fetches now-showing movies from Laravel API
  - [ ] `comingSoon()` fetches upcoming movies from Laravel API
  - [ ] `getMovie(slug)` fetches single movie detail
  - [ ] All methods use `useFetch` for SSR compatibility
  - [ ] Auto-imported by Nuxt

---

### Task 3: `useShowtimes` Composable

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useShowtimes.ts`
- **Details:**
  ```typescript
  function useShowtimes() {
    const getShowtimes = (movieSlug: string, date?: string) =>
      useFetch(`/api/movies/${movieSlug}/showtimes`, {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        query: { date },
        credentials: 'include',
      })
    const getShowtime = (id: string) =>
      useFetch(`/api/showtimes/${id}`, {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        credentials: 'include',
      })
    return { getShowtimes, getShowtime }
  }
  ```

- **Acceptance Criteria:**
  - [ ] `getShowtime(id)` returns showtime + full seat map with availability
  - [ ] `getShowtimes(slug, date)` returns showtimes for a movie
  - [ ] Seat statuses are accurate (available, taken, held)

---

### Task 4: `useAuth` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useAuth.ts`
- **Details:**
  Per STATE_MANAGEMENT.md. Global state via `useState`. Authentication is handled by Laravel Sanctum — the browser sends session cookies directly to the Laravel API. `nuxt-auth-utils` is used only for SSR hydration (storing user state in an encrypted cookie so the Nuxt server-renderer knows the auth state without making an API call on every page load).

  **State:**
  - `user: Ref<User | null>`
  - `isAuthenticated: ComputedRef<boolean>`

  **Methods:**
  - `login(email, password)` — `POST /api/auth/login` to Laravel, sets Sanctum session cookie, updates `user` state
  - `register(name, email, password)` — `POST /api/auth/register` to Laravel
  - `logout()` — `POST /api/auth/logout` to Laravel, clears state
  - `fetchUser()` — `GET /api/auth/me` from Laravel, called on app init to restore session

  All API calls use `$fetch` with `credentials: 'include'` to send Sanctum cookies. No server-side auth routes, middleware, or session utilities in the Nuxt frontend.

- **Acceptance Criteria:**
  - [ ] Login calls Laravel API and sets user state
  - [ ] Register calls Laravel API and sets user state
  - [ ] Logout clears session via Laravel API
  - [ ] `fetchUser()` restores session on app init
  - [ ] `isAuthenticated` reactive computed works correctly
  - [ ] Sanctum session cookies sent with all API requests

---

### Task 5: `useCart` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useCart.ts`
- **Details:**
  Per STATE_MANAGEMENT.md — global state via `useState`. Ephemeral (not persisted). No API calls — purely client-side state management.

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

### Task 6: `useSeatSelection` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useSeatSelection.ts`
- **Details:**
  Per STATE_MANAGEMENT.md — local state (not global). Tracks interaction state for AuditoriumGrid. No API calls — purely client-side state.

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

### Task 7: `useCalendarEvents` Composable

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useCalendarEvents.ts`
- **Details:**
  Per STATE_MANAGEMENT.md. Calls Laravel API directly.

  ```typescript
  function useCalendarEvents() {
    const getEvents = (month: number, year: number, type?: string) =>
      useFetch('/api/calendar/events', {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        query: { month, year, type },
        credentials: 'include',
      })
    const getEvent = (slug: string) =>
      useFetch(`/api/calendar/events/${slug}`, {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        credentials: 'include',
      })
    return { getEvents, getEvent }
  }
  ```

- **Acceptance Criteria:**
  - [ ] Calendar events filterable by type and accessibility tags
  - [ ] Event detail fetchable by slug

---

### Task 8: `useAccount` + `useGiftCards` Composables

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useAccount.ts`
  - `frontend/app/composables/useGiftCards.ts`
- **Details:**
  Per STATE_MANAGEMENT.md. Both call Laravel API directly with `credentials: 'include'` for Sanctum session auth.

  **useAccount:** Profile CRUD, orders (paginated), upcoming bookings, loyalty, payment methods CRUD. All `/api/account/*` endpoints require authentication — Sanctum middleware on the Laravel side handles this.

  **useGiftCards:** Purchase (Stripe) and balance check via `/api/gift-cards/*`.

- **Acceptance Criteria:**
  - [ ] Account endpoints require authentication (Sanctum session)
  - [ ] Order history supports pagination
  - [ ] Gift card balance check returns correct data
  - [ ] Profile update sends PATCH request

---

### Task 9: `useToast` Composable

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
  - `useAuth`: login/logout state transitions (mock API responses)
- **Integration Tests:**
  - Composables successfully call Laravel API endpoints
  - Authenticated composables fail gracefully when unauthenticated
  - CORS and credentials work correctly between frontend and Laravel
- **E2E Tests:** Deferred to Plan 13 (E2E & Polish)

## Dependencies Map

```
Task 1 (API Config) ← all composables depend on this
Task 2 (useMovies) ← needs Task 1
Task 3 (useShowtimes) ← needs Task 1
Task 4 (useAuth) ← needs Task 1
Task 5 (useCart) ← independent (no API calls)
Task 6 (useSeatSelection) ← independent (no API calls)
Task 7 (useCalendarEvents) ← needs Task 1
Task 8 (useAccount + useGiftCards) ← needs Tasks 1, 4 (auth)
Task 9 (useToast) ← independent
```

## Risks & Open Questions

1. **CORS configuration** — The Laravel backend must allow credentials from the frontend origin. Verify `config/cors.php` has `supports_credentials: true` and the frontend origin in `allowed_origins`.
2. **Sanctum cookie domain** — In dev, the frontend (Nuxt) and backend (Laravel) run on different ports. Sanctum's `stateful` domains must include the frontend origin for cookie-based auth to work cross-origin.
3. **SSR and API calls** — When Nuxt renders on the server, `useFetch` calls go from the Nuxt server process to Laravel. Ensure the `apiBaseUrl` is resolvable from within the Docker network (e.g., `http://backend:8000` for SSR vs `https://finalcut.test` for client-side).
4. **Stripe in dev** — Stripe test keys are configured in the Laravel backend. The frontend only needs the publishable key via `NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`.
