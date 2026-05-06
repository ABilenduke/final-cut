import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useFeaturedSlides } from '~/composables/useFeaturedSlides'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useFeaturedSlides', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches from /api/featured-slides', () => {
    useFeaturedSlides()
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/featured-slides',
      expect.any(Object),
    )
  })

  it('passes an explicit key for SSR dedup', () => {
    useFeaturedSlides()
    const [, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(opts.key).toBe('featured-slides')
  })

  it('does not include a location segment in the URL', () => {
    useFeaturedSlides()
    const [path] = mockUseApiFetch.mock.calls[0] as [string]
    expect(path).not.toMatch(/locations/)
  })

  it('returns the result of useApiFetch', () => {
    const fakeResult = {
      data: { value: { data: [] } },
      pending: { value: false },
      error: { value: null },
      refresh: vi.fn(),
    }
    mockUseApiFetch.mockReturnValueOnce(fakeResult as any)

    const result = useFeaturedSlides()
    expect(result).toBe(fakeResult)
  })
})
