import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { ref } from 'vue'
import HomeCalendarStrip from '~/components/home/HomeCalendarStrip.vue'
import type { CalendarEvent } from '~/types/calendar-event'

// The composable returns an array of `useFetch`-shaped objects (one per
// month the week range spans). We expose a mutable ref so each test can
// stage its own event payload.
const mockEventsData = ref<{ data: CalendarEvent[] } | null>(null)

vi.mock('~/composables/useCalendarEvents', () => ({
  useCalendarEvents: () => ({
    getEvents: () => ({ data: mockEventsData }),
    getEvent: () => ({ data: ref(null) }),
  }),
}))

function makeEvent(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: `evt-${Math.random().toString(36).slice(2)}`,
    type: 'showtime',
    title: 'A Film',
    date: '2026-05-13',
    startTime: '2026-05-13T19:00:00',
    endTime: '2026-05-13T21:00:00',
    description: '',
    movieSlug: 'a-film',
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    showtimes: null,
    ...overrides,
  }
}

describe('HomeCalendarStrip', () => {
  beforeEach(() => {
    // Pin "today" inside the week containing 2026-05-13 (a Wednesday) so the
    // 7 day cells are deterministic: Mon 2026-05-11 ... Sun 2026-05-17.
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-05-13T12:00:00'))
    mockEventsData.value = { data: [] }
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders seven day cells for the current week', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    expect(wrapper.findAll('.cal-strip__day')).toHaveLength(7)
  })

  it('day-number link uses /whats-on?date=...&month=...&year=... format', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const links = wrapper.findAll('.cal-strip__date--link')
    expect(links).toHaveLength(7)
    // Monday of the week containing 2026-05-13 is 2026-05-11.
    expect(links[0]!.attributes('href')).toBe('/whats-on?date=2026-05-11&month=5&year=2026')
    // Sunday is 2026-05-17.
    expect(links[6]!.attributes('href')).toBe('/whats-on?date=2026-05-17&month=5&year=2026')
  })

  it('day-link aria-label includes weekday, month, day, AND year', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const firstLink = wrapper.find('.cal-strip__date--link')
    const label = firstLink.attributes('aria-label') ?? ''
    expect(label).toContain('Monday')
    expect(label).toContain('May')
    expect(label).toContain('11')
    expect(label).toContain('2026')
    expect(label).toMatch(/calendar/i)
  })

  it('renders an overflow link when a day has more than two events', async () => {
    mockEventsData.value = {
      data: Array.from({ length: 5 }, (_, i) =>
        makeEvent({
          title: `Film ${i}`,
          date: '2026-05-13',
          startTime: `2026-05-13T${10 + i}:00:00`,
        }),
      ),
    }
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const overflowLinks = wrapper.findAll('.cal-strip__overflow--link')
    expect(overflowLinks).toHaveLength(1)
    expect(overflowLinks[0]!.text()).toContain('3 more')
    expect(overflowLinks[0]!.attributes('href')).toBe('/whats-on?date=2026-05-13&month=5&year=2026')
  })

  it('overflow aria-label states what is more, not just the count, and includes the date', async () => {
    mockEventsData.value = {
      data: Array.from({ length: 4 }, (_, i) =>
        makeEvent({
          title: `Film ${i}`,
          date: '2026-05-13',
          startTime: `2026-05-13T${10 + i}:00:00`,
        }),
      ),
    }
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const overflowLink = wrapper.find('.cal-strip__overflow--link')
    const label = overflowLink.attributes('aria-label') ?? ''
    expect(label).toMatch(/event/i)
    expect(label).toContain('2 more')
    expect(label).toContain('2026')
    expect(label).toContain('Wednesday')
  })

  it('singular "1 more event" when overflow is exactly one', async () => {
    mockEventsData.value = {
      data: Array.from({ length: 3 }, (_, i) =>
        makeEvent({
          title: `Film ${i}`,
          date: '2026-05-13',
          startTime: `2026-05-13T${10 + i}:00:00`,
        }),
      ),
    }
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const label = wrapper.find('.cal-strip__overflow--link').attributes('aria-label') ?? ''
    expect(label).toMatch(/1 more event(?!s)/)
  })

  it('marks today with the --today modifier class', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const todayCell = wrapper.find('.cal-strip__day--today')
    expect(todayCell.exists()).toBe(true)
    // Today is Wednesday 2026-05-13 — the third cell (Mon, Tue, Wed).
    expect(todayCell.find('.cal-strip__date').text()).toBe('13')
  })

  it('renders the "Quiet day" placeholder when a day has zero events', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const empties = wrapper.findAll('.cal-strip__event--empty')
    expect(empties.length).toBeGreaterThan(0)
    expect(empties[0]!.text()).toContain('Quiet day')
  })

  it('renders the "Full Calendar" navigation link in the header', async () => {
    const wrapper = await mountSuspended(HomeCalendarStrip)
    const link = wrapper.find('.cal-strip__link')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/whats-on')
  })
})
