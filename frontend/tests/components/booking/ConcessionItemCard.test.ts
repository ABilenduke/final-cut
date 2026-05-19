import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ConcessionItemCard from '~/components/booking/ConcessionItemCard.vue'
import type { MenuItem } from '~/types/menu-item'

function makeItem(overrides: Partial<MenuItem> = {}): MenuItem {
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
    size: 'Large · 32 oz',
    curator: 'House',
    gradient: 'radial-gradient(#fff, #000)',
    ...overrides,
  }
}

describe('ConcessionItemCard', () => {
  it('renders name, description, price, size, and curator', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 0, interactive: true },
    })
    const text = wrapper.text()
    expect(text).toContain('Large Popcorn')
    expect(text).toContain('Brown-butter popcorn for sharing.')
    expect(text).toContain('$9.99')
    expect(text).toContain('Large · 32 oz')
    expect(text).toContain('House')
  })

  it('renders the flag badge when item.flag is set', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ flag: 'Tonight' }),
        quantity: 0,
        interactive: true,
      },
    })
    expect(wrapper.find('.prod__flag').exists()).toBe(true)
    expect(wrapper.find('.prod__flag').text()).toBe('Tonight')
  })

  it('omits the flag badge when item.flag is not set', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 0, interactive: true },
    })
    expect(wrapper.find('.prod__flag').exists()).toBe(false)
  })

  it('shows the Add button when quantity is 0 and interactive', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 0, interactive: true },
    })
    const addBtn = wrapper.find('.prod__add')
    expect(addBtn.exists()).toBe(true)
    expect(addBtn.text()).toContain('Add')
    expect(wrapper.find('.prod__qty').exists()).toBe(false)
  })

  it('shows the qty stepper when quantity > 0 and interactive', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 2, interactive: true },
    })
    expect(wrapper.find('.prod__qty').exists()).toBe(true)
    expect(wrapper.find('.prod__qty-n').text()).toBe('2')
    expect(wrapper.find('.prod__add').exists()).toBe(false)
  })

  it('hides all controls in browse-only mode regardless of quantity', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 0, interactive: false },
    })
    expect(wrapper.find('.prod__add').exists()).toBe(false)
    expect(wrapper.find('.prod__qty').exists()).toBe(false)
  })

  it('emits add with the item id when Add is clicked', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 0, interactive: true },
    })
    await wrapper.find('.prod__add').trigger('click')
    expect(wrapper.emitted('add')).toEqual([['pop-lg']])
  })

  it('emits increment when + is clicked while quantity > 0', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 1, interactive: true },
    })
    const buttons = wrapper.findAll('.prod__qty-btn')
    // Order: − first, + second
    await buttons[1].trigger('click')
    expect(wrapper.emitted('increment')).toEqual([['pop-lg']])
  })

  it('emits decrement when − is clicked while quantity > 0', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 1, interactive: true },
    })
    const buttons = wrapper.findAll('.prod__qty-btn')
    await buttons[0].trigger('click')
    expect(wrapper.emitted('decrement')).toEqual([['pop-lg']])
  })

  it('marks the card with the prod--on class when quantity > 0', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: { item: makeItem(), quantity: 1, interactive: true },
    })
    expect(wrapper.find('.prod').classes()).toContain('prod--on')
  })

  it('renders the GF dietary mark with its accent class', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ dietary: ['gluten_free'] }),
        quantity: 0,
        interactive: true,
      },
    })
    const gfMark = wrapper.findAll('.prod__diet').find(d => d.text() === 'GF')
    expect(gfMark).toBeDefined()
    expect(gfMark!.classes()).toContain('prod__diet--gf')
  })

  // ─── Thumbnail rendering ──────────────────────────────────────────────────
  // Items with a concession photo render the <img>; items without (the five
  // editorial fallbacks: negroni, olives, chocolate, madeleine, caramel)
  // keep the gradient + glyph placeholder. Both paths show the cat-tag.

  it('renders the concession photo when imageUrl is set', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ imageUrl: 'https://cdn.example.com/finalcut/concessions/popcorn_lg.webp' }),
        quantity: 0,
        interactive: true,
      },
    })
    const img = wrapper.find('img.prod__img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/finalcut/concessions/popcorn_lg.webp')
    expect(img.attributes('loading')).toBe('lazy')
    // Glyph placeholder is suppressed when the photo is present.
    expect(wrapper.find('.prod__glyph').exists()).toBe(false)
    // Thumb gets the has-image modifier so the scanline overlay is hidden.
    expect(wrapper.find('.prod__thumb').classes()).toContain('prod__thumb--has-image')
  })

  it('falls back to gradient + glyph when imageUrl is empty', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ imageUrl: '' }),
        quantity: 0,
        interactive: true,
      },
    })
    expect(wrapper.find('img.prod__img').exists()).toBe(false)
    expect(wrapper.find('.prod__glyph').exists()).toBe(true)
    expect(wrapper.find('.prod__thumb').classes()).not.toContain('prod__thumb--has-image')
  })

  it('falls back to gradient + glyph when the image fails to load', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ imageUrl: 'https://cdn.example.com/finalcut/concessions/popcorn_lg.webp' }),
        quantity: 0,
        interactive: true,
      },
    })
    expect(wrapper.find('img.prod__img').exists()).toBe(true)

    await wrapper.find('img.prod__img').trigger('error')

    expect(wrapper.find('img.prod__img').exists()).toBe(false)
    expect(wrapper.find('.prod__glyph').exists()).toBe(true)
  })

  it('retries the image when imageUrl changes after a prior load failure', async () => {
    const wrapper = await mountSuspended(ConcessionItemCard, {
      props: {
        item: makeItem({ imageUrl: 'https://cdn.example.com/finalcut/concessions/popcorn_lg.webp' }),
        quantity: 0,
        interactive: true,
      },
    })

    // Simulate the CDN object being missing on the first render.
    await wrapper.find('img.prod__img').trigger('error')
    expect(wrapper.find('img.prod__img').exists()).toBe(false)
    expect(wrapper.find('.prod__glyph').exists()).toBe(true)

    // Parent swaps in a fresh URL (API data replacing a fallback fixture, or
    // the row mutating in place under the same :key).
    await wrapper.setProps({
      item: makeItem({ imageUrl: 'https://cdn.example.com/finalcut/concessions/popcorn_lg_v2.webp' }),
    })

    const img = wrapper.find('img.prod__img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/finalcut/concessions/popcorn_lg_v2.webp')
    expect(wrapper.find('.prod__glyph').exists()).toBe(false)
  })
})
