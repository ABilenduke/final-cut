# State Management

How data flows through the application. What lives where. What's global, what's local, and why.

---

## 1. Decision: `useState` Composables, No Pinia

The app has three pieces of global state: auth, cart, and toasts. Pinia adds ceremony (stores, getters, actions, devtools plugin) without proportional benefit for this scope. Nuxt's built-in `useState` is SSR-safe, reactive, and composable. If the state management needs outgrow this pattern, Pinia can be adopted incrementally — composable interfaces stay the same.

---

## 2. Global State

Global state is shared across components and persists across page navigations within a session. Managed via composables that use `useState` internally.

### Auth — `useAuth`

**File:** `app/composables/useAuth.ts`

**State:**
- `user: Ref<User | null>` — current user, null when not authenticated
- `isAuthenticated: ComputedRef<boolean>` — derived from user presence

**Methods:**
- `login(email: string, password: string): Promise<void>` — POST `/api/auth/login`, sets user state + session cookie
- `register(name: string, email: string, password: string): Promise<void>` — POST `/api/auth/register`, sets user state + session cookie
- `logout(): Promise<void>` — POST `/api/auth/logout`, clears user state + cookie
- `fetchUser(): Promise<void>` — GET `/api/auth/me`, refreshes user state from server (called on app init to restore session)

**Persistence:** Two complementary session mechanisms:
- **Laravel Sanctum** (backend) — authenticates API requests via a session cookie. The session is stored in Redis server-side. Sanctum's `AuthenticateSession` middleware validates the password hash on each request, automatically invalidating sessions after password changes.
- **nuxt-auth-utils** (frontend) — stores user state in a sealed encrypted HTTP-only cookie for Nuxt SSR hydration. This allows the server-side renderer to know the user is logged in without making an API call on every page load. The `user` ref in `useState` is hydrated from this cookie on SSR and revalidated on client init via `GET /api/auth/me`.

**Usage:**
```typescript
const { user, isAuthenticated, login, logout } = useAuth()
```

---

### Location — `useLocations`

**File:** `app/composables/useLocations.ts`

**State:**
- `locations: Ref<Location[]>` — all available theater locations
- `activeLocation: Ref<Location | null>` — user's selected location (localStorage-backed); `null` until `fetchLocations()` completes and remains `null` if the API returns no locations

**Methods:**
- `fetchLocations(): Promise<void>` — GET `/api/locations`, called on app init
- `setLocation(slug: string): void` — updates active location, writes to localStorage

**Persistence:** Active location slug stored in localStorage. On init, if the stored slug doesn't match any location from the API, falls back to the first valid location and overwrites localStorage. If the API returns no locations, `activeLocation` remains `null`.

**Usage:**
```typescript
const { activeLocation, setLocation } = useLocations()
```

---

### Cart — `useCart`

**File:** `app/composables/useCart.ts`

**State:**
- `showtime: Ref<Showtime | null>` — the showtime being purchased
- `seats: Ref<BookingSeat[]>` — selected seats
- `foodItems: Ref<Array<{ itemId: string; name: string; quantity: number; unitPrice: number }>>` — food pre-orders
- `promoCode: Ref<string | null>` — applied promo code
- `promoDiscount: Ref<number>` — discount amount in cents
- `giftCardCode: Ref<string | null>` — applied gift card code
- `giftCardAmount: Ref<number>` — amount to deduct from gift card
- `subtotal: ComputedRef<number>` — seats + food in cents
- `total: ComputedRef<number>` — subtotal - discount - gift card

**Methods:**
- `initializeCart(showtime: Showtime): void` — sets showtime, clears previous selections, starts session timer
- `addSeat(seat: BookingSeat): void` — adds seat, updates subtotal
- `removeSeat(seatId: string): void` — removes seat
- `addFoodItem(itemId: string, name: string, unitPrice: number): void` — adds or increments food item
- `removeFoodItem(itemId: string): void` — decrements or removes food item
- `applyPromoCode(code: string, discount: number): void` — sets promo and discount
- `removePromoCode(): void` — clears promo
- `applyGiftCard(code: string, amount: number): void` — sets gift card redemption
- `removeGiftCard(): void` — clears gift card
- `clear(): void` — resets entire cart to empty state, stops timer

