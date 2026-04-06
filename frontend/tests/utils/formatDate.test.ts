import { describe, it, expect } from 'vitest'
import { formatDate, formatDateTime } from '~/utils/formatDate'

// All outputs are deterministic — formatters use a fixed America/New_York timezone.

describe('formatDate', () => {
  it('formats a date-only string without day shift', () => {
    // Date-only strings must not shift to the previous day in western timezones
    expect(formatDate('2026-04-03')).toBe('Apr 3, 2026')
  })

  it('formats a full ISO datetime string', () => {
    // 19:00 UTC = 15:00 ET (EDT), still April 3
    expect(formatDate('2026-04-03T19:00:00Z')).toBe('Apr 3, 2026')
  })

  it('handles late-night UTC that crosses midnight in ET', () => {
    // 03:00 UTC = 23:00 ET previous day (EDT)
    expect(formatDate('2026-04-03T03:00:00Z')).toBe('Apr 2, 2026')
  })
})

describe('formatDateTime', () => {
  it('formats datetime with time in ET', () => {
    // 19:00 UTC = 3:00 PM ET (EDT)
    expect(formatDateTime('2026-04-03T19:00:00Z')).toBe('Apr 3, 2026, 3:00 PM')
  })

  it('formats date-only string without day shift', () => {
    // Date-only strings parsed as noon local — time is meaningless but date must be correct
    const result = formatDateTime('2026-04-03')
    expect(result).toContain('Apr 3, 2026')
  })
})
