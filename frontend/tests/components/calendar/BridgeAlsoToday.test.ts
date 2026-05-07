import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeAlsoToday from '~/components/calendar/BridgeAlsoToday.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function event(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: `evt-${Math.random().toString(36).slice(2)}`,
    type: 'showtime',
    title: 'A Movie',
    date: '2026-05-13',
    startTime: '2026-05-13T19:00:00',
    endTime: null,
    description: '',
    movieSlug: 'a-movie',
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    showtimes: null,
    ...overrides,
  }
}

describe('BridgeAlsoToday', () => {
  it('does not render when there are no events', async () => {
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events: [] } })
    expect(wrapper.find('.bridge-also-today').exists()).toBe(false)
  })

  it('lists up to five rows in event order', async () => {
    const events = Array.from({ length: 7 }, (_, i) =>
      event({ id: `e${i}`, title: `Title ${i}` }),
    )
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events } })
    expect(wrapper.findAll('.bridge-also-today__row')).toHaveLength(5)
  })

  it('shows the total count in the header even when truncating the list', async () => {
    const events = Array.from({ length: 7 }, (_, i) =>
      event({ id: `e${i}`, title: `Title ${i}` }),
    )
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events } })
    expect(wrapper.find('.bridge-also-today__head').text()).toContain('7')
  })

  it('renders rental rows with the muted treatment and × badge', async () => {
    const events = [event({ type: 'private_screening_blackout', title: 'X' })]
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events } })
    expect(wrapper.find('.bridge-also-today__row--rental').exists()).toBe(true)
    expect(wrapper.find('.bridge-also-today__title--rental').text()).toContain('Private rental · all day')
  })

  it('formats the event time on regular rows as HH:mm', async () => {
    const events = [event({ startTime: '2026-05-13T08:30:00' })]
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events } })
    expect(wrapper.find('.bridge-also-today__time').text()).toBe('08:30')
  })

  it('applies the type label per event type', async () => {
    const events = [
      event({ id: 'a', type: 'showtime' }),
      event({ id: 'b', type: 'special_event' }),
      event({ id: 'c', type: 'loyalty_exclusive' }),
    ]
    const wrapper = await mountSuspended(BridgeAlsoToday, { props: { events } })
    const labels = wrapper.findAll('.bridge-also-today__type').map((el) => el.text())
    expect(labels).toContain('Showtime')
    expect(labels).toContain('Special')
    expect(labels).toContain('Members')
  })
})
