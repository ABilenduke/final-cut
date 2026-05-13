import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import OrderHistoryList from '~/components/account/OrderHistoryList.vue'
import type { Booking } from '~/types/booking'

function mockBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: '1',
    confirmationCode: 'CVF-A3X9K2',
    showtimeId: 'st-1',
    movieTitle: 'Blade Runner 2099',
    moviePosterUrl: 'https://example.com/poster.jpg',
    screenName: 'Screen 1',
    startTime: '2026-04-03T19:00:00Z',
    seats: [{ id: 'uuid-a1', label: 'A1', section: 'Standard', price: 1500 }],
    foodItems: [],
    subtotal: 1500,
    discount: 0,
    total: 1500,
    paymentMethod: 'card',
    userId: '1',
    guestEmail: null,
    status: 'confirmed',
    createdAt: '2026-04-01T12:00:00Z',
    ...overrides,
  }
}

describe('OrderHistoryList', () => {
  it('shows empty state when no orders', async () => {
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders: [], total: 0, currentPage: 1, perPage: 10 },
    })
    expect(wrapper.find('.order-history__empty').exists()).toBe(true)
    expect(wrapper.text()).toContain('No orders yet')
  })

  it('renders accordion for each order', async () => {
    const orders = [
      mockBooking({ id: '1' }),
      mockBooking({ id: '2', movieTitle: 'Dune Part Three' }),
    ]
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders, total: 2, currentPage: 1, perPage: 10 },
    })
    const accordions = wrapper.findAll('.order-history__list > *')
    expect(accordions).toHaveLength(2)
  })

  it('shows pagination when multiple pages', async () => {
    const orders = [mockBooking()]
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders, total: 25, currentPage: 1, perPage: 10 },
    })
    const pagination = wrapper.find('.order-history__pagination')
    expect(pagination.exists()).toBe(true)
    expect(pagination.text()).toContain('Page 1 of 3')
  })

  it('hides pagination when only one page', async () => {
    const orders = [mockBooking()]
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders, total: 1, currentPage: 1, perPage: 10 },
    })
    expect(wrapper.find('.order-history__pagination').exists()).toBe(false)
  })

  it('emits page-change on next click', async () => {
    const orders = [mockBooking()]
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders, total: 25, currentPage: 1, perPage: 10 },
    })
    const buttons = wrapper.find('.order-history__pagination').findAll('button')
    const nextButton = buttons.find(b => b.text().includes('Next'))
    await nextButton!.trigger('click')

    const emitted = wrapper.emitted('page-change')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe(2)
  })

  it('emits page-change on prev click', async () => {
    const orders = [mockBooking()]
    const wrapper = await mountSuspended(OrderHistoryList, {
      props: { orders, total: 25, currentPage: 2, perPage: 10 },
    })
    const buttons = wrapper.find('.order-history__pagination').findAll('button')
    const prevButton = buttons.find(b => b.text().includes('Previous'))
    await prevButton!.trigger('click')

    const emitted = wrapper.emitted('page-change')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe(1)
  })
})
