import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useMovies } from '~/composables/useMovies'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useMovies', () => {
  beforeEach(() => { vi.clearAllMocks() })

  // ── Base calls ──────────────────────────────────────────────────────────

  it('nowShowing fetches with status=now_showing', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'now_showing' }),
    }))
  })

  it('nowShowing passes additional options', () => {
    const { nowShowing } = useMovies()
    nowShowing({ genre: 28, per_page: 10 })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'now_showing', genre: 28, per_page: 10 }),
    }))
  })

  it('comingSoon fetches with status=coming_soon', () => {
    const { comingSoon } = useMovies()
    comingSoon()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'coming_soon' }),
    }))
  })

  it('getMovie fetches by slug', () => {
    const { getMovie } = useMovies()
    getMovie('the-dark-knight')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies/the-dark-knight')
  })

  // ── Location filter ─────────────────────────────────────────────────────

  it('nowShowing includes ?location= when location option is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'now_showing', location: 'downtown' }),
    }))
  })

  it('nowShowing does NOT include ?location= when location option is omitted', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    const call = mockUseApiFetch.mock.calls[0]
    const opts = call[1] as { query: Record<string, unknown> }
    expect(opts.query.location).toBeUndefined()
  })

  it('comingSoon includes ?location= when location option is provided', () => {
    const { comingSoon } = useMovies()
    comingSoon({ location: 'uptown' })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'coming_soon', location: 'uptown' }),
    }))
  })

  it('nowShowing uses a location-scoped cache key when location is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      key: 'movies-now-showing-downtown',
    }))
  })

  it('nowShowing uses the default cache key when no location is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      key: 'movies-now-showing',
    }))
  })

  it('comingSoon uses a location-scoped cache key when location is provided', () => {
    const { comingSoon } = useMovies()
    comingSoon({ location: 'uptown' })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      key: 'movies-coming-soon-uptown',
    }))
  })

  it('two different location values produce two different cache keys', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    nowShowing({ location: 'uptown' })

    const keys = mockUseApiFetch.mock.calls.map(call => (call[1] as any).key)
    expect(keys[0]).toBe('movies-now-showing-downtown')
    expect(keys[1]).toBe('movies-now-showing-uptown')
    expect(keys[0]).not.toBe(keys[1])
  })
})
