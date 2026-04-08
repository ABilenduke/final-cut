import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BlogPostCard from '~/components/content/BlogPostCard.vue'

function makePost(overrides: Record<string, unknown> = {}) {
  return {
    title: 'Behind the Screens',
    slug: 'behind-the-screens',
    excerpt: 'A look at the technology behind our auditoriums.',
    date: '2026-03-28',
    author: 'Marcus Chen',
    imageUrl: 'https://example.com/blog/behind-the-screens.jpg',
    ...overrides,
  }
}

describe('BlogPostCard', () => {
  it('renders post title', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    expect(wrapper.find('.blog-post-card__title').text()).toBe('Behind the Screens')
  })

  it('renders post excerpt', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    expect(wrapper.find('.blog-post-card__excerpt').text()).toBe(
      'A look at the technology behind our auditoriums.'
    )
  })

  it('renders formatted date', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    expect(wrapper.find('.blog-post-card__date').text()).toBe('Mar 28, 2026')
  })

  it('renders author name', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    expect(wrapper.find('.blog-post-card__author').text()).toBe('Marcus Chen')
  })

  it('renders image when imageUrl provided', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    const img = wrapper.find('.blog-post-card__image')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/blog/behind-the-screens.jpg')
    expect(img.attributes('alt')).toBe('Behind the Screens')
  })

  it('renders placeholder when no imageUrl', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost({ imageUrl: '' }) },
    })
    expect(wrapper.find('.blog-post-card__image').exists()).toBe(false)
    expect(wrapper.find('.blog-post-card__image-placeholder').exists()).toBe(true)
  })

  it('links to /blog/:slug', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost({ slug: 'test-post' }) },
    })
    const card = wrapper.find('.cv-card')
    expect(card.attributes('href')).toBe('/blog/test-post')
  })

  it('has 16:9 aspect ratio image container', async () => {
    const wrapper = await mountSuspended(BlogPostCard, {
      props: { post: makePost() },
    })
    expect(wrapper.find('.blog-post-card__image-container').exists()).toBe(true)
  })
})
