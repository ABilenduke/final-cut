import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * Architecture regression: activeLocation must not leak into browse-tier code.
 *
 * After Plan 13 Task 2, `useLocations().activeLocation` is a booking-flow
 * concern only. Browse pages, layout components (except LocationPreferenceSwitcher),
 * and content composables must not read it — doing so would reintroduce the
 * forced-location-selection anti-pattern that Task 2 removes.
 *
 * This test walks every .ts and .vue file under frontend/app/ and fails CI if
 * `activeLocation` appears in a file that is NOT on the allowed list.
 *
 * ALLOWED LIST — files that may legitimately read activeLocation:
 *   - useLocations.ts itself (defines and exports the ref)
 *   - Anything under pages/purchase/  (booking flow)
 *   - Anything under components/booking/  (booking UI)
 *   - LocationPreferenceSwitcher.vue  (the one header UI that writes the preference)
 *
 * When Task 8 adds BookingLocationBanner, add that file to the allowed list.
 */

const APP_ROOT = resolve(__dirname, '../../app')

const ALLOWED_PATTERNS = [
  // The composable that owns the state
  /composables\/useLocations\.ts$/,
  // Booking flow pages
  /pages\/purchase\//,
  // Booking UI components
  /components\/booking\//,
  // The preference switcher (may not exist yet — if it does it's allowed)
  /components\/layout\/LocationPreferenceSwitcher\.vue$/,
]

function isAllowed(relativePath: string): boolean {
  return ALLOWED_PATTERNS.some(pattern => pattern.test(relativePath))
}

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

interface Violation {
  file: string
  lineNumber: number
  lineText: string
}

describe('architecture — activeLocation scope', () => {
  it('activeLocation is only referenced in booking-flow files and useLocations.ts', () => {
    const files = walkFiles(APP_ROOT)
    const violations: Violation[] = []

    for (const absPath of files) {
      const rel = relative(APP_ROOT, absPath)
      if (isAllowed(rel)) continue

      const src = readFileSync(absPath, 'utf8')
      const lines = src.split('\n')

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i]!
        // Match direct destructuring or property access of activeLocation.
        // Exclude:
        //   - Lines that are purely a line comment (// ...)
        //   - Lines that are a JSDoc/block comment continuation (* ...)
        //   - Inline comments after code (// ... at end of line)
        //   - Inline block comments (/* ... */ within a line)
        const trimmed = line.trimStart()
        if (trimmed.startsWith('//') || trimmed.startsWith('*')) continue
        const stripped = line.replace(/\/\/.*$/, '').replace(/\/\*[\s\S]*?\*\//g, '')
        if (/\bactiveLocation\b/.test(stripped)) {
          violations.push({
            file: rel,
            lineNumber: i + 1,
            lineText: line.trim().slice(0, 200),
          })
        }
      }
    }

    if (violations.length > 0) {
      const report = violations
        .map(v => `  ${v.file}:${v.lineNumber}  ${v.lineText}`)
        .join('\n')
      throw new Error(
        `\nFound ${violations.length} activeLocation reference(s) outside the booking flow.\n` +
        `Browse pages, layout components, and content composables must not read\n` +
        `useLocations().activeLocation (see Plan 13 Task 2 rationale in useLocations.ts).\n\n` +
        `Allowed files: booking flow (pages/purchase/**, components/booking/**),\n` +
        `useLocations.ts, and LocationPreferenceSwitcher.vue.\n\n` +
        `If you are adding a legitimately booking-scoped file, add it to the\n` +
        `ALLOWED_PATTERNS list in frontend/tests/architecture/activeLocation-scope.test.ts.\n\n` +
        report + '\n',
      )
    }

    expect(violations).toHaveLength(0)
  })
})
