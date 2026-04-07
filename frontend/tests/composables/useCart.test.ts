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
    seatId: 'A1',
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
    expect(cart.foodItems.value).toEqual([])
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
    const seat = makeSeat({ seatId: 'A1', price: 1200 })
    cart.addSeat(seat)

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.seats.value[0]).toEqual(seat)
    expect(cart.subtotal.value).toBe(1200)
  })

  it('addSeat skips duplicate seatId', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    const seat = makeSeat({ seatId: 'A1', price: 1200 })
    cart.addSeat(seat)
    cart.addSeat(seat)

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.subtotal.value).toBe(1200)
  })

  it('removeSeat removes and updates subtotal', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.addSeat(makeSeat({ seatId: 'A2', price: 1200 }))
    cart.removeSeat('A1')

    expect(cart.seats.value).toHaveLength(1)
    expect(cart.seats.value[0].seatId).toBe('A2')
    expect(cart.subtotal.value).toBe(1200)
  })

  it('addFoodItem adds new item with quantity 1', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)

    expect(cart.foodItems.value).toHaveLength(1)
    expect(cart.foodItems.value[0]).toEqual({
      itemId: 'popcorn-1',
      name: 'Large Popcorn',
      quantity: 1,
      unitPrice: 800,
    })
  })

  it('addFoodItem increments existing item quantity', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)

    expect(cart.foodItems.value).toHaveLength(1)
    expect(cart.foodItems.value[0].quantity).toBe(2)
  })

  it('removeFoodItem decrements quantity', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.removeFoodItem('popcorn-1')

    expect(cart.foodItems.value).toHaveLength(1)
    expect(cart.foodItems.value[0].quantity).toBe(1)
  })

  it('removeFoodItem removes item at quantity 0', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.removeFoodItem('popcorn-1')

    expect(cart.foodItems.value).toHaveLength(0)
  })

  it('subtotal includes seats + food', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.addSeat(makeSeat({ seatId: 'A2', price: 1800 }))
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.addFoodItem('drink-1', 'Soda', 500)

    // seats: 1200 + 1800 = 3000, food: 800 + 500 = 1300
    expect(cart.subtotal.value).toBe(4300)
  })

  it('promo code reduces total', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.applyPromoCode('SAVE10', 300)

    expect(cart.promoCode.value).toBe('SAVE10')
    expect(cart.promoDiscount.value).toBe(300)
    expect(cart.total.value).toBe(900)
  })

  it('gift card reduces total', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.applyGiftCard('GC-1234', 500)

    expect(cart.giftCardCode.value).toBe('GC-1234')
    expect(cart.giftCardAmount.value).toBe(500)
    expect(cart.total.value).toBe(700)
  })

  it('total never goes below zero', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 500 }))
    cart.applyPromoCode('BIG', 300)
    cart.applyGiftCard('GC-1234', 500)

    // 500 - 300 - 500 = -300, clamped to 0
    expect(cart.total.value).toBe(0)
  })

  it('clear resets all state', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.applyPromoCode('SAVE10', 300)
    cart.applyGiftCard('GC-1234', 500)
    cart.clear()

    expect(cart.showtime.value).toBeNull()
    expect(cart.seats.value).toEqual([])
    expect(cart.foodItems.value).toEqual([])
    expect(cart.promoCode.value).toBeNull()
    expect(cart.promoDiscount.value).toBe(0)
    expect(cart.giftCardCode.value).toBeNull()
    expect(cart.giftCardAmount.value).toBe(0)
    expect(cart.subtotal.value).toBe(0)
    expect(cart.total.value).toBe(0)
  })

  it('shows warning toast at 10 minutes after first seat', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))

    // Advance to just before 10 minutes
    vi.advanceTimersByTime(10 * 60 * 1000 - 1)
    expect(mockToastShow).not.toHaveBeenCalled()

    // Advance to 10 minutes
    vi.advanceTimersByTime(1)
    expect(mockToastShow).toHaveBeenCalledWith({
      message: 'Your session expires in 5 minutes. Complete your purchase to keep your seats.',
      type: 'error',
      duration: 0,
    })
  })

  it('clears cart at 15 minutes after first seat', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))

    vi.advanceTimersByTime(15 * 60 * 1000)

    expect(mockToastShow).toHaveBeenCalledWith({
      message: 'Your session has expired. Selected seats have been released.',
      type: 'error',
      duration: 0,
    })
    expect(cart.seats.value).toEqual([])
    expect(cart.showtime.value).toBeNull()
  })

  it('stops timer when all seats removed (no toast after 15min)', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))

    // Remove the seat, which should stop timers
    cart.removeSeat('A1')

    // Advance past both timeouts
    vi.advanceTimersByTime(15 * 60 * 1000)

    expect(mockToastShow).not.toHaveBeenCalled()
  })
})
