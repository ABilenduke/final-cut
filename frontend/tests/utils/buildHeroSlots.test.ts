import { describe, it, expect } from 'vitest'
import { buildHeroSlots } from '~/utils/buildHeroSlots'
import type { Showtime } from '~/types/showtime'

function makeShowtime(overrides: Partial<Showtime> & { id: string }): Showtime {
  return {
    movieId: 1,
    movieSlug: 'blade-runner-2049',
    movieTitle: 'Blade Runner 2049',
    screenId: 'aud-1',
    screenName: 'Screen 1',
    startTime: '2026-06-10T23:30:00Z', // 19:30 New York (EDT)
    endTime: '2026-06-11T02:14:00Z',
    priceStandard: 1850,
    pricePremium: 2400,
    priceAccessible: 1850,
    location: { slug: 'downtown', name: 'Downtown', latitude: null, longitude: null },
    ...overrides,
  } as Showtime
}

describe('buildHeroSlots', () => {
  it('formats start times in the app timezone with split meridiem', () => {
    const slots = buildHeroSlots([makeShowtime({ id: 'st-1' })], 'America/New_York')

    expect(slots).toHaveLength(1)
    expect(slots[0]).toMatchObject({
      id: 'st-1',
      locationSlug: 'downtown',
      time: '7:30',
      meridiem: 'PM',
    })
  })

  it('caps the list at eight slots in input order', () => {
    const many = Array.from({ length: 11 }, (_, i) =>
      makeShowtime({ id: `st-${i}`, startTime: `2026-06-10T1${i % 10}:00:00Z` }),
    )
    const slots = buildHeroSlots(many, 'America/New_York')

    expect(slots).toHaveLength(8)
    expect(slots[0]!.id).toBe('st-0')
  })

  it('returns an empty array for no showtimes', () => {
    expect(buildHeroSlots([], 'America/New_York')).toEqual([])
  })
})
