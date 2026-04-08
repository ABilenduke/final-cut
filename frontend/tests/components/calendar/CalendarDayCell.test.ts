import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CalendarDayCell from '~/components/calendar/CalendarDayCell.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'showtime',
    title: 'Test Event',
    date: '2026-04-15',
    startTime: '2026-04-15T19:00:00Z',
    endTime: '2026-04-15T21:00:00Z',
    description: 'A test event',
    movieSlug: null,
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

describe('CalendarDayCell', () => {
  it('renders the day number', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [],
        selected: false,
        today: false,
      },
    })
    expect(wrapper.text()).toContain('15')
  })

  it('applies selected class when selected', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [],
        selected: true,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell').classes()).toContain('calendar-day-cell--selected')
  })

  it('applies today class when today', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-08',
        events: [],
        selected: false,
        today: true,
      },
    })
    expect(wrapper.find('.calendar-day-cell').classes()).toContain('calendar-day-cell--today')
  })

  it('applies outside class when outsideMonth', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-03-31',
        events: [],
        selected: false,
        today: false,
        outsideMonth: true,
      },
    })
    expect(wrapper.find('.calendar-day-cell').classes()).toContain('calendar-day-cell--outside')
  })

  it('renders event dots color-coded by type', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [
          makeEvent({ type: 'showtime' }),
          makeEvent({ id: '2', type: 'special_event' }),
        ],
        selected: false,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell__dot--showtime').exists()).toBe(true)
    expect(wrapper.find('.calendar-day-cell__dot--special_event').exists()).toBe(true)
  })

  it('renders no dots when no events', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [],
        selected: false,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell__dots').exists()).toBe(false)
  })

  it('limits dots to 3 unique types', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [
          makeEvent({ type: 'showtime' }),
          makeEvent({ id: '2', type: 'special_event' }),
          makeEvent({ id: '3', type: 'loyalty_exclusive' }),
          makeEvent({ id: '4', type: 'showtime' }), // duplicate type
        ],
        selected: false,
        today: false,
      },
    })
    const dots = wrapper.findAll('.calendar-day-cell__dot')
    expect(dots).toHaveLength(3)
  })

  it('emits select with date when clicked', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [],
        selected: false,
        today: false,
      },
    })
    await wrapper.find('.calendar-day-cell').trigger('click')
    expect(wrapper.emitted('select')).toEqual([['2026-04-15']])
  })

  it('has correct aria-label with event count', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [makeEvent(), makeEvent({ id: '2' })],
        selected: false,
        today: false,
      },
    })
    const label = wrapper.find('.calendar-day-cell').attributes('aria-label')
    expect(label).toContain('2 events')
  })

  it('has correct aria-label for single event', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [makeEvent()],
        selected: false,
        today: false,
      },
    })
    const label = wrapper.find('.calendar-day-cell').attributes('aria-label')
    expect(label).toContain('1 event.')
  })

  it('shows accessibility indicator when event has accessibility tags', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [makeEvent({ accessibilityTags: ['sensory_friendly'] })],
        selected: false,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell__a11y-indicator').exists()).toBe(true)
  })

  it('does not show accessibility indicator when no events have tags', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [makeEvent()],
        selected: false,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell__a11y-indicator').exists()).toBe(false)
  })

  it('sets aria-selected when selected', async () => {
    const wrapper = await mountSuspended(CalendarDayCell, {
      props: {
        date: '2026-04-15',
        events: [],
        selected: true,
        today: false,
      },
    })
    expect(wrapper.find('.calendar-day-cell').attributes('aria-selected')).toBe('true')
  })
})
