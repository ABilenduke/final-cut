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
  const getShowtimes = (locationSlug: string, movieSlug: string, date?: string) =>
    useApiFetch<ShowtimeListResponse>(
      `/api/locations/${locationSlug}/movies/${movieSlug}/showtimes`,
      { query: date ? { date } : {} },
    )

  const getShowtime = (locationSlug: string, id: string) =>
    useApiFetch<ShowtimeDetailResponse>(
      `/api/locations/${locationSlug}/showtimes/${id}`,
    )

  return { getShowtimes, getShowtime }
}
