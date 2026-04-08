import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeEventStrip from '~/components/home/HomeEventStrip.vue'
import type { CalendarEvent } from '~/types/calendar-event'

// Fixed week: Mon Apr 6 through Sun Apr 12, 2026
vi.mock('~/utils/weekRange', () => ({
  weekRange: () => ({
    start: new Date(2026, 3, 6, 0, 0, 0, 0), // Mon Apr 6
    end: new Date(2026, 3, 13, 0, 0, 0, 0), // Next Mon Apr 13 (exclusive)
    months: [{ month: 4, year: 2026 }],
  }),
}))

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'special_event',
    title: 'Test Event',
    date: '2026-04-09',
    startTime: '2026-04-09T18:00:00Z',
    endTime: '2026-04-09T20:00:00Z',
    description: 'A test event description',
    movieSlug: null,
    imageUrl: null,
    slug: 'test-event',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

const mockEventsData = ref<{ data: CalendarEvent[] } | null>({
  data: [
    makeEvent({
      id: '1',
      type: 'special_event',
      title: 'Kids Movie Night',
      date: '2026-04-09',
      startTime: '2026-04-09T18:00:00Z',
      endTime: '2026-04-09T20:00:00Z',
      description: 'A special evening of family-friendly films for all ages to enjoy together',
      slug: 'kids-movie-night',
    }),
    makeEvent({
      id: '2',
      type: 'loyalty_exclusive',
      title: 'Premier Preview',
      date: '2026-04-11',
      startTime: '2026-04-11T20:00:00Z',
      endTime: null,
      description: 'Exclusive early screening for Premier members',
      slug: 'premier-preview',
      loyaltyOnly: true,
    }),
    // Outside the week range (should be filtered out)
    makeEvent({
      id: '3',
      type: 'special_event',
      title: 'Out of Range',
      date: '2026-04-20',
      startTime: '2026-04-20T18:00:00Z',
      endTime: null,
      description: 'This event is outside the week range',
      slug: 'out-of-range',
    }),
    // Showtime type (should be filtered out)
    makeEvent({
      id: '4',
      type: 'showtime',
      title: 'Regular Showtime',
      date: '2026-04-09',
      startTime: '2026-04-09T14:00:00Z',
      endTime: null,
      description: 'A regular movie showtime',
      movieSlug: 'some-movie',
      slug: null,
    }),
  ],
})

vi.mock('~/composables/useCalendarEvents', () => ({
  useCalendarEvents: () => ({
    getEvents: () => ({
      data: mockEventsData,
      pending: ref(false),
      error: ref(null),
      refresh: vi.fn(),
    }),
    getEvent: vi.fn(),
  }),
}))

