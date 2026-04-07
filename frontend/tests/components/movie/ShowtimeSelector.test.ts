import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ShowtimeSelector from '~/components/movie/ShowtimeSelector.vue'
import type { Showtime } from '~/types/showtime'

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'test-REMOVED',
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
  it('renders date tabs for each unique date', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z' }),
      makeShowtime({ id: 'st-2', startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-3', startTime: '2026-04-08T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs).toHaveLength(2)
  })

  it('active tab has aria-selected true', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z' }),
      makeShowtime({ id: 'st-2', startTime: '2026-04-08T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const tabs = wrapper.findAll('[role="tab"]')
    // The first tab should be active by default (assuming today is not one of these dates)
    const activeTab = tabs.find(t => t.attributes('aria-selected') === 'true')
    expect(activeTab).toBeDefined()
  })

  it('renders time slot text', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    // Should render a time element
    const timeEl = wrapper.find('time')
    expect(timeEl.exists()).toBe(true)
    expect(timeEl.attributes('datetime')).toBe('2026-04-07T19:00:00Z')
  })

  it('time slots link to purchase page', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-42', startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const link = wrapper.find('.showtime-selector__slot')
    expect(link.exists()).toBe(true)
    // NuxtLink renders as an <a> in the test environment
    expect(link.attributes('href')).toBe('/purchase/st-42')
  })

  it('shows formatted price', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T19:00:00Z', priceStandard: 1500 }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    expect(wrapper.text()).toContain('$15.00')
  })

  it('shows empty state when no showtimes', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [] },
    })
    expect(wrapper.text()).toContain('No showtimes available')
  })

  it('tab container has tablist role', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const tablist = wrapper.find('[role="tablist"]')
    expect(tablist.exists()).toBe(true)
    expect(tablist.attributes('aria-label')).toBe('Showtime dates')
  })

  it('panel has tabpanel role', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const panel = wrapper.find('[role="tabpanel"]')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('aria-labelledby')).toBeTruthy()
  })

  it('groups showtimes by date correctly', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z' }),
      makeShowtime({ id: 'st-2', startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-3', startTime: '2026-04-08T14:00:00Z' }),
      makeShowtime({ id: 'st-4', startTime: '2026-04-08T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs).toHaveLength(2)
  })
})