**Lifecycle:** Ephemeral. Created when entering `/purchase/:showtimeId`, destroyed on completion, navigation away, or 15-minute timeout. NOT persisted to localStorage or cookies — seat selections should not survive page reload (prevents stale holds).

**Usage:**
```typescript
const { seats, total, addSeat, removeSeat, clear } = useCart()
```

---

### Toasts — `useToast`

**File:** `app/composables/useToast.ts`

**State:**
- `queue: Ref<Array<{ id: string; message: string; type: 'info' | 'success' | 'error'; duration: number }>>` — active toasts

**Methods:**
- `show(options: { message: string; type?: string; duration?: number }): void` — adds toast to queue, auto-generates ID
- `dismiss(id: string): void` — removes specific toast

The CvToast component in the default layout reads from this queue and renders/animates each toast. Auto-dismissal is handled per-toast based on duration.

**Usage:**
```typescript
const { show } = useToast()
show({ message: 'Seat A1 selected', type: 'success' })
```

---

## 3. Data-Fetching Composables

These composables wrap `useFetch` / `useAsyncData` for server route access. They don't manage global state — they return reactive data scoped to the calling component. Nuxt handles deduplication, SSR hydration, and client-side caching.

### useMovies

**File:** `app/composables/useMovies.ts`

```typescript
function useMovies() {
  const nowShowing = (options?: { genre?: number }) =>
    useFetch('/api/movies', { query: { status: 'now_showing', ...options } })

  const comingSoon = (options?: { genre?: number }) =>
    useFetch('/api/movies', { query: { status: 'coming_soon', ...options } })

  const getMovie = (slug: string) =>
    useFetch(`/api/movies/${slug}`)

  return { nowShowing, comingSoon, getMovie }
}
```

### useShowtimes

**File:** `app/composables/useShowtimes.ts`

```typescript
function useShowtimes() {
  const getShowtimes = (movieSlug: string, date?: string) =>
    useFetch(`/api/movies/${movieSlug}/showtimes`, { query: { date } })

  const getShowtime = (id: string) =>
    useFetch(`/api/showtimes/${id}`)

  return { getShowtimes, getShowtime }
}
```

### useCalendarEvents

**File:** `app/composables/useCalendarEvents.ts`

```typescript
function useCalendarEvents() {
  const getEvents = (month: number, year: number, type?: string) =>
    useFetch('/api/calendar/events', { query: { month, year, type } })

  const getEvent = (slug: string) =>
    useFetch(`/api/calendar/events/${slug}`)

  return { getEvents, getEvent }
}
```

### useSeatSelection

**File:** `app/composables/useSeatSelection.ts`

Manages the local interaction state for the AuditoriumGrid. This is **not** the same as the cart — it tracks which seat has keyboard focus, hover state, and local selection before committing to the cart.

**Important:** Client-side selection is tracked separately from server seat availability. `Seat.status` (`available | taken | held`) reflects the server's truth. A local `selectedSeatIds` set tracks the user's selections without mutating the server model. This separation ensures that seat availability refreshes (polling or WebSocket) can merge cleanly without colliding with the user's in-progress selections.

```typescript
function useSeatSelection(initialSeats: Seat[]) {
  const seats = ref<Seat[]>(initialSeats)          // Server truth: status is available | taken | held
  const selectedSeatIds = ref<Set<string>>(new Set()) // Client-only selection state
  const focusedSeatId = ref<string | null>(null)

  const toggleSeat = (seatId: string): void => { /* add/remove from selectedSeatIds */ }
  const isAvailable = (seatId: string): boolean => { /* checks seats status === 'available' */ }
  const isSelected = (seatId: string): boolean => { /* checks selectedSeatIds.has(seatId) */ }
  const selectedSeats = computed(() =>
    seats.value.filter(s => selectedSeatIds.value.has(s.id))
  )
  const moveFocus = (direction: 'up' | 'down' | 'left' | 'right'): void => { /* ... */ }

  // On seat data refresh: merge new availability, deselect any seats that became taken
  const updateSeats = (newSeats: Seat[]): void => {
    seats.value = newSeats
    for (const id of selectedSeatIds.value) {
      const seat = newSeats.find(s => s.id === id)
      if (!seat || seat.status !== 'available') {
        selectedSeatIds.value.delete(id)
        // Trigger toast: "Seat [id] is no longer available"
      }
    }
  }

  return { seats, selectedSeatIds, focusedSeatId, toggleSeat, isAvailable, isSelected, selectedSeats, moveFocus, updateSeats }
}
```

