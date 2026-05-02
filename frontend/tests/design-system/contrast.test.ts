import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * WCAG 2.1 contrast assertions for brand-state colors against the dark
 * surface stack.
 *
 * The DESIGN_SYSTEM.md § State Semantics table calls specific contrast
 * floors (AA = 4.5:1 for normal text, 3:1 for large text and non-text
 * components per WCAG 1.4.11). This test computes the ratios from the
 * actual hex values in `tokens.css` and fails if any documented pairing
 * stops meeting its floor — for example, if claret-text drifts darker, or
 * if a new surface tier is introduced without checking against the state
 * palette.
 *
 * The point is to keep the design-system docs honest. An earlier
 * docstring claimed `--state-danger` was 4.51:1 against `--surface` when
 * the real value is 3.41:1. A literal token-snapshot test couldn't catch
 * that — only a computed-contrast assertion can.
 */

// `__dirname` is intentional: Vitest provides it under the Nuxt environment,
// and the jsdom `URL` polyfill ignores `file://` bases for relative resolution
// — `new URL(rel, import.meta.url)` returns `http://localhost:3000/...` here.
const TOKENS_PATH = resolve(__dirname, '../../app/assets/css/tokens.css')
const css = readFileSync(TOKENS_PATH, 'utf8')

function readToken(name: string): string {
  // The two-declaration fallback pattern means a single token can be
  // declared twice (static hex, then color-mix). Match all and keep the
  // last hex-only value, which is the one a non-color-mix browser would
  // resolve to. For tokens that are pure hex, this matches the only
  // declaration anyway.
  const re = new RegExp(`${name}\\s*:\\s*(#[0-9a-fA-F]{3,8})\\s*;`, 'g')
  const matches = [...css.matchAll(re)]
  if (matches.length === 0) {
    throw new Error(`token ${name} not found as hex literal in tokens.css`)
  }
  return matches[matches.length - 1]![1]!
}

function srgbChannelToLinear(channel: number): number {
  const c = channel / 255
  return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
}

function relativeLuminance(hex: string): number {
  const clean = hex.replace('#', '')
  const r = parseInt(clean.slice(0, 2), 16)
  const g = parseInt(clean.slice(2, 4), 16)
  const b = parseInt(clean.slice(4, 6), 16)
  return (
    0.2126 * srgbChannelToLinear(r) +
    0.7152 * srgbChannelToLinear(g) +
    0.0722 * srgbChannelToLinear(b)
  )
}

function contrastRatio(a: string, b: string): number {
  const la = relativeLuminance(a)
  const lb = relativeLuminance(b)
  const [hi, lo] = la > lb ? [la, lb] : [lb, la]
  return (hi + 0.05) / (lo + 0.05)
}

const AA_NORMAL = 4.5
const AA_LARGE_OR_NONTEXT = 3.0

describe('state colors — WCAG AA against dark surfaces', () => {
  const surfaces = {
    'surface': readToken('--surface'),
    'surface-container-low': readToken('--surface-container-low'),
    'surface-container': readToken('--surface-container'),
    'surface-container-high': readToken('--surface-container-high'),
  }

  // Normal-text floor (4.5:1) on every documented surface. These are the
  // pairings that must stay AA-clean because they back error copy, success
  // toasts, etc. — places where the user actually reads the color.
  const textPairings: Array<{ token: string; against: Array<keyof typeof surfaces> }> = [
    {
      token: '--state-danger-text',
      against: ['surface', 'surface-container-low', 'surface-container', 'surface-container-high'],
    },
    { token: '--state-success', against: ['surface', 'surface-container-low'] },
    { token: '--state-info', against: ['surface', 'surface-container-low'] },
    {
      token: '--state-warning',
      against: ['surface', 'surface-container-low', 'surface-container', 'surface-container-high'],
    },
  ]

  for (const { token, against } of textPairings) {
    const fg = readToken(token)
    for (const surfaceName of against) {
      const bg = surfaces[surfaceName]!
      it(`${token} on --${surfaceName} ≥ ${AA_NORMAL}:1 (text-readable)`, () => {
        expect(contrastRatio(fg, bg)).toBeGreaterThanOrEqual(AA_NORMAL)
      })
    }
  }

  // Non-text floor (3:1) — applies everywhere the saturated claret might
  // appear as a fill, badge, or border, and also catches the "still
  // useful as decoration" tier for sage/steel on the lighter containers
  // where they don't pass normal-text AA.
  const nonTextPairings: Array<{ token: string; against: Array<keyof typeof surfaces> }> = [
    {
      token: '--state-danger',
      against: ['surface', 'surface-container-low', 'surface-container'],
    },
    {
      token: '--state-success',
      against: ['surface-container', 'surface-container-high'],
    },
    {
      token: '--state-info',
      against: ['surface-container', 'surface-container-high'],
    },
  ]

  for (const { token, against } of nonTextPairings) {
    const fg = readToken(token)
    for (const surfaceName of against) {
      const bg = surfaces[surfaceName]!
      it(`${token} on --${surfaceName} ≥ ${AA_LARGE_OR_NONTEXT}:1 (non-text/large)`, () => {
        expect(contrastRatio(fg, bg)).toBeGreaterThanOrEqual(AA_LARGE_OR_NONTEXT)
      })
    }
  }
})

// Mirror check between customer tokens (`--state-*`) and admin tokens
// (`--fc-state-*`). The two files are hand-maintained; this is the
// cheapest enforcement before a generator replaces the manual mirror.
//
// Skipped automatically when the admin theme file isn't reachable —
// notably the dockerised frontend test container, which only mounts
// `frontend/`. CI runs the full suite at the repo root and exercises
// this block.
const ADMIN_THEME_PATH = resolve(
  __dirname,
  '../../../backend/resources/css/filament/admin/theme.css',
)
const adminThemeAvailable = existsSync(ADMIN_THEME_PATH)
const mirrorDescribe = adminThemeAvailable ? describe : describe.skip

mirrorDescribe('state colors — admin theme mirrors customer values', () => {
  const mirrored = [
    ['--state-success', '--fc-state-success'],
    ['--state-warning', '--fc-state-warning'],
    ['--state-danger', '--fc-state-danger'],
    ['--state-danger-text', '--fc-state-danger-text'],
    ['--state-info', '--fc-state-info'],
  ]

  function readAdminToken(adminCss: string, name: string): string {
    const re = new RegExp(`${name}\\s*:\\s*(#[0-9a-fA-F]{3,8})\\s*;`, 'g')
    const matches = [...adminCss.matchAll(re)]
    if (matches.length === 0) {
      throw new Error(`admin token ${name} not found in theme.css`)
    }
    return matches[matches.length - 1]![1]!.toLowerCase()
  }

  for (const [customer, admin] of mirrored) {
    it(`${admin} mirrors ${customer}`, () => {
      // Read inside the test so module evaluation never touches the
      // admin path when the file isn't there (the describe.skip would
      // not save us if the readFileSync ran at module load).
      const adminCss = readFileSync(ADMIN_THEME_PATH, 'utf8')
      expect(readAdminToken(adminCss, admin!)).toBe(readToken(customer!).toLowerCase())
    })
  }
})
