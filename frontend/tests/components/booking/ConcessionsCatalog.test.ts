import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ConcessionsCatalog from '~/components/booking/ConcessionsCatalog.vue'
import type { MenuItem } from '~/types/menu-item'

function makeItem(overrides: Partial<MenuItem>): MenuItem {
  return {
    id: 'x',
    name: 'X',
    description: '',
    price: 100,
    category: 'popcorn',
    imageUrl: '',
    allergens: [],
    dietary: [],
    available: true,
    ...overrides,
  }
}

const items: MenuItem[] = [
  makeItem({ id: 'pop-1', name: 'Brown butter popcorn', category: 'popcorn' }),
  makeItem({ id: 'pop-2', name: 'Caramel popcorn', category: 'popcorn', flag: 'Tonight' }),
  makeItem({ id: 'wine-1', name: 'Programme wine', category: 'drinks', flag: 'Tonight' }),
  makeItem({ id: 'choc-1', name: 'Valrhona dark', category: 'specials', flag: 'Tonight' }),
  makeItem({ id: 'olives-1', name: 'Castelvetrano olives', category: 'snacks' }),
]

describe('ConcessionsCatalog', () => {
  it('shows all items by default with the All chip selected', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    const cards = wrapper.findAll('.prod')
    expect(cards).toHaveLength(items.length)

    const allChip = wrapper.findAll('.catalog__chip').find(c => c.text().includes('All'))!
    expect(allChip.classes()).toContain('catalog__chip--on')
  })

  it('shows per-category counts on chips with two-digit padding', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    const allChip = wrapper.findAll('.catalog__chip').find(c => c.text().includes('All'))!
    expect(allChip.find('.catalog__chip-count').text()).toBe('05')
  })

  it('filters to a single category when its chip is selected', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    const popcornChip = wrapper.findAll('.catalog__chip').find(c => c.text().includes('Popcorn'))!
    await popcornChip.trigger('click')
    expect(wrapper.findAll('.prod')).toHaveLength(2)
  })

  it('virtual "Pairings" chip filters to items where flag is set', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    const pairingsChip = wrapper.findAll('.catalog__chip').find(c => c.text().includes('Pairings'))!
    await pairingsChip.trigger('click')
    // 3 flagged items above
    expect(wrapper.findAll('.prod')).toHaveLength(3)
  })

  it('does not show the view toggle in browse-only mode', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: false },
    })
    expect(wrapper.find('.catalog__view').exists()).toBe(false)
  })

  it('switches to list layout when List is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    expect(wrapper.find('.catalog__products').classes()).not.toContain('catalog__products--list')
    const listBtn = wrapper.findAll('.catalog__view-btn').find(b => b.text() === 'List')!
    await listBtn.trigger('click')
    expect(wrapper.find('.catalog__products').classes()).toContain('catalog__products--list')
  })

  it('propagates add events from child cards', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, cart: {}, interactive: true },
    })
    const firstAdd = wrapper.findAll('.prod__add')[0]
    await firstAdd.trigger('click')
    expect(wrapper.emitted('add')).toEqual([['pop-1']])
  })

  it('passes per-item quantity from the cart prop', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, cart: { 'pop-1': 3 }, interactive: true },
    })
    const popCard = wrapper.findAll('.prod')[0]
    expect(popCard.find('.prod__qty-n').text()).toBe('3')
  })

  it('renders the section header with two-digit § number and category label', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items, interactive: true },
    })
    const head = wrapper.find('.catalog__sec-n')
    expect(head.text()).toContain('§ 01')
    expect(head.text()).toContain('All')
  })

  it('shows the empty message when no items match the active filter', async () => {
    const wrapper = await mountSuspended(ConcessionsCatalog, {
      props: { items: [], interactive: true },
    })
    expect(wrapper.find('.catalog__empty').exists()).toBe(true)
  })
})
