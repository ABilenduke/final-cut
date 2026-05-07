import { describe, it, expect, beforeEach } from 'vitest'
import { useBridgeFilters, ALL_CHIPS } from '~/composables/useBridgeFilters'
import type { CalendarEvent } from '~/types/calendar-event'

function makeEvent(overrides: Partial<CalendarEvent>): CalendarEvent {
  return {
    id: 'evt-1',
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

describe('useBridgeFilters', () => {
  beforeEach(() => {
    // Reset state between tests by force-setting all chips on.
    const { setChips } = useBridgeFilters()
    setChips(ALL_CHIPS)
  })

  it('defaults to all six chips active', () => {
    const { activeChips } = useBridgeFilters()
    expect(Array.from(activeChips.value).sort()).toEqual([...ALL_CHIPS].sort())
  })

  it('toggleChip removes an active chip', () => {
    const { toggleChip, isActive } = useBridgeFilters()
    toggleChip('member')
    expect(isActive('member')).toBe(false)
    expect(isActive('showtime')).toBe(true)
  })

  it('toggleChip restores a previously removed chip', () => {
    const { toggleChip, isActive } = useBridgeFilters()
    toggleChip('sensory')
    toggleChip('sensory')
    expect(isActive('sensory')).toBe(true)
  })

  it('isVisible returns true for a showtime event when showtime chip is on', () => {
    const { isVisible } = useBridgeFilters()
    expect(isVisible(makeEvent({ type: 'showtime' }))).toBe(true)
  })

  it('isVisible returns false for a loyalty event when member chip is off', () => {
    const { toggleChip, isVisible } = useBridgeFilters()
    toggleChip('member')
    expect(isVisible(makeEvent({ type: 'loyalty_exclusive' }))).toBe(false)
  })

  it('isVisible returns true for a showtime tagged sensory_friendly when only sensory chip is on', () => {
    const { setChips, isVisible } = useBridgeFilters()
    setChips(['sensory'])
    expect(
      isVisible(
        makeEvent({
          type: 'showtime',
          accessibilityTags: ['sensory_friendly'],
        }),
      ),
    ).toBe(true)
  })

  it('isVisible returns false for an untagged showtime when only sensory chip is on', () => {
    const { setChips, isVisible } = useBridgeFilters()
    setChips(['sensory'])
    expect(isVisible(makeEvent({ type: 'showtime' }))).toBe(false)
  })

  it('isVisible always returns true for rental events regardless of chip state', () => {
    const { setChips, isVisible } = useBridgeFilters()
    setChips([])
    expect(isVisible(makeEvent({ type: 'private_screening_blackout' }))).toBe(true)
  })

  it('toQueryValue returns undefined when all six chips are active', () => {
    const { toQueryValue } = useBridgeFilters()
    expect(toQueryValue()).toBeUndefined()
  })

  it('toQueryValue serializes the active subset in canonical order', () => {
    const { setChips, toQueryValue } = useBridgeFilters()
    setChips(['captions', 'member'])
    expect(toQueryValue()).toBe('member,captions')
  })

  it('toQueryValue returns empty string when no chips are active', () => {
    const { setChips, toQueryValue } = useBridgeFilters()
    setChips([])
    expect(toQueryValue()).toBe('')
  })

  it('fromQueryValue resets to defaults when value is undefined', () => {
    const { setChips, fromQueryValue, activeChips } = useBridgeFilters()
    setChips(['member'])
    fromQueryValue(undefined)
    expect(activeChips.value.size).toBe(ALL_CHIPS.length)
  })

  it('fromQueryValue parses a comma-separated chip list', () => {
    const { fromQueryValue, isActive } = useBridgeFilters()
    fromQueryValue('showtime,sensory')
    expect(isActive('showtime')).toBe(true)
    expect(isActive('sensory')).toBe(true)
    expect(isActive('member')).toBe(false)
  })

  it('fromQueryValue ignores unknown chip slugs', () => {
    const { fromQueryValue, activeChips } = useBridgeFilters()
    fromQueryValue('showtime,bogus,sensory')
    expect(Array.from(activeChips.value).sort()).toEqual(['sensory', 'showtime'])
  })

  it('fromQueryValue with empty string clears all chips', () => {
    const { fromQueryValue, activeChips } = useBridgeFilters()
    fromQueryValue('')
    expect(activeChips.value.size).toBe(0)
  })

  it('fromLegacyQuery translates legacy ?type=loyalty_exclusive to the member chip', () => {
    const { fromLegacyQuery, activeChips } = useBridgeFilters()
    const applied = fromLegacyQuery('loyalty_exclusive', undefined)
    expect(applied).toBe(true)
    expect(Array.from(activeChips.value)).toEqual(['member'])
  })

  it('fromLegacyQuery translates ?accessibility=sensory_friendly,open_caption to chips', () => {
    const { fromLegacyQuery, activeChips } = useBridgeFilters()
    fromLegacyQuery(undefined, 'sensory_friendly,open_caption')
    expect(Array.from(activeChips.value).sort()).toEqual(['captions', 'sensory'])
  })

  it('fromLegacyQuery returns false when neither legacy param is present', () => {
    const { fromLegacyQuery } = useBridgeFilters()
    expect(fromLegacyQuery(undefined, undefined)).toBe(false)
    expect(fromLegacyQuery(null, '')).toBe(false)
  })
})
