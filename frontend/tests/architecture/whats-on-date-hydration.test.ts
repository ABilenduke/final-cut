import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const WHATS_ON_PAGE = readFileSync(resolve(__dirname, '../../app/pages/whats-on.vue'), 'utf8')
const BRIDGE_MONTH_GRID = readFileSync(
  resolve(__dirname, '../../app/components/calendar/BridgeMonthGrid.vue'),
  'utf8',
)
const BRIDGE_DAY_CELL = readFileSync(
  resolve(__dirname, '../../app/components/calendar/BridgeDayCell.vue'),
  'utf8',
)
const NUXT_CONFIG = readFileSync(resolve(__dirname, '../../nuxt.config.ts'), 'utf8')

describe("architecture — what's on date hydration", () => {
  it('hydrates the calendar from a shared today date instead of recomputing it independently', () => {
    expect(WHATS_ON_PAGE).toContain("useState<string>('whats-on:today-date'")
    expect(WHATS_ON_PAGE).toContain(':today-date="todayDate"')
    expect(WHATS_ON_PAGE).toContain('public.appTimeZone')
    expect(WHATS_ON_PAGE).toMatch(/Intl\.DateTimeFormat\([^)]*timeZone:\s*appTimeZone/s)
  })

  it('Bridge calendar components consume todayDate as a prop instead of calling new Date()', () => {
    // BridgeMonthGrid must require todayDate so today highlighting is SSR-safe
    // (no per-render `new Date()` calls in the component).
    expect(BRIDGE_MONTH_GRID).toMatch(/todayDate:\s*string/)
    expect(BRIDGE_MONTH_GRID).not.toMatch(/new Date\(\)/)
    // BridgeDayCell receives an explicit `today` boolean, never derives it.
    expect(BRIDGE_DAY_CELL).toMatch(/today:\s*boolean/)
    expect(BRIDGE_DAY_CELL).not.toMatch(/new Date\(\)/)
  })

  it("does not ISR-cache the date-sensitive what's on page", () => {
    expect(NUXT_CONFIG).toContain('appTimeZone')
    expect(NUXT_CONFIG).not.toMatch(/['"]\/whats-on['"]\s*:\s*\{\s*isr\b/)
  })
})
