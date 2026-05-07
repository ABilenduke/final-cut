import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeDayCell from '~/components/calendar/BridgeDayCell.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function event(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: `evt-${Math.random().toString(36).slice(2)}`,
    type: 'showtime',
    title: 'A Movie',
    date: '2026-05-13',
    startTime: '2026-05-13T19:00:00',
    endTime: '2026-05-13T21:00:00',
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

const baseProps = {
  date: '2026-05-13',
  dayNumber: 13,
  events: [],
  selected: false,
  today: false,
  outsideMonth: false,
  focused: false,
}

describe('BridgeDayCell', () => {
  it('renders the day number', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, { props: baseProps })
    expect(wrapper.find('.bridge-day-cell__num').text()).toBe('13')
  })

  it('renders up to two event lines and an overflow row', async () => {
    const events = Array.from({ length: 4 }, (_, i) =>
      event({
        title: `Movie ${i}`,
        startTime: `2026-05-13T${10 + i}:00:00`,
      }),
    )
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, events },
    })
    expect(wrapper.findAll('.bridge-day-cell__event')).toHaveLength(2)
    expect(wrapper.find('.bridge-day-cell__overflow').text()).toBe('+2 more')
  })

  it('skips the overflow row when fewer than three events', async () => {
    const events = [event({ title: 'Solo' })]
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, events },
    })
    expect(wrapper.find('.bridge-day-cell__overflow').exists()).toBe(false)
  })

  it('renders rental events as "Privately booked" and applies the corner stripe class', async () => {
    const events = [event({ type: 'private_screening_blackout', title: 'Foo' })]
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, events },
    })
    expect(wrapper.classes()).toContain('bridge-day-cell--has-rental')
    expect(wrapper.find('.bridge-day-cell__event-title').text()).toBe('Privately booked')
  })

  it('applies the selected modifier class', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, selected: true },
    })
    expect(wrapper.classes()).toContain('bridge-day-cell--selected')
    expect(wrapper.attributes('aria-selected')).toBe('true')
  })

  it('applies the today modifier class', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, today: true },
    })
    expect(wrapper.classes()).toContain('bridge-day-cell--today')
  })

  it('applies the muted modifier class for dates outside the visible month', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, outsideMonth: true },
    })
    expect(wrapper.classes()).toContain('bridge-day-cell--muted')
  })

  it('emits select on click for in-month days', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, { props: baseProps })
    await wrapper.trigger('click')
    expect(wrapper.emitted('select')?.[0]).toEqual(['2026-05-13'])
  })

  it('does not emit select for out-of-month days', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, outsideMonth: true },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('emits select on Enter keydown', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, { props: baseProps })
    await wrapper.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('select')?.[0]).toEqual(['2026-05-13'])
  })

  it('renders type-color flag dots, capped at four', async () => {
    const events = [
      event({ type: 'showtime' }),
      event({ type: 'special_event' }),
      event({ type: 'loyalty_exclusive' }),
      event({ type: 'private_screening_blackout' }),
      event({ type: 'showtime' }),
    ]
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, events },
    })
    expect(wrapper.findAll('.bridge-day-cell__flag')).toHaveLength(4)
  })

  it('formats event-line time as HH:mm', async () => {
    const events = [event({ startTime: '2026-05-13T07:05:00' })]
    const wrapper = await mountSuspended(BridgeDayCell, {
      props: { ...baseProps, events },
    })
    expect(wrapper.find('.bridge-day-cell__event-time').text()).toBe('07:05')
  })

  it('exposes role=gridcell for screen readers', async () => {
    const wrapper = await mountSuspended(BridgeDayCell, { props: baseProps })
    expect(wrapper.attributes('role')).toBe('gridcell')
  })
})
