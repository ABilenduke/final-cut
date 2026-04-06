export interface CalendarEvent {
  id: string
  type: 'showtime' | 'special_event' | 'loyalty_exclusive' | 'private_screening_blackout'
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
}

export type AccessibilityTag = 'sensory_friendly' | 'open_caption' | 'audio_described'
