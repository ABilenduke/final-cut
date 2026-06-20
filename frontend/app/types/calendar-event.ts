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

/**
 * Structured venue for an event, exposed only by the detail endpoint
 * (`/api/calendar/events/:slug`). The month listing returns `null` to avoid a
 * per-row relation load. Powers the schema.org Event/Place on `/events/:slug`.
 * `latitude`/`longitude` arrive as decimal strings from the Laravel cast.
 */
export interface EventLocation {
  name: string
  street: string | null
  city: string | null
  state: string | null
  postalCode: string | null
  country: string | null
  latitude: number | string | null
  longitude: number | string | null
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
  /**
   * Structured venue — present on the detail endpoint when the event is
   * venue-scoped; `null` for venue-agnostic events and on the month listing.
   */
  location?: EventLocation | null
}

export type AccessibilityTag = 'sensory_friendly' | 'open_caption' | 'audio_described'
