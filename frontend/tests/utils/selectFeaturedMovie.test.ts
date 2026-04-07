import { describe, it, expect } from 'vitest'
import { selectFeaturedMovie } from '~/utils/selectFeaturedMovie'
import type { Movie } from '~/types/movie'

function makeMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'test-movie',
    title: 'Test Movie',
    tagline: '',
    synopsis: '',
    runtime: 120,
    rating: 7.5,
    releaseDate: '2026-01-01',
    genres: [],
    cast: [],
    posterUrl: 'https://example.com/poster.jpg',
    backdropUrl: 'https://example.com/backdrop.jpg',
    trailerKey: null,
    status: 'now_showing',
    ...overrides,
  }
}

describe('selectFeaturedMovie', () => {
  it('returns most recent movie when multiple have backdrops', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Older', releaseDate: '2026-01-01' }),
      makeMovie({ id: 2, title: 'Newest', releaseDate: '2026-04-03' }),
      makeMovie({ id: 3, title: 'Middle', releaseDate: '2026-02-15' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.title).toBe('Newest')
  })

  it('returns null when no movies provided', () => {
    expect(selectFeaturedMovie([])).toBeNull()
  })

  it('returns null when no movies have valid backdropUrl', () => {
    const movies = [
      makeMovie({ id: 1, backdropUrl: '' }),
      makeMovie({ id: 2, backdropUrl: '' }),
    ]

    expect(selectFeaturedMovie(movies)).toBeNull()
  })

  it('ignores movies with empty string backdropUrl', () => {
    const movies = [
      makeMovie({ id: 1, title: 'No Backdrop', releaseDate: '2026-04-01', backdropUrl: '' }),
      makeMovie({ id: 2, title: 'Has Backdrop', releaseDate: '2026-01-01', backdropUrl: 'https://example.com/bg.jpg' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.title).toBe('Has Backdrop')
  })

  it('returns the single movie when only one has a backdrop', () => {
    const movies = [
      makeMovie({ id: 1, backdropUrl: '' }),
      makeMovie({ id: 2, title: 'Only Valid', backdropUrl: 'https://example.com/bg.jpg' }),
      makeMovie({ id: 3, backdropUrl: '' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.title).toBe('Only Valid')
  })

  it('ignores coming_soon movies even with valid backdropUrl', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Coming Soon Film', releaseDate: '2026-06-01', status: 'coming_soon', backdropUrl: 'https://example.com/bg.jpg' }),
      makeMovie({ id: 2, title: 'Now Showing Film', releaseDate: '2026-01-01', status: 'now_showing', backdropUrl: 'https://example.com/bg2.jpg' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.title).toBe('Now Showing Film')
  })

  it('returns null when all movies with backdrops are coming_soon', () => {
    const movies = [
      makeMovie({ id: 1, status: 'coming_soon', backdropUrl: 'https://example.com/bg.jpg' }),
      makeMovie({ id: 2, status: 'coming_soon', backdropUrl: 'https://example.com/bg2.jpg' }),
    ]

    expect(selectFeaturedMovie(movies)).toBeNull()
  })

  it('sorts correctly with ISO datetime strings', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Earlier', releaseDate: '2026-03-15T00:00:00Z' }),
      makeMovie({ id: 2, title: 'Later', releaseDate: '2026-04-01T14:30:00Z' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.title).toBe('Later')
  })

  it('handles movies with same releaseDate deterministically', () => {
    const movies = [
      makeMovie({ id: 1, title: 'First', releaseDate: '2026-03-01' }),
      makeMovie({ id: 2, title: 'Second', releaseDate: '2026-03-01' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    // Stable sort preserves input order for equal dates
    expect(result!.title).toBe('First')
  })
})
