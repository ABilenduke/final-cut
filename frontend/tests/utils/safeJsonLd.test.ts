import { describe, it, expect } from 'vitest'
import { safeJsonLd } from '~/utils/safeJsonLd'

// Build the U+2028 / U+2029 line terminators at runtime so no raw code point
// ever lands in this source file (they ARE JS line terminators and would break
// parsing). The expected output uses escaped backslashes ('\\u2028'), which is
// the literal 6-char sequence safeJsonLd emits.
const LS = String.fromCharCode(0x2028)
const PS = String.fromCharCode(0x2029)

describe('safeJsonLd', () => {
  it('escapes < so a nested </script> cannot break out', () => {
    const out = safeJsonLd({ x: '</script>' })
    expect(out).not.toContain('</script>')
    expect(out).toContain('\\u003c')
  })

  it('escapes >', () => {
    const out = safeJsonLd({ x: 'a > b' })
    expect(out).toContain('\\u003e')
    expect(out).not.toContain('> b')
  })

  it('escapes & (full exact-string assertion)', () => {
    expect(safeJsonLd({ x: 'a & b' })).toBe('{"x":"a \\u0026 b"}')
  })

  it('escapes the U+2028 / U+2029 line terminators', () => {
    expect(safeJsonLd({ x: LS + PS })).toBe('{"x":"\\u2028\\u2029"}')
  })

  it('serializes undefined to the string "null"', () => {
    expect(safeJsonLd(undefined)).toBe('null')
  })

  it('round-trips: un-escaping then JSON.parse equals the original', () => {
    const value = { a: '</script> & <b> ' + LS + PS + ' end' }
    const restored = JSON.parse(
      safeJsonLd(value)
        .replace(/\\u003c/g, '<')
        .replace(/\\u003e/g, '>')
        .replace(/\\u0026/g, '&')
        .replace(/\\u2028/g, LS)
        .replace(/\\u2029/g, PS),
    )
    expect(restored).toEqual(value)
  })
})
