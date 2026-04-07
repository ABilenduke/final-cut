import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useCalendarEvents } from '~/composables/useCalendarEvents'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useCalendarEvents', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('getEvents passes month and year', () => {
    const { getEvents } = useCalendarEvents()
    getEvents(4, 2026)
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/calendar/events', {
      query: { month: 4, year: 2026 },
    })
  })

  it('getEvents passes type filter', () => {
    const { getEvents } = useCalendarEvents()
    getEvents(4, 2026, 'special_event')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/calendar/events', {
      query: { month: 4, year: 2026, type: 'special_event' },
    })
  })

  it('getEvents omits type when not provided', () => {
    const { getEvents } = useCalendarEvents()
    getEvents(12, 2025)
    const [, opts] = mockUseApiFetch.mock.calls[0]
    expect(opts?.query).not.toHaveProperty('type')
  })

  it('getEvents passes accessibility filter', () => {
    const { getEvents } = useCalendarEvents()
    getEvents(4, 2026, undefined, 'sensory_friendly,open_caption')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/calendar/events', {
      query: { month: 4, year: 2026, accessibility: 'sensory_friendly,open_caption' },
    })
  })

  it('getEvents omits accessibility when not provided', () => {
    const { getEvents } = useCalendarEvents()
    getEvents(4, 2026, 'special_event')
    const [, opts] = mockUseApiFetch.mock.calls[0]
    expect(opts?.query).not.toHaveProperty('accessibility')
  })

  it('getEvent fetches by slug', () => {
    const { getEvent } = useCalendarEvents()
    getEvent('summer-film-fest')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/calendar/events/summer-film-fest')
  })
})
