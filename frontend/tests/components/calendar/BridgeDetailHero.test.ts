import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeDetailHero from '~/components/calendar/BridgeDetailHero.vue'
import type { CalendarEvent, CalendarEventShowtime } from '~/types/calendar-event'

function event(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: 'evt',
    type: 'showtime',
    title: 'Inception',
    date: '2026-05-13',
    startTime: '2026-05-13T19:00:00',
    endTime: '2026-05-13T21:00:00',
    description: '',
    movieSlug: 'inception',
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    showtimes: null,
    ...overrides,
  }
}

function tile(overrides: Partial<CalendarEventShowtime> = {}): CalendarEventShowtime {
  return {
    id: 's1',
    startTime: '2026-05-13T19:00:00',
    auditoriumLabel: 'Auditorium 03',
    soldOut: false,
    ...overrides,
  }
}

describe('BridgeDetailHero', () => {
  it('renders the day numeral, weekday, and screening count', async () => {
    const events = [event(), event({ id: 'b' })]
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events, heroEvent: events[0]! },
    })
    expect(wrapper.find('.bridge-hero-card__day-num').text()).toBe('13')
    // 2026-05-13 is a Wednesday.
    expect(wrapper.find('.bridge-hero-card__day-meta').text()).toContain('Wednesday')
    expect(wrapper.find('.bridge-hero-card__day-meta').text()).toContain('2 screenings')
  })

  it('renders the hero film title linked to the movie page', async () => {
    const ev = event({ movieSlug: 'inception' })
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events: [ev], heroEvent: ev },
    })
    const link = wrapper.find('.bridge-hero-card__title')
    expect(link.text()).toBe('Inception')
    expect(link.attributes('href')).toBe('/movies/inception')
  })

  it('renders showtime tiles from the hero events showtimes payload, capped at four', async () => {
    const ev = event({
      showtimes: [
        tile({ id: 's1', startTime: '2026-05-13T18:00:00' }),
        tile({ id: 's2', startTime: '2026-05-13T20:00:00', auditoriumLabel: 'Auditorium 02' }),
        tile({ id: 's3', startTime: '2026-05-13T22:00:00', auditoriumLabel: 'IMAX' }),
        tile({ id: 's4', startTime: '2026-05-14T00:00:00', auditoriumLabel: 'Screen 7' }),
        tile({ id: 's5', startTime: '2026-05-14T01:00:00', auditoriumLabel: 'Screen 9' }),
      ],
    })
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events: [ev], heroEvent: ev },
    })
    const tiles = wrapper.findAll('.bridge-hero-card__time')
    expect(tiles).toHaveLength(4)
    expect(tiles[0]!.text()).toContain('18:00')
    expect(tiles[1]!.text()).toContain('Aud 02')
    expect(tiles[2]!.text()).toContain('IMAX')
  })

  it('marks sold-out showtime tiles', async () => {
    const ev = event({
      showtimes: [
        tile({ id: 'a', soldOut: false }),
        tile({ id: 'b', startTime: '2026-05-13T21:00:00', soldOut: true }),
      ],
    })
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events: [ev], heroEvent: ev },
    })
    const tiles = wrapper.findAll('.bridge-hero-card__time')
    expect(tiles[1]!.classes()).toContain('bridge-hero-card__time--soldout')
    expect(tiles[1]!.attributes('aria-disabled')).toBe('true')
  })

  it('renders an empty placeholder when no hero event exists', async () => {
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events: [], heroEvent: null },
    })
    expect(wrapper.find('.bridge-hero-card__empty').exists()).toBe(true)
    expect(wrapper.find('.bridge-hero-card__hero').exists()).toBe(false)
  })

  it('shows the description when present', async () => {
    const ev = event({ description: 'A heist within a heist.' })
    const wrapper = await mountSuspended(BridgeDetailHero, {
      props: { selectedDate: '2026-05-13', events: [ev], heroEvent: ev },
    })
    expect(wrapper.find('.bridge-hero-card__sub').text()).toContain('A heist within a heist.')
  })
})
