import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * Anti-drift scanner: flag hex literals assigned to brand-chrome CSS
 * properties inside Vue components — both `<style>` blocks and
 * template `:style="…"` bindings.
 *
 * The drift class this catches is real and recurring. In the audit that
 * produced this test, seven sites had `background-color: #e6d585` (gold
 * hover) and `background-color: #6a0a0a` (maroon hover) — invented mid-step
 * colors that should have referenced `--secondary-hover` and
 * `--primary-container-hover`. This scanner makes that mistake fail CI.
 *
 * Properties flagged: `background-color`, `color`, `border`,
 * `border-color`, `border-{top,right,bottom,left}`,
 * `border-{*}-color`, `outline`, `outline-color`, `fill`, `stroke`,
 * `box-shadow`, `text-shadow`, `caret-color`, `accent-color`,
 * `column-rule-color`, `text-decoration-color`. Together those cover
 * everything the design system considers chrome — boundaries, fills,
 * focus rings, decorative shadows tinted to a surface.
 *
 * Multi-line declarations are scanned at the declaration level (split on
 * `;`), so a sneaky
 *   border:
 *     0.0625rem solid
 *     #6a0a0a;
 * still trips the lint.
 *
 * Allowlisted:
 * - Hex inside `gradient(...)` — decorative imagery (poster placeholders,
 *   hero atmosphere layers, food category bloom).
 * - Hex inside `rgba(...)`/`rgb(...)`/`hsla(...)` — the token system
 *   provides RGB channel variants for opacity, so these calls are
 *   intentional.
 * - Hex inside `<script>` blocks — JS literals aren't CSS at runtime.
 * - Declarations annotated `/* token-exception: <reason> *\/`.
 *
 * If this test fails: replace the hex with a token from
 * `frontend/app/assets/css/tokens.css`. If no token fits, propose a new
 * token (preferred) or annotate the declaration with a token-exception
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

interface Block {
  /** Source content with comments and gradient/rgba calls intact. */
  raw: string
  /** Line number in the original file where this block starts (1-based). */
  startLine: number
}

function extractBlocks(src: string, tagPattern: RegExp): Block[] {
  const blocks: Block[] = []
  let match: RegExpExecArray | null
  while ((match = tagPattern.exec(src)) !== null) {
    const inner = match[1] ?? ''
    const startLine = src.slice(0, match.index).split('\n').length
    blocks.push({ raw: inner, startLine })
  }
  return blocks
}

function stripCalls(input: string, fnNamesPattern: string): string {
  // Iteratively collapse fn(...) calls. CSS gradient/color signatures
  // don't produce nested parens in practice; the loop handles back-to-back
  // calls cleanly.
  const re = new RegExp(`(?:${fnNamesPattern})\\([^()]*\\)`, 'gi')
  let cleaned = input
  let prev: string
  do {
    prev = cleaned
    cleaned = cleaned.replace(re, ' ')
  } while (cleaned !== prev)
  return cleaned
}

