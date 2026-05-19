import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import SeatProjectionistPick from '~/components/booking/SeatProjectionistPick.vue'

const pair = [
  { id: '46547fb2-574b-5d69-96da-528e70c9a72d', label: 'D6', row: 'D', number: 6 },
  { id: '1651e760-5adb-5c5c-b9f7-657dd96efb8b', label: 'D7', row: 'D', number: 7 },
]

describe('SeatProjectionistPick', () => {
  it('renders nothing when no picks are available', async () => {
    const wrapper = await mountSuspended(SeatProjectionistPick, {
      props: { pickSeats: [] },
    })
    expect(wrapper.find('.pick').exists()).toBe(false)
  })

  it('renders human-readable Row + seat numbers for a pair', async () => {
    const wrapper = await mountSuspended(SeatProjectionistPick, {
      props: { pickSeats: pair },
    })
    const title = wrapper.find('.pick__title em')
    expect(title.text()).toBe('Row D · seats 6 & 7')
  })

  it('never leaks seat UUIDs into the rendered headline', async () => {
    const wrapper = await mountSuspended(SeatProjectionistPick, {
      props: { pickSeats: pair },
    })
    const rendered = wrapper.find('.pick__title').text()
    for (const seat of pair) {
      expect(rendered).not.toContain(seat.id)
    }
    expect(rendered).not.toMatch(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)
  })

  it('falls back to "Seat <label>" when only one pick is provided', async () => {
    const wrapper = await mountSuspended(SeatProjectionistPick, {
      props: { pickSeats: [pair[0]] },
    })
    expect(wrapper.find('.pick__title em').text()).toBe('Seat D6')
  })

  it('emits take-pick when the action button is clicked', async () => {
    const wrapper = await mountSuspended(SeatProjectionistPick, {
      props: { pickSeats: pair },
    })
    await wrapper.find('.pick__btn').trigger('click')
    expect(wrapper.emitted('take-pick')).toHaveLength(1)
  })
})
