import type { Movie } from '~/types/movie'

/**
 * Select the featured movie for the homepage hero.
 *
 * An admin-flagged movie (`homeFeaturedAt`, set via the Feature-on-home
 * action — admin-v2 Plan 16) wins outright when it qualifies (now showing,
 * has a backdrop). Otherwise falls back to the most recently released
 * now-showing movie with a valid backdrop image.
 */
export function selectFeaturedMovie(movies: Movie[]): Movie | null {
  const withBackdrop = movies.filter((m) => m.backdropUrl && m.status === 'now_showing')

  if (withBackdrop.length === 0) return null

  // Admin curation first. The backend enforces a single flag, but data can
  // drift — break ties by the latest flag time.
  const flagged = withBackdrop
    .filter((m) => m.homeFeaturedAt)
    .sort((a, b) => new Date(b.homeFeaturedAt!).getTime() - new Date(a.homeFeaturedAt!).getTime())
  if (flagged.length > 0) return flagged[0]!

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
