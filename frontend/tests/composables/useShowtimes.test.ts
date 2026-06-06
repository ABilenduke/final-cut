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

  // ── fetchByMovie (cross-location public browse path) ──────────────────────

  it('fetchByMovie hits the cross-location URL with no query when no options given', () => {
    const { fetchByMovie } = useShowtimes()
    fetchByMovie('the-dark-knight')
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/movies/the-dark-knight/showtimes',
      { query: {} },
    )
  })

  it('fetchByMovie passes a date filter', () => {
    const { fetchByMovie } = useShowtimes()
    fetchByMovie('the-dark-knight', { date: '2026-05-10' })
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/movies/the-dark-knight/showtimes',
      { query: { date: '2026-05-10' } },
    )
  })

  it('fetchByMovie passes a days filter', () => {
    const { fetchByMovie } = useShowtimes()
    fetchByMovie('inception', { days: 14 })
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/movies/inception/showtimes',
      { query: { days: 14 } },
    )
  })

  it('fetchByMovie passes both date and days when both are supplied', () => {
    const { fetchByMovie } = useShowtimes()
    fetchByMovie('pulp-fiction', { date: '2026-06-01', days: 7 })
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/movies/pulp-fiction/showtimes',
      { query: { date: '2026-06-01', days: 7 } },
    )
  })

  // ── getShowtime (per-location seat-map fetch, booking flow) ───────────────

  it('getShowtime builds location-scoped URL with ID', () => {
    const { getShowtime } = useShowtimes()
    getShowtime('downtown', 'st-123')
    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/locations/downtown/showtimes/st-123',
    )
  })
})
