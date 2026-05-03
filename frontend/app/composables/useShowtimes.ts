import type { Showtime } from '~/types/showtime'
import type { Auditorium, Seat } from '~/types/auditorium'
import { useApiFetch } from '~/utils/api'

interface ShowtimeListResponse {
  data: Showtime[]
}

interface ShowtimeDetailResponse {
  data: {
    showtime: Showtime
    auditorium: Auditorium
    seats: Seat[]
  }
}

export function useShowtimes() {
  /**
   * Cross-location fetch for a movie's upcoming showtimes.
   * Used by the movie detail page (/movies/:slug).
   * Returns showtimes from ALL venues; each entry carries a `location` payload.
   * Endpoint: GET /api/movies/:slug/showtimes
   */
  const fetchByMovie = (
    movieSlug: string,
    options?: { date?: string; days?: number },
  ) => {
    const query: Record<string, unknown> = {}
    if (options?.date) query.date = options.date
    if (options?.days !== undefined) query.days = options.days

    return useApiFetch<ShowtimeListResponse>(
      `/api/movies/${movieSlug}/showtimes`,
      { query },
    )
  }

  /**
   * Per-location showtime list. Retained for any legacy callers;
   * the public movie-detail page now uses `fetchByMovie`.
   * @deprecated Use fetchByMovie for the public browse path.
   */
  const getShowtimes = (locationSlug: string, movieSlug: string, date?: string) =>
    useApiFetch<ShowtimeListResponse>(
      `/api/locations/${locationSlug}/movies/${movieSlug}/showtimes`,
      { query: date ? { date } : {} },
    )

  /**
   * Per-location showtime + seat-map fetch.
   * Used by the booking flow (/purchase/:showtimeId).
   * The {location} segment is part of the showtime URL contract.
   */
  const getShowtime = (locationSlug: string, id: string) =>
    useApiFetch<ShowtimeDetailResponse>(
      `/api/locations/${locationSlug}/showtimes/${id}`,
    )

  return { fetchByMovie, getShowtimes, getShowtime }
}