function stripCssNoise(declaration: string): string {
  // Block comments first (their contents could falsely match).
  const noComments = declaration.replace(/\/\*[\s\S]*?\*\//g, ' ')
  // Then gradient(...) and rgba/rgb/hsl(...) — documented decorative
  // escape hatches, not chrome drift.
  return stripCalls(noComments, '[a-z-]*gradient|rgba?|hsla?')
}

const CHROME_PROPS = [
  'background-color',
  'color',
  'border',
  'border-color',
  'border-top',
  'border-right',
  'border-bottom',
  'border-left',
  'border-top-color',
  'border-right-color',
  'border-bottom-color',
  'border-left-color',
  'outline',
  'outline-color',
  'fill',
  'stroke',
  'box-shadow',
  'text-shadow',
  'caret-color',
  'accent-color',
  'column-rule-color',
  'text-decoration-color',
]
const CHROME_PROP_GROUP = CHROME_PROPS.join('|')
const HEX_LITERAL = /#[0-9a-f]{3,8}\b/i

interface Violation {
  file: string
  lineNumber: number
  text: string
}

function scanStyleBlocks(absPath: string, src: string): Violation[] {
  const violations: Violation[] = []
  const blocks = extractBlocks(src, /<style[^>]*>([\s\S]*?)<\/style>/gi)

  for (const block of blocks) {
    // Walk declarations (split on `;`) so multi-line declarations get
    // scanned as one unit. Track each declaration's starting line by
    // counting newlines before its position in the block.
    let cursor = 0
    while (cursor < block.raw.length) {
      const semi = block.raw.indexOf(';', cursor)
      const end = semi === -1 ? block.raw.length : semi
      const declaration = block.raw.slice(cursor, end)
      const startLineInBlock = block.raw.slice(0, cursor).split('\n').length
      const lineInFile = block.startLine + startLineInBlock - 1

      // For `token-exception` detection we need to include any trailing
      // comment that sits on the same line AFTER the semicolon
      // (`color: #e6a97a; /* token-exception: ... */`). Without this
      // widening, the comment falls into the next declaration's slice
      // and the exception is silently ignored.
      const lineEnd = semi === -1
        ? block.raw.length
        : (() => {
            const nl = block.raw.indexOf('\n', semi)
            return nl === -1 ? block.raw.length : nl
          })()
      const declarationWithTrailing = block.raw.slice(cursor, lineEnd)

      if (/token-exception/i.test(declarationWithTrailing)) {
        if (semi === -1) break
        cursor = semi + 1
        continue
      }

      const cleaned = stripCssNoise(declaration)
      const propRe = new RegExp(`(?:^|[\\s;{])(${CHROME_PROP_GROUP})\\s*:`, 'i')
      const propMatch = propRe.exec(cleaned)
      if (propMatch && HEX_LITERAL.test(cleaned.slice(propMatch.index))) {
        violations.push({
          file: relative(COMPONENTS_ROOT, absPath),
          lineNumber: lineInFile,
          text: declaration.trim().replace(/\s+/g, ' ').slice(0, 200),
        })
      }

      if (semi === -1) break
      cursor = semi + 1
    }
  }
  return violations
}

function scanTemplateStyleBindings(absPath: string, src: string): Violation[] {
  // Strip <script> blocks first so JS color literals don't false-positive,
  // but preserve newline counts for accurate line numbers.
  const noScript = src.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, m =>
    '\n'.repeat(Math.max(0, m.split('\n').length - 1)),
  )

  const violations: Violation[] = []
  const re = /:style\s*=\s*("([^"]*)"|'([^']*)')/g
  let match: RegExpExecArray | null
  while ((match = re.exec(noScript)) !== null) {
    const body = match[2] ?? match[3] ?? ''
    if (/token-exception/i.test(body)) continue
    const cleaned = stripCssNoise(body)
    const propRe = new RegExp(`(?:'|"|\\b)(${CHROME_PROP_GROUP})(?:'|")?\\s*[:,]`, 'i')
    if (propRe.test(cleaned) && HEX_LITERAL.test(cleaned)) {
      const startLine = noScript.slice(0, match.index).split('\n').length
      violations.push({
        file: relative(COMPONENTS_ROOT, absPath),
        lineNumber: startLine,
        text: `:style=${match[1]}`.slice(0, 200),
      })
    }
  }
  return violations
}

function scanComponent(absPath: string): Violation[] {
  const src = readFileSync(absPath, 'utf8')
  return [
    ...scanStyleBlocks(absPath, src),
    ...scanTemplateStyleBindings(absPath, src),
  ]
}

describe('component CSS — no chrome-hex drift', () => {
  const files = walkVueFiles(COMPONENTS_ROOT)

  it('finds at least one .vue component to scan', () => {
    expect(files.length).toBeGreaterThan(0)
  })

  it('every component\'s chrome colors come from tokens, not hex literals', () => {
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
        `declaration with /* token-exception: <reason> *\/ if the hex is genuinely needed.\n\n` +
        report + '\n',
      )
    }
    expect(allViolations).toHaveLength(0)
  })
})
