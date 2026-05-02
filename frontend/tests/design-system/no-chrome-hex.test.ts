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

// `__dirname` is intentional: Vitest provides it under the Nuxt environment,
// and the jsdom `URL` polyfill ignores `file://` bases for relative resolution
// — `new URL(rel, import.meta.url)` returns `http://localhost:3000/...` here.
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
  // Then gradient(...), rgba/rgb/hsl(...), var(...), calc(...), color-mix(...) —
  // documented decorative escape hatches, not chrome drift. `var`/`calc`/
  // `color-mix` are stripped on the same pass so nested calls (e.g.
  // `linear-gradient(180deg, #1a0a05, var(--surface-container-lowest))`)
  // can be peeled off in two passes — inner var() first, outer gradient()
  // after — instead of leaving the gradient un-strippable due to nested
  // parens and producing a false positive on its decorative hex stops.
  return stripCalls(noComments, '[a-z-]*gradient|rgba?|hsla?|var|calc|color-mix')
}

const CHROME_PROPS = [
  'background',
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
// Kebab-only: `<style>` blocks are real CSS, where `backgroundColor` would be
// invalid. Including camelCase here would false-positive on JS strings that
// happen to land inside a style block via dynamic SSR.
const CHROME_PROP_GROUP = CHROME_PROPS.join('|')
// Kebab + camel: Vue's `:style="{ ... }"` accepts either form as object keys.
const CHROME_PROPS_CAMEL = CHROME_PROPS.map(p =>
  p.replace(/-([a-z])/g, (_m, c: string) => c.toUpperCase()),
)
const CHROME_PROP_GROUP_ALL = [...CHROME_PROPS, ...CHROME_PROPS_CAMEL].join('|')
const HEX_LITERAL = /#[0-9a-f]{3,8}\b/i
const STYLE_BLOCK_PROP_RE = new RegExp(`(?:^|[\\s;{])(${CHROME_PROP_GROUP})\\s*:`, 'i')
const TEMPLATE_BINDING_PROP_RE = new RegExp(
  `(?:'|"|\\b)(${CHROME_PROP_GROUP_ALL})(?:'|")?\\s*[:,]`,
  'i',
)

interface Violation {
  file: string
  lineNumber: number
  text: string
}

function scanStyleBlocks(absPath: string, src: string): Violation[] {
  const violations: Violation[] = []
  // `\s*` before `>` so `</style >` (HTML allows trailing whitespace) still
  // closes the block — same precaution `scanTemplateStyleBindings` takes for
  // `</script>`.
  const blocks = extractBlocks(src, /<style[^>]*>([\s\S]*?)<\/style\s*>/gi)

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
      const propMatch = STYLE_BLOCK_PROP_RE.exec(cleaned)
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
  // but preserve newline counts for accurate line numbers. The closing tag
  // pattern accepts `</script>`, `</script >`, and `</script foo="bar">` —
  // the HTML parser ends the script-data state on whitespace after `script`
  // even with bogus trailing attributes, so a tighter regex (e.g. `</script\s*>`)
  // can be bypassed by a parser that accepts those tags.
  const noScript = src.replace(/<script[^>]*>[\s\S]*?<\/script(?:\s[^>]*)?>/gi, m =>
    '\n'.repeat(Math.max(0, m.split('\n').length - 1)),
  )

  const violations: Violation[] = []
  const re = /:style\s*=\s*("([^"]*)"|'([^']*)')/g
  let match: RegExpExecArray | null
  while ((match = re.exec(noScript)) !== null) {
    const body = match[2] ?? match[3] ?? ''
    if (/token-exception/i.test(body)) continue
    const cleaned = stripCssNoise(body)
    // Computed bindings (`:style="getStyle()"`) can't be statically analyzed
    // and intentionally fall through — the `<style>` scanner catches anything
    // those functions read out of class-based CSS.
    if (TEMPLATE_BINDING_PROP_RE.test(cleaned) && HEX_LITERAL.test(cleaned)) {
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

// Exposed for the contract tests below. Synthetic sources let us prove the
// scanner catches hex literals inside object-form `:style` bindings without
// planting fake violations in real component files.
function scanSource(src: string): Violation[] {
  return [
    ...scanStyleBlocks('<inline>', src),
    ...scanTemplateStyleBindings('<inline>', src),
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

describe('component CSS — scanner contract (object-form :style bindings)', () => {
  // These cases lock in coverage for `:style="{ … }"` object bindings — the
  // common Vue form. A regression here would let a hex literal slip into a
  // template binding without tripping CI.

  it('flags hex literals on camelCase keys (backgroundColor, borderColor)', () => {
    const src = `<template><div :style="{ backgroundColor: '#ff0000' }" /></template>`
    expect(scanSource(src)).toHaveLength(1)
    const src2 = `<template><div :style="{ borderColor: '#abcdef' }" /></template>`
    expect(scanSource(src2)).toHaveLength(1)
  })

  it('flags hex literals on quoted kebab keys ("background-color")', () => {
    const src = `<template><div :style="{ 'background-color': '#abc' }" /></template>`
    expect(scanSource(src)).toHaveLength(1)
  })

  it('flags hex on the background shorthand inside an object binding', () => {
    const src = `<template><div :style="{ background: '#123456' }" /></template>`
    expect(scanSource(src)).toHaveLength(1)
  })

  it('does NOT flag JS variable references (no static hex present)', () => {
    const src = `<template><div :style="{ background: fallbackGradient }" /></template>`
    expect(scanSource(src)).toHaveLength(0)
  })

  it('does NOT flag gradient(...) usage with hex stops (decorative escape hatch)', () => {
    const src = `<template><div :style="{ background: 'linear-gradient(180deg, #550000, #0e0e0e)' }" /></template>`
    expect(scanSource(src)).toHaveLength(0)
  })

  it('honors token-exception annotations inside object bindings', () => {
    const src = `<template><div :style="{ /* token-exception: brand asset */ background: '#123456' }" /></template>`
    expect(scanSource(src)).toHaveLength(0)
  })
})
