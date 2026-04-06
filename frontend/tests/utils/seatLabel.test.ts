import { describe, it, expect } from 'vitest'
import { seatLabel } from '~/utils/seatLabel'

describe('seatLabel', () => {
  it('formats a simple seat ID', () => {
    expect(seatLabel('A1')).toBe('Row A, Seat 1')
  })

  it('formats a two-digit seat number', () => {
    expect(seatLabel('B12')).toBe('Row B, Seat 12')
  })

  it('handles lowercase input', () => {
    expect(seatLabel('c5')).toBe('Row C, Seat 5')
  })

  it('returns original string for invalid format', () => {
    expect(seatLabel('invalid')).toBe('invalid')
  })

  it('returns original string for empty string', () => {
    expect(seatLabel('')).toBe('')
  })
})
