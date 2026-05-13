import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutHoldTimer from '~/components/booking/CheckoutHoldTimer.vue'

const sampleSeats = [
  { id: 'uuid-f7', label: 'F7', section: 'Standard', price: 1850 },
  { id: 'uuid-f8', label: 'F8', section: 'Standard', price: 1850 },
]

describe('CheckoutHoldTimer', () => {
  it('formats time remaining as mm:ss', async () => {
    const wrapper = await mountSuspended(CheckoutHoldTimer, {
      props: { timeRemaining: 462, seats: sampleSeats },
    })
    expect(wrapper.find('.hold-strip__timer').text()).toBe('07:42')
  })

  it('clamps negative time remaining to 00:00', async () => {
    const wrapper = await mountSuspended(CheckoutHoldTimer, {
      props: { timeRemaining: -5, seats: sampleSeats },
    })
    expect(wrapper.find('.hold-strip__timer').text()).toBe('00:00')
  })

  it('derives row letter and seat numbers from seat ids', async () => {
    const wrapper = await mountSuspended(CheckoutHoldTimer, {
      props: { timeRemaining: 600, seats: sampleSeats, auditorium: 'Aud 01' },
    })
    const text = wrapper.text()
    expect(text).toContain('Row F')
    expect(text).toContain('Seats 7, 8')
    expect(text).toContain('Aud 01')
  })

  it('emits release when Release seats is clicked', async () => {
    const wrapper = await mountSuspended(CheckoutHoldTimer, {
      props: { timeRemaining: 300, seats: sampleSeats },
    })
    const buttons = wrapper.findAll('button.hold-strip__link')
    const release = buttons.find(b => b.text().toLowerCase().includes('release'))
    expect(release).toBeDefined()
    await release!.trigger('click')
    expect(wrapper.emitted('release')).toBeDefined()
  })

  it('renders change-seats link when href is provided', async () => {
    const wrapper = await mountSuspended(CheckoutHoldTimer, {
      props: {
        timeRemaining: 600,
        seats: sampleSeats,
        changeSeatsHref: '/purchase/show-123',
      },
    })
    expect(wrapper.text()).toContain('Change seats')
  })
})
