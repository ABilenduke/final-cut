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

  it('nowShowing fetches with status=now_showing', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', {
      query: { status: 'now_showing' },
    })
  })

  it('nowShowing passes additional options', () => {
    const { nowShowing } = useMovies()
    nowShowing({ genre: 28, per_page: 10 })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', {
      query: { status: 'now_showing', genre: 28, per_page: 10 },
    })
  })

  it('comingSoon fetches with status=coming_soon', () => {
    const { comingSoon } = useMovies()
    comingSoon()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', {
      query: { status: 'coming_soon' },
    })
  })

  it('getMovie fetches by slug', () => {
    const { getMovie } = useMovies()
    getMovie('the-dark-knight')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies/the-dark-knight')
  })
})
