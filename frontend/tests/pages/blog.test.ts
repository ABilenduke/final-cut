import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BlogIndex from '~/pages/blog/index.vue'
import BlogDetail from '~/pages/blog/[slug].vue'
import type { BlogPost } from '~/types/blog-post'

// The pages consume the API through useApiFetch (via useBlogPosts) — mock the
// transport module and serve fixtures keyed by path, same idiom as the
// composable tests.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

const posts: BlogPost[] = [
  {
    id: '1',
    title: 'Final Cut Grand Opening: A New Chapter in Cinema',
    slug: 'grand-opening-announcement',
    excerpt: 'We are thrilled to announce the grand opening.',
    date: '2026-03-15',
    author: 'Final Cut Staff',
    imageUrl: null,
  },
  {
    id: '2',
    title: 'Behind the Screens',
    slug: 'behind-the-screens',
    excerpt: 'A look at the technology.',
    date: '2026-03-28',
    author: 'Marcus Chen',
    imageUrl: null,
  },
]

const detail: BlogPost = {
  ...posts[0]!,
  body: 'First paragraph of the post.\n\nSecond paragraph of the post.',
}

function fetchTuple<T>(payload: T) {
  return {
    data: ref({ data: payload }),
    pending: ref(false),
    error: ref(null),
    refresh: vi.fn(),
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/blog-posts') return fetchTuple(posts)
    if (path.startsWith('/api/blog-posts/')) return fetchTuple(detail)
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
})

describe('Blog Listing Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.blog-page__title').text()).toBe('Blog')
  })

  it('renders blog post cards from the API', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    const cards = wrapper.findAll('.blog-post-card__title')
    expect(cards.length).toBe(posts.length)
  })

  it('renders posts in the ensemble grid', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.ensemble').exists()).toBe(true)
  })

  it('renders the empty state when the API returns no posts', async () => {
    mockUseApiFetch.mockImplementation((() => fetchTuple([])) as any)
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.blog-page__empty').exists()).toBe(true)
  })
})

describe('Blog Detail Page', () => {
  it('renders post title for a valid slug', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__title').text()).toContain('Grand Opening')
  })

  it('renders post body paragraphs split on blank lines', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    const paragraphs = wrapper.findAll('.blog-post-page__body p')
    expect(paragraphs.length).toBe(2)
  })

  it('renders author and date', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__author').text()).toBe('Final Cut Staff')
    expect(wrapper.find('.blog-post-page__date').text()).toContain('2026')
  })

  it('renders related posts excluding the current slug', async () => {
    const wrapper = await mountSuspended(BlogDetail, {
      route: { params: { slug: 'grand-opening-announcement' } },
    })
    expect(wrapper.find('.blog-post-page__related').exists()).toBe(true)
    expect(wrapper.find('.blog-post-page__related').text()).toContain('Behind the Screens')
    expect(wrapper.find('.blog-post-page__related').text()).not.toContain('Grand Opening')
  })
})