describe('HomeEventStrip', () => {
  beforeEach(() => {
    // Reset to default events before each test
    mockEventsData.value = {
      data: [
        makeEvent({
          id: '1',
          type: 'special_event',
          title: 'Kids Movie Night',
          date: '2026-04-09',
          startTime: '2026-04-09T18:00:00Z',
          endTime: '2026-04-09T20:00:00Z',
          description: 'A special evening of family-friendly films for all ages to enjoy together',
          slug: 'kids-movie-night',
        }),
        makeEvent({
          id: '2',
          type: 'loyalty_exclusive',
          title: 'Premier Preview',
          date: '2026-04-11',
          startTime: '2026-04-11T20:00:00Z',
          endTime: null,
          description: 'Exclusive early screening for Premier members',
          slug: 'premier-preview',
          loyaltyOnly: true,
        }),
        makeEvent({
          id: '3',
          type: 'special_event',
          title: 'Out of Range',
          date: '2026-04-20',
          startTime: '2026-04-20T18:00:00Z',
          endTime: null,
          description: 'This event is outside the week range',
          slug: 'out-of-range',
        }),
        makeEvent({
          id: '4',
          type: 'showtime',
          title: 'Regular Showtime',
          date: '2026-04-09',
          startTime: '2026-04-09T14:00:00Z',
          endTime: null,
          description: 'A regular movie showtime',
          movieSlug: 'some-movie',
          slug: null,
        }),
      ],
    }
  })

  it('renders the heading', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.text()).toContain("What's On This Week")
  })

  it('renders qualifying events (special_event and loyalty_exclusive)', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.text()).toContain('Kids Movie Night')
    expect(wrapper.text()).toContain('Premier Preview')
  })

  it('filters out events outside the week range', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.text()).not.toContain('Out of Range')
  })

  it('filters out showtime type events', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.text()).not.toContain('Regular Showtime')
  })

  it('displays formatted date for each event', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    // "2026-04-09" should format as something like "Thu, Apr 9"
    const formatter = new Intl.DateTimeFormat('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    })
    const expectedDate = formatter.format(new Date('2026-04-09T12:00:00'))
    expect(wrapper.text()).toContain(expectedDate)
  })

  it('shows "View full calendar" link', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.text()).toContain('View full calendar')
    const calendarLink = wrapper.find('a[href="/whats-on"]')
    expect(calendarLink.exists()).toBe(true)
  })

  it('links events to their detail page', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    // "kids-movie-night" slug should produce /events/kids-movie-night
    const eventLink = wrapper.find('a[href="/events/kids-movie-night"]')
    expect(eventLink.exists()).toBe(true)
  })

  it('truncates long descriptions', async () => {
    const longDescription = 'A'.repeat(120)
    mockEventsData.value = {
      data: [
        makeEvent({
          id: '10',
          title: 'Long Desc Event',
          date: '2026-04-08',
          description: longDescription,
          slug: 'long-desc',
        }),
      ],
    }

    const wrapper = await mountSuspended(HomeEventStrip)
    const descEl = wrapper.find('.event-strip__desc')
    expect(descEl.exists()).toBe(true)
    // Should end with ellipsis character and be shorter than original
    expect(descEl.text()).toContain('\u2026')
    expect(descEl.text().length).toBeLessThan(longDescription.length)
  })

  it('does not truncate short descriptions', async () => {
    const shortDescription = 'A short one'
    mockEventsData.value = {
      data: [
        makeEvent({
          id: '10',
          title: 'Short Desc Event',
          date: '2026-04-08',
          description: shortDescription,
          slug: 'short-desc',
        }),
      ],
    }

    const wrapper = await mountSuspended(HomeEventStrip)
    const descEl = wrapper.find('.event-strip__desc')
    expect(descEl.text()).toBe(shortDescription)
  })

  it('shows max 5 events', async () => {
    const dates = [
      '2026-04-06',
      '2026-04-07',
      '2026-04-08',
      '2026-04-09',
      '2026-04-10',
      '2026-04-11',
      '2026-04-12',
    ]
    mockEventsData.value = {
      data: dates.map((date, i) =>
        makeEvent({
          id: `evt-${i}`,
          title: `Event ${i}`,
          date,
          slug: `event-${i}`,
        }),
      ),
    }

    const wrapper = await mountSuspended(HomeEventStrip)
    const items = wrapper.findAll('.event-strip__item')
    expect(items.length).toBe(5)
  })

  it('sorts events by date', async () => {
    mockEventsData.value = {
      data: [
        makeEvent({
          id: 'late',
          title: 'Later Event',
          date: '2026-04-11',
          slug: 'later',
        }),
        makeEvent({
          id: 'early',
          title: 'Earlier Event',
          date: '2026-04-07',
          slug: 'earlier',
        }),
      ],
    }

    const wrapper = await mountSuspended(HomeEventStrip)
    const titles = wrapper.findAll('.event-strip__title')
    expect(titles[0].text()).toBe('Earlier Event')
    expect(titles[1].text()).toBe('Later Event')
  })

  it('is hidden when no qualifying events exist', async () => {
    mockEventsData.value = { data: [] }

    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.find('.event-strip').exists()).toBe(false)
  })

  it('is hidden when only non-qualifying event types exist', async () => {
    mockEventsData.value = {
      data: [
        makeEvent({
          id: '1',
          type: 'showtime',
          title: 'Just a Showtime',
          date: '2026-04-09',
          movieSlug: 'some-movie',
          slug: null,
        }),
        makeEvent({
          id: '2',
          type: 'private_screening_blackout',
          title: 'Blackout',
          date: '2026-04-10',
          slug: 'blackout',
        }),
      ],
    }

    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.find('.event-strip').exists()).toBe(false)
  })

  it('uses semantic section element', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    expect(wrapper.find('section.event-strip').exists()).toBe(true)
  })

  it('heading is h2', async () => {
    const wrapper = await mountSuspended(HomeEventStrip)
    const heading = wrapper.find('h2')
    expect(heading.exists()).toBe(true)
    expect(heading.text()).toContain("What's On This Week")
  })
})
