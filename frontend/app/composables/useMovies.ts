import type { Movie } from '~/types/movie'
import { useApiFetch } from '~/utils/api'

interface MovieListResponse {
  data: Movie[]
  meta: { total: number; page: number; per_page: number }
}

interface MovieDetailResponse {
  data: Movie
}

export function useMovies() {
  const nowShowing = (options?: { genre?: number; per_page?: number }) =>
    useApiFetch<MovieListResponse>('/api/movies', {
      query: { status: 'now_showing' as const, ...options },
    })

  const comingSoon = (options?: { genre?: number; per_page?: number }) =>
    useApiFetch<MovieListResponse>('/api/movies', {
      query: { status: 'coming_soon' as const, ...options },
    })

  const getMovie = (slug: string) =>
    useApiFetch<MovieDetailResponse>(`/api/movies/${slug}`)

  return { nowShowing, comingSoon, getMovie }
}
