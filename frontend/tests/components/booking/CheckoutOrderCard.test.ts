import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutOrderCard from '~/components/booking/CheckoutOrderCard.vue'

const showtime = {
  id: 'show-123',
  movieId: 1,
  movieSlug: 'dune-part-three',
  movieTitle: 'Dune: Part Three',
  screenId: 's1',
  screenName: 'Aud 01',
  startTime: '2026-04-17T19:30:00Z',
  endTime: '2026-04-17T22:28:00Z',
  priceStandard: 1850,
  pricePremium: 2500,
  priceAccessible: 1500,
}

const seats = [
  { seatId: 'F7', section: 'Standard', price: 1850 },
  { seatId: 'F8', section: 'Standard', price: 1850 },
]

describe('CheckoutOrderCard', () => {
  it('splits the title at the colon into main + italic accent', async () => {
    const wrapper = await mountSuspended(CheckoutOrderCard, {
      props: { showtime, seats },
    })
    const title = wrapper.find('.order-card__title')
    expect(title.text()).toContain('Dune')
    expect(title.find('em').exists()).toBe(true)
    expect(title.find('em').text()).toContain(': Part Three')
  })

  it('renders a meta grid with 6 bullets', async () => {
    const wrapper = await mountSuspended(CheckoutOrderCard, {
      props: { showtime, seats },
    })
    const bullets = wrapper.findAll('.order-card__bullets > div')
    expect(bullets.length).toBe(6)
    const keys = bullets.map(b => b.find('dt').text())
    expect(keys).toContain('Date')
    expect(keys).toContain('Time')
    expect(keys).toContain('Auditorium')
    expect(keys).toContain('Runtime')
    expect(keys).toContain('Doors')
  })

  it('renders seat callout with middle dot separator', async () => {
    const wrapper = await mountSuspended(CheckoutOrderCard, {
      props: { showtime, seats },
    })
    const callout = wrapper.find('.order-card__seats-num')
    expect(callout.text()).toBe('F·7 / F·8')
  })

  it('renders change seats link when href is provided', async () => {
    const wrapper = await mountSuspended(CheckoutOrderCard, {
      props: { showtime, seats, changeSeatsHref: '/purchase/show-123' },
    })
    expect(wrapper.text()).toContain('Change seats')
  })

  it('handles movie titles without a colon gracefully', async () => {
    const wrapper = await mountSuspended(CheckoutOrderCard, {
      props: {
        showtime: { ...showtime, movieTitle: 'Blade Runner' },
        seats,
      },
    })
    const title = wrapper.find('.order-card__title')
    expect(title.text()).toBe('Blade Runner')
    expect(title.find('em').exists()).toBe(false)
  })
})
