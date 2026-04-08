import { describe, it, expect } from 'vitest'
import { weekRange } from '~/utils/weekRange'

describe('weekRange', () => {
  it('returns correct range for a mid-week same-month date', () => {
    // Wednesday April 9, 2026
    const result = weekRange(new Date(2026, 3, 9))

    expect(result.start.getFullYear()).toBe(2026)
    expect(result.start.getMonth()).toBe(3) // April (0-indexed)
    expect(result.start.getDate()).toBe(6)  // Monday April 6

    // End is next Monday (exclusive upper bound)
    expect(result.end.getFullYear()).toBe(2026)
    expect(result.end.getMonth()).toBe(3)
    expect(result.end.getDate()).toBe(13)   // Next Monday April 13

    expect(result.months).toEqual([{ month: 4, year: 2026 }])
  })

  it('returns both months for a cross-month week', () => {
    // Wednesday April 29, 2026
    const result = weekRange(new Date(2026, 3, 29))

    expect(result.start.getMonth()).toBe(3) // April
    expect(result.start.getDate()).toBe(27) // Monday April 27

    // End is next Monday (exclusive upper bound)
    expect(result.end.getMonth()).toBe(4)   // May
    expect(result.end.getDate()).toBe(4)    // Next Monday May 4

    expect(result.months).toEqual([
      { month: 4, year: 2026 },
      { month: 5, year: 2026 },
    ])
  })

  it('returns both months and years for a year-boundary week', () => {
    // Wednesday December 31, 2025
    const result = weekRange(new Date(2025, 11, 31))

    expect(result.start.getFullYear()).toBe(2025)
    expect(result.start.getMonth()).toBe(11) // December
    expect(result.start.getDate()).toBe(29)  // Monday Dec 29

    // End is next Monday (exclusive upper bound)
    expect(result.end.getFullYear()).toBe(2026)
    expect(result.end.getMonth()).toBe(0)    // January
    expect(result.end.getDate()).toBe(5)     // Next Monday Jan 5

    expect(result.months).toEqual([
      { month: 12, year: 2025 },
      { month: 1, year: 2026 },
    ])
  })

  it('returns the same day as start when input is a Monday', () => {
    // Monday April 6, 2026
    const result = weekRange(new Date(2026, 3, 6))

    expect(result.start.getDate()).toBe(6)  // Monday April 6
    expect(result.end.getDate()).toBe(13)   // Next Monday April 13
  })

  it('returns correct range when input is a Sunday', () => {
    // Sunday April 12, 2026
    const result = weekRange(new Date(2026, 3, 12))

    expect(result.start.getMonth()).toBe(3)
    expect(result.start.getDate()).toBe(6)  // Monday April 6
    expect(result.end.getDate()).toBe(13)   // Next Monday April 13
  })

  it('returns one month entry when the week is entirely within one month', () => {
    // Monday April 13, 2026 (Mon Apr 13 - Sun Apr 19)
    const result = weekRange(new Date(2026, 3, 13))

    expect(result.start.getDate()).toBe(13)
    expect(result.end.getDate()).toBe(20)  // Next Monday April 20
    expect(result.months).toHaveLength(1)
    expect(result.months[0]).toEqual({ month: 4, year: 2026 })
  })

  it('does not mutate the input date', () => {
    const input = new Date(2026, 3, 9, 14, 30, 0)
    const originalTime = input.getTime()

    weekRange(input)

    expect(input.getTime()).toBe(originalTime)
  })

  it('sets start and end times to midnight', () => {
    const result = weekRange(new Date(2026, 3, 9, 15, 45, 30))

    expect(result.start.getHours()).toBe(0)
    expect(result.start.getMinutes()).toBe(0)
    expect(result.start.getSeconds()).toBe(0)

    // End is next Monday at midnight (exclusive upper bound)
    expect(result.end.getHours()).toBe(0)
    expect(result.end.getMinutes()).toBe(0)
    expect(result.end.getSeconds()).toBe(0)
  })
})
