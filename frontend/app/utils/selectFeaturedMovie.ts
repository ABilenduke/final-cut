import type { Movie } from '~/types/movie'

/**
 * Select the featured movie for the homepage hero.
 * Picks the most recently released now-showing movie with a valid backdrop image.
 */
export function selectFeaturedMovie(movies: Movie[]): Movie | null {
  const withBackdrop = movies.filter((m) => m.backdropUrl && m.status === 'now_showing')

  if (withBackdrop.length === 0) return null

  withBackdrop.sort((a, b) => new Date(b.releaseDate).getTime() - new Date(a.releaseDate).getTime())

  return withBackdrop[0] ?? null
}
