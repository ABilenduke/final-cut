import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { ref } from 'vue'
import HomeFoodDrink from '~/components/home/HomeFoodDrink.vue'
import type { MenuItem } from '~/types/menu-item'

// The component self-fetches the cross-location menu via useFoodMenu().fetchAll().
const fetchAllMock = vi.fn()

vi.mock('~/composables/useFoodMenu', () => ({
  useFoodMenu: () => ({ fetchAll: fetchAllMock }),
}))

function makeItem(overrides: Partial<MenuItem> = {}): MenuItem {
  return {
    id: 'id',
    name: 'Item',
    description: 'A description.',
    price: 650,
    category: 'popcorn',
    imageUrl: '',
    allergens: [],
    dietary: [],
    available: true,
    ...overrides,
  }
}

function mockMenu(items: MenuItem[] | null) {
  fetchAllMock.mockReturnValue({
    data: ref(items === null ? null : { data: items }),
  })
}

beforeEach(() => {
  fetchAllMock.mockReset()
})

describe('HomeFoodDrink', () => {
  it('renders a curated trio from the API with real names and formatted prices', async () => {
    mockMenu([
      makeItem({ id: 'p1', name: 'Brown Butter Popcorn', price: 650, category: 'popcorn' }),
      makeItem({ id: 's1', name: 'Loaded Truffle Fries', price: 1200, category: 'specials' }),
      makeItem({ id: 'd1', name: 'Cold Brew Negroni', price: 1600, category: 'drinks' }),
      makeItem({ id: 'd2', name: 'Bottled Water', price: 400, category: 'drinks' }),
    ])

    const wrapper = await mountSuspended(HomeFoodDrink)

    const cards = wrapper.findAll('.food__card')
    expect(cards).toHaveLength(3)

    const text = wrapper.text()
    expect(text).toContain('Brown Butter Popcorn')
    expect(text).toContain('Loaded Truffle Fries')
    expect(text).toContain('Cold Brew Negroni')

    // cents → $X.XX via formatCurrency
    expect(text).toContain('$6.50')
    expect(text).toContain('$12.00')
    expect(text).toContain('$16.00')

    // only the curated trio — the 4th drink must not appear
    expect(text).not.toContain('Bottled Water')
  })

  it('falls back to the static catalog trio when the API yields zero items', async () => {
    mockMenu([])
    const wrapper = await mountSuspended(HomeFoodDrink)
    expect(wrapper.findAll('.food__card')).toHaveLength(3)
  })

  it('still renders the section heading', async () => {
    mockMenu([makeItem({ name: 'Solo Popcorn' })])
    const wrapper = await mountSuspended(HomeFoodDrink)
    expect(wrapper.find('#food-heading').exists()).toBe(true)
  })
})
