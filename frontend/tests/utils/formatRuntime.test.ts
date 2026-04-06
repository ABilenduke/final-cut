import { describe, it, expect } from 'vitest'
import { formatRuntime } from '~/utils/formatRuntime'

describe('formatRuntime', () => {
  it('formats hours and minutes', () => {
    expect(formatRuntime(154)).toBe('2h 34m')
  })

  it('formats minutes only when under an hour', () => {
    expect(formatRuntime(45)).toBe('45m')
  })

  it('formats exact hours', () => {
    expect(formatRuntime(60)).toBe('1h 0m')
  })

  it('formats zero minutes', () => {
    expect(formatRuntime(0)).toBe('0m')
  })

  it('formats 120 minutes', () => {
    expect(formatRuntime(120)).toBe('2h 0m')
  })
})
