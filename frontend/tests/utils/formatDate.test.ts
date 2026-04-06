import { describe, it, expect } from 'vitest'
import { formatDate, formatDateTime } from '~/utils/formatDate'

describe('formatDate', () => {
  it('formats a date-only string without day shift', () => {
    // Date-only strings anchored to UTC noon — should never shift calendar date
    expect(formatDate('2026-04-03')).toBe('Apr 3, 2026')
  })

  it('formats a full ISO datetime string', () => {
    const result = formatDate('2026-04-03T19:00:00Z')
    // Exact output depends on local timezone, but should contain year
    expect(result).toContain('2026')
  })
})

describe('formatDateTime', () => {
  it('formats datetime with local time', () => {
    const result = formatDateTime('2026-04-03T19:00:00Z')
    expect(result).toContain('2026')
    // Should include a time component (AM or PM)
    expect(result).toMatch(/AM|PM/)
  })

  it('formats date-only string without day shift', () => {
    const result = formatDateTime('2026-04-03')
    expect(result).toContain('Apr 3, 2026')
  })
})
