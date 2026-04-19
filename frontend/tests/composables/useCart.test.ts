import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'
import type { ProgrammePairing } from '~/types/programme-pairing'

const mockToastShow = vi.fn()
vi.mock('~/composables/useToast', () => ({
  useToast: () => ({ show: mockToastShow, dismiss: vi.fn(), toasts: { value: [] } }),
}))

import { useCart } from '~/composables/useCart'

function makePairing(overrides: Partial<ProgrammePairing> = {}): ProgrammePairing {
  return {
    id: 'pairing-test',
    movieSlug: 'interstellar',
    number: '17',
    tag: 'Programme Pairing No. 17',
    title: 'The Endurance flight.',
    titleAccent: 'Endurance',
    titleTail: 'flight.',
    curatorNote: 'note',
    courses: [
      { id: 'c1', name: 'Course 1', price: 999 },
      { id: 'c2', name: 'Course 2', price: 1199 },
      { id: 'c3', name: 'Course 3', price: 599 },
    ],
    bundlePrice: 2299,
    curatorName: 'Curator',
    curatorRole: 'Role',
    glyph: 'E',
    ...overrides,
  }
}

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
    expect(cart.pairing.value).toBeNull()
    expect(cart.pairingPrice.value).toBe(0)
    expect(cart.pairingSavings.value).toBe(0)
    expect(cart.pairingCoursesTotal.value).toBe(0)
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

  it('clear resets all state including the pairing', () => {
    const cart = useCart()
    cart.initializeCart(makeShowtime())
    cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
    cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
    cart.applyPromoCode('SAVE10', 300)
    cart.applyGiftCard('GC-1234', 500)
    cart.setPairing(makePairing())
    cart.clear()

    expect(cart.showtime.value).toBeNull()
    expect(cart.seats.value).toEqual([])
    expect(cart.foodItems.value).toEqual([])
    expect(cart.promoCode.value).toBeNull()
    expect(cart.promoDiscount.value).toBe(0)
    expect(cart.giftCardCode.value).toBeNull()
    expect(cart.giftCardAmount.value).toBe(0)
    expect(cart.pairing.value).toBeNull()
    expect(cart.subtotal.value).toBe(0)
    expect(cart.total.value).toBe(0)
  })

  describe('programme pairings', () => {
    it('setPairing stores the pairing and exposes its price + savings', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime())
      cart.setPairing(makePairing())

      expect(cart.pairing.value?.id).toBe('pairing-test')
      // Course sum: 999 + 1199 + 599 = 2797
      expect(cart.pairingCoursesTotal.value).toBe(2797)
      expect(cart.pairingPrice.value).toBe(2299)
      expect(cart.pairingSavings.value).toBe(498)
    })

    it('clearPairing removes the pairing and resets derived values', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime())
      cart.setPairing(makePairing())
      cart.clearPairing()

      expect(cart.pairing.value).toBeNull()
      expect(cart.pairingCoursesTotal.value).toBe(0)
      expect(cart.pairingPrice.value).toBe(0)
      expect(cart.pairingSavings.value).toBe(0)
    })

    it('subtotal does NOT include the pairing price (backend correctness)', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime())
      cart.addSeat(makeSeat({ seatId: 'A1', price: 1200 }))
      cart.addFoodItem('popcorn-1', 'Large Popcorn', 800)
      cart.setPairing(makePairing())

      // 1200 (seat) + 800 (food) = 2000. Pairing is excluded.
      expect(cart.subtotal.value).toBe(2000)
      expect(cart.total.value).toBe(2000)
    })

    it('reports zero savings when bundlePrice is not actually discounted', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime())
      cart.setPairing(makePairing({ bundlePrice: 2797 }))

      expect(cart.pairingSavings.value).toBe(0)
    })

    it('initializeCart on a different showtime clears the pairing', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime({ id: 'st-1' }))
      cart.setPairing(makePairing())
      expect(cart.pairing.value).not.toBeNull()

      cart.initializeCart(makeShowtime({ id: 'st-2' }))
      expect(cart.pairing.value).toBeNull()
    })

    it('initializeCart on the SAME showtime preserves the pairing', () => {
      const cart = useCart()
      cart.initializeCart(makeShowtime({ id: 'st-1' }))
      cart.setPairing(makePairing())

      cart.initializeCart(makeShowtime({ id: 'st-1' }))
      expect(cart.pairing.value?.id).toBe('pairing-test')
    })
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
