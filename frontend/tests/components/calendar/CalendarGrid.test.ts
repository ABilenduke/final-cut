import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CalendarGrid from '~/components/calendar/CalendarGrid.vue'
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

describe('CalendarGrid', () => {
  describe('Month view', () => {
    it('renders 42 day cells (6 weeks)', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      const cells = wrapper.findAll('.calendar-day-cell')
      expect(cells).toHaveLength(42)
    })

    it('renders weekday headers', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      const headers = wrapper.findAll('[role="columnheader"]')
      expect(headers).toHaveLength(7)
      expect(headers[0].text()).toBe('Mon')
      expect(headers[6].text()).toBe('Sun')
    })

    it('displays month and year in header', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      expect(wrapper.find('.calendar-grid__title').text()).toBe('April 2026')
    })

    it('marks selected date', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-15',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      const selected = wrapper.find('[data-date="2026-04-15"]')
      expect(selected.classes()).toContain('calendar-day-cell--selected')
    })

    it('distributes events to correct day cells', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [
            makeEvent({ date: '2026-04-15' }),
            makeEvent({ id: '2', date: '2026-04-15', type: 'special_event' }),
          ],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      const cell = wrapper.find('[data-date="2026-04-15"]')
      const dots = cell.findAll('.calendar-day-cell__dot')
      expect(dots).toHaveLength(2)
    })

    it('emits navigate with previous month on prev click', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      await wrapper.findAll('.calendar-grid__nav-btn')[0].trigger('click')
      expect(wrapper.emitted('navigate')?.[0]).toEqual([{ month: 3, year: 2026 }])
    })

    it('emits navigate with next month on next click', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      await wrapper.findAll('.calendar-grid__nav-btn')[1].trigger('click')
      expect(wrapper.emitted('navigate')?.[0]).toEqual([{ month: 5, year: 2026 }])
    })

    it('wraps year on January prev', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-01-15',
          view: 'month',
          month: 1,
          year: 2026,
        },
      })
      await wrapper.findAll('.calendar-grid__nav-btn')[0].trigger('click')
      expect(wrapper.emitted('navigate')?.[0]).toEqual([{ month: 12, year: 2025 }])
    })

    it('emits select-date when a day cell is clicked', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      await wrapper.find('[data-date="2026-04-15"]').trigger('click')
      expect(wrapper.emitted('select-date')?.[0]).toEqual(['2026-04-15'])
    })

    it('has role="grid" on the month grid', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'month',
          month: 4,
          year: 2026,
        },
      })
      expect(wrapper.find('[role="grid"]').exists()).toBe(true)
    })
  })

  describe('Week view', () => {
    it('renders 7 day cells', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'week',
          month: 4,
          year: 2026,
        },
      })
      const cells = wrapper.findAll('.calendar-day-cell')
      expect(cells).toHaveLength(7)
    })

    it('renders weekday headers', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'week',
          month: 4,
          year: 2026,
        },
      })
      const headers = wrapper.findAll('[role="columnheader"]')
      expect(headers).toHaveLength(7)
    })
  })

  describe('List view', () => {
    it('renders events sorted by date', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [
            makeEvent({ id: '2', date: '2026-04-20', startTime: '2026-04-20T19:00:00Z', title: 'Later Event' }),
            makeEvent({ id: '1', date: '2026-04-10', startTime: '2026-04-10T19:00:00Z', title: 'Earlier Event' }),
          ],
          selectedDate: '2026-04-08',
          view: 'list',
          month: 4,
          year: 2026,
        },
      })
      const items = wrapper.findAll('.calendar-grid__list-title')
      expect(items[0].text()).toBe('Earlier Event')
      expect(items[1].text()).toBe('Later Event')
    })

    it('renders empty state when no events', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [],
          selectedDate: '2026-04-08',
          view: 'list',
          month: 4,
          year: 2026,
        },
      })
      expect(wrapper.find('.calendar-grid__empty').text()).toBe('No events this month')
    })

    it('groups events by date', async () => {
      const wrapper = await mountSuspended(CalendarGrid, {
        props: {
          events: [
            makeEvent({ id: '1', date: '2026-04-10', startTime: '2026-04-10T19:00:00Z' }),
            makeEvent({ id: '2', date: '2026-04-10', startTime: '2026-04-10T21:00:00Z', title: 'Second' }),
            makeEvent({ id: '3', date: '2026-04-15', startTime: '2026-04-15T19:00:00Z', title: 'Different Day' }),
          ],
          selectedDate: '2026-04-08',
          view: 'list',
          month: 4,
          year: 2026,
        },
      })
      const groups = wrapper.findAll('.calendar-grid__list-group')
      expect(groups).toHaveLength(2)
    })
  })
})
