import { describe, it, expect } from 'vitest'
import { formatCurrency, formatCurrencyParts } from '~/utils/formatCurrency'

describe('formatCurrency', () => {
  it('formats cents to dollar string', () => {
    expect(formatCurrency(1299)).toBe('$12.99')
  })

  it('passes negatives through with a leading minus (discount/refund lines)', () => {
    expect(formatCurrency(-500)).toBe('-$5.00')
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

describe('formatCurrencyParts', () => {
  it('splits into thousands-separated whole and 2-digit decimal', () => {
    expect(formatCurrencyParts(123456)).toEqual({ whole: '1,234', dec: '56' })
  })

  it('pads single-digit cents and zero-pads the whole', () => {
    expect(formatCurrencyParts(5)).toEqual({ whole: '0', dec: '05' })
  })

  it('clamps negatives to zero (consumers never display a minus)', () => {
    expect(formatCurrencyParts(-500)).toEqual({ whole: '0', dec: '00' })
  })
})
