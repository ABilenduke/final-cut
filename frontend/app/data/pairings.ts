import type { ProgrammePairing } from '~/types/programme-pairing'

/**
 * Programme Pairings — curated three-course bundles tied to specific films.
 *
 * Pairings are scoped by location slug first, then by movie slug. The bar
 * inventory, curator, and price points differ between our theatres; keying
 * the lookup by location prevents one location's pairing from leaking into
 * another's snacks step.
 *
 * Use `GLOBAL_PAIRINGS_KEY` for pairings that are intentionally available
 * at every location (e.g. a label that our programming team rolls out chain-
 * wide). `getPairingForMovie(location, movie)` checks the location bucket
 * first, then falls back to the global bucket.
 *
 * Movie slug lookup is case-sensitive; slugs come from `Movie.slug`, which
 * the backend generates via `Str::slug(title)`. Location slugs come from
 * `useLocations().activeLocation.slug`.
 *
 * TODO(backend): promote to a `programme_pairings` table once the data
 * model supports location_id + movie_id keys.
 */
type PairingsByMovieSlug = Record<string, ProgrammePairing>
type PairingsByLocationAndMovie = Record<string, PairingsByMovieSlug>

export const GLOBAL_PAIRINGS_KEY = '__global__'

export const programmePairings: PairingsByLocationAndMovie = {
  // Pairings curated across every location. Until the pairing content
  // diverges per venue, both of our locations fall back here.
  [GLOBAL_PAIRINGS_KEY]: {
    // Interstellar — the long, three-act film deserves a slow drink and something to bite.
    interstellar: {
      id: 'pairing-interstellar',
      movieSlug: 'interstellar',
      number: '17',
      tag: 'Programme Pairing No. 17 · Tonight only',
      title: 'The Endurance flight.',
      titleAccent: 'Endurance',
      titleTail: 'flight.',
      curatorNote:
        'Chosen by programming to travel with the film — caramel-corn for the launch, a chilled white for the long quiet between acts, and a single square of 72% to meet the score.',
      courses: [
        { id: 'course-popcorn', name: 'Caramel popcorn · sea salt', price: 999 },
        { id: 'course-wine', name: 'Programme wine — Chenin, 125 ml', price: 1199 },
        { id: 'course-chocolate', name: 'Valrhona dark · 72% square', price: 599 },
      ],
      bundlePrice: 2299,
      curatorName: 'Nadia Obéron',
      curatorRole: 'Head of Programming',
      validUntil: 'Save $5 tonight',
      gradient: 'radial-gradient(ellipse at 30% 30%, #6a85a8 0%, #2a3858 55%, #0e1820 100%)',
      glyph: 'E',
    },

    // The Dark Knight — heavy, smoky, long-pull program. Negroni weight.
    'the-dark-knight': {
      id: 'pairing-dark-knight',
      movieSlug: 'the-dark-knight',
      number: '08',
      tag: 'Programme Pairing No. 08 · Tonight only',
      title: 'The Gotham short.',
      titleAccent: 'Gotham',
      titleTail: 'short.',
      curatorNote:
        'Picked for the third act — a Negroni stirred over a single cube, salted popcorn to cut the bitter, and a fleur de sel caramel to soften the landing.',
      courses: [
        { id: 'course-popcorn', name: 'Brown butter popcorn', price: 999 },
        { id: 'course-negroni', name: 'Negroni · house stir, 90 ml', price: 1299 },
        { id: 'course-caramel', name: 'Fleur de sel caramels · box of 4', price: 599 },
      ],
      bundlePrice: 2599,
      curatorName: 'Theo Marquand',
      curatorRole: 'Bar Director',
      validUntil: 'Save $3 tonight',
      gradient: 'radial-gradient(ellipse at 30% 30%, #6a4a20 0%, #2a1808 55%, #0e0e0e 100%)',
      glyph: 'G',
    },
  },
}

/**
 * Resolve the pairing for the current movie at the current location. Checks
 * the location's bucket first, then falls back to the global bucket. Callers
 * that don't have a location handy (e.g., SSR paths before location hydration)
 * can omit it and get the global pairing, which matches prior behavior.
 */
export function getPairingForMovie(
  movieSlug: string | null | undefined,
): ProgrammePairing | null
export function getPairingForMovie(
  locationSlug: string | null | undefined,
  movieSlug: string | null | undefined,
): ProgrammePairing | null
export function getPairingForMovie(
  a: string | null | undefined,
  b?: string | null | undefined,
): ProgrammePairing | null {
  const hasLocation = arguments.length > 1
  const locationSlug = hasLocation ? a : null
  const movieSlug = hasLocation ? b : a
  if (!movieSlug) return null

  if (locationSlug) {
    const scoped = programmePairings[locationSlug]?.[movieSlug]
    if (scoped) return scoped
  }
  return programmePairings[GLOBAL_PAIRINGS_KEY]?.[movieSlug] ?? null
}
