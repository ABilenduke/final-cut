import type { CalendarEvent } from '~/types/calendar-event'
import { useApiFetch } from '~/utils/api'

interface CalendarEventsResponse {
  data: CalendarEvent[]
}

interface CalendarEventResponse {
  data: CalendarEvent
}

export function useCalendarEvents() {
  const getEvents = (month: number, year: number, type?: string) =>
    useApiFetch<CalendarEventsResponse>('/api/calendar/events', {
      query: { month, year, ...(type ? { type } : {}) },
    })

  const getEvent = (slug: string) =>
    useApiFetch<CalendarEventResponse>(`/api/calendar/events/${slug}`)

  return { getEvents, getEvent }
}
