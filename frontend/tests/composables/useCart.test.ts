import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'

const mockToastShow = vi.fn()
vi.mock('~/composables/useToast', () => ({
  useToast: () => ({ show: mockToastShow, dismiss: vi.fn(), toasts: { value: [] } }),
}))

import { useCart } from '~/composables/useCart'

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'test-movie',
    movieTitle: 'Test Movie',
    screenId: 'screen-1',
    screenName: 'Screen 1',
    startTime: '2026-04-10T19:00:00Z',
    endTime: '2026-04-10T21:00:00Z',
    priceStandard: 1200,
    pricePremium: 1800,
    priceAccessible: 1000,
    ...overrides,
  }
}

function makeSeat(overrides: Partial<BookingSeat> = {}): BookingSeat {
  return {
    id: 'seat-uuid-1',
    label: 'A1',
    section: 'Standard',
    price: 1200,
    ...overrides,
  }
}

describe('useCart', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockToastShow.mockClear()
    const cart = useCart()
    cart.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('initializes with showtime and empty state', () => {
    const cart = useCart()
    const showtime = makeShowtime()
    cart.initializeCart(showtime)

    expect(cart.showtime.value).toEqual(showtime)
    expect(cart.seats.value).toEqual([])
    expect(cart.promoCode.value).toBeNull()
    expect(cart.promoDiscount.value).toBe(0)
    expect(cart.giftCardCode.value).toBeNull()
    expect(cart.giftCardAmount.value).toBe(0)
    expect(cart.subtotal.value).toBe(0)
    expect(cart.total.value).toBe(0)
  })

  it('addSeat adds to seats and updates subtotal', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    const seat = makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 })
    cart.addSeat(seat)

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.seats.value[0]).toEqual(seat)
    expect(cart.subtotal.value).toBe(1200)
  })

  it('addSeat skips duplicate by id (UUID), not label', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    const seat = makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 })
    cart.addSeat(seat)
    cart.addSeat(seat)

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.subtotal.value).toBe(1200)
  })

  it('removeSeat removes by id (UUID) and updates subtotal', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))
    cart.addSeat(makeSeat({ id: 'uuid-a2', label: 'A2', price: 1200 }))
    cart.removeSeat('uuid-a1')

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.seats.value[0].id).toBe('uuid-a2')
    expect(cart.seats.value[0].label).toBe('A2')
    expect(cart.subtotal.value).toBe(1200)
  })

  it('subtotal is seats-only (concessions are not part of the booking flow)', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))
    cart.addSeat(makeSeat({ id: 'uuid-a2', label: 'A2', price: 1800 }))

    expect(cart.subtotal.value).toBe(3000)
  })

  it('promo code reduces total', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))
    cart.applyPromoCode('SAVE10', 300)

    expect(cart.promoCode.value).toBe('SAVE10')
    expect(cart.promoDiscount.value).toBe(300)
    expect(cart.total.value).toBe(900)
  })

  it('gift card reduces total', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))
    cart.applyGiftCard('GC-1234', 500)

    expect(cart.giftCardCode.value).toBe('GC-1234')
    expect(cart.giftCardAmount.value).toBe(500)
    expect(cart.total.value).toBe(700)
  })

  it('total never goes below zero', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 500 }))
    cart.applyPromoCode('BIG', 300)
    cart.applyGiftCard('GC-1234', 500)

    // 500 - 300 - 500 = -300, clamped to 0
    expect(cart.total.value).toBe(0)
  })

  it('clear resets all state', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))
    cart.applyPromoCode('SAVE10', 300)
    cart.applyGiftCard('GC-1234', 500)
    cart.clear()

    expect(cart.showtime.value).toBeNull()
    expect(cart.seats.value).toEqual([])
    expect(cart.promoCode.value).toBeNull()
    expect(cart.promoDiscount.value).toBe(0)
    expect(cart.giftCardCode.value).toBeNull()
    expect(cart.giftCardAmount.value).toBe(0)
    expect(cart.subtotal.value).toBe(0)
    expect(cart.total.value).toBe(0)
  })

  it('initializeCart on the SAME showtime preserves seat selections', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime({ id: 'st-1' }))
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))

    cart.initializeCart(makeShowtime({ id: 'st-1' }))
    expect(cart.seats.value).toHaveLength(1)
  })

  it('initializeCart on a different showtime clears selections', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime({ id: 'st-1' }))
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))

    cart.initializeCart(makeShowtime({ id: 'st-2' }))
    expect(cart.seats.value).toEqual([])
  })

  // SESSION_HOLD_MINUTES = 8, WARNING_LEAD_MINUTES = 2 → warning at 6min, expiry at 8min
  it('shows warning toast 2 minutes before the 8-minute hold expires', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))

    // Advance to just before the 6-minute warning threshold
    vi.advanceTimersByTime(6 * 60 * 1000 - 1)
    expect(mockToastShow).not.toHaveBeenCalled()

    // Cross the threshold
    vi.advanceTimersByTime(1)
    expect(mockToastShow).toHaveBeenCalledWith({
      message: 'Your session expires in 2 minutes. Complete your purchase to keep your seats.',
      type: 'error',
      duration: 0,
    })
  })

  it('clears cart at 8 minutes after the first seat was added', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))

    vi.advanceTimersByTime(8 * 60 * 1000)

    expect(mockToastShow).toHaveBeenCalledWith({
      message: 'Your session has expired. Selected seats have been released.',
      type: 'error',
      duration: 0,
    })
    expect(cart.seats.value).toEqual([])
    expect(cart.showtime.value).toBeNull()
  })

  it('stops timer when all seats removed (no toast after 8min)', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ id: 'uuid-a1', label: 'A1', price: 1200 }))

    // Remove the seat by UUID, which should stop timers
    cart.removeSeat('uuid-a1')

    // Advance past both timeouts
    vi.advanceTimersByTime(8 * 60 * 1000)

    expect(mockToastShow).not.toHaveBeenCalled()
  })
})
