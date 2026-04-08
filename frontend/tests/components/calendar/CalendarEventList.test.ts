import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CalendarEventList from '~/components/calendar/CalendarEventList.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'showtime',
    title: 'Blade Runner 2099',
    date: '2026-04-15',
    startTime: '2026-04-15T19:00:00Z',
    endTime: '2026-04-15T21:30:00Z',
    description: 'A futuristic thriller',
    movieSlug: 'blade-runner-2099',
    imageUrl: null,
    slug: null,
    ticketUrl: '/purchase/st-123',
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

describe('CalendarEventList', () => {
  it('renders formatted date heading', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [makeEvent()],
        date: '2026-04-15',
      },
    })
    expect(wrapper.find('.calendar-event-list__heading').text()).toBe('Apr 15, 2026')
  })

  it('renders empty state when no events', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [],
        date: '2026-04-15',
      },
    })
    expect(wrapper.find('.calendar-event-list__empty').text()).toBe('No events on this day')
  })

  it('groups events by type', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [
          makeEvent(),
          makeEvent({
            id: '2',
            type: 'special_event',
            title: 'Q&A Night',
            slug: 'qa-night',
            movieSlug: null,
            ticketUrl: null,
          }),
        ],
        date: '2026-04-15',
      },
    })
    const groupHeadings = wrapper.findAll('.calendar-event-list__group-heading')
    expect(groupHeadings).toHaveLength(2)
    expect(groupHeadings[0].text()).toBe('Showtimes')
    expect(groupHeadings[1].text()).toBe('Special Events')
  })

  it('renders event title and time', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [makeEvent()],
        date: '2026-04-15',
      },
    })
    expect(wrapper.text()).toContain('Blade Runner 2099')
    const time = wrapper.find('time')
    expect(time.exists()).toBe(true)
    expect(time.attributes('datetime')).toBe('2026-04-15T19:00:00Z')
  })

  it('renders type badge for each event', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [makeEvent()],
        date: '2026-04-15',
      },
    })
    expect(wrapper.text()).toContain('Showtimes')
  })

  it('renders accessibility badges when tags present', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [
          makeEvent({ accessibilityTags: ['sensory_friendly', 'open_caption'] }),
        ],
        date: '2026-04-15',
      },
    })
    expect(wrapper.text()).toContain('Sensory Friendly')
    expect(wrapper.text()).toContain('Open Captions')
  })

  it('renders Members Only badge for loyalty events', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [makeEvent({ loyaltyOnly: true })],
        date: '2026-04-15',
      },
    })
    expect(wrapper.text()).toContain('Members Only')
  })

  it('links showtime events to ticket URL', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [makeEvent({ ticketUrl: '/purchase/st-123' })],
        date: '2026-04-15',
      },
    })
    const link = wrapper.find('a.calendar-event-list__title')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/purchase/st-123')
  })

  it('links special events to /events/:slug', async () => {
    const wrapper = await mountSuspended(CalendarEventList, {
      props: {
        events: [
          makeEvent({
            type: 'special_event',
            slug: 'qa-night',
            ticketUrl: null,
            movieSlug: null,
          }),
        ],
        date: '2026-04-15',
      },
    })
    const link = wrapper.find('a.calendar-event-list__title')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/events/qa-night')
  })
})
