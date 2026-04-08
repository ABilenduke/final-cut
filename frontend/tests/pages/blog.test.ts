import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BlogIndex from '~/pages/blog/index.vue'

describe('Blog Listing Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.blog-page__title').text()).toBe('Blog')
  })

  it('renders blog post cards from static data', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    const cards = wrapper.findAll('.blog-post-card__title')
    expect(cards.length).toBeGreaterThan(0)
  })

  it('renders posts in the ensemble grid', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.ensemble').exists()).toBe(true)
  })
})
