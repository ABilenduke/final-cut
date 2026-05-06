import type { Movie } from '~/types/movie'
import { useApiFetch } from '~/utils/api'

interface MovieListResponse {
  data: Movie[]
  meta: { total: number; page: number; per_page: number }
}

interface MovieDetailResponse {
  data: Movie
}

type MaybeRef<T> = T | Ref<T>

export interface MovieListOptions {
  genre?: number
  per_page?: number
  /**
   * Optional location slug. When set, results are narrowed to movies that
   * have at least one upcoming showtime at that venue. Pass a Ref/computed
   * to make in-page navigation (chip clicks) re-fetch as the URL changes —
   * the underlying useFetch watches the source and refetches with the new
   * slug. Static strings are also supported for SSR / one-shot calls.
   */
  location?: MaybeRef<string | undefined>
}

function buildList(status: 'now_showing' | 'coming_soon', options?: MovieListOptions) {
  const { location, ...rest } = options ?? {}
  const locationRef = isRef(location) ? location : ref(location)
  const baseKey = status === 'now_showing' ? 'movies-now-showing' : 'movies-coming-soon'

  return useApiFetch<MovieListResponse>('/api/movies', {
    query: {
      ...rest,
      status,
      // useFetch unwraps reactive values inside the query object on each
      // fetch — passing the ref keeps the request in sync with the URL.
      location: locationRef,
    },
    // Re-fetch when the slug changes (chip click → router.push). Without
    // this watch the data stays on the initial slug despite the URL update.
    watch: [locationRef],
    key: computed(() => (locationRef.value ? `${baseKey}-${locationRef.value}` : baseKey)),
  })
}

export function useMovies() {
  const nowShowing = (options?: MovieListOptions) => buildList('now_showing', options)
  const comingSoon = (options?: MovieListOptions) => buildList('coming_soon', options)

  const getMovie = (slug: string) =>
    useApiFetch<MovieDetailResponse>(`/api/movies/${slug}`)

  return { nowShowing, comingSoon, getMovie }
}
