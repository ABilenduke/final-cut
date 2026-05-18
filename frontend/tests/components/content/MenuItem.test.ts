import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MenuItem from '~/components/content/MenuItem.vue'
import type { MenuItem as MenuItemType } from '~/types/menu-item'

const ALL_VENUES = [
  { slug: 'downtown', name: 'Downtown' },
  { slug: 'uptown', name: 'Uptown' },
  { slug: 'midtown', name: 'Midtown' },
]

function makeItem(overrides: Partial<MenuItemType> = {}): MenuItemType {
  return {
    id: 'pop-lg',
    name: 'Large Popcorn',
    description: 'Brown-butter popcorn for sharing.',
    price: 999,
    category: 'popcorn',
    imageUrl: 'https://cdn.example.test/finalcut/concessions/popcorn_lg.webp',
    allergens: ['dairy'],
    dietary: ['vegetarian'],
    available: true,
    available_at: ['downtown', 'uptown', 'midtown'],
    ...overrides,
  }
}

describe('MenuItem', () => {
  // ─── Core content ──────────────────────────────────────────────────────────

  it('renders item name, description, and price', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: { item: makeItem(), venues: ALL_VENUES },
    })
    const text = wrapper.text()
    expect(text).toContain('Large Popcorn')
    expect(text).toContain('Brown-butter popcorn for sharing.')
    expect(text).toContain('$9.99')
  })

  it('renders dietary badges when present', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: { item: makeItem({ dietary: ['gluten_free', 'vegan'] }), venues: ALL_VENUES },
    })
    const text = wrapper.text()
    expect(text).toContain('GF')
    expect(text).toContain('Vegan')
  })

  it('omits the dietary section when dietary array is empty', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: { item: makeItem({ dietary: [] }), venues: ALL_VENUES },
    })
    expect(wrapper.find('.menu-item__dietary').exists()).toBe(false)
  })

  // ─── Availability captions ─────────────────────────────────────────────────

  it('renders NO availability caption when item is available at ALL venues', async () => {
    // available_at matches the full roster → no caption.
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: ['downtown', 'uptown', 'midtown'] }),
        venues: ALL_VENUES,
      },
    })
    expect(wrapper.find('.menu-item__availability').exists()).toBe(false)
  })

  it('renders "Available at X only" when item has a single-venue available_at', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: ['downtown'] }),
        venues: ALL_VENUES,
      },
    })
    const caption = wrapper.find('.menu-item__availability')
    expect(caption.exists()).toBe(true)
    expect(caption.text()).toBe('Available at Downtown only')
  })

  it('renders "Available at X · Y" when item has a subset of venues (2 of 3+)', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: ['downtown', 'uptown'] }),
        venues: ALL_VENUES,
      },
    })
    const caption = wrapper.find('.menu-item__availability')
    expect(caption.exists()).toBe(true)
    expect(caption.text()).toBe('Available at Downtown · Uptown')
  })

  it('renders NO caption when available_at is empty (item filtered upstream)', async () => {
    // available_at: [] items should be filtered before reaching this component,
    // but the component gracefully renders no caption in case one slips through.
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: [] }),
        venues: ALL_VENUES,
      },
    })
    expect(wrapper.find('.menu-item__availability').exists()).toBe(false)
  })

  it('renders NO caption when venues prop is empty (graceful degradation)', async () => {
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: ['downtown'] }),
        venues: [],
      },
    })
    expect(wrapper.find('.menu-item__availability').exists()).toBe(false)
  })

  it('renders NO caption when venues prop is not provided', async () => {
    // venues is optional; omitting it suppresses captions rather than crashing.
    const wrapper = await mountSuspended(MenuItem, {
      props: { item: makeItem({ available_at: ['downtown'] }) },
    })
    expect(wrapper.find('.menu-item__availability').exists()).toBe(false)
  })

  it('renders NO caption when item has no available_at field at all', async () => {
    // Legacy / per-location items may not carry available_at.
    const item = makeItem()
    delete (item as any).available_at

    const wrapper = await mountSuspended(MenuItem, {
      props: { item, venues: ALL_VENUES },
    })
    expect(wrapper.find('.menu-item__availability').exists()).toBe(false)
  })

  it('handles unknown slugs gracefully (skips them in the caption)', async () => {
    // available_at contains a slug not in venues — skip that entry.
    const wrapper = await mountSuspended(MenuItem, {
      props: {
        item: makeItem({ available_at: ['downtown', 'unknown-venue'] }),
        venues: ALL_VENUES,
      },
    })
    const caption = wrapper.find('.menu-item__availability')
    // 'unknown-venue' resolves to nothing — only 'downtown' is rendered.
    // With one valid result and item count < venues.length → "X only".
    expect(caption.exists()).toBe(true)
    expect(caption.text()).toBe('Available at Downtown only')
  })
})
