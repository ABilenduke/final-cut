import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'

export interface CartFoodItem {
  itemId: string
  name: string
  quantity: number
  unitPrice: number
}

const SESSION_TIMEOUT_MS = 15 * 60 * 1000
const WARNING_TIMEOUT_MS = 10 * 60 * 1000
const SESSION_TIMEOUT_SECONDS = Math.floor(SESSION_TIMEOUT_MS / 1000)

// Module-scoped timer IDs (not useState — these are process-level, not SSR-safe state)
let warningTimerId: ReturnType<typeof setTimeout> | null = null
let expiryTimerId: ReturnType<typeof setTimeout> | null = null
let tickIntervalId: ReturnType<typeof setInterval> | null = null
let sessionStartedAt: number | null = null

export function useCart() {
  const showtime = useState<Showtime | null>('cart-showtime', () => null)
  const seats = useState<BookingSeat[]>('cart-seats', () => [])
  const foodItems = useState<CartFoodItem[]>('cart-food-items', () => [])
  const promoCode = useState<string | null>('cart-promo-code', () => null)
  const promoDiscount = useState<number>('cart-promo-discount', () => 0)
  const giftCardCode = useState<string | null>('cart-gift-card-code', () => null)
  const giftCardAmount = useState<number>('cart-gift-card-amount', () => 0)
  const timeRemaining = useState<number>('cart-time-remaining', () => 0)

  function stopTimers(): void {
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

  const subtotal = computed<number>(() => {
    const seatsTotal = seats.value.reduce((sum, s) => sum + s.price, 0)
    const foodTotal = foodItems.value.reduce((sum, f) => sum + f.unitPrice * f.quantity, 0)
    return seatsTotal + foodTotal
  })

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
    // Only start if not already running
    if (warningTimerId !== null) return

    sessionStartedAt = Date.now()
    timeRemaining.value = SESSION_TIMEOUT_SECONDS
    tickIntervalId = setInterval(tick, 1000)

    warningTimerId = setTimeout(() => {
      showToast({
        message: 'Your session expires in 5 minutes. Complete your purchase to keep your seats.',
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
    foodItems.value = []
    promoCode.value = null
    promoDiscount.value = 0
    giftCardCode.value = null
    giftCardAmount.value = 0
  }

  function addSeat(seat: BookingSeat): void {
    if (seats.value.some((s) => s.seatId === seat.seatId)) return
    seats.value = [...seats.value, seat]
    if (seats.value.length === 1) {
      startTimers()
    }
  }

  function removeSeat(seatId: string): void {
    seats.value = seats.value.filter((s) => s.seatId !== seatId)
    if (seats.value.length === 0) {
      stopTimers()
    }
  }

  function addFoodItem(itemId: string, name: string, unitPrice: number): void {
    const existing = foodItems.value.find((f) => f.itemId === itemId)
    if (existing) {
      foodItems.value = foodItems.value.map((f) =>
        f.itemId === itemId ? { ...f, quantity: f.quantity + 1 } : f,
      )
    } else {
      foodItems.value = [...foodItems.value, { itemId, name, quantity: 1, unitPrice }]
    }
  }

  function removeFoodItem(itemId: string): void {
    const existing = foodItems.value.find((f) => f.itemId === itemId)
    if (!existing) return
    if (existing.quantity <= 1) {
      foodItems.value = foodItems.value.filter((f) => f.itemId !== itemId)
    } else {
      foodItems.value = foodItems.value.map((f) =>
        f.itemId === itemId ? { ...f, quantity: f.quantity - 1 } : f,
      )
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
    foodItems.value = []
    promoCode.value = null
    promoDiscount.value = 0
    giftCardCode.value = null
    giftCardAmount.value = 0
  }

  return {
    showtime: readonly(showtime),
    seats: readonly(seats),
    foodItems: readonly(foodItems),
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
    addFoodItem,
    removeFoodItem,
    applyPromoCode,
    removePromoCode,
    applyGiftCard,
    removeGiftCard,
    clear,
  }
}
