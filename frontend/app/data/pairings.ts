import type { ProgrammePairing } from '~/types/programme-pairing'

/**
 * Programme Pairings — curated three-course bundles tied to specific films.
 *
 * Keyed by movie slug. Lookup is case-sensitive; slugs come from `Movie.slug`
 * which the backend generates via `Str::slug(title)`.
 *
 * For films without a curated pairing, the snacks step renders the catalog only.
 */
export const programmePairings: Record<string, ProgrammePairing> = {
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
}

export function getPairingForMovie(movieSlug: string | null | undefined): ProgrammePairing | null {
  if (!movieSlug) return null
  return programmePairings[movieSlug] ?? null
}
