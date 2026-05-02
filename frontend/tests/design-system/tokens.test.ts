import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * Source-of-truth check for the design system tokens.
 *
 * `tokens.css` is the contract between every Cv* primitive, every page-level
 * CSS file, and the brand spec in docs/design-system/DESIGN_SYSTEM.md. A
 * silent edit here can shift the entire system off-brand without any other
 * test catching it. This file parses the declarations and locks the values
 * that the spec calls canonical.
 *
 * Adding a new token is fine — just add the assertion. Changing a brand hex
 * is a deliberate design decision: update DESIGN_SYSTEM.md § Token Mapping
 * AND this test in the same commit.
 */

// `__dirname` is intentional: Vitest provides it under the Nuxt environment,
// and the jsdom `URL` polyfill ignores `file://` bases for relative resolution
// — `new URL(rel, import.meta.url)` returns `http://localhost:3000/...` here.
const TOKENS_PATH = resolve(__dirname, '../../app/assets/css/tokens.css')

function parseTokens(css: string): Record<string, string> {
  const tokens: Record<string, string> = {}
  const declarations = css.matchAll(/^\s*(--[a-z0-9-]+)\s*:\s*([^;]+);/gim)
  for (const match of declarations) {
    const [, name, value] = match
    tokens[name!.trim()] = value!.trim()
  }
  return tokens
}

const css = readFileSync(TOKENS_PATH, 'utf8')
const tokens = parseTokens(css)

describe('design system tokens — canonical brand values', () => {
  // Hex values that appear in DESIGN_SYSTEM.md § Token Mapping. Lowercase the
  // comparison so case differences in the source don't fail the test.
  const brandHexes: Record<string, string> = {
    '--primary': '#ffb4a8',
    '--primary-container': '#550000',
    '--secondary': '#dac769',
    '--tertiary': '#ccc6b6',
    '--surface': '#131313',
    '--surface-variant': '#3b3636',
    '--surface-container-lowest': '#0e0e0e',
    '--surface-container-low': '#1c1b1b',
    '--surface-container': '#201f1f',
    '--surface-container-high': '#2a2a2a',
    '--on-surface': '#e5e2e1',
    '--on-tertiary-fixed-variant': '#a89f91',
    '--outline': '#a58b86',
    '--outline-variant': '#57423e',
    '--secondary-container': '#675900',
    '--tertiary-container': '#29261b',
  }

  for (const [name, expected] of Object.entries(brandHexes)) {
    it(`${name} = ${expected}`, () => {
      expect(tokens[name]?.toLowerCase()).toBe(expected)
    })
  }
})

describe('design system tokens — state semantics', () => {
  // Spec: § State Semantics (sage / gold / claret / steel).
  // Two claret shades — the saturated one is for non-text fills, the
  // lighter one is for body copy on every dark surface tier.
  const stateColors: Record<string, string> = {
    '--state-success': '#5b8f6c',
    '--state-warning': '#dac769', // shares --secondary
    '--state-danger': '#b5443d',
    '--state-danger-text': '#e07a72',
    '--state-info': '#5a8aa0',
  }
  for (const [name, expected] of Object.entries(stateColors)) {
    it(`${name} = ${expected}`, () => {
      expect(tokens[name]?.toLowerCase()).toBe(expected)
    })
  }
})

