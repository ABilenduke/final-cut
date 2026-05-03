export interface ShowtimeLocation {
  slug: string
  name: string
  latitude: number | null
  longitude: number | null
  /** Present on per-location seatmap responses (Task 8). */
  street?: string
  city?: string
  state?: string
  postal_code?: string
  phone?: string
}

export interface Showtime {
  id: string
  movieId: number
  movieSlug: string
  movieTitle: string
  screenId: string
  screenName: string
  startTime: string
  endTime: string
  priceStandard: number
  pricePremium: number
  priceAccessible: number
  /** Present on cross-location responses (/api/movies/:slug/showtimes). */
  location?: ShowtimeLocation
}
