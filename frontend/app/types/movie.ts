export interface Movie {
  id: number
  slug: string
  title: string
  tagline: string
  synopsis: string
  runtime: number
  rating: number | null
  releaseDate: string
  genres: Genre[]
  cast: CastMember[]
  posterUrl: string
  backdropUrl: string
  trailerKey: string | null
  status: 'now_showing' | 'coming_soon'
  /** ISO timestamp when an admin pinned this movie as the home hero feature; null/absent otherwise. */
  homeFeaturedAt?: string | null
  /** Admin-authored crew credits (admin-v4 Plan 03); null when never filled. */
  credits?: MovieCredits | null
  /** Admin-authored press quotes; empty when none. */
  pressQuotes?: PressQuoteData[]
  /** Admin-authored extra clips; empty when none. */
  clips?: MovieClipData[]
}

export interface Genre {
  id: number
  name: string
}

export interface CastMember {
  id: number
  name: string
  character: string
  profileUrl: string | null
}

export interface MovieCredits {
  director?: string
  screenplay?: string
  cinematography?: string
  editor?: string
  composer?: string
  aspect?: string
  advisory?: string
}

export interface PressQuoteData {
  quote: string
  author: string
  publication: string
}

export interface MovieClipData {
  label: string
  sub?: string
  duration?: string
  youtube_key: string
}
