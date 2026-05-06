import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const NUXT_CONFIG = readFileSync(resolve(__dirname, '../../nuxt.config.ts'), 'utf8')

describe('architecture — Nuxt payload cache', () => {
  it('keeps Nuxt runtime cache in memory during development', () => {
    expect(NUXT_CONFIG).toMatch(/\bnitro\s*:/)
    expect(NUXT_CONFIG).toMatch(/devStorage/)
    expect(NUXT_CONFIG).toMatch(/['"]cache:nuxt['"]\s*:\s*\{\s*driver:\s*['"]memory['"]/s)
  })
})
