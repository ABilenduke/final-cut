import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeAgendaList from '~/components/calendar/BridgeAgendaList.vue'
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

describe('BridgeAgendaList', () => {
  it('groups the month chronologically by day and emits select-date', async () => {
    const wrapper = await mountSuspended(BridgeAgendaList, {
      props: {
        events: [
          makeEvent({ id: 'b', date: '2026-05-20', title: 'Late Event' }),
          makeEvent({ id: 'a', date: '2026-05-03', title: 'Early Event' }),
        ],
        selectedDate: '2026-05-03',
        todayDate: '2026-05-12',
      },
    })

    const groups = wrapper.findAll('[data-testid="agenda-day"]')
    expect(groups).toHaveLength(2)
    expect(groups[0]!.text()).toContain('Early Event')
    expect(groups[1]!.text()).toContain('Late Event')

    await groups[1]!.find('[data-testid="agenda-day-heading"]').trigger('click')
    expect(wrapper.emitted('select-date')![0]![0]).toBe('2026-05-20')
  })

  it('renders a quiet empty state when no events survive the filters', async () => {
    const wrapper = await mountSuspended(BridgeAgendaList, {
      props: { events: [], selectedDate: '2026-05-01', todayDate: '2026-05-12' },
    })

    expect(wrapper.find('[data-testid="agenda-empty"]').exists()).toBe(true)
  })
})
