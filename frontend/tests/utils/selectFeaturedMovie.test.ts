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

  it('handles movies with same releaseDate deterministically via slug tie-breaker', () => {
    const movies = [
      makeMovie({ id: 1, slug: 'alpha', title: 'Alpha', releaseDate: '2026-03-01' }),
      makeMovie({ id: 2, slug: 'zulu', title: 'Zulu', releaseDate: '2026-03-01' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    // Alphabetically later slug wins on tie
    expect(result!.slug).toBe('zulu')
  })

  it('uses id as final tie-breaker when slug and releaseDate match', () => {
    const movies = [
      makeMovie({ id: 5, slug: 'same-slug', title: 'Lower ID', releaseDate: '2026-03-01' }),
      makeMovie({ id: 10, slug: 'same-slug', title: 'Higher ID', releaseDate: '2026-03-01' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.id).toBe(10)
  })

  it('prefers an admin-flagged movie over a newer release', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Newest', releaseDate: '2026-05-01' }),
      makeMovie({
        id: 2,
        title: 'Curated Pick',
        releaseDate: '2026-01-01',
        homeFeaturedAt: '2026-06-09T12:00:00Z',
      }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.id).toBe(2)
  })

  it('ignores the flag when the flagged movie has no backdrop', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Algorithmic', releaseDate: '2026-05-01' }),
      makeMovie({
        id: 2,
        title: 'Flagged But Broken',
        backdropUrl: '',
        homeFeaturedAt: '2026-06-09T12:00:00Z',
      }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.id).toBe(1)
  })

  it('breaks a multi-flag drift by latest homeFeaturedAt', () => {
    const movies = [
      makeMovie({ id: 1, title: 'Older Flag', homeFeaturedAt: '2026-06-01T12:00:00Z' }),
      makeMovie({ id: 2, title: 'Newer Flag', homeFeaturedAt: '2026-06-09T12:00:00Z' }),
    ]

    const result = selectFeaturedMovie(movies)

    expect(result).not.toBeNull()
    expect(result!.id).toBe(2)
  })
})
