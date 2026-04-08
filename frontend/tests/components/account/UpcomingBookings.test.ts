import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import UpcomingBookings from '~/components/account/UpcomingBookings.vue'
import type { Booking } from '~/types/booking'

function mockBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: '1',
    confirmationCode: 'CVF-A3X9K2',
    showtimeId: 'st-1',
    movieTitle: 'Blade Runner 2099',
    moviePosterUrl: 'https://example.com/poster.jpg',
    screenName: 'Screen 1',
    startTime: '2026-04-10T19:00:00Z',
    seats: [
      { seatId: 'A1', section: 'Standard', price: 1500 },
      { seatId: 'A2', section: 'Standard', price: 1500 },
    ],
    foodItems: [],
    subtotal: 3000,
    discount: 0,
    total: 3000,
    paymentMethod: 'card',
    userId: '1',
    guestEmail: null,
    status: 'confirmed',
    createdAt: '2026-04-01T12:00:00Z',
    ...overrides,
  }
}

describe('UpcomingBookings', () => {
  it('shows empty state with browse movies link when no bookings', async () => {
    const wrapper = await mountSuspended(UpcomingBookings, {
      props: { bookings: [] },
    })
    expect(wrapper.find('.upcoming-bookings__empty').exists()).toBe(true)
    expect(wrapper.text()).toContain('No upcoming bookings')
    const link = wrapper.find('a[href="/movies"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('Browse Movies')
  })

  it('renders a card for each booking', async () => {
    const bookings = [
      mockBooking({ id: '1' }),
      mockBooking({ id: '2', movieTitle: 'Dune Part Three' }),
    ]
    const wrapper = await mountSuspended(UpcomingBookings, {
      props: { bookings },
    })
    const cards = wrapper.findAll('.upcoming-bookings__card')
    expect(cards).toHaveLength(2)
  })

  it('shows movie poster image when available', async () => {
    const wrapper = await mountSuspended(UpcomingBookings, {
      props: { bookings: [mockBooking()] },
    })
    const img = wrapper.find('.upcoming-bookings__poster-img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/poster.jpg')
    expect(img.attributes('alt')).toBe('Blade Runner 2099 poster')
  })

  it('shows poster fallback when no poster URL', async () => {
    const wrapper = await mountSuspended(UpcomingBookings, {
      props: { bookings: [mockBooking({ moviePosterUrl: '' })] },
    })
    expect(wrapper.find('.upcoming-bookings__poster-img').exists()).toBe(false)
    expect(wrapper.find('.upcoming-bookings__poster-fallback').exists()).toBe(true)
  })

  it('shows seats summary', async () => {
    const wrapper = await mountSuspended(UpcomingBookings, {
      props: { bookings: [mockBooking()] },
    })
    const seats = wrapper.find('.upcoming-bookings__seats')
    expect(seats.text()).toContain('A1')
    expect(seats.text()).toContain('A2')
  })
})
