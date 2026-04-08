import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MenuCategoryTabs from '~/components/content/MenuCategoryTabs.vue'

const categories = ['popcorn', 'drinks', 'snacks', 'combos']

describe('MenuCategoryTabs', () => {
  it('renders all category buttons', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    const buttons = wrapper.findAll('button')
    expect(buttons).toHaveLength(4)
    expect(buttons[0].text()).toBe('Popcorn')
    expect(buttons[1].text()).toBe('Drinks')
  })

  it('marks active category with aria-pressed', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'drinks' },
    })
    const buttons = wrapper.findAll('button')
    expect(buttons[1].attributes('aria-pressed')).toBe('true')
    expect(buttons[0].attributes('aria-pressed')).toBe('false')
  })

  it('active button has tabindex 0, others have -1', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'snacks' },
    })
    const buttons = wrapper.findAll('button')
    expect(buttons[2].attributes('tabindex')).toBe('0')
    expect(buttons[0].attributes('tabindex')).toBe('-1')
    expect(buttons[1].attributes('tabindex')).toBe('-1')
  })

  it('emits select on click', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    await wrapper.findAll('button')[2].trigger('click')
    expect(wrapper.emitted('select')?.[0]).toEqual(['snacks'])
  })

  it('ArrowRight moves to next category and emits select', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    await wrapper.findAll('button')[0].trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('select')?.[0]).toEqual(['drinks'])
  })

  it('ArrowLeft wraps to last category from first', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    await wrapper.findAll('button')[0].trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.emitted('select')?.[0]).toEqual(['combos'])
  })

  it('Home moves to first category', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'snacks' },
    })
    await wrapper.findAll('button')[2].trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('select')?.[0]).toEqual(['popcorn'])
  })

  it('End moves to last category', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    await wrapper.findAll('button')[0].trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('select')?.[0]).toEqual(['combos'])
  })

  it('renders nav element with aria-label', async () => {
    const wrapper = await mountSuspended(MenuCategoryTabs, {
      props: { categories, active: 'popcorn' },
    })
    const nav = wrapper.find('nav')
    expect(nav.exists()).toBe(true)
    expect(nav.attributes('aria-label')).toBe('Menu categories')
  })
})
