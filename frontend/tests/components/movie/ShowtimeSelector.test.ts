import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ShowtimeSelector from '~/components/movie/ShowtimeSelector.vue'
import type { Showtime } from '~/types/showtime'

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'test-movie',
    movieTitle: 'Test',
    screenId: 's1',
    screenName: 'Screen 1',
    startTime: '2026-04-07T19:00:00Z',
    endTime: '2026-04-07T21:00:00Z',
    priceStandard: 1500,
    pricePremium: 2000,
    priceAccessible: 1200,
    ...overrides,
  }
}

describe('ShowtimeSelector', () => {
  it('renders a date strip tab for each day in the 7-day window', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z' }),
      makeShowtime({ id: 'st-2', startTime: '2026-04-08T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    // The date strip exposes 7 day buttons regardless of how many dates have data.
    const days = wrapper.findAll('.showtime-selector__day')
    expect(days).toHaveLength(7)
  })

  it('active tab has aria-selected=true', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z' }),
      makeShowtime({ id: 'st-2', startTime: '2026-04-08T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const tabs = wrapper.findAll('[role="tab"]')
    const active = tabs.filter(t => t.attributes('aria-selected') === 'true')
    expect(active).toHaveLength(1)
  })

  it('date strip uses the tablist role with a descriptive aria-label', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [makeShowtime()] },
    })
    const tablist = wrapper.find('[role="tablist"]')
    expect(tablist.exists()).toBe(true)
    expect(tablist.attributes('aria-label')).toBe('Showtime dates')
  })

  it('renders the Reserve your seat title', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [makeShowtime()] },
    })
    expect(wrapper.find('.bay-title').text()).toContain('Reserve your')
  })

  it('renders all 7 format filter chips', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [makeShowtime()] },
    })
    const chips = wrapper.findAll('.showtime-selector__fmt')
    expect(chips.length).toBe(7)
    const labels = chips.map(c => c.text())
    expect(labels).toEqual(
      expect.arrayContaining([
        expect.stringContaining('All formats'),
        expect.stringContaining('70mm'),
        expect.stringContaining('IMAX'),
        expect.stringContaining('Digital'),
        expect.stringContaining('Late Show'),
        expect.stringContaining('Members Only'),
        expect.stringContaining('Closed Captions'),
      ]),
    )
  })

  it('toggles the active format chip on click', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [makeShowtime()] },
    })
    const chips = wrapper.findAll('.showtime-selector__fmt')
    // First chip (All formats) is on by default
    expect(chips[0].classes()).toContain('showtime-selector__fmt--on')
    await chips[1].trigger('click')
    expect(chips[1].classes()).toContain('showtime-selector__fmt--on')
    expect(chips[0].classes()).not.toContain('showtime-selector__fmt--on')
  })

  it('slot links point to /purchase/:id', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: {
        showtimes: [makeShowtime({ id: 'st-42', startTime: '2026-04-07T19:00:00Z' })],
      },
    })
    const slot = wrapper.find('.showtime-selector__slot')
    expect(slot.exists()).toBe(true)
    expect(slot.attributes('href')).toBe('/purchase/st-42')
  })

  it('shows an empty-state message when there are no showtimes at all', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [] },
    })
    expect(wrapper.text()).toContain('No showtimes')
  })

  it('renders the "From $X" price for the lowest-priced slot in each group', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: {
        showtimes: [
          makeShowtime({ id: 'st-1', startTime: '2026-04-07T19:00:00Z', priceStandard: 1500 }),
        ],
      },
    })
    expect(wrapper.text()).toContain('$15.00')
  })
})
