export type CalendarEventType =
  | 'showtime'
  | 'special_event'
  | 'loyalty_exclusive'
  | 'private_screening_blackout'

export interface CalendarEventShowtime {
  id: string
  startTime: string
  auditoriumLabel: string
  soldOut: boolean
}

export interface CalendarEvent {
  id: string
  type: CalendarEventType
  title: string
  date: string
  startTime: string
  endTime: string | null
  description: string
  movieSlug: string | null
  imageUrl: string | null
  slug: string | null
  ticketUrl: string | null
  loyaltyOnly: boolean
  accessibilityTags: AccessibilityTag[]
  /**
   * Per-screening tile data for synthesized showtime entries (one entry per
   * (movie, location, day)). Backend emits `null` for stored calendar_events
   * rows; consumers should treat the field as optional.
   */
  showtimes?: CalendarEventShowtime[] | null
}

export type AccessibilityTag = 'sensory_friendly' | 'open_caption' | 'audio_described'
