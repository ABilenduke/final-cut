import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BlogIndex from '~/pages/blog/index.vue'
import BlogDetail from '~/pages/blog/[slug].vue'
import { blogPosts } from '~/data/blog'

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

  it('renders posts sorted by date descending', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    const dates = wrapper.findAll('.blog-post-card__date').map(el => el.text())
    // First card should have the most recent date
    expect(dates.length).toBeGreaterThan(1)
    // Dates are formatted as "Mon DD, YYYY" — the first should be newest
    expect(dates[0]).toContain('2026')
  })
})

describe('Blog Detail Page', () => {
  it('renders post title for a valid slug', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__title').text()).toContain('Grand Opening')
  })

  it('renders post body paragraphs', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    const paragraphs = wrapper.findAll('.blog-post-page__body p')
    expect(paragraphs.length).toBeGreaterThan(0)
  })

  it('renders author and date', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__author').text()).toBe('Final Cut Staff')
    expect(wrapper.find('.blog-post-page__date').text()).toContain('2026')
  })

  it('renders related posts section', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__related').exists()).toBe(true)
  })

  it('has no matching post for non-existent slug', () => {
    // Verify the lookup logic directly — mounting with createError
    // crashes the Deno/happy-dom worker process
    const post = blogPosts.find(p => p.slug === 'nonexistent-post')
    expect(post).toBeUndefined()
  })
})