### useAccount

**File:** `app/composables/useAccount.ts`

```typescript
function useAccount() {
  const profile = () => useFetch('/api/account/profile')
  const orders = (page?: number) => useFetch('/api/account/orders', { query: { page } })
  const bookings = () => useFetch('/api/account/bookings', { query: { upcoming: true } })
  const loyalty = () => useFetch('/api/account/loyalty')
  const updateProfile = (data: Partial<UserProfile>) =>
    useFetch('/api/account/profile', { method: 'PATCH', body: data })

  return { profile, orders, bookings, loyalty, updateProfile }
}
```

### useGiftCards

**File:** `app/composables/useGiftCards.ts`

```typescript
function useGiftCards() {
  const purchase = (data: {
    amount: number
    recipientEmail: string
    recipientName: string
    senderName: string
    message: string
    paymentMethodId: string
  }) => useFetch('/api/gift-cards/purchase', { method: 'POST', body: data })

  const checkBalance = (code: string) =>
    useFetch('/api/gift-cards/balance', { query: { code } })

  return { purchase, checkBalance }
}
```

---

## 4. Local State

Local state is component-scoped. It uses Vue's `ref()` and `reactive()` directly — no composables, no global store.

### What stays local

| State | Where | Why |
| ----- | ----- | --- |
| Form field values | Form components | Only the form cares about draft input |
| Form validation errors | Form components | Cleared on submit, no cross-component need |
| Accordion open/closed | CvAccordion | UI toggle per instance |
| Modal open/closed | Per modal instance | Each modal manages its own visibility |
| Calendar current month/year | CalendarGrid | Navigation state for one calendar |
| Calendar selected date | CalendarGrid / page | Passed via props/emits, not global |
| Active tab (category tabs) | MenuCategoryTabs, ShowtimeSelector | Local UI state |
| Movie list filters (genre, rating) | Movie listing pages | URL query params, not state |
| Search query | Search components | Ephemeral input |
| Seat hover state | AuditoriumSeat | Visual feedback, no persistence |

### URL as State

For filterable/pageable content, state lives in the URL query string:

- `/movies?status=now_showing&genre=28` — movie filter
- `/account/orders?page=2` — pagination
- `/whats-on?month=4&year=2026&type=special_event` — calendar filters

This makes filtered views shareable and bookmarkable. Composables read from `useRoute().query` rather than maintaining separate state.

---

## 5. State Flow Diagram

```
                    ┌──────────────┐
                    │  Server API  │
                    │  (Nuxt /api) │
                    └──────┬───────┘
                           │
                    useFetch / useAsyncData
                           │
              ┌────────────┼────────────────┐
              │            │                │
    ┌─────────▼──┐  ┌──────▼──────┐  ┌──────▼──────┐
    │  useAuth   │  │  useMovies  │  │ useAccount  │
    │  (global)  │  │  (per-call) │  │  (per-call) │
    │  useState  │  │  useFetch   │  │  useFetch   │
    └─────┬──────┘  └──────┬──────┘  └──────┬──────┘
          │                │                │
          │         ┌──────▼──────┐         │
          │         │   useCart   │         │
          │         │   (global)  │         │
          │         │   useState  │         │
          │         └──────┬──────┘         │
          │                │                │
    ┌───��─▼────────────────▼────────────────▼─────┐
    │              Components / Pages              │
    │        (local ref/reactive for UI state)     │
    └──────────────────────────────────────────────┘
```

---

## 6. Rules

1. **No Pinia.** If this changes in the future, composable interfaces remain the same — only the internal implementation switches.
2. **useState for global, ref for local.** If state is needed by siblings or unrelated components, promote to a composable with `useState`. Otherwise, keep it local.
3. **URL for filterable state.** Anything the user might want to share or bookmark goes in query params.
4. **No localStorage for cart.** Stale seat selections cause confusion. Cart dies with the session.
5. **Server is the source of truth.** Client state is optimistic for UX, but the server validates everything at transaction time (especially seat availability).
