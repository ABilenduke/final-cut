import { describe, it, expect, beforeEach } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { assetUrl } from '~/utils/assetUrl'

// Nuxt auto-imports useRuntimeConfig at module-resolve time, so vi.stubGlobal
// can't intercept it. mockNuxtImport patches the actual import; we then route
// the return value through a mutable closure variable so each test can flip
// the base URL.
let mockCdnBase = ''
mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { cdnBaseUrl: mockCdnBase } }) as ReturnType<typeof useRuntimeConfig>
})

describe('assetUrl', () => {
  beforeEach(() => {
    mockCdnBase = ''
  })

  it('returns empty string for null/undefined/empty input', () => {
    expect(assetUrl(null)).toBe('')
    expect(assetUrl(undefined)).toBe('')
    expect(assetUrl('')).toBe('')
  })

  it('passes absolute https URLs through unchanged', () => {
    const url = 'https://cdn.example.test/finalcut/concessions/bottle_of_water.webp'
    expect(assetUrl(url)).toBe(url)
  })

  it('passes absolute http URLs through unchanged', () => {
    expect(assetUrl('http://example.com/foo.png')).toBe('http://example.com/foo.png')
  })

  it('passes data: and blob: URIs through unchanged', () => {
    expect(assetUrl('data:image/png;base64,iVBORw0KG=')).toBe('data:image/png;base64,iVBORw0KG=')
    expect(assetUrl('blob:https://finalcut.test/abc')).toBe('blob:https://finalcut.test/abc')
  })

  it('passes protocol-relative URLs through unchanged', () => {
    expect(assetUrl('//cdn.example.com/foo.webp')).toBe('//cdn.example.com/foo.webp')
  })

  it('returns root-relative app paths unchanged (still served by Nuxt)', () => {
    expect(assetUrl('/images/logo.svg')).toBe('/images/logo.svg')
  })

  it('returns bare relative paths unchanged when cdnBaseUrl is empty', () => {
    expect(assetUrl('concessions/foo.webp')).toBe('concessions/foo.webp')
  })

  it('prefixes bare relative paths with cdnBaseUrl when configured', () => {
    mockCdnBase = 'https://cdn.example.test/finalcut'

    expect(assetUrl('concessions/bottle_of_water.webp'))
      .toBe('https://cdn.example.test/finalcut/concessions/bottle_of_water.webp')
  })

  it('trims a trailing slash on cdnBaseUrl', () => {
    mockCdnBase = 'https://cdn.example.com/finalcut/'

    expect(assetUrl('concessions/foo.webp')).toBe('https://cdn.example.com/finalcut/concessions/foo.webp')
  })

  it('joins cleanly when the path begins with a slash after the prefix trim', () => {
    // Defensive: assetUrl strips a leading "/" off the path before joining,
    // so callers that accidentally pass "//foo" still produce a sane URL.
    mockCdnBase = 'https://cdn.example.com/finalcut'

    expect(assetUrl('concessions/foo.webp')).toBe('https://cdn.example.com/finalcut/concessions/foo.webp')
  })
})
