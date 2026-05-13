import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'

// The seat-hold duration the UI promises ("eight minutes" — see
// SeatSelectionHouseRules, SeatSelectionHero, MovieSeatPreview,
// SeatSelectionRail's '08:00' fallback). Centralized here so the user-
// visible copy and the actual timer can never drift.
const SESSION_HOLD_MINUTES = 8
const WARNING_LEAD_MINUTES = 2
const SESSION_TIMEOUT_MS = SESSION_HOLD_MINUTES * 60 * 1000
const WARNING_TIMEOUT_MS = Math.max(SESSION_HOLD_MINUTES - WARNING_LEAD_MINUTES, 0) * 60 * 1000
const SESSION_TIMEOUT_SECONDS = Math.floor(SESSION_TIMEOUT_MS / 1000)

// Module-scoped timer IDs (not useState — these are process-level, not SSR-safe state)
let warningTimerId: ReturnType<typeof setTimeout> | null = null
let expiryTimerId: ReturnType<typeof setTimeout> | null = null
let tickIntervalId: ReturnType<typeof setInterval> | null = null
let sessionStartedAt: number | null = null

export function useCart() {
  const showtime = useState<Showtime | null>('cart-showtime', () => null)
  const seats = useState<BookingSeat[]>('cart-seats', () => [])
  const promoCode = useState<string | null>('cart-promo-code', () => null)
  const promoDiscount = useState<number>('cart-promo-discount', () => 0)
  const giftCardCode = useState<string | null>('cart-gift-card-code', () => null)
  const giftCardAmount = useState<number>('cart-gift-card-amount', () => 0)
  const timeRemaining = useState<number>('cart-time-remaining', () => 0)

  function stopTimers(): void {
    // Timer IDs live in module scope; they are only ever set on the client
    // (see startTimers guard). On the server there is nothing to clear —
    // short-circuit so we never leak an interval into a worker request.
    if (!import.meta.client) return
    if (warningTimerId !== null) {
      clearTimeout(warningTimerId)
      warningTimerId = null
    }
    if (expiryTimerId !== null) {
      clearTimeout(expiryTimerId)
      expiryTimerId = null
    }
    if (tickIntervalId !== null) {
      clearInterval(tickIntervalId)
      tickIntervalId = null
    }
    sessionStartedAt = null
    timeRemaining.value = 0
  }

  // Concessions are not part of the booking flow — they live on /food-drink
  // as a browse-only catalog. The Stripe charge created at /purchase/checkout
  // only covers seats.
  const subtotal = computed<number>(() =>
    seats.value.reduce((sum, s) => sum + s.price, 0),
  )

  const total = computed<number>(() =>
    Math.max(0, subtotal.value - promoDiscount.value - giftCardAmount.value),
  )

  const { show: showToast } = useToast()

  function tick(): void {
    if (sessionStartedAt === null) return
    const elapsed = Math.floor((Date.now() - sessionStartedAt) / 1000)
    timeRemaining.value = Math.max(0, SESSION_TIMEOUT_SECONDS - elapsed)
  }

  function startTimers(): void {
    // Cart hold timers must only run in the browser. Module-scoped timer
    // IDs would otherwise be shared across concurrent SSR requests and
    // could keep the Node worker alive between renders.
    if (!import.meta.client) return
    // Only start if not already running
    if (warningTimerId !== null) return

    sessionStartedAt = Date.now()
    timeRemaining.value = SESSION_TIMEOUT_SECONDS
    tickIntervalId = setInterval(tick, 1000)

    warningTimerId = setTimeout(() => {
      showToast({
        message: `Your session expires in ${WARNING_LEAD_MINUTES} minutes. Complete your purchase to keep your seats.`,
        type: 'error',
        duration: 0,
      })
    }, WARNING_TIMEOUT_MS)

    expiryTimerId = setTimeout(() => {
      showToast({
        message: 'Your session has expired. Selected seats have been released.',
        type: 'error',
        duration: 0,
      })
      clear()
    }, SESSION_TIMEOUT_MS)
  }

  function initializeCart(st: Showtime): void {
    // Re-entering the seat page for the SAME showtime (e.g. after
    // navigating back from checkout via the step indicator) should
    // preserve the user's selections. Only reset when the showtime
    // actually changes — switching showtimes logically invalidates
    // the old selections.
    if (showtime.value?.id === st.id) {
      showtime.value = st
      return
    }

    stopTimers()
    showtime.value = st
    seats.value = []
    promoCode.value = null
    promoDiscount.value = 0
    giftCardCode.value = null
    giftCardAmount.value = 0
  }

  function addSeat(seat: BookingSeat): void {
    if (seats.value.some((s) => s.id === seat.id)) return
    seats.value = [...seats.value, seat]
    if (seats.value.length === 1) {
      startTimers()
    }
  }

  function removeSeat(seatId: string): void {
    seats.value = seats.value.filter((s) => s.id !== seatId)
    if (seats.value.length === 0) {
      stopTimers()
    }
  }

  function applyPromoCode(code: string, discount: number): void {
    promoCode.value = code
    promoDiscount.value = discount
  }

  function removePromoCode(): void {
    promoCode.value = null
    promoDiscount.value = 0
  }

  function applyGiftCard(code: string, amount: number): void {
    giftCardCode.value = code
    giftCardAmount.value = amount
  }

  function removeGiftCard(): void {
    giftCardCode.value = null
    giftCardAmount.value = 0
  }

  function clear(): void {
    stopTimers()
    showtime.value = null
    seats.value = []
    promoCode.value = null
    promoDiscount.value = 0
    giftCardCode.value = null
    giftCardAmount.value = 0
  }

  return {
    showtime: readonly(showtime),
    seats: readonly(seats),
    promoCode: readonly(promoCode),
    promoDiscount: readonly(promoDiscount),
    giftCardCode: readonly(giftCardCode),
    giftCardAmount: readonly(giftCardAmount),
    timeRemaining: readonly(timeRemaining),
    subtotal,
    total,
    initializeCart,
    addSeat,
    removeSeat,
    applyPromoCode,
    removePromoCode,
    applyGiftCard,
    removeGiftCard,
    clear,
  }
}
