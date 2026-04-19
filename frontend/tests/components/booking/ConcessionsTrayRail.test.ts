import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ConcessionsTrayRail from '~/components/booking/ConcessionsTrayRail.vue'
import type { Showtime } from '~/types/showtime'
import type { ProgrammePairing } from '~/types/programme-pairing'

const showtime: Showtime = {
  id: 'st-1',
  movieId: 1,
  movieSlug: 'interstellar',
  movieTitle: 'Interstellar',
  screenId: 'screen-1',
  screenName: 'Aud 01',
  startTime: '2026-04-17T19:30:00Z',
  endTime: '2026-04-17T22:30:00Z',
  priceStandard: 1850,
  pricePremium: 2200,
  priceAccessible: 1500,
}

const seats = [
  { seatId: 'F7', section: 'Standard', price: 1850 },
  { seatId: 'F8', section: 'Standard', price: 1850 },
]

const foodItems = [
  { itemId: 'pop-lg', name: 'Large Popcorn', quantity: 2, unitPrice: 999 },
]

const pairing: ProgrammePairing = {
  id: 'pairing-test',
  movieSlug: 'interstellar',
  number: '17',
  tag: 'Programme Pairing No. 17 · Tonight only',
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
}

describe('ConcessionsTrayRail', () => {
  it('renders empty-state when no food items and no pairing', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems: [],
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    expect(wrapper.find('.tray__empty').exists()).toBe(true)
    expect(wrapper.find('.tray__empty').text()).toContain('Your tray is empty')
  })

  it('renders food items and the show summary when populated', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    const text = wrapper.text()
    expect(text).toContain('Interstellar')
    expect(text).toContain('Aud 01')
    expect(text).toContain('Large Popcorn')
    expect(text).toContain('Row F')
  })

  it('shows the pairing line first when a pairing is added', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing,
        pairingPrice: 2299,
        pairingSavings: 498,
      },
    })
    const items = wrapper.findAll('.tray__item')
    expect(items[0].classes()).toContain('tray__item--pairing')
    expect(items[0].text()).toContain('The Endurance flight.')
  })

  it('shows the savings line only when pairingSavings > 0', async () => {
    const withSavings = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing,
        pairingPrice: 2299,
        pairingSavings: 498,
      },
    })
    expect(withSavings.find('.tray__line--saving').exists()).toBe(true)

    const withoutSavings = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing,
        pairingPrice: 2299,
        pairingSavings: 0,
      },
    })
    expect(withoutSavings.find('.tray__line--saving').exists()).toBe(false)
  })

  it('excludes the pairing from the charged-now total (bar-side tab)', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,             // 2 × 1850 = 3700
        foodItems,         // 2 × 999 = 1998
        pairing,
        pairingPrice: 2299,
        pairingSavings: 498,
      },
    })
    // Programme pairings are paid at the bar on collection, so the
    // running-total only tallies the seats + food the user is actually
    // charged for on the checkout step. 3700 + 1998 = 5698 = $56.98.
    expect(wrapper.find('.tray__grand-v').text()).toBe('$56.98')
  })

  it('lists the pairing price on its own row, flagged as bar-side', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing,
        pairingPrice: 2299,
        pairingSavings: 498,
      },
    })
    const pairingRow = wrapper.find('.tray__line--pairing')
    expect(pairingRow.exists()).toBe(true)
    expect(pairingRow.text()).toContain('Programme pairing')
    expect(pairingRow.text()).toContain('pay at the bar')
    expect(pairingRow.text()).toContain('$22.99')
  })

  it('emits removePairing when the pairing Remove link is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems: [],
        pairing,
        pairingPrice: 2299,
        pairingSavings: 498,
      },
    })
    const removeBtn = wrapper.findAll('.tray__rm').find(b => b.text() === 'Remove')!
    await removeBtn.trigger('click')
    expect(wrapper.emitted('removePairing')).toHaveLength(1)
  })

  it('emits removeItem with itemId when a food Remove link is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    await wrapper.find('.tray__rm').trigger('click')
    expect(wrapper.emitted('removeItem')).toEqual([['pop-lg']])
  })

  it('emits continue when the gold pay button is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems,
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    await wrapper.find('.tray__pay').trigger('click')
    expect(wrapper.emitted('continue')).toHaveLength(1)
  })

  it('emits skip when the Skip button is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats,
        foodItems: [],
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    await wrapper.find('.tray__skip').trigger('click')
    expect(wrapper.emitted('skip')).toHaveLength(1)
  })

  it('disables Continue when no seats are selected', async () => {
    const wrapper = await mountSuspended(ConcessionsTrayRail, {
      props: {
        showtime,
        seats: [],
        foodItems: [],
        pairing: null,
        pairingPrice: 0,
        pairingSavings: 0,
      },
    })
    expect(wrapper.find('.tray__pay').attributes('disabled')).toBeDefined()
  })
})
