import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const APP_ROOT = resolve(__dirname, '../../app')

function walkFiles(dir: string): string[] {
  const out: string[] = []
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry)
    const stat = statSync(path)
    if (stat.isDirectory()) {
      out.push(...walkFiles(path))
    } else if (entry.endsWith('.ts') || entry.endsWith('.vue')) {
      out.push(path)
    }
  }
  return out
}

describe('architecture — Nuxt composable scope', () => {
  it('does not call Nuxt instance composables from nested computed callbacks', () => {
    const violations: string[] = []

    for (const absPath of walkFiles(APP_ROOT)) {
      const rel = relative(APP_ROOT, absPath)
      const lines = readFileSync(absPath, 'utf8').split('\n')

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i]!
        if (line.includes('computed(() =>') && line.includes('useRuntimeConfig()')) {
          violations.push(`${rel}:${i + 1} ${line.trim()}`)
        }
      }
    }

    expect(violations).toEqual([])
  })
})
