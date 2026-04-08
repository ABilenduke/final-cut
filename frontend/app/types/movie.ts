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
