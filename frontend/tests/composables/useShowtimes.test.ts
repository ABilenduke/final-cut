import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useShowtimes } from '~/composables/useShowtimes'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useShowtimes', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('getShowtimes builds location-scoped URL', () => {
    const { getShowtimes } = useShowtimes()
    getShowtimes('downtown', 'the-dark-knight')
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/locations/downtown/movies/the-dark-knight/showtimes',
      { query: {} },
    )
  })

  it('getShowtimes passes date filter', () => {
    const { getShowtimes } = useShowtimes()
    getShowtimes('downtown', 'the-dark-knight', '2026-04-10')
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/locations/downtown/movies/the-dark-knight/showtimes',
      { query: { date: '2026-04-10' } },
    )
  })

  it('getShowtime builds location-scoped URL with ID', () => {
    const { getShowtime } = useShowtimes()
    getShowtime('downtown', 'st-123')
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/locations/downtown/showtimes/st-123',
    )
  })
})
