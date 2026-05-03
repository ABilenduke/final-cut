import type { Movie } from '~/types/movie'
import { useApiFetch } from '~/utils/api'

interface MovieListResponse {
  data: Movie[]
  meta: { total: number; page: number; per_page: number }
}

interface MovieDetailResponse {
  data: Movie
}

export interface MovieListOptions {
  genre?: number
  per_page?: number
  /**
   * Optional location slug. When set, results are narrowed to movies that
   * have at least one upcoming showtime at that venue. Each `?location=` value
   * produces a distinct `useFetch` key so ISR caches each filtered URL
   * independently — matching the route-level ISR contract for /movies.
   */
  location?: string
}

export function useMovies() {
  const nowShowing = (options?: MovieListOptions) => {
    const { location, ...rest } = options ?? {}
    const query: Record<string, unknown> = { status: 'now_showing' as const, ...rest }
    if (location) {
      query.location = location
    }
    // Include location in the fetch key so Nuxt deduplicates and caches each
    // filtered URL independently (ISR cache separation per location filter).
    const key = location ? `movies-now-showing-${location}` : 'movies-now-showing'
    return useApiFetch<MovieListResponse>('/api/movies', { query, key })
  }

  const comingSoon = (options?: MovieListOptions) => {
    const { location, ...rest } = options ?? {}
    const query: Record<string, unknown> = { status: 'coming_soon' as const, ...rest }
    if (location) {
      query.location = location
    }
    const key = location ? `movies-coming-soon-${location}` : 'movies-coming-soon'
    return useApiFetch<MovieListResponse>('/api/movies', { query, key })
  }

  const getMovie = (slug: string) =>
    useApiFetch<MovieDetailResponse>(`/api/movies/${slug}`)

  return { nowShowing, comingSoon, getMovie }
}
