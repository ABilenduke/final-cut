import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useBlogPost, useBlogPosts } from '~/composables/useBlogPosts'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useBlogPosts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the list from /api/blog-posts with an SSR-dedup key', () => {
    useBlogPosts()
    const [path, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(path).toBe('/api/blog-posts')
    expect(opts.key).toBe('blog-posts')
  })

  it('fetches a post by slug with a per-slug key', () => {
    useBlogPost('grand-opening-announcement')
    const [path, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(path).toBe('/api/blog-posts/grand-opening-announcement')
    expect(opts.key).toBe('blog-post-grand-opening-announcement')
  })

  it('returns the result of useApiFetch', () => {
    const fakeResult = {
      data: { value: { data: [] } },
      pending: { value: false },
      error: { value: null },
      refresh: vi.fn(),
    }
    mockUseApiFetch.mockReturnValueOnce(fakeResult as any)

    expect(useBlogPosts()).toBe(fakeResult)
  })
})
