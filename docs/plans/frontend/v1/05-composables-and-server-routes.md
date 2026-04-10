# Plan 05: Composables & API Integration

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 01 (types, config), Plan 02 (CSS for any UI feedback)
> **Unlocks:** Plans 06, 07, 08, 09, 10 (all domain plans need data)

## Overview

Build all 10 composables that form the frontend data layer. The frontend calls the Laravel API directly — there is no Nuxt server-side BFF layer. Composables wrap `useFetch` / `$fetch` calls targeting the Laravel backend via `NUXT_PUBLIC_API_BASE_URL`. Authentication uses Laravel Sanctum (session cookies). SSR hydration via nuxt-auth-utils is deferred for v1 — auth state is restored on app init via `GET /api/auth/me`.

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
  Create a centralized API client layer that ALL composables consume. Individual composables must never independently configure credentials, headers, or error handling.

  The API client must:
  - Read `useRuntimeConfig().public.apiBaseUrl` for the base URL
  - Always include `credentials: 'include'` and `Accept: application/json`
  - **CSRF bootstrap:** Call `GET /sanctum/csrf-cookie` before the first state-changing request (POST/PATCH/DELETE) in a session. Read the `X-XSRF-TOKEN` from the cookie and send it on subsequent requests. This is automatic — composable consumers don't need to think about CSRF.
  - **Error envelope parsing:** Parse `{ errors: [{ message, field? }] }` responses into a consistent error shape. Handle dot-notation field paths for nested payloads (e.g., `foodItems.0.quantity`).
  - **Idempotency-Key support:** Provide a mechanism for composables to attach `Idempotency-Key` UUID headers on specific operations (opt-in, not universal).
  - **Retry policy:** Never auto-retry on deterministic 4xx (400/401/403/409/419/422/429). For 5xx and network errors, preserve state and offer manual retry only.
  - **Error categories:**
    - Actionable user errors (deterministic): 409 seat conflicts, 410 expired sessions, 401/419 session expired, 402 payment declined, 422 validation, 429 rate limited
    - Transport failures (non-deterministic): 5xx server errors, network timeouts — preserve form/cart state, show manual retry button

- **Acceptance Criteria:**
  - [ ] CSRF cookie is automatically fetched before first mutation
  - [ ] X-XSRF-TOKEN header sent on all subsequent requests
  - [ ] Error envelope `{ errors }` parsed into consistent shape
  - [ ] Dot-notation field paths (e.g., `foodItems.0.quantity`) correctly mapped
  - [ ] No auto-retry on 4xx status codes
  - [ ] Idempotency-Key attachment works when requested by a composable
  - [ ] Base URL read from `NUXT_PUBLIC_API_BASE_URL` runtime config
  - [ ] Credentials included for Sanctum cookie auth
  - [ ] Auto-imported by Nuxt from `app/utils/`

---

### Task 1b: `useLocations` Composable

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/composables/useLocations.ts`
- **Details:**
  Global state via `useState`. Manages the user's selected theater location. Location is a first-class concern — booking, showtime, food menu, and other calls require a location slug.

  **State:**
  - `locations: Ref<Location[]>` — all locations from `GET /api/locations`
  - `activeLocation: Ref<Location | null>` — current selection (localStorage-backed); `null` until `fetchLocations()` completes

  **Methods:**
  - `fetchLocations()` — fetches all locations on app init
  - `setLocation(slug: string)` — updates active location, writes to localStorage

  **Stale storage fallback:** On init, if the localStorage slug doesn't match any location from the API, silently fall back to the first valid location and overwrite localStorage. If no locations are available, `activeLocation` remains `null`.

- **Acceptance Criteria:**
  - [ ] Locations fetched from `GET /api/locations` on app init
  - [ ] `activeLocation` is `null` until `fetchLocations()` resolves
  - [ ] Active location persisted to localStorage when a valid location exists
  - [ ] Stale localStorage value falls back to first valid location
  - [ ] `activeLocation` remains `null` when the API returns zero locations
  - [ ] Auto-imported by Nuxt

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
  Showtimes are location-scoped. The composable accepts a `locationId` parameter (selected by the user or defaulted).

  ```typescript
  function useShowtimes() {
    const getShowtimes = (locationId: string, movieSlug: string, date?: string) =>
      useFetch(`/api/locations/${locationId}/movies/${movieSlug}/showtimes`, {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        query: { date },
        credentials: 'include',
      })
    const getShowtime = (locationId: string, id: string) =>
      useFetch(`/api/locations/${locationId}/showtimes/${id}`, {
        baseURL: useRuntimeConfig().public.apiBaseUrl,
        credentials: 'include',
      })
    return { getShowtimes, getShowtime }
  }
  ```

- **Acceptance Criteria:**
  - [ ] `getShowtime(locationId, id)` returns showtime + full seat map with availability
  - [ ] `getShowtimes(locationId, slug, date)` returns showtimes for a movie at a location
  - [ ] Seat statuses are accurate (available, taken, held)

---

### Task 4: `useAuth` Composable

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/composables/useAuth.ts`
- **Details:**
  Per STATE_MANAGEMENT.md. Global state via `useState`. Authentication is handled by Laravel Sanctum — the browser sends session cookies directly to the Laravel API. SSR hydration via nuxt-auth-utils is deferred for v1. On app init, `fetchUser()` calls `GET /api/auth/me` to restore session state from the Sanctum session cookie.

  **CSRF bootstrap:** `login()` and `register()` must call `GET /sanctum/csrf-cookie` before the POST (handled automatically by the centralized API client from Task 1).

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

  **useAccount:** Profile CRUD, orders (paginated via `?page=N&limit=M`, where `page` selects the results page and `limit` controls page size), upcoming bookings (no query params needed — always returns upcoming confirmed bookings), loyalty, payment methods CRUD. All `/api/account/*` endpoints require authentication — Sanctum middleware on the Laravel side handles this.

  **useGiftCards:** Purchase (Stripe) and balance check via `/api/gift-cards/*`. Gift card purchase requires an `Idempotency-Key` UUID header on the POST request. Generate a UUID per purchase attempt and store it so retries reuse the same key. The purchase can return a `requiresAction` response for 3DS — handle identically to booking 3DS flow, then call `POST /api/gift-cards/confirm` with the `paymentIntentId`. Show 'retrieving status...' UX on duplicate submission detection (409 with payload mismatch).

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
- **E2E Tests:** Deferred to Plan 12 (E2E & Polish)

## Dependencies Map

```
Task 1 (API Config) ← all composables depend on this
Task 1b (useLocations) ← needs Task 1
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
3. **Stripe in dev** — Stripe test keys are configured in the Laravel backend. The frontend only needs the publishable key via `NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`.
4. **Location selection required** — Most data-fetching composables (useShowtimes, food menu, bookings) require an active location. The app must fetch locations and establish a default before these composables can be called. `useLocations` handles this on app init.
