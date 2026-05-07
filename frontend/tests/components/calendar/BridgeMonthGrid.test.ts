import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeMonthGrid from '~/components/calendar/BridgeMonthGrid.vue'
import type { CalendarEvent } from '~/types/calendar-event'

const baseProps = {
  events: [] as CalendarEvent[],
  selectedDate: '2026-05-13',
  todayDate: '2026-05-06',
  month: 5,
  year: 2026,
}

describe('BridgeMonthGrid', () => {
  it('renders 35 cells for a month that fits in five weeks', async () => {
    const wrapper = await mountSuspended(BridgeMonthGrid, { props: baseProps })
    expect(wrapper.findAll('.bridge-day-cell')).toHaveLength(35)
  })

  it('renders 42 cells for a month that spans six weeks', async () => {
    // August 2026 starts on Saturday and has 31 days → 6 weeks needed.
    const wrapper = await mountSuspended(BridgeMonthGrid, {
      props: { ...baseProps, month: 8, year: 2026, selectedDate: '2026-08-01', todayDate: '2026-08-01' },
    })
    expect(wrapper.findAll('.bridge-day-cell')).toHaveLength(42)
  })

  it('marks today and selected cells in the visible month', async () => {
    const wrapper = await mountSuspended(BridgeMonthGrid, { props: baseProps })
    const today = wrapper.find('[data-date="2026-05-06"]')
    const selected = wrapper.find('[data-date="2026-05-13"]')
    expect(today.classes()).toContain('bridge-day-cell--today')
    expect(selected.classes()).toContain('bridge-day-cell--selected')
  })

  it('marks dates outside the visible month as muted', async () => {
    const wrapper = await mountSuspended(BridgeMonthGrid, { props: baseProps })
    // April 27 falls in the first cell of the grid (Mon-start, May 1 is Friday).
    const muted = wrapper.find('[data-date="2026-04-27"]')
    expect(muted.classes()).toContain('bridge-day-cell--muted')
  })

  it('emits select-date when a cell is clicked', async () => {
    const wrapper = await mountSuspended(BridgeMonthGrid, { props: baseProps })
    await wrapper.find('[data-date="2026-05-20"]').trigger('click')
    expect(wrapper.emitted('select-date')?.[0]).toEqual(['2026-05-20'])
  })

  it('renders the month and year toolbar label', async () => {
    const wrapper = await mountSuspended(BridgeMonthGrid, { props: baseProps })
    expect(wrapper.find('.bridge-month-grid__title').text()).toContain('May')
    expect(wrapper.find('.bridge-month-grid__year').text()).toContain('2026')
  })

  it('derives screening + special counters from the events array', async () => {
    const eventOf = (type: CalendarEvent['type'], id: string): CalendarEvent => ({
      id,
      type,
      title: id,
      date: '2026-05-13',
      startTime: '2026-05-13T19:00:00',
      endTime: null,
      description: '',
      movieSlug: 'm',
      imageUrl: null,
      slug: null,
      ticketUrl: null,
      loyaltyOnly: false,
      accessibilityTags: [],
    })
    const wrapper = await mountSuspended(BridgeMonthGrid, {
      props: {
        ...baseProps,
        events: [
          eventOf('showtime', 's1'),
          eventOf('showtime', 's2'),
          eventOf('showtime', 's3'),
          eventOf('special_event', 'sp1'),
        ],
      },
    })
    expect(wrapper.find('.bridge-month-grid__counters').text()).toContain('3')
    expect(wrapper.find('.bridge-month-grid__counters').text()).toContain('1')
  })

  it('partitions events into the right day cells', async () => {
    const events: CalendarEvent[] = [
      {
        id: '1',
        type: 'showtime',
        title: 'May 13 Movie',
        date: '2026-05-13',
        startTime: '2026-05-13T19:00:00',
        endTime: null,
        description: '',
        movieSlug: 'm',
        imageUrl: null,
        slug: null,
        ticketUrl: null,
        loyaltyOnly: false,
        accessibilityTags: [],
        showtimes: null,
      },
    ]
    const wrapper = await mountSuspended(BridgeMonthGrid, {
      props: { ...baseProps, events },
    })
    const cell = wrapper.find('[data-date="2026-05-13"]')
    expect(cell.text()).toContain('May 13 Movie')
    const otherCell = wrapper.find('[data-date="2026-05-14"]')
    expect(otherCell.text()).not.toContain('May 13 Movie')
  })
})
