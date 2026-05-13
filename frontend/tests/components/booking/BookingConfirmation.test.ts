import { describe, it, expect, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BookingConfirmation from '~/components/booking/BookingConfirmation.vue'
import type { Booking } from '~/types/booking'

vi.mock('qrcode', () => ({
  default: { toDataURL: vi.fn().mockResolvedValue('data:image/png;base64,test') },
}))

function makeBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: 'booking-1',
    confirmationCode: 'CVF-A3X9K2',
    showtimeId: 'st-1',
    movieTitle: 'Blade Runner 2049',
    moviePosterUrl: 'https://image.tmdb.org/t/p/w500/poster.jpg',
    screenName: 'Screen 3',
    startTime: '2026-04-10T19:00:00Z',
    seats: [
      { id: 'uuid-a1', label: 'A1', section: 'Standard', price: 1200 },
      { id: 'uuid-a2', label: 'A2', section: 'Premium', price: 1800 },
    ],
    foodItems: [],
    subtotal: 3000,
    discount: 0,
    total: 3000,
    paymentMethod: 'card',
    userId: null,
    guestEmail: 'guest@example.com',
    status: 'confirmed',
    createdAt: '2026-04-08T10:00:00Z',
    ...overrides,
  }
}

describe('BookingConfirmation', () => {
  it('renders confirmation code prominently', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking() },
    })
    const code = wrapper.find('.confirmation__code')
    expect(code.exists()).toBe(true)
    expect(code.text()).toBe('CVF-A3X9K2')
  })

  it('renders movie title and showtime', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking() },
    })
    const title = wrapper.find('.confirmation__movie-title')
    expect(title.text()).toBe('Blade Runner 2049')

    const screenDd = wrapper.findAll('.confirmation__meta-item dd')
    const screenText = screenDd.map(dd => dd.text())
    expect(screenText).toContain('Screen 3')
  })

  it('renders seat list', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking() },
    })
    const seatItems = wrapper.findAll('.confirmation__seat-item')
    // 2 seats, no food items
    expect(seatItems).toHaveLength(2)
    expect(seatItems[0].text()).toContain('A1')
    expect(seatItems[0].text()).toContain('Standard')
    expect(seatItems[0].text()).toContain('$12.00')
    expect(seatItems[1].text()).toContain('A2')
    expect(seatItems[1].text()).toContain('Premium')
    expect(seatItems[1].text()).toContain('$18.00')
  })

  it('renders food items when present', async () => {
    const booking = makeBooking({
      foodItems: [
        { itemId: 'popcorn-1', name: 'Large Popcorn', quantity: 2, unitPrice: 800, totalPrice: 1600 },
      ],
      subtotal: 4600,
      total: 4600,
    })
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking },
    })
    const html = wrapper.html()
    expect(html).toContain('Food &amp; Drink')
    expect(html).toContain('Large Popcorn')
    expect(html).toContain('x2')
    expect(html).toContain('$16.00')
  })

  it('does not render food section when no food items', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking({ foodItems: [] }) },
    })
    expect(wrapper.html()).not.toContain('Food &amp; Drink')
  })

  it('shows payment summary with formatted currency', async () => {
    const booking = makeBooking({
      subtotal: 3000,
      discount: 500,
      total: 2500,
    })
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking },
    })
    const paymentRows = wrapper.findAll('.confirmation__payment-row')
    // Subtotal row
    expect(paymentRows[0].text()).toContain('Subtotal')
    expect(paymentRows[0].text()).toContain('$30.00')
    // Discount row
    expect(paymentRows[1].text()).toContain('Discount')
    expect(paymentRows[1].text()).toContain('$5.00')
    // Total row
    const totalRow = wrapper.find('.confirmation__payment-row--total')
    expect(totalRow.text()).toContain('Total Paid')
    expect(totalRow.text()).toContain('$25.00')
  })

  it('hides discount row when discount is zero', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking({ discount: 0 }) },
    })
    const discountRow = wrapper.find('.confirmation__payment-row--discount')
    expect(discountRow.exists()).toBe(false)
  })

  it('shows "View in Order History" link only when userId is set', async () => {
    const wrapperGuest = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking({ userId: null }) },
    })
    expect(wrapperGuest.html()).not.toContain('View in Order History')

    const wrapperAuth = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking({ userId: 'user-1' }) },
    })
    expect(wrapperAuth.html()).toContain('View in Order History')
    const orderLink = wrapperAuth.findAll('.confirmation__link').find(l => l.text() === 'View in Order History')
    expect(orderLink).toBeDefined()
  })

  it('always shows "Back to Home" link', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking() },
    })
    const homeLink = wrapper.findAll('.confirmation__link').find(l => l.text() === 'Back to Home')
    expect(homeLink).toBeDefined()
  })

  it('renders success banner', async () => {
    const wrapper = await mountSuspended(BookingConfirmation, {
      props: { booking: makeBooking() },
    })
    expect(wrapper.find('.confirmation__banner').exists()).toBe(true)
    expect(wrapper.find('.confirmation__title').text()).toBe('Booking Confirmed')
  })
})
