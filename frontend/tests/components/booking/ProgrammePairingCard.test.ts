import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ProgrammePairingCard from '~/components/booking/ProgrammePairingCard.vue'
import type { ProgrammePairing } from '~/types/programme-pairing'

function makePairing(overrides: Partial<ProgrammePairing> = {}): ProgrammePairing {
  return {
    id: 'pairing-test',
    movieSlug: 'interstellar',
    number: '17',
    tag: 'Programme Pairing No. 17 · Tonight only',
    title: 'The Endurance flight.',
    titleAccent: 'Endurance',
    titleTail: 'flight.',
    curatorNote: 'Chosen by programming to travel with the film.',
    courses: [
      { id: 'c1', name: 'Caramel popcorn · sea salt', price: 999 },
      { id: 'c2', name: 'Programme wine — Chenin, 125 ml', price: 1199 },
      { id: 'c3', name: 'Valrhona dark · 72% square', price: 599 },
    ],
    bundlePrice: 2299,
    curatorName: 'Nadia Obéron',
    curatorRole: 'Head of Programming',
    validUntil: 'Save $5 tonight',
    glyph: 'E',
    ...overrides,
  }
}

describe('ProgrammePairingCard', () => {
  it('renders the title with italic accent', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    const title = wrapper.find('.pairing__title')
    expect(title.text()).toContain('The')
    expect(title.find('em').text()).toBe('Endurance')
    expect(title.text()).toContain('flight.')
  })

  it('renders all three courses with roman numerals and prices', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    const items = wrapper.findAll('.pairing__items li')
    expect(items).toHaveLength(3)
    expect(items[0].text()).toContain('I.')
    expect(items[0].text()).toContain('Caramel popcorn · sea salt')
    expect(items[0].text()).toContain('$9.99')
    expect(items[1].text()).toContain('II.')
    expect(items[2].text()).toContain('III.')
  })

  it('renders the bundle price and the slashed à-la-carte total when there are savings', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    expect(wrapper.find('.pairing__now').text()).toBe('$22.99')
    // À la carte total: 999 + 1199 + 599 = 2797 = $27.97
    expect(wrapper.find('.pairing__was').text()).toBe('$27.97')
  })

  it('omits the slashed price when there are no savings', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: {
        pairing: makePairing({ bundlePrice: 2797 }),
        added: false,
      },
    })
    expect(wrapper.find('.pairing__was').exists()).toBe(false)
  })

  it('shows "Add the flight" label when not added', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    const btn = wrapper.find('.pairing__btn')
    expect(btn.text()).toContain('Add the flight')
    expect(btn.attributes('aria-pressed')).toBe('false')
    expect(btn.classes()).not.toContain('pairing__btn--added')
  })

  it('shows "✓ Added" label and added class when added', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: true },
    })
    const btn = wrapper.find('.pairing__btn')
    expect(btn.text()).toContain('Added')
    expect(btn.attributes('aria-pressed')).toBe('true')
    expect(btn.classes()).toContain('pairing__btn--added')
  })

  it('emits toggle when the button is clicked', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    await wrapper.find('.pairing__btn').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('renders the curator byline with name and role', async () => {
    const wrapper = await mountSuspended(ProgrammePairingCard, {
      props: { pairing: makePairing(), added: false },
    })
    const by = wrapper.find('.pairing__by')
    expect(by.text()).toContain('Nadia Obéron')
    expect(by.text()).toContain('Head of Programming')
  })
})
