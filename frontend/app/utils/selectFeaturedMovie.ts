import type { Movie } from '~/types/movie'

/**
 * Select the featured movie for the homepage hero.
 * Picks the most recently released now-showing movie with a valid backdrop image.
 */
export function selectFeaturedMovie(movies: Movie[]): Movie | null {
  const withBackdrop = movies.filter((m) => m.backdropUrl && m.status === 'now_showing')

  if (withBackdrop.length === 0) return null

  // Single-pass selection with explicit tie-breaker (slug, then id) for determinism
  return withBackdrop.reduce<Movie | null>((selected, candidate) => {
    if (!selected) return candidate

    const candidateRelease = new Date(candidate.releaseDate).getTime()
    const selectedRelease = new Date(selected.releaseDate).getTime()

    if (candidateRelease !== selectedRelease) {
      return candidateRelease > selectedRelease ? candidate : selected
    }

    // Tie-breaker: alphabetically later slug, then higher id
    if (candidate.slug !== selected.slug) {
      return candidate.slug > selected.slug ? candidate : selected
    }

    return candidate.id > selected.id ? candidate : selected
  }, null)
}
