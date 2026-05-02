import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * Anti-drift scanner: flag hex literals assigned to brand-chrome CSS
 * properties inside Vue component `<style>` blocks.
 *
 * The drift class this catches is real and recurring. In the audit that
 * produced this test, seven sites had `background-color: #e6d585` (gold
 * hover) and `background-color: #6a0a0a` (maroon hover) — invented mid-step
 * colors that should have referenced `--secondary-hover` and
 * `--primary-container-hover`. This scanner makes that mistake fail CI.
 *
 * What's flagged: hex values directly assigned to `background-color`,
 * `color`, `border`, `border-color`, `outline-color`, `fill`, `stroke`.
 *
 * What's allowlisted:
 * - Hex inside `gradient(...)` calls — those are decorative imagery (poster
 *   placeholders, atmospheric hero layers, food category bloom).
 * - Hex inside `<script>` blocks — JS strings aren't CSS at runtime.
 * - Lines explicitly annotated with `/* token-exception: <reason> *\/`.
 *   When you legitimately need a hex literal, name the reason inline.
 *
 * If this test fails: replace the hex with a token from
 * `frontend/app/assets/css/tokens.css`. If no token fits, either propose
 * a new token (preferred) or annotate the line with a token-exception
 * comment naming why.
 */

const COMPONENTS_ROOT = resolve(__dirname, '../../app/components')

function walkVueFiles(dir: string): string[] {
  const out: string[] = []
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry)
    if (statSync(path).isDirectory()) {
      out.push(...walkVueFiles(path))
    } else if (entry.endsWith('.vue')) {
      out.push(path)
    }
  }
  return out
}

function extractStyleBlocks(src: string): string {
  const blocks: string[] = []
  const blockRe = /<style[^>]*>([\s\S]*?)<\/style>/gi
  let match: ReturnType<typeof blockRe.exec>
  while ((match = blockRe.exec(src)) !== null) {
    blocks.push(match[1] ?? '')
  }
  return blocks.join('\n\n')
}

function stripGradientCalls(line: string): string {
  // Iteratively replace gradient(...) including nested parens. CSS gradient
  // signatures don't typically contain nested parens themselves; this loop
  // collapses adjacent gradient calls cleanly.
  let cleaned = line
  let prev: string
  do {
    prev = cleaned
    cleaned = cleaned.replace(/[a-z-]*gradient\([^()]*\)/gi, ' ')
  } while (cleaned !== prev)
  return cleaned
}

const CHROME_PROPERTY_RE =
  /(?:^|\s|;|\{)(background-color|color|border-color|border|outline-color|fill|stroke)\s*:[^;{}]*#[0-9a-f]{3,8}\b/i

interface Violation {
  file: string
  lineNumber: number
  text: string
}

function scanComponent(absPath: string): Violation[] {
  const src = readFileSync(absPath, 'utf8')
  const css = extractStyleBlocks(src)
  if (css.length === 0) return []

  const violations: Violation[] = []
  const lines = css.split('\n')

  for (let i = 0; i < lines.length; i++) {
    const original = lines[i] ?? ''
    // Token-exception check uses the ORIGINAL line so we can see the comment.
    if (/token-exception/i.test(original)) continue

    // Strip inline /* ... *\/ block comments from this line so we don't
    // false-positive on hex values discussed in comments.
    const noComments = original.replace(/\/\*.*?\*\//g, '')

    // Strip gradient() calls — decorative imagery is allowed.
    const cleaned = stripGradientCalls(noComments)

    if (CHROME_PROPERTY_RE.test(cleaned)) {
      violations.push({
        file: relative(COMPONENTS_ROOT, absPath),
        lineNumber: i + 1,
        text: original.trim(),
      })
    }
  }

  return violations
}

describe('component CSS — no chrome-hex drift', () => {
  const files = walkVueFiles(COMPONENTS_ROOT)

  it('finds at least one .vue component to scan', () => {
    expect(files.length).toBeGreaterThan(0)
  })

  it('every component\'s <style> block uses tokens for chrome colors', () => {
    const allViolations: Violation[] = []
    for (const file of files) {
      allViolations.push(...scanComponent(file))
    }

    if (allViolations.length > 0) {
      const report = allViolations
        .map(v => `  ${v.file}:${v.lineNumber}  ${v.text}`)
        .join('\n')
      throw new Error(
        `\nFound ${allViolations.length} hex literal(s) assigned to brand-chrome CSS properties.\n` +
        `Replace with tokens from frontend/app/assets/css/tokens.css, or annotate the\n` +
        `line with /* token-exception: <reason> *\/ if the hex is genuinely needed.\n\n` +
        report + '\n',
      )
    }
    expect(allViolations).toHaveLength(0)
  })
})
