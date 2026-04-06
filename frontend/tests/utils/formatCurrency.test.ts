import { describe, it, expect } from 'vitest'
import { formatCurrency } from '~/utils/formatCurrency'

describe('formatCurrency', () => {
  it('formats cents to dollar string', () => {
    expect(formatCurrency(1299)).toBe('$12.99')
  })

  it('formats zero cents', () => {
    expect(formatCurrency(0)).toBe('$0.00')
  })

  it('formats whole dollar amounts', () => {
    expect(formatCurrency(1000)).toBe('$10.00')
  })

  it('formats large amounts', () => {
    expect(formatCurrency(99999)).toBe('$999.99')
  })

  it('formats single digit cents', () => {
    expect(formatCurrency(5)).toBe('$0.05')
  })
})