describe('design system tokens — interactive state colors', () => {
  // Hover/active anchors derived via color-mix(). Locking the formula
  // prevents future authors from re-introducing ad-hoc hex hovers.
  it('--primary-container-hover uses color-mix in oklch', () => {
    expect(tokens['--primary-container-hover']).toMatch(/^color-mix\(in oklch/)
    expect(tokens['--primary-container-hover']).toContain('var(--primary-container)')
  })
  it('--secondary-hover uses color-mix in oklch', () => {
    expect(tokens['--secondary-hover']).toMatch(/^color-mix\(in oklch/)
    expect(tokens['--secondary-hover']).toContain('var(--secondary)')
  })
  it('declares both hover and active anchors for primary-container and secondary', () => {
    expect(tokens['--primary-container-active']).toBeDefined()
    expect(tokens['--secondary-active']).toBeDefined()
  })
})

describe('design system tokens — structural primitives', () => {
  it('declares the full spacing scale (2xs through 5xl)', () => {
    const sizes = ['2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']
    for (const size of sizes) {
      expect(tokens[`--space-${size}`]).toBeDefined()
    }
    // Anchor a couple of values so the 8pt-grid intent stays locked.
    expect(tokens['--space-sm']).toBe('0.5rem')
    expect(tokens['--space-md']).toBe('1rem')
    expect(tokens['--space-2xl']).toBe('3rem')
  })

  it('declares the four-step radius scale', () => {
    expect(tokens['--radius-none']).toBe('0')
    expect(tokens['--radius-sm']).toBe('0.125rem')
    expect(tokens['--radius-card']).toBe('0.25rem')
    expect(tokens['--radius-full']).toBe('50%')
  })

  it('declares hairline and thick border widths', () => {
    expect(tokens['--border-hairline']).toBe('0.0625rem')
    expect(tokens['--border-thick']).toBe('0.125rem')
  })

  it('declares the opacity scale', () => {
    expect(tokens['--opacity-edge-catch']).toBe('0.15')
    expect(tokens['--opacity-glass']).toBe('0.60')
    expect(tokens['--opacity-glass-solid']).toBe('0.85')
    expect(tokens['--opacity-disabled']).toBe('0.40')
  })

  it('declares control heights aligned to the 2.25 / 3 / 3.5 ladder', () => {
    expect(tokens['--control-height-sm']).toBe('2.25rem')
    expect(tokens['--control-height']).toBe('3rem')
    expect(tokens['--control-height-lg']).toBe('3.5rem')
  })
})

describe('design system tokens — z-index scale', () => {
  // Spec § 5: 11 named layers. Anchor the ones that are load-bearing for
  // cross-component layering — recessed below content, modal above sticky,
  // toast above modal, skip-nav on top.
  it('orders recessed layers below the base', () => {
    expect(Number(tokens['--z-recessed-deep'])).toBeLessThan(Number(tokens['--z-recessed']))
    expect(Number(tokens['--z-recessed'])).toBeLessThan(Number(tokens['--z-base']))
  })
  it('places sticky below modal', () => {
    expect(Number(tokens['--z-sticky'])).toBeLessThan(Number(tokens['--z-modal']))
  })
  it('places toast above modal', () => {
    expect(Number(tokens['--z-toast'])).toBeGreaterThan(Number(tokens['--z-modal']))
  })
  it('places skip-nav at the very top', () => {
    const all = ['--z-card', '--z-sticky', '--z-modal', '--z-toast', '--z-tooltip']
      .map(name => Number(tokens[name]))
    for (const value of all) {
      expect(Number(tokens['--z-skip-nav'])).toBeGreaterThan(value)
    }
  })
})

describe('design system tokens — motion', () => {
  it('declares exactly four discrete duration tokens', () => {
    expect(tokens['--duration-micro']).toBe('100ms')
    expect(tokens['--duration-standard']).toBe('250ms')
    expect(tokens['--duration-emphasis']).toBe('400ms')
    expect(tokens['--duration-cinematic']).toBe('700ms')
  })

  it('declares the five easing curves from the spec', () => {
    for (const ease of ['standard', 'enter', 'exit', 'emphasis', 'linear']) {
      expect(tokens[`--ease-${ease}`]).toBeDefined()
    }
  })
})

describe('design system tokens — fonts and icons', () => {
  it('declares all four icon sizes', () => {
    expect(tokens['--icon-sm']).toBe('1rem')
    expect(tokens['--icon-md']).toBe('1.5rem')
    expect(tokens['--icon-lg']).toBe('3rem')
    expect(tokens['--icon-xl']).toBe('4rem')
  })
})

describe('design system tokens — anti-drift sentinels', () => {
  it('does NOT declare the salmon-pink (#FFB4A8) as any container/fill token', () => {
    // Catches the most common implementation error: using --primary as a fill.
    // --primary stays text-on-dark-maroon only.
    const fillNames = [
      '--primary-container',
      '--secondary-container',
      '--tertiary-container',
      '--surface',
      '--surface-variant',
      '--surface-container-lowest',
      '--surface-container-low',
      '--surface-container',
      '--surface-container-high',
    ]
    for (const name of fillNames) {
      expect(tokens[name]?.toLowerCase()).not.toBe('#ffb4a8')
    }
  })

  it('keeps --primary-container as the brand reactor-core fill, not a text color', () => {
    expect(tokens['--primary-container']?.toLowerCase()).toBe('#550000')
  })

  it('does NOT introduce pure white anywhere', () => {
    for (const value of Object.values(tokens)) {
      const lower = value.toLowerCase()
      // Allow color-mix's "white 15%" syntax; only block standalone #fff or #ffffff.
      const standaloneWhite = /(?<![a-z])#fff(?![0-9a-f])|(?<![a-z])#ffffff(?![0-9a-f])/i
      expect(lower).not.toMatch(standaloneWhite)
    }
  })
})
