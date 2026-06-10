import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeWeekStrip from '~/components/calendar/BridgeWeekStrip.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function makeEvent(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: 'e1',
    type: 'special_event',
    title: 'Director Q&A',
    date: '2026-05-13',
    startTime: '2026-05-13T19:00:00Z',
    endTime: null,
    description: '',
    movieSlug: null,
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  } as CalendarEvent
}

// 2026-05-13 is a Wednesday; its Mon-start week is 11–17 May.
const PROPS = {
  selectedDate: '2026-05-13',
  todayDate: '2026-05-12',
  month: 5,
  year: 2026,
}

describe('BridgeWeekStrip', () => {
  it('renders the Monday-start week containing the selected date', async () => {
    const wrapper = await mountSuspended(BridgeWeekStrip, {
      props: { ...PROPS, events: [] },
    })

    const days = wrapper.findAll('[data-testid="week-day"]')
    expect(days).toHaveLength(7)
    expect(days[0]!.text()).toContain('11')
    expect(days[6]!.text()).toContain('17')
  })

  it('lists every event for its day without a cap and emits select-date on click', async () => {
    const events = Array.from({ length: 5 }, (_, i) =>
      makeEvent({ id: `e${i}`, title: `Event ${i}`, date: '2026-05-13' }),
    )
    const wrapper = await mountSuspended(BridgeWeekStrip, {
      props: { ...PROPS, events },
    })

    expect(wrapper.text()).toContain('Event 4')
    expect(wrapper.text()).not.toContain('more')

    await wrapper.findAll('[data-testid="week-day"]')[0]!.trigger('click')
    expect(wrapper.emitted('select-date')![0]![0]).toBe('2026-05-11')
  })

  it('marks today and the selected day distinctly', async () => {
    const wrapper = await mountSuspended(BridgeWeekStrip, {
      props: { ...PROPS, events: [] },
    })

    expect(wrapper.find('.week-strip__day--today').text()).toContain('12')
    expect(wrapper.find('.week-strip__day--selected').text()).toContain('13')
  })
})
